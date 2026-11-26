<?php

namespace App\Controllers;

use App\Models\Activity_Model;
use App\Models\AlumniModel;
use App\Models\UserAccountModel;
use App\Services\SupabaseAuthService;
use CodeIgniter\HTTP\ResponseInterface;

class Auth extends BaseController
{
    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_WINDOW_SECONDS = 300;
    private const LOGIN_LOCKOUT_SECONDS = 60;

    private const REGISTER_MAX_ATTEMPTS = 5;
    private const REGISTER_WINDOW_SECONDS = 300;
    private const REGISTER_LOCKOUT_SECONDS = 60;

    private UserAccountModel $userAccounts;
    private Activity_Model $activityModel;
    private AlumniModel $alumniModel;
    private SupabaseAuthService $supabaseAuth;

    public function __construct()
    {
        helper('role');
        $this->userAccounts = new UserAccountModel();
        $this->activityModel = new Activity_Model();
        $this->alumniModel = new AlumniModel();
        $this->supabaseAuth = new SupabaseAuthService();
    }


    public function login(): ResponseInterface
    {
        $identifier = trim((string) $this->request->getPost('identifier'));
        $password = (string) $this->request->getPost('password');

        $rateScope = 'login:' . strtolower($identifier ?: 'blank');
        $lockoutRemaining = max(
            $this->getRateLimitLockoutRemaining('login_device'),
            $this->getRateLimitLockoutRemaining('login_last'),
            $this->getRateLimitLockoutRemaining($rateScope)
        );
        if ($lockoutRemaining > 0) {
            return $this->buildRateLimitResponse(
                "Too many failed login attempts. Please wait {$lockoutRemaining} seconds before trying again.",
                $lockoutRemaining
            );
        }

        if ($identifier === '' || $password === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please enter your email and password.',
            ]);
        }

        $user = null;

        // Auto-detect if identifier is email or account_id
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = $this->userAccounts->findByEmail($identifier);
        } else {
            $user = $this->userAccounts->findByAccountId($identifier);

            // If account_id was not yet backfilled in user_accounts, resolve via alumni.alumni_id
            if ($user === null) {
                $alumniByAccountId = $this->alumniModel->builder()
                    ->select('alumni_id, Email')
                    ->where('alumni_id', $identifier)
                    ->get()
                    ->getRowArray();

                if (is_array($alumniByAccountId) && ! empty($alumniByAccountId['Email'])) {
                    $user = $this->userAccounts->findByEmail((string) $alumniByAccountId['Email']);
                }
            }
        }

        if ($user === null) {
            return $this->handleFailedLoginAttempt($rateScope, 'Invalid credentials.');
        }

        $localPasswordMatches = $this->passwordMatches($password, (string) ($user['Password'] ?? ''));

        // Authenticate via Supabase Auth (with automatic provisioning/sync for existing MySQL accounts)
        $authResult = $this->supabaseAuth->authenticateHybrid($user, $password, $localPasswordMatches);
        if (! $authResult['success']) {
            return $this->handleFailedLoginAttempt($rateScope, 'Invalid credentials.');
        }

        // Extra account_id validation for alumni logins.
        // Requirement: for role=alumni, ensure the user is linked to a real alumni row
        // by matching user_accounts.account_id with alumni.alumni_id.
        $role = strtolower((string) ($user['Role'] ?? 'alumni'));
        if ($role === 'alumni') {
            $emailKey = (string) ($user['Email'] ?? $identifier);
            $expectedAlumni = $this->alumniModel->builder()
                ->select('alumni_id, Email')
                ->where('LOWER("Email")', strtolower($emailKey))
                ->get()
                ->getRowArray();

            // If there is no corresponding alumni record, deny login.
            if (! is_array($expectedAlumni) || empty($expectedAlumni)) {
                return $this->handleFailedLoginAttempt($rateScope, 'Invalid account (alumni record not found).');
            }

            $expectedAccountId = (string) ($expectedAlumni['alumni_id'] ?? '');
            $providedAccountId = (string) ($user['account_id'] ?? '');

            // Back-compat: older alumni accounts might have NULL account_id.
            // If so, set it automatically from the alumni row.
            if ($providedAccountId === '' && $expectedAccountId !== '') {
                $this->userAccounts->set('account_id', $expectedAccountId)->where('LOWER("Email")', strtolower($emailKey))->update();
                $user['account_id'] = $expectedAccountId;
                $providedAccountId = $expectedAccountId;
            }

            if ($expectedAccountId !== '' && $providedAccountId !== $expectedAccountId) {
                return $this->handleFailedLoginAttempt($rateScope, 'Invalid account linkage.');
            }
        }

        // Clear login rate limit counters on successful authentication
        $this->clearRateLimit('login_device');
        $this->clearRateLimit('login_last');
        $this->clearRateLimit($rateScope);

        $supabaseUser = is_array($authResult['user'] ?? null) ? $authResult['user'] : [];
        $supabaseSession = is_array($authResult['session'] ?? null) ? $authResult['session'] : null;

        // Authorization role is resolved from app_metadata (if set in Supabase) or local DB — never user_metadata
        $resolvedRole = $this->supabaseAuth->extractAuthorizedRole($supabaseUser) ?? $role;
        $normalizedResolvedRole = normalize_role($resolvedRole);

        // Enforce Campus / College / Department assignment validation for Head Admins and Coordinators
        $verifiedScope = null;
        if ($normalizedResolvedRole === 'coordinator') {
            $scopeService = new \App\Services\AssignmentScopeService();
            $verifiedScope = $scopeService->validateCoordinatorAssignment($user);
            if (($verifiedScope['valid'] ?? false) !== true) {
                return $this->response->setStatusCode(403)->setJSON([
                    'success' => false,
                    'message' => (string) ($verifiedScope['message'] ?? 'Your coordinator assignment is incomplete or invalid. Please contact your Head Admin.'),
                ]);
            }
        } elseif (is_staff_role($normalizedResolvedRole)) {
            $scopeService = new \App\Services\AssignmentScopeService();
            $verifiedScope = $scopeService->validateHeadAdminAssignment($user);
            if (($verifiedScope['valid'] ?? false) !== true) {
                return $this->response->setStatusCode(403)->setJSON([
                    'success' => false,
                    'message' => (string) ($verifiedScope['message'] ?? 'Your account has not been assigned to a campus. Please contact the Developer.'),
                ]);
            }
        }

        session()->set('auth_user', [
            'id'               => $user['id'] ?? ($supabaseUser['id'] ?? null),
            'supabase_user_id' => $supabaseUser['id'] ?? ($supabaseSession['supabase_user_id'] ?? null),
            'first_name'       => $user['First_Name'] ?? '',
            'last_name'        => $user['Last_Name'] ?? '',
            'email'            => $user['Email'] ?? '',
            'role'             => $resolvedRole,
            'department'       => $verifiedScope['department_name'] ?? ($user['Department'] ?? ''),
            'department_id'    => $verifiedScope['department_id'] ?? null,
            'college'          => $verifiedScope['college_name'] ?? ($user['College'] ?? ''),
            'college_id'       => $verifiedScope['college_id'] ?? null,
            'campus'           => isset($verifiedScope['campus_id']) ? (string) $verifiedScope['campus_id'] : ($user['Campus'] ?? ''),
            'campus_id'        => $verifiedScope['campus_id'] ?? null,
            'campus_name'      => $verifiedScope['campus_name'] ?? '',
            'account_id'       => $user['account_id'] ?? null,
            'name'             => trim(($user['First_Name'] ?? '') . ' ' . ($user['Last_Name'] ?? '')),
            'auth_provider'    => $authResult['provider'] ?? 'supabase',
        ]);

        if ($supabaseSession !== null) {
            session()->set('supabase_session', $supabaseSession);
        } else {
            session()->remove('supabase_session');
        }

        $redirect = login_redirect_for_role($resolvedRole);

        $this->activityModel->logActivity(
            session('auth_user'),
            'User logged in',
            'auth',
            [
                'email'         => $user['Email'] ?? '',
                'auth_provider' => $authResult['provider'] ?? 'supabase',
            ],
            activity_user_type_for_role($resolvedRole),
            (string) $this->request->getIPAddress(),
            (string) $this->request->getUserAgent()
        );

        $profileIncomplete = false;
        $alumniPersonalInfo = null;
        if ($normalizedResolvedRole === 'alumni') {
            $alumniRow = $this->alumniModel->findByEmail((string) ($user['Email'] ?? ''));
            if (! is_array($alumniRow) && ! empty($user['account_id'])) {
                $alumniRow = $this->alumniModel->where('alumni_id', (string) $user['account_id'])->first();
            }

            if (is_array($alumniRow)) {
                $sex = trim((string) ($alumniRow['sex'] ?? ''));
                $birthMonth = (int) ($alumniRow['birth_month'] ?? 0);
                $birthDate = (int) ($alumniRow['birth_date'] ?? 0);
                $birthYear = (int) ($alumniRow['birth_year'] ?? 0);
                $emContact = trim((string) ($alumniRow['emergency_contact'] ?? ''));
                $emName = trim((string) ($alumniRow['emergency_contact_name'] ?? ''));

                $validDob = $birthMonth >= 1 && $birthMonth <= 12
                    && $birthDate >= 1 && $birthDate <= 31
                    && $birthYear >= 1920
                    && checkdate($birthMonth, $birthDate, $birthYear);

                $profileIncomplete = (
                    $sex === ''
                    || ! $validDob
                    || $emContact === ''
                    || $emName === ''
                );

                $alumniPersonalInfo = [
                    'sex'                    => $sex,
                    'birth_month'            => $birthMonth > 0 ? $birthMonth : null,
                    'birth_date'             => $birthDate > 0 ? $birthDate : null,
                    'birth_year'             => $birthYear > 0 ? $birthYear : null,
                    'emergency_contact'      => $emContact,
                    'emergency_contact_name' => $emName,
                ];
            }
        }

        return $this->response->setJSON([
            'success'              => true,
            'message'              => 'Login successful.',
            'redirect'             => $redirect,
            'profile_incomplete'   => $profileIncomplete,
            'alumni_personal_info' => $alumniPersonalInfo,
            'user'                 => session('auth_user'),
            'supabase'             => [
                'authenticated' => $supabaseSession !== null,
                'provider'      => $authResult['provider'] ?? 'supabase',
                'expires_at'    => $supabaseSession['expires_at'] ?? null,
            ],
        ]);
    }

    public function signup(): ResponseInterface
    {
        $signupLockout = $this->getRateLimitLockoutRemaining('register_submit');
        if ($signupLockout > 0) {
            return $this->buildRateLimitResponse(
                "Too many registration attempts. Please wait {$signupLockout} seconds before trying again.",
                $signupLockout
            );
        }

        $firstName = trim((string) $this->request->getPost('first_name'));
        $lastName = trim((string) $this->request->getPost('last_name'));
        $college = trim((string) $this->request->getPost('college'));
        $course = trim((string) $this->request->getPost('course'));
        $graduationYear = trim((string) $this->request->getPost('graduation_year'));
        $province = trim((string) $this->request->getPost('province'));
        $municipality = trim((string) $this->request->getPost('municipality'));
        $barangay = trim((string) $this->request->getPost('barangay'));
        $contactNumber = trim((string) $this->request->getPost('contact_number'));
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');
        $confirmPassword = (string) $this->request->getPost('confirm_password');

        if (
            $firstName === ''
            || $lastName === ''
            || $college === ''
            || $course === ''
            || $graduationYear === ''
            || $province === ''
            || $municipality === ''
            || $barangay === ''
            || $contactNumber === ''
            || $email === ''
            || $password === ''
            || $confirmPassword === ''
        ) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please complete all sign up fields.',
            ]);
        }

        if (! preg_match('/^\d{4}$/', $graduationYear)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please provide a valid graduation year.',
            ]);
        }

        if (! preg_match('/^09\d{9}$/', $contactNumber)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please provide a valid contact number.',
            ]);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please provide a valid email address.',
            ]);
        }

        if ($password !== $confirmPassword) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Passwords do not match.',
            ]);
        }

        if (strlen($password) < 6) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Password must be at least 6 characters long.',
            ]);
        }

        $verifiedEmail = (string) (session('email_verified_email') ?? '');
        if ($verifiedEmail === '' || strtolower($verifiedEmail) !== $email) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Please verify your email address with the 6-digit OTP code before proceeding.',
            ]);
        }

        $alumni = $this->alumniModel->builder()
            ->select('alumni.id, alumni.alumni_id, alumni.FirstName, alumni.LastName, alumni.Email, alumni.GraduationYear, c.College_Name AS College, d.Department_Name AS Course')
            ->join('colleges c', 'c.College_ID = alumni.College_ID', 'inner')
            ->join('departments d', 'd.Department_ID = alumni.Department_ID', 'inner')
            ->where('LOWER("FirstName")', strtolower($firstName))
            ->where('LOWER("LastName")', strtolower($lastName))
            ->where('LOWER(c."College_Name")', strtolower($college))
            ->where('LOWER(d."Department_Name")', strtolower($course))
            ->where('GraduationYear', $graduationYear)
            ->get()
            ->getRowArray();

        if (! is_array($alumni) || empty($alumni)) {
            $state = $this->recordRateLimitAttempt('register_submit', self::REGISTER_MAX_ATTEMPTS, self::REGISTER_WINDOW_SECONDS, self::REGISTER_LOCKOUT_SECONDS);
            if ($state['locked_remaining'] > 0) {
                return $this->buildRateLimitResponse(
                    "Too many failed registration attempts. Please wait {$state['locked_remaining']} seconds before trying again.",
                    $state['locked_remaining']
                );
            }
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'No matching alumni record was found for the provided signup details.',
            ]);
        }

        $existingByEmail = $this->userAccounts->findByEmail($email);
        $currentAlumniId = (int) ($alumni['id'] ?? 0);
        $currentAlumniEmail = strtolower(trim((string) ($alumni['Email'] ?? '')));
        $currentAlumniAccountId = trim((string) ($alumni['alumni_id'] ?? ''));

        if ($currentAlumniEmail !== '') {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'This alumni record already has an email on file. Please use that account to log in.',
            ]);
        }

        if (is_array($existingByEmail)) {
            $existingByEmailAccountId = trim((string) ($existingByEmail['account_id'] ?? ''));
            $sameMatchedAccount = $currentAlumniAccountId !== '' && $existingByEmailAccountId === $currentAlumniAccountId;

            if (! $sameMatchedAccount && strtolower(trim((string) ($existingByEmail['Email'] ?? ''))) !== $currentAlumniEmail) {
                return $this->response->setStatusCode(409)->setJSON([
                    'success' => false,
                    'message' => 'An account with this email already exists.',
                ]);
            }
        }

        $existingAlumniByEmail = $this->alumniModel->findByEmail($email);
        if (is_array($existingAlumniByEmail) && (int) ($existingAlumniByEmail['id'] ?? 0) !== $currentAlumniId) {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'An account with this email already exists.',
            ]);
        }

        $this->alumniModel->update($currentAlumniId, [
            'Email'          => $email,
            'Province'       => $province,
            'Municipality'   => $municipality,
            'Barangay'       => $barangay,
            'Contact_Number' => $contactNumber,
        ]);

        $existingAccount = null;
        if ($currentAlumniEmail !== '') {
            $existingAccount = $this->userAccounts->findByEmail($currentAlumniEmail);
        }
        if (! is_array($existingAccount) && $currentAlumniAccountId !== '') {
            $existingAccount = $this->userAccounts->findByAccountId($currentAlumniAccountId);
        }
        if (! is_array($existingAccount) && is_array($existingByEmail)) {
            $existingAccount = $existingByEmail;
        }

        $accountPayload = [
            'First_Name' => (string) ($alumni['FirstName'] ?? ''),
            'Last_Name'  => (string) ($alumni['LastName'] ?? ''),
            'Role'       => 'alumni',
            'Email'      => $email,
            'College'    => (string) ($alumni['College'] ?? ''),
            'Department' => (string) ($alumni['Course'] ?? ''),
            'account_id' => $currentAlumniAccountId !== '' ? $currentAlumniAccountId : null,
            'Password'   => $password,
        ];

        $saved = false;
        if (is_array($existingAccount)) {
            $existingAccountEmail = (string) ($existingAccount['Email'] ?? '');
            $saved = $this->userAccounts
                ->where('Email', $existingAccountEmail)
                ->set($accountPayload)
                ->update();
        } else {
            $saved = $this->userAccounts->insert($accountPayload) !== false;
        }

        if ($saved === false) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to update your account right now.',
            ]);
        }

        // Provision/sync the newly registered alumni account in Supabase Auth (with app_metadata role & account_id)
        $syncResult = $this->supabaseAuth->syncLocalUserToSupabase($accountPayload, $password);
        if (! ($syncResult['success'] ?? false)) {
            log_message('warning', 'Signup Supabase sync notice: ' . ($syncResult['error'] ?? 'unknown'));
        }

        $this->clearRateLimit('register_submit');
        $this->clearRateLimit('register_lookup');
        $this->clearRateLimit('register_otp_send');
        $this->clearRateLimit('register_otp_verify');
        session()->remove(['email_otp_data', 'email_verified_email']);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Account updated successfully. You can log in now.',
        ]);
    }

    public function checkSignupRecord(): ResponseInterface
    {
        $lookupLockout = $this->getRateLimitLockoutRemaining('register_lookup');
        if ($lookupLockout > 0) {
            return $this->buildRateLimitResponse(
                "Too many record verification attempts. Please wait {$lookupLockout} seconds before trying again.",
                $lookupLockout
            );
        }

        $firstName = trim((string) $this->request->getPost('first_name'));
        $lastName = trim((string) $this->request->getPost('last_name'));
        $college = trim((string) $this->request->getPost('college'));
        $course = trim((string) $this->request->getPost('course'));
        $graduationYear = trim((string) $this->request->getPost('graduation_year'));

        if (
            $firstName === ''
            || $lastName === ''
            || $college === ''
            || $course === ''
            || $graduationYear === ''
        ) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please complete all basic information fields.',
            ]);
        }

        if (! preg_match('/^\d{4}$/', $graduationYear)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please provide a valid graduation year.',
            ]);
        }

        $alumni = $this->alumniModel->builder()
            ->select('alumni.id, alumni.Email')
            ->join('colleges c', 'c.College_ID = alumni.College_ID', 'inner')
            ->join('departments d', 'd.Department_ID = alumni.Department_ID', 'inner')
            ->where('LOWER("FirstName")', strtolower($firstName))
            ->where('LOWER("LastName")', strtolower($lastName))
            ->where('LOWER(c."College_Name")', strtolower($college))
            ->where('LOWER(d."Department_Name")', strtolower($course))
            ->where('GraduationYear', $graduationYear)
            ->get()
            ->getRowArray();

        if (! is_array($alumni) || empty($alumni)) {
            $state = $this->recordRateLimitAttempt('register_lookup', self::REGISTER_MAX_ATTEMPTS, self::REGISTER_WINDOW_SECONDS, self::REGISTER_LOCKOUT_SECONDS);
            if ($state['locked_remaining'] > 0) {
                return $this->buildRateLimitResponse(
                    "Too many failed record lookup attempts. Please wait {$state['locked_remaining']} seconds before trying again.",
                    $state['locked_remaining']
                );
            }
            return $this->response->setStatusCode(404)->setJSON([
                'success'            => false,
                'remaining_attempts' => $state['remaining_attempts'],
                'message'            => "No matching alumni record was found for the provided basic information. ({$state['remaining_attempts']} attempt(s) remaining)",
            ]);
        }

        if (trim((string) ($alumni['Email'] ?? '')) !== '') {
            $state = $this->recordRateLimitAttempt('register_lookup', self::REGISTER_MAX_ATTEMPTS, self::REGISTER_WINDOW_SECONDS, self::REGISTER_LOCKOUT_SECONDS);
            if ($state['locked_remaining'] > 0) {
                return $this->buildRateLimitResponse(
                    "Too many record lookup attempts. Please wait {$state['locked_remaining']} seconds before trying again.",
                    $state['locked_remaining']
                );
            }
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'This alumni record already has an email on file. Please use that account to log in.',
            ]);
        }

        $this->clearRateLimit('register_lookup');

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Matching alumni record found.',
        ]);
    }

    public function logout(): ResponseInterface
    {
        $user = session('auth_user');
        $supabaseSession = session('supabase_session');

        if (is_array($supabaseSession) && ! empty($supabaseSession['access_token'])) {
            $this->supabaseAuth->signOut((string) $supabaseSession['access_token']);
        }

        if (is_array($user)) {
            $this->activityModel->logActivity(
                $user,
                'User logged out',
                'auth',
                ['email' => $user['email'] ?? ''],
                activity_user_type_for_role($user['role'] ?? ''),
                (string) $this->request->getIPAddress(),
                (string) $this->request->getUserAgent()
            );
        }

        session()->remove(['auth_user', 'supabase_session']);
        session()->destroy();

        return $this->response->setJSON([
            'success'  => true,
            'redirect' => base_url('/'),
        ]);
    }

    public function sendEmailOtp(): ResponseInterface
    {
        $otpSendLockout = $this->getRateLimitLockoutRemaining('register_otp_send');
        if ($otpSendLockout > 0) {
            return $this->buildRateLimitResponse(
                "Too many verification code requests. Please wait {$otpSendLockout} seconds before trying again.",
                $otpSendLockout
            );
        }

        $email = strtolower(trim((string) $this->request->getPost('email')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please provide a valid email address.',
            ]);
        }

        if (! str_ends_with($email, '@gmail.com')) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Email address must end with @gmail.com.',
            ]);
        }

        // Check if email already registered in user_accounts
        $existingAccount = $this->userAccounts->findByEmail($email);
        if (is_array($existingAccount)) {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'An account with this email already exists. Please sign in.',
            ]);
        }

        // Check if email already assigned in alumni records
        $existingAlumni = $this->alumniModel->findByEmail($email);
        if (is_array($existingAlumni)) {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'This email is already associated with an alumni record. Please sign in.',
            ]);
        }

        // Check cooldown (60 seconds)
        $otpData = session('email_otp_data');
        $now = time();
        if (is_array($otpData) && isset($otpData['last_sent']) && ($now - (int) $otpData['last_sent']) < 60) {
            $remaining = 60 - ($now - (int) $otpData['last_sent']);
            return $this->response->setStatusCode(429)->setJSON([
                'success'            => false,
                'message'            => "Please wait {$remaining} seconds before requesting a new code.",
                'cooldown_remaining' => $remaining,
            ]);
        }

        // Track OTP send requests against brute-force / mail flooding (max 5 sends per 10 minutes)
        $otpSendState = $this->recordRateLimitAttempt('register_otp_send', self::REGISTER_MAX_ATTEMPTS, 600, self::REGISTER_LOCKOUT_SECONDS);
        if ($otpSendState['locked_remaining'] > 0) {
            return $this->buildRateLimitResponse(
                "Too many verification code requests. Please wait {$otpSendState['locked_remaining']} seconds before requesting another code.",
                $otpSendState['locked_remaining']
            );
        }

        $otp = (string) random_int(100000, 999999);

        session()->set('email_otp_data', [
            'email'      => $email,
            'otp_hash'   => password_hash($otp, PASSWORD_DEFAULT),
            'expires_at' => $now + 600, // 10 minutes
            'attempts'   => 0,
            'last_sent'  => $now,
        ]);

        // If email was previously marked verified with a different address, remove it
        if (session('email_verified_email') !== $email) {
            session()->remove('email_verified_email');
        }

        $mailSent = false;
        try {
            $emailConfig = config('Email');
            $emailService = \Config\Services::email($emailConfig, false);

            $fromEmail = ! empty($emailConfig->fromEmail) ? $emailConfig->fromEmail : 'noreply@alumni-tracer.edu.ph';
            $fromName = ! empty($emailConfig->fromName) ? $emailConfig->fromName : 'Alumni Tracer Portal';
            $subject = 'Alumni Tracer Portal - Email Verification Code';

            $htmlMessage = '
            <div style="max-width: 560px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
                <div style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); padding: 28px 24px; text-align: center; color: #ffffff;">
                    <h1 style="margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -0.3px;">Alumni Tracer Portal</h1>
                    <p style="margin: 6px 0 0; font-size: 14px; opacity: 0.85;">Official Graduate Verification</p>
                </div>
                <div style="padding: 32px 24px; color: #1e293b;">
                    <p style="font-size: 15px; line-height: 1.6; margin: 0 0 20px;">Hello,</p>
                    <p style="font-size: 15px; line-height: 1.6; margin: 0 0 24px;">Thank you for registering with the Alumni Tracer Portal. To verify that this email belongs to you, please enter the following 6-digit verification code:</p>
                    <div style="background: #f8fafc; border: 1.5px dashed #3b82f6; border-radius: 10px; padding: 20px; text-align: center; margin: 0 0 24px;">
                        <span style="font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #1d4ed8; font-family: monospace;">' . $otp . '</span>
                    </div>
                    <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0 0 16px;">This code is valid for <strong>10 minutes</strong>. If you did not request this verification code, you can safely ignore this email.</p>
                </div>
                <div style="background: #f8fafc; padding: 16px 24px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #94a3b8;">
                    &copy; ' . date('Y') . ' Alumni Tracer Portal. All rights reserved.
                </div>
            </div>';

            // 1. Prefer HTTPS Email API (Brevo or Resend) when configured — works on Render Free Tier where SMTP ports 25/465/587 are blocked
            $httpApiAttempted = false;
            $brevoKey = trim((string) (env('BREVO_API_KEY') ?? env('email_brevoApiKey') ?? env('email.brevoApiKey') ?? ''));
            $resendKey = trim((string) (env('RESEND_API_KEY') ?? env('email_resendApiKey') ?? env('email.resendApiKey') ?? ''));

            if ($brevoKey !== '') {
                $httpApiAttempted = true;
                $mailSent = $this->sendEmailViaBrevoApi($brevoKey, $fromEmail, $fromName, $email, $subject, $htmlMessage);
            } elseif ($resendKey !== '') {
                $httpApiAttempted = true;
                $mailSent = $this->sendEmailViaResendApi($resendKey, $fromEmail, $fromName, $email, $subject, $htmlMessage);
            }

            // 2. Fallback to CodeIgniter SMTP (e.g., local XAMPP with smtp.gmail.com:587)
            if (! $mailSent && ! $httpApiAttempted) {
                $emailService->setFrom($fromEmail, $fromName);
                $emailService->setTo($email);
                $emailService->setSubject($subject);
                $emailService->setMessage($htmlMessage);
                $mailSent = (bool) $emailService->send(false);
                if (! $mailSent) {
                    log_message('error', 'OTP Email Delivery Failed: ' . $emailService->printDebugger(['headers']));
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'OTP Email Send Error: ' . $e->getMessage());
        }

        if (! $mailSent) {
            session()->remove('email_otp_data');
            log_message('error', "Sign-up OTP email delivery failed for {$email}.");

            return $this->response->setStatusCode(503)->setJSON([
                'success'   => false,
                'mail_sent' => false,
                'message'   => 'Unable to send the verification code email right now. Please verify your email address and try again in a moment.',
            ]);
        }

        log_message('info', "Sign-up OTP email delivered successfully to {$email}.");

        return $this->response->setJSON([
            'success'    => true,
            'message'    => "A 6-digit verification code has been sent to {$email}.",
            'expires_in' => 600,
            'cooldown'   => 60,
            'mail_sent'  => true,
        ]);
    }

    /**
     * Send transactional email over HTTPS via Brevo API v3 (works on Render Free Tier).
     */
    private function sendEmailViaBrevoApi(
        string $apiKey,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlContent
    ): bool {
        try {
            $payload = json_encode([
                'sender'      => ['name' => $fromName, 'email' => $fromEmail],
                'to'          => [['email' => $toEmail]],
                'subject'     => $subject,
                'htmlContent' => $htmlContent,
            ]);

            $ch = curl_init('https://api.brevo.com/v3/smtp/email');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_HTTPHEADER     => [
                    'accept: application/json',
                    'content-type: application/json',
                    'api-key: ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS     => $payload,
            ]);
            $responseBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($curlErr !== '' || $httpCode < 200 || $httpCode >= 300) {
                log_message('error', "Brevo API OTP Delivery Failed (HTTP {$httpCode}): {$curlErr} {$responseBody}");
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            log_message('error', 'Brevo API OTP Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send transactional email over HTTPS via Resend API (works on Render Free Tier).
     */
    private function sendEmailViaResendApi(
        string $apiKey,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlContent
    ): bool {
        try {
            $payload = json_encode([
                'from'    => "{$fromName} <{$fromEmail}>",
                'to'      => [$toEmail],
                'subject' => $subject,
                'html'    => $htmlContent,
            ]);

            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS     => $payload,
            ]);
            $responseBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($curlErr !== '' || $httpCode < 200 || $httpCode >= 300) {
                log_message('error', "Resend API OTP Delivery Failed (HTTP {$httpCode}): {$curlErr} {$responseBody}");
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            log_message('error', 'Resend API OTP Error: ' . $e->getMessage());
            return false;
        }
    }

    public function verifyEmailOtp(): ResponseInterface
    {
        $otpVerifyLockout = $this->getRateLimitLockoutRemaining('register_otp_verify');
        if ($otpVerifyLockout > 0) {
            return $this->buildRateLimitResponse(
                "Too many invalid OTP attempts. Please wait {$otpVerifyLockout} seconds before trying again.",
                $otpVerifyLockout
            );
        }

        $email = strtolower(trim((string) $this->request->getPost('email')));
        $otp = trim((string) $this->request->getPost('otp'));

        if ($email === '' || $otp === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please enter both your email and the 6-digit verification code.',
            ]);
        }

        if (! preg_match('/^\d{6}$/', $otp)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'The verification code must be exactly 6 digits.',
            ]);
        }

        $otpData = session('email_otp_data');

        if (! is_array($otpData) || empty($otpData['email'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'No active verification code found. Please click "Send Code" first.',
            ]);
        }

        if (strtolower((string) $otpData['email']) !== $email) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'The email address does not match the active verification request.',
            ]);
        }

        if (time() > (int) ($otpData['expires_at'] ?? 0)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'The verification code has expired. Please click "Resend Code".',
            ]);
        }

        $attempts = (int) ($otpData['attempts'] ?? 0);
        if ($attempts >= 5) {
            return $this->response->setStatusCode(429)->setJSON([
                'success' => false,
                'message' => 'Too many invalid attempts. Please click "Resend Code" for a new code.',
            ]);
        }

        if (! password_verify($otp, (string) ($otpData['otp_hash'] ?? ''))) {
            $otpData['attempts'] = $attempts + 1;
            session()->set('email_otp_data', $otpData);
            $state = $this->recordRateLimitAttempt('register_otp_verify', self::REGISTER_MAX_ATTEMPTS, self::REGISTER_WINDOW_SECONDS, self::REGISTER_LOCKOUT_SECONDS);
            if ($state['locked_remaining'] > 0) {
                return $this->buildRateLimitResponse(
                    "Too many invalid verification attempts. Please wait {$state['locked_remaining']} seconds before trying again.",
                    $state['locked_remaining']
                );
            }
            $remaining = min(max(0, 5 - $otpData['attempts']), $state['remaining_attempts']);

            return $this->response->setStatusCode(422)->setJSON([
                'success'            => false,
                'remaining_attempts' => $remaining,
                'message'            => "Invalid verification code. {$remaining} attempt(s) remaining.",
            ]);
        }

        // Verification successful
        $this->clearRateLimit('register_otp_verify');
        session()->set('email_verified_email', $email);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Email address verified successfully!',
            'email'   => $email,
        ]);
    }

    public function getOtpStatus(): ResponseInterface
    {
        $verifiedEmail = (string) (session('email_verified_email') ?? '');
        $otpData = session('email_otp_data');
        $now = time();

        $cooldownRemaining = 0;
        $expiresRemaining = 0;
        $pendingEmail = null;

        if (is_array($otpData) && ! empty($otpData['email'])) {
            $pendingEmail = (string) $otpData['email'];
            $lastSent = (int) ($otpData['last_sent'] ?? 0);
            $expiresAt = (int) ($otpData['expires_at'] ?? 0);

            $cooldownRemaining = max(0, 60 - ($now - $lastSent));
            $expiresRemaining = max(0, $expiresAt - $now);
        }

        $loginLockoutRemaining = max(
            $this->getRateLimitLockoutRemaining('login_device'),
            $this->getRateLimitLockoutRemaining('login_last')
        );
        $registerLockoutRemaining = max(
            $this->getRateLimitLockoutRemaining('register_lookup'),
            $this->getRateLimitLockoutRemaining('register_otp_send', 600),
            $this->getRateLimitLockoutRemaining('register_otp_verify'),
            $this->getRateLimitLockoutRemaining('register_submit')
        );

        return $this->response->setJSON([
            'verified_email'             => $verifiedEmail !== '' ? $verifiedEmail : null,
            'pending_email'              => $pendingEmail,
            'has_pending_otp'            => $pendingEmail !== null && $expiresRemaining > 0,
            'cooldown_remaining'         => $cooldownRemaining,
            'expires_remaining'          => $expiresRemaining,
            'login_lockout_remaining'    => $loginLockoutRemaining,
            'login_locked_until'         => $loginLockoutRemaining > 0 ? ($now + $loginLockoutRemaining) : 0,
            'register_lockout_remaining' => $registerLockoutRemaining,
            'register_locked_until'      => $registerLockoutRemaining > 0 ? ($now + $registerLockoutRemaining) : 0,
        ]);
    }

    public function sessionStatus(): ResponseInterface
    {
        $user = session('auth_user');
        $supabaseSession = session('supabase_session');

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return $this->response->setJSON([
            'loggedIn' => $user !== null,
            'user'     => $user,
            'supabase' => [
                'authenticated' => is_array($supabaseSession) && ! empty($supabaseSession['access_token']),
                'provider'      => is_array($user) ? ($user['auth_provider'] ?? 'supabase') : null,
                'expires_at'    => is_array($supabaseSession) ? ($supabaseSession['expires_at'] ?? null) : null,
            ],
        ]);
    }

    public function refreshSession(): ResponseInterface
    {
        $supabaseSession = session('supabase_session');
        $refreshToken = trim((string) ($this->request->getPost('refresh_token') ?: ($supabaseSession['refresh_token'] ?? '')));

        if ($refreshToken === '') {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'No active refresh token available.',
            ]);
        }

        $result = $this->supabaseAuth->refreshSession($refreshToken);
        if (! $result['success'] || empty($result['session'])) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to refresh Supabase session.',
            ]);
        }

        session()->set('supabase_session', $result['session']);

        return $this->response->setJSON([
            'success'  => true,
            'message'  => 'Session refreshed.',
            'supabase' => [
                'authenticated' => true,
                'expires_at'    => $result['session']['expires_at'] ?? null,
            ],
        ]);
    }

    public function me(): ResponseInterface
    {
        $user = session('auth_user');
        if (! is_array($user)) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'user'    => $user,
        ]);
    }

    private function passwordMatches(string $plainPassword, string $storedPassword): bool
    {
        if ($storedPassword === '') {
            return false;
        }
        if (str_starts_with($storedPassword, '$2y$') || str_starts_with($storedPassword, '$argon2')) {
            if (password_verify($plainPassword, $storedPassword)) {
                return true;
            }
        }
        return hash_equals($storedPassword, $plainPassword);
    }

    private function handleFailedLoginAttempt(string $identifierScope, string $reason): ResponseInterface
    {
        $deviceState = $this->recordRateLimitAttempt(
            'login_device',
            self::LOGIN_MAX_ATTEMPTS,
            self::LOGIN_WINDOW_SECONDS,
            self::LOGIN_LOCKOUT_SECONDS
        );
        $lastState = $this->recordRateLimitAttempt(
            'login_last',
            self::LOGIN_MAX_ATTEMPTS,
            self::LOGIN_WINDOW_SECONDS,
            self::LOGIN_LOCKOUT_SECONDS
        );
        $idState = $this->recordRateLimitAttempt(
            $identifierScope,
            self::LOGIN_MAX_ATTEMPTS,
            self::LOGIN_WINDOW_SECONDS,
            self::LOGIN_LOCKOUT_SECONDS
        );

        $lockedRemaining = max($deviceState['locked_remaining'], $lastState['locked_remaining'], $idState['locked_remaining']);
        if ($lockedRemaining > 0) {
            return $this->buildRateLimitResponse(
                "Too many failed login attempts. Please wait {$lockedRemaining} seconds before trying again.",
                $lockedRemaining
            );
        }

        $remaining = min($deviceState['remaining_attempts'], $lastState['remaining_attempts'], $idState['remaining_attempts']);

        return $this->response->setStatusCode(401)->setJSON([
            'success'            => false,
            'remaining_attempts' => $remaining,
            'message'            => "{$reason} Please try again. ({$remaining} attempt(s) remaining)",
        ]);
    }

    private function buildRateLimitResponse(string $message, int $retryAfter): ResponseInterface
    {
        $retryAfter = max(1, $retryAfter);
        $lockedUntil = time() + $retryAfter;

        return $this->response
            ->setStatusCode(429)
            ->setHeader('Retry-After', (string) $retryAfter)
            ->setJSON([
                'success'            => false,
                'rate_limited'       => true,
                'retry_after'        => $retryAfter,
                'cooldown_remaining' => $retryAfter,
                'locked_until'       => $lockedUntil,
                'remaining_attempts' => 0,
                'message'            => $message,
            ]);
    }

    /**
     * Resolves a unique per-device key (never IP-based) using a persistent device token
     * combined with the device's hardware/browser fingerprint.
     */
    private function getClientDeviceKey(): string
    {
        $headerDeviceId = trim($this->request->getHeaderLine('X-Device-Id'));
        $cookieDeviceId = trim((string) ($_COOKIE['gv_device_id'] ?? ''));
        $sessionDeviceId = trim((string) (session('gv_device_id') ?? ''));

        $deviceId = '';
        foreach ([$headerDeviceId, $cookieDeviceId, $sessionDeviceId] as $candidate) {
            if ($candidate !== '' && preg_match('/^[a-zA-Z0-9_-]{16,64}$/', $candidate)) {
                $deviceId = $candidate;
                break;
            }
        }

        if ($deviceId === '') {
            try {
                $deviceId = bin2hex(random_bytes(16));
            } catch (\Throwable $e) {
                $deviceId = substr(hash('sha256', uniqid('gv_dev_', true)), 0, 32);
            }
        }

        if ($sessionDeviceId !== $deviceId) {
            session()->set('gv_device_id', $deviceId);
        }

        if ($cookieDeviceId !== $deviceId && ! headers_sent()) {
            setcookie('gv_device_id', $deviceId, [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
            $_COOKIE['gv_device_id'] = $deviceId;
        }

        $clientFingerprint = trim($this->request->getHeaderLine('X-Device-Fingerprint'));
        if ($clientFingerprint === '') {
            $clientFingerprint = implode('|', [
                (string) $this->request->getUserAgent(),
                $this->request->getHeaderLine('Sec-CH-UA'),
                $this->request->getHeaderLine('Sec-CH-UA-Platform'),
                $this->request->getHeaderLine('Sec-CH-UA-Mobile'),
                $this->request->getHeaderLine('Accept-Language'),
            ]);
        }

        return $deviceId . '|' . substr(hash('sha256', $clientFingerprint), 0, 24);
    }

    /**
     * @return array{attempts: int, window_started_at: int, locked_until: int}
     */
    private function readRateLimitBucket(string $scope, int $windowSeconds): array
    {
        $now = time();
        $deviceHash = substr(hash('sha256', $this->getClientDeviceKey() . '|' . $scope), 0, 32);
        $cacheKey = 'auth_rl_dev_' . $deviceHash;
        $sessionKey = 'auth_rl_dev_' . md5($scope);

        $default = [
            'attempts'          => 0,
            'window_started_at' => $now,
            'locked_until'      => 0,
        ];

        $cacheBucket = null;
        try {
            $cacheBucket = cache()->get($cacheKey);
        } catch (\Throwable $e) {
            $cacheBucket = null;
        }

        $sessionBucket = session($sessionKey);
        $bucket = is_array($cacheBucket) ? $cacheBucket : (is_array($sessionBucket) ? $sessionBucket : $default);

        // Take the stricter state if both exist
        if (is_array($cacheBucket) && is_array($sessionBucket)) {
            $bucket['attempts'] = max((int) ($cacheBucket['attempts'] ?? 0), (int) ($sessionBucket['attempts'] ?? 0));
            $bucket['locked_until'] = max((int) ($cacheBucket['locked_until'] ?? 0), (int) ($sessionBucket['locked_until'] ?? 0));
            $bucket['window_started_at'] = min(
                (int) ($cacheBucket['window_started_at'] ?? $now),
                (int) ($sessionBucket['window_started_at'] ?? $now)
            );
        }

        $lockedUntil = (int) ($bucket['locked_until'] ?? 0);
        $windowStart = (int) ($bucket['window_started_at'] ?? $now);

        if ($lockedUntil > 0 && $now >= $lockedUntil) {
            $this->clearRateLimit($scope);
            return $default;
        }
        if ($lockedUntil === 0 && ($now - $windowStart) >= $windowSeconds) {
            $this->clearRateLimit($scope);
            return $default;
        }

        return [
            'attempts'          => (int) ($bucket['attempts'] ?? 0),
            'window_started_at' => $windowStart,
            'locked_until'      => $lockedUntil,
        ];
    }

    private function writeRateLimitBucket(string $scope, array $bucket, int $ttlSeconds): void
    {
        $deviceHash = substr(hash('sha256', $this->getClientDeviceKey() . '|' . $scope), 0, 32);
        $cacheKey = 'auth_rl_dev_' . $deviceHash;
        $sessionKey = 'auth_rl_dev_' . md5($scope);

        session()->set($sessionKey, $bucket);
        try {
            cache()->save($cacheKey, $bucket, max(60, $ttlSeconds));
        } catch (\Throwable $e) {
            // Non-blocking if cache driver is unavailable
        }
    }

    private function getRateLimitLockoutRemaining(string $scope, int $windowSeconds = 300): int
    {
        $bucket = $this->readRateLimitBucket($scope, $windowSeconds);
        $lockedUntil = (int) ($bucket['locked_until'] ?? 0);
        $now = time();

        return $lockedUntil > $now ? ($lockedUntil - $now) : 0;
    }

    /**
     * @return array{attempts: int, remaining_attempts: int, locked_remaining: int}
     */
    private function recordRateLimitAttempt(
        string $scope,
        int $maxAttempts,
        int $windowSeconds,
        int $lockoutSeconds
    ): array {
        $now = time();
        $bucket = $this->readRateLimitBucket($scope, $windowSeconds);

        if ($bucket['locked_until'] > $now) {
            return [
                'attempts'           => $bucket['attempts'],
                'remaining_attempts' => 0,
                'locked_remaining'   => $bucket['locked_until'] - $now,
            ];
        }

        $bucket['attempts']++;
        $lockedRemaining = 0;

        if ($bucket['attempts'] >= $maxAttempts) {
            $bucket['locked_until'] = $now + $lockoutSeconds;
            $lockedRemaining = $lockoutSeconds;
        }

        $ttl = $lockedRemaining > 0 ? $lockoutSeconds : $windowSeconds;
        $this->writeRateLimitBucket($scope, $bucket, $ttl);

        return [
            'attempts'           => $bucket['attempts'],
            'remaining_attempts' => max(0, $maxAttempts - $bucket['attempts']),
            'locked_remaining'   => $lockedRemaining,
        ];
    }

    private function clearRateLimit(string $scope): void
    {
        $deviceHash = substr(hash('sha256', $this->getClientDeviceKey() . '|' . $scope), 0, 32);
        $cacheKey = 'auth_rl_dev_' . $deviceHash;
        $sessionKey = 'auth_rl_dev_' . md5($scope);

        session()->remove($sessionKey);
        try {
            cache()->delete($cacheKey);
        } catch (\Throwable $e) {
            // Non-blocking
        }
    }
}
