<?php

namespace App\Controllers;

use App\Models\Activity_Model;
use App\Models\AlumniModel;
use App\Models\UserAccountModel;
use App\Services\SupabaseAuthService;
use CodeIgniter\HTTP\ResponseInterface;

class Auth extends BaseController
{
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

        if ($identifier === '' || $password === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please enter both identifier and password.',
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
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Invalid credentials. Please try again.',
            ]);
        }

        $localPasswordMatches = $this->passwordMatches($password, (string) ($user['Password'] ?? ''));

        // Authenticate via Supabase Auth (with automatic provisioning/sync for existing MySQL accounts)
        $authResult = $this->supabaseAuth->authenticateHybrid($user, $password, $localPasswordMatches);
        if (! $authResult['success']) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Invalid credentials. Please try again.',
            ]);
        }

        // Extra account_id validation for alumni logins.
        // Requirement: for role=alumni, ensure the user is linked to a real alumni row
        // by matching user_accounts.account_id with alumni.alumni_id.
        $role = strtolower((string) ($user['Role'] ?? 'alumni'));
        if ($role === 'alumni') {
            $emailKey = (string) ($user['Email'] ?? $identifier);
            $expectedAlumni = $this->alumniModel->builder()
                ->select('alumni_id, Email')
                ->where('Email', $emailKey)
                ->get()
                ->getRowArray();

            // If there is no corresponding alumni record, deny login.
            if (! is_array($expectedAlumni) || empty($expectedAlumni)) {
                return $this->response->setStatusCode(401)->setJSON([
                    'success' => false,
                    'message' => 'Invalid account (alumni record not found). Please try again.',
                ]);
            }

            $expectedAccountId = (string) ($expectedAlumni['alumni_id'] ?? '');
            $providedAccountId = (string) ($user['account_id'] ?? '');

            // Back-compat: older alumni accounts might have NULL account_id.
            // If so, set it automatically from the alumni row.
            if ($providedAccountId === '' && $expectedAccountId !== '') {
                $this->userAccounts->set('account_id', $expectedAccountId)->where('Email', $emailKey)->update();
                $user['account_id'] = $expectedAccountId;
                $providedAccountId = $expectedAccountId;
            }

            if ($expectedAccountId !== '' && $providedAccountId !== $expectedAccountId) {
                return $this->response->setStatusCode(401)->setJSON([
                    'success' => false,
                    'message' => 'Invalid account linkage. Please try again.',
                ]);
            }
        }

        $supabaseUser = is_array($authResult['user'] ?? null) ? $authResult['user'] : [];
        $supabaseSession = is_array($authResult['session'] ?? null) ? $authResult['session'] : null;

        // Authorization role is resolved from app_metadata (if set in Supabase) or local DB — never user_metadata
        $resolvedRole = $this->supabaseAuth->extractAuthorizedRole($supabaseUser) ?? $role;

        session()->set('auth_user', [
            'id'               => $user['id'] ?? ($supabaseUser['id'] ?? null),
            'supabase_user_id' => $supabaseUser['id'] ?? ($supabaseSession['supabase_user_id'] ?? null),
            'first_name'       => $user['First_Name'] ?? '',
            'last_name'        => $user['Last_Name'] ?? '',
            'email'            => $user['Email'] ?? '',
            'role'             => $resolvedRole,
            'department'       => $user['Department'] ?? '',
            'campus'           => $user['Campus'] ?? '',
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

        return $this->response->setJSON([
            'success'  => true,
            'message'  => 'Login successful.',
            'redirect' => $redirect,
            'user'     => session('auth_user'),
            'supabase' => [
                'authenticated' => $supabaseSession !== null,
                'provider'      => $authResult['provider'] ?? 'supabase',
                'expires_at'    => $supabaseSession['expires_at'] ?? null,
            ],
        ]);
    }

    public function signup(): ResponseInterface
    {
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
        $this->supabaseAuth->syncLocalUserToSupabase($accountPayload, $password);

        session()->remove(['email_otp_data', 'email_verified_email']);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Account updated successfully. You can log in now.',
        ]);
    }

    public function checkSignupRecord(): ResponseInterface
    {
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
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'No matching alumni record was found for the provided basic information.',
            ]);
        }

        if (trim((string) ($alumni['Email'] ?? '')) !== '') {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'This alumni record already has an email on file. Please use that account to log in.',
            ]);
        }

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
            $emailService = \Config\Services::email();

            $fromEmail = ! empty($emailConfig->fromEmail) ? $emailConfig->fromEmail : 'noreply@alumni-tracer.edu.ph';
            $fromName = ! empty($emailConfig->fromName) ? $emailConfig->fromName : 'Alumni Tracer Portal';

            $emailService->setFrom($fromEmail, $fromName);
            $emailService->setTo($email);
            $emailService->setSubject('Alumni Tracer Portal - Email Verification Code');

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

            $emailService->setMessage($htmlMessage);
            $mailSent = (bool) $emailService->send();
            if (! $mailSent) {
                log_message('error', 'OTP Email Delivery Failed: ' . $emailService->printDebugger(['headers']));
            }
        } catch (\Throwable $e) {
            log_message('error', 'OTP Email Send Error: ' . $e->getMessage());
        }

        log_message('info', "Sign-up OTP for {$email}: {$otp} (mail sent: " . ($mailSent ? 'yes' : 'no') . ")");

        $isDev = (defined('ENVIRONMENT') && ENVIRONMENT === 'development');

        return $this->response->setJSON([
            'success'            => true,
            'message'            => $mailSent
                ? "A 6-digit verification code has been sent to {$email}."
                : ($isDev
                    ? "Verification code generated for {$email}."
                    : "Verification code sent to {$email}."),
            'expires_in'         => 600,
            'cooldown'           => 60,
            'mail_sent'          => $mailSent,
            'dev_otp'            => ($isDev || ! $mailSent) ? $otp : null,
        ]);
    }

    public function verifyEmailOtp(): ResponseInterface
    {
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
            $remaining = max(0, 5 - $otpData['attempts']);

            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => "Invalid verification code. {$remaining} attempts remaining.",
            ]);
        }

        // Verification successful
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

        return $this->response->setJSON([
            'verified_email'     => $verifiedEmail !== '' ? $verifiedEmail : null,
            'pending_email'      => $pendingEmail,
            'has_pending_otp'    => $pendingEmail !== null && $expiresRemaining > 0,
            'cooldown_remaining' => $cooldownRemaining,
            'expires_remaining'  => $expiresRemaining,
        ]);
    }

    public function sessionStatus(): ResponseInterface
    {
        $user = session('auth_user');
        $supabaseSession = session('supabase_session');

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
}
