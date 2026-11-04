<?php

namespace App\Controllers;

use App\Models\UserAccountModel;
use App\Models\CampusModel;
use App\Services\SupabaseAuthService;
use CodeIgniter\HTTP\ResponseInterface;

class DeveloperController extends BaseController
{
    private UserAccountModel $userAccountModel;
    private CampusModel $campusModel;
    private SupabaseAuthService $supabaseAuth;

    public function __construct()
    {
        $this->userAccountModel = new UserAccountModel();
        $this->campusModel = new CampusModel();
        $this->supabaseAuth = new SupabaseAuthService();
    }

    public function dashboard(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Developer/Dashboard.html');
    }

    public function overview(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Developer/Overview.html');
    }

    public function userManagement(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Developer/UserManagement.html');
    }

    public function systemSettings(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Developer/SystemSettings.html');
    }

    public function schoolInfo(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Developer/SchoolInfo.html');
    }

    public function backupRestore(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Developer/BackupRestore.html');
    }

    public function systemLogs(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Developer/SystemLogs.html');
    }

    public function announcements(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Developer/Announcements.html');
    }

    public function emailSMS(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Developer/EmailSMS.html');
    }

    public function surveyTemplates(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Developer/SurveyTemplates.html');
    }

    public function maintenance(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Developer/Maintenance.html');
    }

    public function commonCss(): ResponseInterface
    {
        $css = file_get_contents(APPPATH . 'Views/HTML/Developer/DeveloperCommon.css');

        return $this->response
            ->setContentType('text/css')
            ->setBody($css === false ? '' : $css);
    }

    public function commonJs(): ResponseInterface
    {
        $js = file_get_contents(APPPATH . 'Views/HTML/Developer/DeveloperCommon.js');
        $rolesJson = json_encode(developer_roles(), JSON_UNESCAPED_SLASHES);
        $roleOptionsJson = json_encode(developer_user_role_options(), JSON_UNESCAPED_SLASHES);
        $prefix = "const DEVELOPER_ROLES = {$rolesJson};\nconst DEVELOPER_ROLE_OPTIONS = {$roleOptionsJson};\n";

        return $this->response
            ->setContentType('application/javascript')
            ->setBody(($js === false ? '' : $prefix . $js));
    }

