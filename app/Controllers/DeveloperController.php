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

    public function overviewData(): ResponseInterface
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

        $db = \Config\Database::connect();
        $pingStart = microtime(true);

        $allAccounts = $this->userAccountModel->findAll();
        $pingMs = round((microtime(true) - $pingStart) * 1000, 1);

        $roleCounts = [
            'head_admin'  => 0,
            'coordinator' => 0,
            'alumni'      => 0,
            'developer'   => 0,
            'staff'       => 0,
        ];
        $assignedHeadAdmins = 0;
        $linkedAlumniAccounts = 0;

        foreach ($allAccounts as $acct) {
            $normRole = normalize_role((string) ($acct['Role'] ?? 'alumni'));
            if ($normRole === 'system_admin') {
                $normRole = 'developer';
            }
            if (isset($roleCounts[$normRole])) {
                $roleCounts[$normRole]++;
            }
            if ($normRole === 'head_admin' && ! empty($acct['Campus'])) {
                $assignedHeadAdmins++;
            }
            if ($normRole === 'alumni' && trim((string) ($acct['account_id'] ?? '')) !== '') {
                $linkedAlumniAccounts++;
            }
        }

        // Alumni Directory
        $alumniRows = $db->tableExists('alumni')
            ? $db->table('alumni')->select('Status')->get()->getResultArray()
            : [];
        $totalAlumni = count($alumniRows);
        $activeAlumni = 0;
        $pendingAlumni = 0;
        foreach ($alumniRows as $al) {
            $st = strtolower(trim((string) ($al['Status'] ?? '')));
            if ($st === 'active') {
                $activeAlumni++;
            } else {
                $pendingAlumni++;
            }
        }

        // Campuses, Colleges, Departments
        $campuses = $this->campusModel->orderBy('Campus', 'ASC')->findAll();
        $totalCampuses = count($campuses);
        $assignedCampuses = 0;
        foreach ($campuses as $camp) {
            if (trim((string) ($camp['Alumni_Head_Email'] ?? '')) !== '') {
                $assignedCampuses++;
            }
        }

        $totalColleges = $db->tableExists('colleges') ? (int) $db->table('colleges')->countAllResults() : 0;
        $deptRows = $db->tableExists('departments')
            ? $db->table('departments')->select('Coordinator_Name')->get()->getResultArray()
            : [];
        $totalDepartments = count($deptRows);
        $assignedDepartments = 0;
        foreach ($deptRows as $dept) {
            if (trim((string) ($dept['Coordinator_Name'] ?? '')) !== '') {
                $assignedDepartments++;
            }
        }

        // Surveys & Responses
        $surveyRows = $db->tableExists('surveys')
            ? $db->table('surveys')->select('status, survey_state')->get()->getResultArray()
            : [];
        $totalSurveys = count($surveyRows);
        $activeSurveys = 0;
        foreach ($surveyRows as $sv) {
            $st = strtolower(trim((string) ($sv['status'] ?? '')));
            $state = strtolower(trim((string) ($sv['survey_state'] ?? '')));
            if ($st === 'published' || $st === 'active' || $state === 'open') {
                $activeSurveys++;
            }
        }

        $responseRows = $db->tableExists('responses')
            ? $db->table('responses')->select('review_status')->get()->getResultArray()
            : [];
        $totalResponses = count($responseRows);
        $pendingResponses = 0;
        foreach ($responseRows as $rsp) {
            if (strtolower(trim((string) ($rsp['review_status'] ?? 'pending'))) === 'pending') {
                $pendingResponses++;
            }
        }

        // Events, News, Payments
        $totalEvents = $db->tableExists('events') ? (int) $db->table('events')->countAllResults() : 0;
        $totalNews = $db->tableExists('news_posts') ? (int) $db->table('news_posts')->countAllResults() : 0;
        $totalPayments = $db->tableExists('payments') ? (int) $db->table('payments')->countAllResults() : 0;

        // Recent System Activities
        $recentActivities = [];
        if ($db->tableExists('activities')) {
            $actRows = $db->table('activities')
                ->orderBy('id', 'DESC')
                ->limit(8)
                ->get()
                ->getResultArray();
            foreach ($actRows as $act) {
                $recentActivities[] = [
                    'id'          => (int) ($act['id'] ?? 0),
                    'userType'    => (string) ($act['user_type'] ?? 'system'),
                    'action'      => (string) ($act['action'] ?? 'System event'),
                    'category'    => (string) ($act['category'] ?? 'general'),
                    'description' => (string) ($act['description'] ?? ''),
                    'ipAddress'   => (string) ($act['ip_address'] ?? ''),
                    'createdAt'   => (string) ($act['created_at'] ?? ($act['created_date'] ?? '')),
                ];
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'telemetry' => [
                'accounts' => [
                    'total'                => count($allAccounts),
                    'headAdmins'           => $roleCounts['head_admin'],
                    'assignedHeadAdmins'   => $assignedHeadAdmins,
                    'unassignedHeadAdmins' => max(0, $roleCounts['head_admin'] - $assignedHeadAdmins),
                    'coordinators'         => $roleCounts['coordinator'],
                    'alumniAccounts'       => $roleCounts['alumni'],
                    'linkedAlumniAccounts' => $linkedAlumniAccounts,
                    'developers'           => $roleCounts['developer'],
                ],
                'alumniDirectory' => [
                    'total'   => $totalAlumni,
                    'active'  => $activeAlumni,
                    'pending' => $pendingAlumni,
                ],
                'academicUnits' => [
                    'totalCampuses'       => $totalCampuses,
                    'assignedCampuses'    => $assignedCampuses,
                    'unassignedCampuses'  => max(0, $totalCampuses - $assignedCampuses),
                    'totalColleges'       => $totalColleges,
                    'totalDepartments'    => $totalDepartments,
                    'assignedDepartments' => $assignedDepartments,
                ],
                'modules' => [
                    'totalSurveys'     => $totalSurveys,
                    'activeSurveys'    => $activeSurveys,
                    'totalResponses'   => $totalResponses,
                    'pendingResponses' => $pendingResponses,
                    'totalEvents'      => $totalEvents,
                    'totalNews'        => $totalNews,
                    'totalPayments'    => $totalPayments,
                ],
                'system' => [
                    'dbConnected'    => true,
                    'dbPingMs'       => $pingMs,
                    'phpVersion'     => PHP_VERSION,
                    'environment'    => ENVIRONMENT,
                    'writableStatus' => is_writable(WRITEPATH),
                    'serverTime'     => date('M d, Y h:i:s A'),
                ],
                'recentActivities' => $recentActivities,
            ],
        ]);
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

        $headAdminUsers = array_values(array_filter(
            $users,
            static fn (array $user): bool => normalize_role((string) ($user['Role'] ?? '')) === 'head_admin'
        ));

        return $this->response->setJSON([
            'success' => true,
            'users' => array_map(fn (array $user): array => $this->formatUserAccountForResponse($user), $headAdminUsers),
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

    public function listBackups(): ResponseInterface
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

        return $this->response->setJSON([
            'success' => true,
            'backups' => $this->getStoredBackupsList(),
        ]);
    }

    public function createBackup(): ResponseInterface
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

        $backupDir = WRITEPATH . 'backups';
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        $filename = 'supabase_backup_' . date('Y_m_d_His') . '.sql';
        $filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;

        try {
            $dumpResult = $this->generateSupabaseSqlDump();
            file_put_contents($filepath, $dumpResult['sql']);
            file_put_contents(
                $filepath . '.sha256',
                hash_hmac('sha256', $dumpResult['sql'], $this->getBackupSigningKey())
            );
            $bytes = filesize($filepath) ?: strlen($dumpResult['sql']);

            $backupEntry = [
                'name'        => $filename,
                'date'        => date('M d, Y h:i A'),
                'size'        => $this->formatBackupBytes((int) $bytes),
                'tablesCount' => $dumpResult['tablesCount'],
                'rowsCount'   => $dumpResult['rowsCount'],
                'format'      => 'Supabase PostgreSQL (.sql)',
                'status'      => 'Completed',
                'createdBy'   => (string) (session('auth_user.email') ?: 'Developer'),
                'downloadUrl' => '../developer/backups/download/' . rawurlencode($filename),
            ];

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Supabase-compatible PostgreSQL backup created.',
                'backup'  => $backupEntry,
                'backups' => $this->getStoredBackupsList(),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to generate Supabase backup: {msg}', ['msg' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Failed to generate Supabase backup. Please check server logs.',
            ]);
        }
    }

    public function downloadBackup(string $filename): ResponseInterface
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

        $safeName = basename(rawurldecode($filename));
        if ($safeName === '' || ! str_ends_with(strtolower($safeName), '.sql')) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Invalid backup filename.',
            ]);
        }

        $filepath = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . $safeName;
        if (! is_file($filepath)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Backup file not found.',
            ]);
        }

        $sqlContent = (string) file_get_contents($filepath);

        return $this->response
            ->setHeader('Content-Type', 'application/sql; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $safeName . '"')
            ->setHeader('Content-Length', (string) strlen($sqlContent))
            ->setBody($sqlContent);
    }

    public function deleteBackup(string $filename): ResponseInterface
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

        $safeName = basename(rawurldecode($filename));
        $filepath = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . $safeName;
        if ($safeName !== '' && str_ends_with(strtolower($safeName), '.sql') && is_file($filepath)) {
            @unlink($filepath);
            if (is_file($filepath . '.sha256')) {
                @unlink($filepath . '.sha256');
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Backup deleted.',
            'backups' => $this->getStoredBackupsList(),
        ]);
    }

    public function restoreBackup(): ResponseInterface
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

        $safeName = basename(trim((string) ($payload['filename'] ?? $payload['backup'] ?? '')));
        if ($safeName === '' || ! preg_match('/^supabase_backup_\d{4}_\d{2}_\d{2}_\d{6}\.sql$/', $safeName)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please select a valid .sql backup snapshot to restore.',
            ]);
        }

        $filepath = WRITEPATH . 'backups' . DIRECTORY_SEPARATOR . $safeName;
        if (! is_file($filepath)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Selected backup snapshot does not exist on the server.',
            ]);
        }

        try {
            $sql = (string) file_get_contents($filepath);
            $sigPath = $filepath . '.sha256';
            $expectedSig = is_file($sigPath) ? trim((string) file_get_contents($sigPath)) : '';
            $actualSig = hash_hmac('sha256', $sql, $this->getBackupSigningKey());

            if ($expectedSig === '' || ! hash_equals($expectedSig, $actualSig)) {
                return $this->response->setStatusCode(403)->setJSON([
                    'success' => false,
                    'message' => 'Backup integrity verification failed. Please create a fresh signed backup before restoring.',
                ]);
            }

            $db = \Config\Database::connect();
            $db->initialize();

            if (is_object($db->connID) || is_resource($db->connID)) {
                $ok = @pg_query($db->connID, $sql);
                if ($ok === false) {
                    $err = pg_last_error($db->connID);
                    throw new \RuntimeException($err ?: 'PostgreSQL restore query execution failed.');
                }
            } else {
                $db->query($sql);
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => "Backup snapshot {$safeName} restored to Supabase PostgreSQL.",
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Backup restore failed ({file}): {msg}', [
                'file' => $safeName,
                'msg'  => $e->getMessage(),
            ]);
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Restore failed. Please check server logs for details.',
            ]);
        }
    }

    /**
     * Generates a complete Supabase SQL Editor & psql compatible PostgreSQL dump
     * for the public schema, including CREATE TABLE IF NOT EXISTS, UPSERT/INSERT statements,
     * identity/sequence synchronization (setval), and RLS flags.
     *
     * @return array{sql:string, tablesCount:int, rowsCount:int}
     */
    private function generateSupabaseSqlDump(): array
    {
        $db = \Config\Database::connect();
        $db->initialize();
        $existingTables = $db->listTables();

        // Preferred dependency order so parent tables precede child tables
        $preferredOrder = [
            'campuses',
            'colleges',
            'departments',
            'user_accounts',
            'staffs',
            'alumni',
            'alumni_files',
            'alumni_batches',
            'alumni_batch_departments',
            'surveys',
            'categories',
            'questions',
            'choices',
            'question_logic',
            'responses',
            'answers',
            'response_reviews',
            'payment_purposes',
            'payments',
            'events',
            'news_posts',
            'activities',
        ];

        $orderedTables = [];
        foreach ($preferredOrder as $tbl) {
            if (in_array($tbl, $existingTables, true)) {
                $orderedTables[] = $tbl;
            }
        }
        foreach ($existingTables as $tbl) {
            if (! in_array($tbl, $orderedTables, true)) {
                $orderedTables[] = $tbl;
            }
        }

        // Introspect columns in public schema
        $colRows = $db->query("
            SELECT table_name, column_name, data_type, udt_name, is_nullable,
                   column_default, character_maximum_length, numeric_precision, numeric_scale,
                   is_identity, identity_generation
            FROM information_schema.columns
            WHERE table_schema = 'public'
            ORDER BY table_name, ordinal_position
        ")->getResultArray();

        $columnsByTable = [];
        foreach ($colRows as $col) {
            $columnsByTable[$col['table_name']][] = $col;
        }

        // Introspect primary keys in public schema
        $pkRows = $db->query("
            SELECT tc.table_name, kcu.column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON tc.constraint_name = kcu.constraint_name
             AND tc.table_schema = kcu.table_schema
            WHERE tc.constraint_type = 'PRIMARY KEY'
              AND tc.table_schema = 'public'
            ORDER BY tc.table_name, kcu.ordinal_position
        ")->getResultArray();

        $pkByTable = [];
        foreach ($pkRows as $pk) {
            $pkByTable[$pk['table_name']][] = $pk['column_name'];
        }

        // Introspect RLS enabled tables in public schema
        $rlsRows = $db->query("
            SELECT c.relname AS table_name, c.relrowsecurity
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relkind = 'r'
        ")->getResultArray();
        $rlsByTable = [];
        foreach ($rlsRows as $rls) {
            $rlsByTable[$rls['table_name']] = ($rls['relrowsecurity'] === 't' || $rls['relrowsecurity'] === true);
        }

        $lines = [];
        $lines[] = '-- ============================================================================';
        $lines[] = '-- USTP Alumni Tracer - Supabase PostgreSQL Database Backup (.sql)';
        $lines[] = '-- Generated At: ' . date('Y-m-d H:i:s P');
        $lines[] = '-- Target Schema: public (Supabase Postgres 15+ compatible)';
        $lines[] = '-- How to Import in Supabase:';
        $lines[] = '--   Option 1: Open Supabase Dashboard -> SQL Editor -> New Query, paste or';
        $lines[] = '--             upload this .sql file, and click "Run".';
        $lines[] = '--   Option 2: Run via CLI: psql "$SUPABASE_DB_URL" -f ' . 'supabase_backup.sql';
        $lines[] = '-- ============================================================================';
        $lines[] = '';
        $lines[] = 'BEGIN;';
        $lines[] = 'SET statement_timeout = 0;';
        $lines[] = 'SET lock_timeout = 0;';
        $lines[] = 'SET client_encoding = \'UTF8\';';
        $lines[] = 'SET standard_conforming_strings = on;';
        $lines[] = 'SET check_function_bodies = false;';
        $lines[] = 'SET client_min_messages = warning;';
        $lines[] = 'SET search_path = public, pg_catalog;';
        $lines[] = '';

        $totalRowsExported = 0;

        foreach ($orderedTables as $table) {
            $cols = $columnsByTable[$table] ?? [];
            if (empty($cols)) {
                continue;
            }

            $quotedTable = $this->quotePostgresIdentifier($db, $table);
            $pkCols = $pkByTable[$table] ?? [];
            $lines[] = '-- ----------------------------------------------------------------------------';
            $lines[] = '-- Table: "public".' . $quotedTable;
            $lines[] = '-- ----------------------------------------------------------------------------';

            $colDefs = [];
            $sequenceCols = [];
            $hasAlwaysIdentity = false;

            foreach ($cols as $c) {
                $colName = (string) $c['column_name'];
                $quotedColName = $this->quotePostgresIdentifier($db, $colName);
                $sqlType = $this->mapPostgresColumnType($c);
                $nullable = ($c['is_nullable'] === 'NO') ? ' NOT NULL' : '';
                $defaultClause = '';

                if (($c['is_identity'] ?? 'NO') === 'YES') {
                    $genType = strtoupper((string) ($c['identity_generation'] ?? 'BY DEFAULT'));
                    if ($genType === 'ALWAYS') {
                        $hasAlwaysIdentity = true;
                    }
                    $defaultClause = " GENERATED {$genType} AS IDENTITY";
                    $sequenceCols[] = $colName;
                } elseif (! empty($c['column_default'])) {
                    $defVal = (string) $c['column_default'];
                    if (str_starts_with($defVal, 'nextval(')) {
                        $defaultClause = ' GENERATED BY DEFAULT AS IDENTITY';
                        $sequenceCols[] = $colName;
                    } else {
                        $defaultClause = ' DEFAULT ' . $defVal;
                    }
                }

                $colDefs[] = '    ' . $quotedColName . ' ' . $sqlType . $defaultClause . $nullable;
            }

            if (! empty($pkCols)) {
                $quotedPks = array_map(fn (string $k): string => $this->quotePostgresIdentifier($db, $k), $pkCols);
                $colDefs[] = '    PRIMARY KEY (' . implode(', ', $quotedPks) . ')';
            }

            $lines[] = 'CREATE TABLE IF NOT EXISTS "public".' . $quotedTable . ' (';
            $lines[] = implode(",\n", $colDefs);
            $lines[] = ');';

            if (! empty($rlsByTable[$table])) {
                $lines[] = 'ALTER TABLE "public".' . $quotedTable . ' ENABLE ROW LEVEL SECURITY;';
            }
            $lines[] = '';

            // Export rows
            $rows = $db->table($table)->get()->getResultArray();
            if (! empty($rows)) {
                $colNames = array_map(static fn (array $c): string => (string) $c['column_name'], $cols);
                $quotedColList = implode(', ', array_map(fn (string $n): string => $this->quotePostgresIdentifier($db, $n), $colNames));
                $overrideClause = $hasAlwaysIdentity ? ' OVERRIDING SYSTEM VALUE' : '';

                // Build ON CONFLICT clause for idempotent Supabase imports
                $conflictClause = '';
                if (! empty($pkCols)) {
                    $quotedPks = implode(', ', array_map(fn (string $k): string => $this->quotePostgresIdentifier($db, $k), $pkCols));
                    $nonPkCols = array_values(array_diff($colNames, $pkCols));
                    if (! empty($nonPkCols)) {
                        $updateAssignments = array_map(
                            fn (string $col): string => $this->quotePostgresIdentifier($db, $col) . ' = EXCLUDED.' . $this->quotePostgresIdentifier($db, $col),
                            $nonPkCols
                        );
                        $conflictClause = ' ON CONFLICT (' . $quotedPks . ') DO UPDATE SET ' . implode(', ', $updateAssignments);
                    } else {
                        $conflictClause = ' ON CONFLICT (' . $quotedPks . ') DO NOTHING';
                    }
                }

                foreach ($rows as $row) {
                    $formattedValues = [];
                    foreach ($cols as $c) {
                        $cName = (string) $c['column_name'];
                        $val = $row[$cName] ?? null;
                        $formattedValues[] = $this->formatPostgresSqlLiteral($val, $c, $db);
                    }

                    $lines[] = 'INSERT INTO "public".' . $quotedTable . ' (' . $quotedColList . ')'
                        . $overrideClause
                        . ' VALUES (' . implode(', ', $formattedValues) . ')'
                        . $conflictClause . ';';
                    $totalRowsExported++;
                }
                $lines[] = '';
            }

            // Synchronize identity / sequence counters
            foreach ($sequenceCols as $seqCol) {
                $quotedSeqCol = $this->quotePostgresIdentifier($db, $seqCol);
                $seqTableArg = $this->quotePostgresLiteral($db, '"public".' . $quotedTable);
                $seqColArg = $this->quotePostgresLiteral($db, $seqCol);
                $lines[] = "SELECT setval(pg_get_serial_sequence({$seqTableArg}, {$seqColArg}), COALESCE((SELECT MAX({$quotedSeqCol}) FROM \"public\".{$quotedTable}), 1), (SELECT MAX({$quotedSeqCol}) IS NOT NULL FROM \"public\".{$quotedTable}));";
            }
            if (! empty($sequenceCols)) {
                $lines[] = '';
            }
        }

        $lines[] = 'COMMIT;';
        $lines[] = '';

        return [
            'sql'         => implode("\n", $lines),
            'tablesCount' => count($orderedTables),
            'rowsCount'   => $totalRowsExported,
        ];
    }

    private function mapPostgresColumnType(array $col): string
    {
        $dataType = strtolower((string) ($col['data_type'] ?? 'text'));
        $udtName = strtolower((string) ($col['udt_name'] ?? 'text'));
        $maxLen = $col['character_maximum_length'] ?? null;
        $numPrec = $col['numeric_precision'] ?? null;
        $numScale = $col['numeric_scale'] ?? null;

        return match ($dataType) {
            'character varying' => $maxLen !== null ? "varchar({$maxLen})" : 'varchar',
            'character'         => $maxLen !== null ? "char({$maxLen})" : 'char',
            'numeric', 'decimal'=> ($numPrec !== null && $numScale !== null) ? "numeric({$numPrec}, {$numScale})" : 'numeric',
            'integer'           => 'integer',
            'bigint'            => 'bigint',
            'smallint'          => 'smallint',
            'boolean'           => 'boolean',
            'text'              => 'text',
            'date'              => 'date',
            'time without time zone' => 'time',
            'time with time zone'    => 'timetz',
            'timestamp without time zone' => 'timestamp',
            'timestamp with time zone'    => 'timestamptz',
            'json'              => 'json',
            'jsonb'             => 'jsonb',
            'uuid'              => 'uuid',
            'bytea'             => 'bytea',
            'double precision'  => 'double precision',
            'real'              => 'real',
            default             => $udtName !== '' ? $udtName : 'text',
        };
    }

    private function getBackupSigningKey(): string
    {
        $encKey = (string) (config('Encryption')->key ?? '');
        if ($encKey !== '') {
            return $encKey;
        }

        return hash('sha256', ROOTPATH . '|' . (string) (config('Database')->default['database'] ?? 'postgres') . '|gv_backup_hmac');
    }

    private function quotePostgresIdentifier($db, string $identifier): string
    {
        $clean = str_replace("\0", '', $identifier);
        if ((is_object($db->connID) || is_resource($db->connID)) && function_exists('pg_escape_identifier')) {
            $quoted = @pg_escape_identifier($db->connID, $clean);
            if (is_string($quoted) && $quoted !== '') {
                return $quoted;
            }
        }

        return '"' . str_replace('"', '""', $clean) . '"';
    }

    private function quotePostgresLiteral($db, string $value): string
    {
        $clean = str_replace("\0", '', $value);
        if ((is_object($db->connID) || is_resource($db->connID)) && function_exists('pg_escape_literal')) {
            $quoted = @pg_escape_literal($db->connID, $clean);
            if (is_string($quoted) && $quoted !== '') {
                return $quoted;
            }
        }

        return (string) $db->escape($clean);
    }

    private function formatPostgresSqlLiteral(mixed $value, array $col, $db = null): string
    {
        if ($value === null) {
            return 'NULL';
        }

        $dataType = strtolower((string) ($col['data_type'] ?? 'text'));

        if ($dataType === 'boolean') {
            if (is_bool($value)) {
                return $value ? 'TRUE' : 'FALSE';
            }
            $strVal = strtolower(trim((string) $value));
            return in_array($strVal, ['t', 'true', '1', 'yes'], true) ? 'TRUE' : 'FALSE';
        }

        if (in_array($dataType, ['integer', 'bigint', 'smallint', 'numeric', 'decimal', 'real', 'double precision'], true)) {
            $strVal = trim((string) $value);
            if ($strVal === '') {
                return 'NULL';
            }
            if (preg_match('/^-?\d+(\.\d+)?$/', $strVal)) {
                return $strVal;
            }
        }

        $dbConn = $db ?? \Config\Database::connect();
        return $this->quotePostgresLiteral($dbConn, (string) $value);
    }

    private function getStoredBackupsList(): array
    {
        $backupDir = WRITEPATH . 'backups';
        if (! is_dir($backupDir)) {
            return [];
        }

        $files = glob($backupDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        usort($files, static fn (string $a, string $b): int => (int) filemtime($b) - (int) filemtime($a));

        $result = [];
        foreach ($files as $file) {
            $name = basename($file);
            $bytes = (int) (filesize($file) ?: 0);
            $mtime = (int) (filemtime($file) ?: time());

            $result[] = [
                'name'        => $name,
                'date'        => date('M d, Y h:i A', $mtime),
                'size'        => $this->formatBackupBytes($bytes),
                'format'      => 'Supabase PostgreSQL (.sql)',
                'status'      => 'Completed',
                'createdBy'   => 'Developer',
                'downloadUrl' => '../developer/backups/download/' . rawurlencode($name),
            ];
        }

        return $result;
    }

    private function formatBackupBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
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
        $role = normalize_role((string) ($user['Role'] ?? 'head_admin'));

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
            default => 'head_admin',
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