    public function users(): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized',
            ]);
        }

        $role = normalize_role(session('auth_user.role') ?? '');
        if (! is_developer_role($role)) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden',
            ]);
        }

        $users = $this->userAccountModel
            ->orderBy('First_Name', 'ASC')
            ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'users' => array_map(fn (array $user): array => $this->formatUserAccountForResponse($user), $users),
            'roles' => developer_user_role_options(),
        ]);
    }

    public function saveUser(): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized',
            ]);
        }

        $role = normalize_role(session('auth_user.role') ?? '');
        if (! is_developer_role($role)) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden',
            ]);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $firstName = trim((string) ($payload['firstName'] ?? $payload['first_name'] ?? ''));
        $lastName = trim((string) ($payload['lastName'] ?? $payload['last_name'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $department = trim((string) ($payload['department'] ?? ''));
        $roleValue = $this->normalizeRoleValue((string) ($payload['role'] ?? ''));
        $existingEmail = strtolower(trim((string) ($payload['existingEmail'] ?? '')));

        if ($firstName === '' || $lastName === '' || $email === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please complete the required fields.',
            ]);
        }

        $existingUser = $existingEmail !== '' ? $this->userAccountModel->findByEmail($existingEmail) : null;
        $password = $existingUser['Password'] ?? (isset($payload['resetPassword']) && (bool) $payload['resetPassword'] ? 'ustp123' : 'ustp123');

        $userData = [
            'First_Name' => $firstName,
            'Last_Name' => $lastName,
            'Role' => $roleValue,
            'Email' => $email,
            'Department' => $department,
            'Password' => $password,
        ];

        if ($existingUser !== null && $existingEmail !== '' && $existingEmail !== $email) {
            $this->userAccountModel->where('Email', $existingEmail)->delete();
            $this->userAccountModel->insert($userData);
            $this->supabaseAuth->adminDeleteUserByEmail($existingEmail);
        } elseif ($existingUser !== null) {
            $this->userAccountModel->set($userData)->where('Email', $existingEmail !== '' ? $existingEmail : $email)->update();
        } else {
            $this->userAccountModel->insert($userData);
        }

        // Sync user and app_metadata role to Supabase Auth
        $this->supabaseAuth->syncLocalUserToSupabase($userData, (string) $password);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'User account saved.',
        ]);
    }

    public function deleteUser(string $email): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized',
            ]);
        }

        $role = normalize_role(session('auth_user.role') ?? '');
        if (! is_developer_role($role)) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden',
            ]);
        }

        $normalizedEmail = strtolower(trim(urldecode($email)));
        if ($normalizedEmail !== '') {
            $this->userAccountModel->where('Email', $normalizedEmail)->delete();
            $this->supabaseAuth->adminDeleteUserByEmail($normalizedEmail);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'User account deleted.',
        ]);
    }

    public function campuses(): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized',
            ]);
        }

        $role = normalize_role(session('auth_user.role') ?? '');
        if (! is_developer_role($role)) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden',
            ]);
        }

        $campuses = $this->campusModel
            ->orderBy('Campus', 'ASC')
            ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'campuses' => array_map(fn (array $campus): array => $this->formatCampusForResponse($campus), $campuses),
        ]);
    }

    public function saveCampus(): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized',
            ]);
        }

        $role = normalize_role(session('auth_user.role') ?? '');
        if (! is_developer_role($role)) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden',
            ]);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $schoolName = trim((string) ($payload['schoolName'] ?? ''));
        $abbreviation = trim((string) ($payload['abbreviation'] ?? ''));
        $campus = trim((string) ($payload['campus'] ?? ''));
        $address = trim((string) ($payload['address'] ?? ''));
        $alumniHeadEmail = trim((string) ($payload['alumniHeadEmail'] ?? ''));
        $existingId = (int) ($payload['existingId'] ?? 0);

        if ($schoolName === '' || $abbreviation === '' || $campus === '' || $address === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please complete the required fields.',
            ]);
        }

        $campusData = [
            'School_Name' => $schoolName,
            'Abbreviation' => $abbreviation,
            'Campus' => $campus,
            'Address' => $address,
            'Alumni_Head_Email' => $alumniHeadEmail !== '' ? $alumniHeadEmail : null,
        ];

        $previousAlumniHeadEmail = '';
        if ($existingId > 0) {
            $existingCampus = $this->campusModel->where('Campus_ID', $existingId)->first();
            $previousAlumniHeadEmail = strtolower(trim((string) ($existingCampus['Alumni_Head_Email'] ?? '')));
            $this->campusModel->set($campusData)->where('Campus_ID', $existingId)->update();
            $campusId = $existingId;
        } else {
            $campusId = (int) $this->campusModel->insert($campusData);
        }

        if ($campusId > 0) {
            $this->syncCampusAssignment($alumniHeadEmail, $campusId, $previousAlumniHeadEmail);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Campus saved.',
        ]);
    }

    private function syncCampusAssignment(string $alumniHeadEmail, int $campusId, string $previousAlumniHeadEmail = ''): void
    {
        $normalizedEmail = strtolower(trim($alumniHeadEmail));
        $previousEmail = strtolower(trim($previousAlumniHeadEmail));

        if ($previousEmail !== '' && $previousEmail !== $normalizedEmail) {
            $this->userAccountModel->set(['Campus' => null])
                ->where('Email', $previousEmail)
                ->update();
        }

        if ($normalizedEmail === '') {
            return;
        }

        $this->userAccountModel->set(['Campus' => $campusId])
            ->where('Email', $normalizedEmail)
            ->update();
    }

    public function deleteCampus(int $id): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized',
            ]);
        }

        $role = normalize_role(session('auth_user.role') ?? '');
        if (! is_developer_role($role)) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden',
            ]);
        }

        if ($id > 0) {
            $this->campusModel->where('Campus_ID', $id)->delete();
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Campus deleted.',
        ]);
    }

    private function formatCampusForResponse(array $campus): array
    {
        return [
            'id' => (int) ($campus['Campus_ID'] ?? 0),
            'schoolName' => (string) ($campus['School_Name'] ?? ''),
            'abbreviation' => (string) ($campus['Abbreviation'] ?? ''),
            'campus' => (string) ($campus['Campus'] ?? ''),
            'address' => (string) ($campus['Address'] ?? ''),
            'alumniHeadEmail' => (string) ($campus['Alumni_Head_Email'] ?? ''),
            'createdAt' => (string) ($campus['Created_At'] ?? ''),
            'updatedAt' => (string) ($campus['Updated_At'] ?? ''),
        ];
    }

    private function formatUserAccountForResponse(array $user): array
    {
        $role = normalize_role((string) ($user['Role'] ?? 'alumni'));

        $campusId = $user['Campus'] ?? null;
        $campusName = '';
        if ($campusId !== null && $campusId !== '' && (int) $campusId > 0) {
            $campusRecord = $this->campusModel->where('Campus_ID', (int) $campusId)->first();
            if (is_array($campusRecord)) {
                $campusName = (string) ($campusRecord['Campus'] ?? '');
            }
        }

        return [
            'id' => (string) ($user['Email'] ?? ''),
            'firstName' => (string) ($user['First_Name'] ?? ''),
            'lastName' => (string) ($user['Last_Name'] ?? ''),
            'email' => (string) ($user['Email'] ?? ''),
            'department' => (string) ($user['Department'] ?? ''),
            'role' => format_user_role_label($role),
            'roleValue' => $role,
            'campus' => $campusName,
            'status' => 'Active',
            'lastActive' => 'Database synced',
        ];
    }

    private function normalizeRoleValue(string $role): string
    {
        $normalizedRole = normalize_role($role);

        return match ($normalizedRole) {
            'head_admin' => 'head_admin',
            'staff' => 'staff',
            'developer', 'system_admin' => 'developer',
            'alumni' => 'alumni',
            default => 'alumni',
        };
    }

    private function renderProtectedView(string $view): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return redirect()->to(base_url('/'));
        }

        $role = normalize_role(session('auth_user.role') ?? '');
        if (! is_developer_role($role)) {
            if ($role === 'head_admin') {
                return redirect()->to(base_url('staff/Dashboard.html'));
            }

            return redirect()->to(base_url('alumni/Main.html'));
        }

        return $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setBody(view($view));
    }
}
