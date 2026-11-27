<?php

namespace App\Controllers;

use App\Models\AlumniFilesModel;
use App\Models\AlumniModel;
use App\Models\NewsModel;
use App\Models\UserAccountModel;
use App\Services\SupabaseStorageService;
use CodeIgniter\HTTP\ResponseInterface;

class AlumniController extends BaseController
{
    private AlumniModel $alumniModel;
    private NewsModel $newsModel;
    private UserAccountModel $userAccountModel;
    private AlumniFilesModel $alumniFilesModel;
    private SupabaseStorageService $storage;

    public function __construct()
    {
        $this->alumniModel      = new AlumniModel();
        $this->newsModel        = new NewsModel();
        $this->userAccountModel = new UserAccountModel();
        $this->alumniFilesModel = new AlumniFilesModel();
        $this->storage          = new SupabaseStorageService();
    }

    public function main(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Alumni/Main.html');
    }

    public function eventsPage(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Alumni/Events.html');
    }

    public function newsPage(): ResponseInterface
    {
        return redirect()->to(base_url('alumni/Main.html'));
    }

    public function surveysPage(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Alumni/Surveys.html');
    }

    public function surveyRespondPage(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Alumni/SurveyRespond.html');
    }

    public function commonCss(): ResponseInterface
    {
        $css = file_get_contents(APPPATH . 'Views/HTML/Alumni/AlumniCommon.css');

        return $this->response
            ->setContentType('text/css')
            ->setBody($css === false ? '' : $css);
    }

    public function commonJs(): ResponseInterface
    {
        $js = file_get_contents(APPPATH . 'Views/HTML/Alumni/AlumniCommon.js');

        return $this->response
            ->setContentType('application/javascript')
            ->setBody($js === false ? '' : $js);
    }

    public function news(): ResponseInterface
    {
        if (! $this->isAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $newsItems = array_map(function (array $item): array {
            return [
                'id' => (int) ($item['id'] ?? 0),
                'title' => (string) ($item['title'] ?? 'Update'),
                'content' => (string) ($item['content'] ?? ''),
                'description' => (string) ($item['content'] ?? ''),
                'link_url' => (string) ($item['link_url'] ?? ''),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'author' => (string) ($item['author_name'] ?? 'Staff Office'),
                'author_role' => (string) ($item['author_role'] ?? 'staff'),
                'image_url' => $this->storage->resolvePublicUrl($item['image_path'] ?? null),
                'category' => 'campus news',
            ];
        }, $this->newsModel->getAllPublished());

        return $this->response->setJSON([
            'success' => true,
            'news' => $newsItems,
        ]);
    }

    public function list(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $scope = (new \App\Services\AssignmentScopeService())->resolveCurrentUserScope();
        if ($scope !== null && ($scope['valid'] ?? false) !== true) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => (string) ($scope['message'] ?? 'Forbidden.'),
            ]);
        }

        $sort = (string) ($this->request->getGet('sort') ?? 'last_name_asc');
        $filters = [
            'year'           => $this->request->getGet('year'),
            'graduationYear' => $this->request->getGet('graduationYear'),
            'college'        => $this->request->getGet('college'),
            'course'         => $this->request->getGet('course'),
            'status'         => $this->request->getGet('status'),
        ];
        $records = $this->alumniModel->getSortedList($sort, $filters, $scope);

        return $this->response->setJSON([
            'success' => true,
            'alumni'  => array_map([$this, 'formatAlumnus'], $records),
        ]);
    }

    public function batches(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $scope = (new \App\Services\AssignmentScopeService())->resolveCurrentUserScope();
        if ($scope !== null && ($scope['valid'] ?? false) !== true) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => (string) ($scope['message'] ?? 'Forbidden.'),
            ]);
        }

        $batchMap = [];
        foreach ($this->alumniModel->getBatchSummaries($scope) as $batch) {
            $year = trim((string) ($batch['GraduationYear'] ?? ''));
            if ($year === '') {
                continue;
            }

            $batchMap[$year] = [
                'graduationYear' => $year,
                'alumniCount' => (int) ($batch['alumni_count'] ?? 0),
                'activeCount' => (int) ($batch['active_count'] ?? 0),
                'colleges' => [],
                'departments' => [],
                'departmentSelections' => [],
            ];
        }

        $db = \Config\Database::connect();
        $yearColumn = $this->getBatchRegistryYearColumn();
        $campusId = $scope !== null ? (int) ($scope['campus_id'] ?? 0) : 0;

        $this->ensureMultiCampusBatchSchema();

        try {
            $regBuilder = $db->table('alumni_batches')
                ->select($yearColumn)
                ->orderBy($yearColumn, 'DESC');
            if ($campusId > 0) {
                $regBuilder->groupStart()
                    ->where('"Campus_ID"', $campusId)
                    ->orWhere('"Campus_ID" IS NULL', null, false)
                    ->groupEnd();
            }
            $registered = $regBuilder->get()->getResultArray();
        } catch (\Throwable) {
            $registered = [];
        }

        foreach ($registered as $row) {
            $year = trim((string) ($row[$yearColumn] ?? ''));
            if ($year === '' || isset($batchMap[$year])) {
                continue;
            }

            $batchMap[$year] = [
                'graduationYear' => $year,
                'alumniCount' => 0,
                'activeCount' => 0,
                'colleges' => [],
                'departments' => [],
                'departmentSelections' => [],
            ];
        }

        try {
            $this->attachBatchDepartmentSelections($batchMap, $campusId);
        } catch (\Throwable) {
            $this->ensureMultiCampusBatchSchema();
        }

        $batches = array_values($batchMap);
        usort($batches, static function (array $a, array $b): int {
            return strcmp((string) ($b['graduationYear'] ?? ''), (string) ($a['graduationYear'] ?? ''));
        });

        $summary = $this->alumniModel->getOverallCounts($scope);
        $summary['totalBatches'] = count($batches);

        return $this->response->setJSON([
            'success' => true,
            'batches' => $batches,
            'summary' => $summary,
        ]);
    }

    public function createBatch(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $scope = (new \App\Services\AssignmentScopeService())->resolveCurrentUserScope();
        if ($scope === null || ($scope['valid'] ?? false) !== true || ($scope['role'] ?? '') !== 'head_admin') {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Your account does not have a valid campus assignment.',
            ]);
        }

        $campusId = (int) $scope['campus_id'];
        $graduationYear = trim((string) ($this->request->getPost('graduationYear') ?? $this->request->getJSON(true)['graduationYear'] ?? ''));
        if (! preg_match('/^\d{4}$/', $graduationYear) || (int) $graduationYear < 1900 || (int) $graduationYear > 2100) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please enter a valid 4-digit graduation year (1900–2100).',
            ]);
        }

        $this->ensureMultiCampusBatchSchema();
        $db = \Config\Database::connect();
        $yearColumn = $this->getBatchRegistryYearColumn();

        try {
            // Prevent duplicate batch creation within the Head Admin's campus only
            $existing = $db->table('alumni_batches')
                ->select('id')
                ->where($yearColumn, (int) $graduationYear)
                ->where('"Campus_ID"', $campusId)
                ->get()
                ->getRowArray();

            if (is_array($existing)) {
                return $this->response->setStatusCode(409)->setJSON([
                    'success' => false,
                    'message' => "Batch {$graduationYear} already exists in your campus.",
                ]);
            }

            $db->table('alumni_batches')->insert([
                'Campus_ID' => $campusId,
                $yearColumn => (int) $graduationYear,
            ]);

            return $this->response->setJSON([
                'success' => true,
                'message' => "Batch {$graduationYear} created successfully.",
                'batch' => [
                    'graduationYear' => $graduationYear,
                    'alumniCount' => 0,
                    'activeCount' => 0,
                    'colleges' => [],
                    'departments' => [],
                    'departmentSelections' => [],
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'AlumniController::createBatch failed: {msg}', ['msg' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to create batch right now. Please try again.',
            ]);
        }
    }

    public function batchStructure(int $graduationYear): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized.']);
        }

        $scopeService = new \App\Services\AssignmentScopeService();
        $scope = $scopeService->resolveCurrentUserScope();
        if ($scope === null || ($scope['valid'] ?? false) !== true) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden.']);
        }

        $campusId = (int) $scope['campus_id'];
        $yearStr = (string) $graduationYear;
        if (! preg_match('/^\d{4}$/', $yearStr)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Invalid batch year.']);
        }

        $this->ensureMultiCampusBatchSchema();
        $db = \Config\Database::connect();

        // Collect all campus colleges linked to this batch (via alumni_batch_colleges, alumni_batch_departments, or alumni rows)
        $collegesById = [];

        $bcRows = $db->table('alumni_batch_colleges abc')
            ->select('c."College_ID", c."College_Name", c."Abbreviation"')
            ->join('colleges c', 'c."College_ID" = abc."College_ID"', 'inner')
            ->where('abc."Campus_ID"', $campusId)
            ->where('abc."GraduationYear"', $graduationYear)
            ->where('c."Campus_ID"', $campusId)
            ->orderBy('c."College_Name"', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($bcRows as $r) {
            $cid = (int) $r['College_ID'];
            $collegesById[$cid] = [
                'college_id'   => $cid,
                'college'      => (string) $r['College_Name'],
                'abbreviation' => (string) ($r['Abbreviation'] ?? ''),
                'departments'  => [],
                'alumniCount'  => 0,
            ];
        }

        $bdRows = $db->table('alumni_batch_departments abd')
            ->select('c."College_ID", c."College_Name", c."Abbreviation" AS college_abbr, d."Department_ID", d."Department_Name", d."Abbreviation" AS dept_abbr')
            ->join('colleges c', 'c."College_ID" = abd."College_ID"', 'inner')
            ->join('departments d', 'd."Department_ID" = abd."Department_ID"', 'inner')
            ->where('abd."GraduationYear"', $graduationYear)
            ->where('c."Campus_ID"', $campusId)
            ->where('d."Campus_ID"', $campusId)
            ->orderBy('c."College_Name"', 'ASC')
            ->orderBy('d."Department_Name"', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($bdRows as $r) {
            $cid = (int) $r['College_ID'];
            $did = (int) $r['Department_ID'];
            if (! isset($collegesById[$cid])) {
                $collegesById[$cid] = [
                    'college_id'   => $cid,
                    'college'      => (string) $r['College_Name'],
                    'abbreviation' => (string) ($r['college_abbr'] ?? ''),
                    'departments'  => [],
                    'alumniCount'  => 0,
                ];
            }
            $collegesById[$cid]['departments'][$did] = [
                'department_id' => $did,
                'department'    => (string) $r['Department_Name'],
                'abbreviation'  => (string) ($r['dept_abbr'] ?? ''),
                'alumniCount'   => 0,
            ];
        }

        // Also include colleges/departments inferred from existing alumni records in this campus & batch
        $alRows = $db->table('alumni a')
            ->select('c."College_ID", c."College_Name", c."Abbreviation" AS college_abbr, d."Department_ID", d."Department_Name", d."Abbreviation" AS dept_abbr, COUNT(a.id) AS alumni_count')
            ->join('colleges c', 'c."College_ID" = a."College_ID"', 'inner')
            ->join('departments d', 'd."Department_ID" = a."Department_ID"', 'left')
            ->where('a."GraduationYear"', $yearStr)
            ->where('c."Campus_ID"', $campusId)
            ->groupBy('c."College_ID", c."College_Name", c."Abbreviation", d."Department_ID", d."Department_Name", d."Abbreviation"')
            ->get()
            ->getResultArray();

        $totalAlumni = 0;
        foreach ($alRows as $r) {
            $cid = (int) $r['College_ID'];
            $did = (int) ($r['Department_ID'] ?? 0);
            $cnt = (int) ($r['alumni_count'] ?? 0);
            $totalAlumni += $cnt;

            if (! isset($collegesById[$cid])) {
                $collegesById[$cid] = [
                    'college_id'   => $cid,
                    'college'      => (string) $r['College_Name'],
                    'abbreviation' => (string) ($r['college_abbr'] ?? ''),
                    'departments'  => [],
                    'alumniCount'  => 0,
                ];
            }
            $collegesById[$cid]['alumniCount'] += $cnt;

            if ($did > 0) {
                if (! isset($collegesById[$cid]['departments'][$did])) {
                    $collegesById[$cid]['departments'][$did] = [
                        'department_id' => $did,
                        'department'    => (string) ($r['Department_Name'] ?? ''),
                        'abbreviation'  => (string) ($r['dept_abbr'] ?? ''),
                        'alumniCount'   => 0,
                    ];
                }
                $collegesById[$cid]['departments'][$did]['alumniCount'] += $cnt;
            }
        }

        $collegesList = [];
        $totalDepartments = 0;
        foreach ($collegesById as $c) {
            $depts = array_values($c['departments']);
            usort($depts, static fn (array $a, array $b): int => strcasecmp($a['department'], $b['department']));
            $c['departments'] = $depts;
            $c['departmentCount'] = count($depts);
            $totalDepartments += count($depts);
            $collegesList[] = $c;
        }
        usort($collegesList, static fn (array $a, array $b): int => strcasecmp($a['college'], $b['college']));

        return $this->response->setJSON([
            'success' => true,
            'graduationYear' => $yearStr,
            'colleges' => $collegesList,
            'counts' => [
                'colleges'    => count($collegesList),
                'departments' => $totalDepartments,
                'alumni'      => $totalAlumni,
            ],
        ]);
    }

    public function addBatchCollege(int $graduationYear): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized.']);
        }

        $scope = (new \App\Services\AssignmentScopeService())->resolveCurrentUserScope();
        if ($scope === null || ($scope['valid'] ?? false) !== true || ($scope['role'] ?? '') !== 'head_admin') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden.']);
        }

        $campusId = (int) $scope['campus_id'];
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }

        $collegeName = trim((string) ($payload['college_name'] ?? $payload['collegeName'] ?? ''));
        $abbreviation = trim((string) ($payload['abbreviation'] ?? ''));

        if ($collegeName === '') {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'College name is required.']);
        }
        if (mb_strlen($collegeName) > 150 || mb_strlen($abbreviation) > 20) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'College name or abbreviation exceeds maximum length.']);
        }

        $this->ensureMultiCampusBatchSchema();
        $db = \Config\Database::connect();
        $collegeModel = new \App\Models\CollegeModel();
        $importSvc = new \App\Services\BatchStructureImportService();

        $db->transBegin();
        try {
            $existing = $collegeModel
                ->where('Campus_ID', $campusId)
                ->where('LOWER("College_Name")', strtolower($collegeName))
                ->first();

            if (is_array($existing)) {
                $collegeId = (int) $existing['College_ID'];
                $alreadyLinked = $db->table('alumni_batch_colleges')
                    ->where('"Campus_ID"', $campusId)
                    ->where('"GraduationYear"', $graduationYear)
                    ->where('"College_ID"', $collegeId)
                    ->countAllResults() > 0;

                if ($alreadyLinked) {
                    $db->transRollback();
                    return $this->response->setStatusCode(409)->setJSON([
                        'success' => false,
                        'message' => 'This college is already added to Batch ' . $graduationYear . '.',
                    ]);
                }
                $importSvc->linkBatchCollege($db, $campusId, $graduationYear, $collegeId);
                $message = 'Existing campus college linked to Batch ' . $graduationYear . '.';
            } else {
                $collegeId = (int) $collegeModel->insert([
                    'Campus_ID'    => $campusId,
                    'College_Name' => $collegeName,
                    'Abbreviation' => $abbreviation,
                ]);
                $importSvc->linkBatchCollege($db, $campusId, $graduationYear, $collegeId);
                $message = 'College created and added to Batch ' . $graduationYear . '.';
            }

            $db->transCommit();
            return $this->response->setJSON([
                'success'    => true,
                'message'    => $message,
                'college_id' => $collegeId,
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'AlumniController::addBatchCollege failed: {msg}', ['msg' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Unable to add college right now.']);
        }
    }

    public function previewBatchCollegesImport(int $graduationYear): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized.']);
        }
        $scope = (new \App\Services\AssignmentScopeService())->resolveCurrentUserScope();
        if ($scope === null || ($scope['valid'] ?? false) !== true || ($scope['role'] ?? '') !== 'head_admin') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden.']);
        }

        $importSvc = new \App\Services\BatchStructureImportService();
        $parsed = $importSvc->parseUploadedFile($this->request->getFile('file'));
        if (! ($parsed['ok'] ?? false)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $parsed['error']]);
        }

        $analysis = $importSvc->analyzeCollegesAndDepartments($parsed['rows'], (int) $scope['campus_id']);
        if (! ($analysis['ok'] ?? false)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $analysis['error']]);
        }

        return $this->response->setJSON([
            'success'  => true,
            'colleges' => $analysis['colleges'],
            'rows'     => $analysis['rows'],
            'counts'   => $analysis['counts'],
        ]);
    }

    public function importBatchColleges(int $graduationYear): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized.']);
        }
        $scope = (new \App\Services\AssignmentScopeService())->resolveCurrentUserScope();
        if ($scope === null || ($scope['valid'] ?? false) !== true || ($scope['role'] ?? '') !== 'head_admin') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden.']);
        }

        $campusId = (int) $scope['campus_id'];
        $this->ensureMultiCampusBatchSchema();

        $importSvc = new \App\Services\BatchStructureImportService();
        $parsed = $importSvc->parseUploadedFile($this->request->getFile('file'));
        if (! ($parsed['ok'] ?? false)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $parsed['error']]);
        }

        $analysis = $importSvc->analyzeCollegesAndDepartments($parsed['rows'], $campusId);
        if (! ($analysis['ok'] ?? false)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $analysis['error']]);
        }

        if (($analysis['counts']['valid_rows'] ?? 0) === 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'No valid rows found to import. Please fix the validation errors and try again.',
                'rows'    => $analysis['rows'],
                'counts'  => $analysis['counts'],
            ]);
        }

        try {
            $summary = $importSvc->commitCollegesAndDepartments($analysis, $campusId, $graduationYear);
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Colleges and departments imported successfully.',
                'summary' => $summary,
                'rows'    => $analysis['rows'],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'AlumniController::importBatchColleges failed: {msg}', ['msg' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Import failed and was rolled back. No partial records were saved.']);
        }
    }

    public function downloadBatchCollegesTemplate(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setBody('Unauthorized.');
        }

        $format = (string) ($this->request->getGet('format') ?? 'xlsx');
        $rows = [
            ['College Name', 'College Abbreviation', 'Department Name', 'Department Abbreviation'],
            ['College of Engineering', 'COE', 'BS Information Technology', 'BSIT'],
            ['College of Engineering', 'COE', 'BS Computer Science', 'BSCS'],
            ['College of Agriculture', 'COA', 'BS Agriculture', 'BSA'],
        ];

        $tpl = (new \App\Services\BatchStructureImportService())->buildTemplate($rows, $format);
        return $this->response
            ->setHeader('Content-Type', $tpl['mime'])
            ->setHeader('Content-Disposition', 'attachment; filename="colleges_departments_template.' . $tpl['ext'] . '"')
            ->setBody($tpl['content']);
    }

    public function addBatchDepartment(int $graduationYear, int $collegeId): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized.']);
        }
        $scope = (new \App\Services\AssignmentScopeService())->resolveCurrentUserScope();
        if ($scope === null || ($scope['valid'] ?? false) !== true || ($scope['role'] ?? '') !== 'head_admin') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden.']);
        }

        $campusId = (int) $scope['campus_id'];
        $collegeModel = new \App\Models\CollegeModel();
        $college = $collegeModel->where('College_ID', $collegeId)->where('Campus_ID', $campusId)->first();
        if (! is_array($college)) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Selected college does not belong to your campus.']);
        }

        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            $payload = $this->request->getPost();
        }
        $deptName = trim((string) ($payload['department_name'] ?? $payload['departmentName'] ?? ''));
        $abbreviation = trim((string) ($payload['abbreviation'] ?? ''));

        if ($deptName === '') {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Department name is required.']);
        }
        if (mb_strlen($deptName) > 150 || mb_strlen($abbreviation) > 20) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Department name or abbreviation exceeds maximum length.']);
        }

        $this->ensureMultiCampusBatchSchema();
        $db = \Config\Database::connect();
        $deptModel = new \App\Models\DepartmentModel();
        $importSvc = new \App\Services\BatchStructureImportService();

        $db->transBegin();
        try {
            $existing = $deptModel
                ->where('Campus_ID', $campusId)
                ->where('College_ID', $collegeId)
                ->where('LOWER("Department_Name")', strtolower($deptName))
                ->first();

            if (is_array($existing)) {
                $deptId = (int) $existing['Department_ID'];
                $alreadyLinked = $db->table('alumni_batch_departments')
                    ->where('"GraduationYear"', $graduationYear)
                    ->where('"Department_ID"', $deptId)
                    ->countAllResults() > 0;
                if ($alreadyLinked) {
                    $db->transRollback();
                    return $this->response->setStatusCode(409)->setJSON([
                        'success' => false,
                        'message' => 'This department already exists under this college in Batch ' . $graduationYear . '.',
                    ]);
                }
                $importSvc->linkBatchCollege($db, $campusId, $graduationYear, $collegeId);
                $importSvc->linkBatchDepartment($db, $graduationYear, $collegeId, $deptId);
                $message = 'Existing department linked to Batch ' . $graduationYear . '.';
            } else {
                $deptId = (int) $deptModel->insert([
                    'Campus_ID'        => $campusId,
                    'College_ID'       => $collegeId,
                    'Department_Name'  => $deptName,
                    'Abbreviation'     => $abbreviation,
                    'Coordinator_Name' => '',
                ]);
                $importSvc->linkBatchCollege($db, $campusId, $graduationYear, $collegeId);
                $importSvc->linkBatchDepartment($db, $graduationYear, $collegeId, $deptId);
                $message = 'Department created and added to ' . $college['College_Name'] . '.';
            }

            $db->transCommit();
            return $this->response->setJSON([
                'success'       => true,
                'message'       => $message,
                'department_id' => $deptId,
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'AlumniController::addBatchDepartment failed: {msg}', ['msg' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Unable to add department right now.']);
        }
    }

    public function previewBatchDepartmentsImport(int $graduationYear, int $collegeId): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized.']);
        }
        $scope = (new \App\Services\AssignmentScopeService())->resolveCurrentUserScope();
        if ($scope === null || ($scope['valid'] ?? false) !== true || ($scope['role'] ?? '') !== 'head_admin') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden.']);
        }

        $campusId = (int) $scope['campus_id'];
        $college = (new \App\Models\CollegeModel())->where('College_ID', $collegeId)->where('Campus_ID', $campusId)->first();
        if (! is_array($college)) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Selected college does not belong to your campus.']);
        }

        $importSvc = new \App\Services\BatchStructureImportService();
        $parsed = $importSvc->parseUploadedFile($this->request->getFile('file'));
        if (! ($parsed['ok'] ?? false)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $parsed['error']]);
        }

        $analysis = $importSvc->analyzeDepartmentsForCollege($parsed['rows'], $campusId, $collegeId);
        if (! ($analysis['ok'] ?? false)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $analysis['error']]);
        }

        return $this->response->setJSON([
            'success'     => true,
            'college'     => (string) $college['College_Name'],
            'departments' => $analysis['departments'],
            'rows'        => $analysis['rows'],
            'counts'      => $analysis['counts'],
        ]);
    }

    public function importBatchDepartments(int $graduationYear, int $collegeId): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized.']);
        }
        $scope = (new \App\Services\AssignmentScopeService())->resolveCurrentUserScope();
        if ($scope === null || ($scope['valid'] ?? false) !== true || ($scope['role'] ?? '') !== 'head_admin') {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Forbidden.']);
        }

        $campusId = (int) $scope['campus_id'];
        $college = (new \App\Models\CollegeModel())->where('College_ID', $collegeId)->where('Campus_ID', $campusId)->first();
        if (! is_array($college)) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Selected college does not belong to your campus.']);
        }

        $this->ensureMultiCampusBatchSchema();
        $importSvc = new \App\Services\BatchStructureImportService();
        $parsed = $importSvc->parseUploadedFile($this->request->getFile('file'));
        if (! ($parsed['ok'] ?? false)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $parsed['error']]);
        }

        $analysis = $importSvc->analyzeDepartmentsForCollege($parsed['rows'], $campusId, $collegeId);
        if (! ($analysis['ok'] ?? false)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $analysis['error']]);
        }

        if (($analysis['counts']['valid_rows'] ?? 0) === 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'No valid department rows found to import.',
                'rows'    => $analysis['rows'],
                'counts'  => $analysis['counts'],
            ]);
        }

        try {
            $summary = $importSvc->commitDepartmentsForCollege($analysis, $campusId, $graduationYear, $collegeId);
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Departments imported successfully under ' . $college['College_Name'] . '.',
                'summary' => $summary,
                'rows'    => $analysis['rows'],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'AlumniController::importBatchDepartments failed: {msg}', ['msg' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Import failed and was rolled back. No partial records were saved.']);
        }
    }

    public function downloadBatchDepartmentsTemplate(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setBody('Unauthorized.');
        }

        $format = (string) ($this->request->getGet('format') ?? 'xlsx');
        $rows = [
            ['Department Name', 'Department Abbreviation'],
            ['BS Information Technology', 'BSIT'],
            ['BS Computer Science', 'BSCS'],
            ['BS Information Systems', 'BSIS'],
        ];

        $tpl = (new \App\Services\BatchStructureImportService())->buildTemplate($rows, $format);
        return $this->response
            ->setHeader('Content-Type', $tpl['mime'])
            ->setHeader('Content-Disposition', 'attachment; filename="departments_template.' . $tpl['ext'] . '"')
            ->setBody($tpl['content']);
    }

    public function deleteBatch(string $graduationYear): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $year = trim($graduationYear);
        if (! preg_match('/^\d{4}$/', $year)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please provide a valid 4-digit batch year.',
            ]);
        }

        $scope = (new \App\Services\AssignmentScopeService())->resolveCurrentUserScope();
        if ($scope === null || ($scope['valid'] ?? false) !== true || ($scope['role'] ?? '') !== 'head_admin') {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden.',
            ]);
        }
        $campusId = (int) $scope['campus_id'];
        $this->ensureMultiCampusBatchSchema();

        $db = \Config\Database::connect();
        $yearColumn = $this->getBatchRegistryYearColumn();

        $existing = $db->table('alumni_batches')
            ->select('id, "Campus_ID"')
            ->where($yearColumn, (int) $year)
            ->groupStart()
                ->where('"Campus_ID"', $campusId)
                ->orWhere('"Campus_ID" IS NULL', null, false)
            ->groupEnd()
            ->get()
            ->getRowArray();

        if (! $existing) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Batch not found in your campus.',
            ]);
        }

        // Check if alumni records exist in this batch FOR THIS CAMPUS ONLY
        $alumniCount = $db->table('alumni a')
            ->join('colleges c', 'c."College_ID" = a."College_ID"', 'inner')
            ->where('a."GraduationYear"', $year)
            ->where('c."Campus_ID"', $campusId)
            ->countAllResults();

        if ($alumniCount > 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Cannot delete batch: ' . $alumniCount . ' alumni record(s) exist in this batch. Please remove or reassign all alumni before deleting.',
            ]);
        }

        // Delete ONLY this campus's batch relationships (never affect other campuses)
        $campusCollegeIds = $scope['college_ids'] ?? [];
        if ($campusCollegeIds !== []) {
            $db->table('alumni_batch_departments')
                ->where('"GraduationYear"', (int) $year)
                ->whereIn('"College_ID"', $campusCollegeIds)
                ->delete();
        }

        $db->table('alumni_batch_colleges')
            ->where('"Campus_ID"', $campusId)
            ->where('"GraduationYear"', (int) $year)
            ->delete();

        $db->table('alumni_batches')
            ->where('id', (int) $existing['id'])
            ->delete();

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Batch deleted successfully.',
        ]);
    }

    public function create(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $payload = [
            'LastName'       => trim((string) $this->request->getPost('lastName')),
            'FirstName'      => trim((string) $this->request->getPost('firstName')),
            'Email'          => null,
            'GraduationYear' => trim((string) $this->request->getPost('graduationYear')),
            'Status'         => trim((string) $this->request->getPost('status')),
            'College'        => trim((string) $this->request->getPost('college')),
            'Course'         => trim((string) $this->request->getPost('course')),
        ];

        foreach ($payload as $field => $value) {
            if ($field === 'Email') {
                continue;
            }

            if ($value === '') {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Please fill in all alumni fields.',
                ]);
            }
        }

        if (! preg_match('/^\d{4}$/', $payload['GraduationYear'])) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Graduation Year must be a 4-digit year.',
            ]);
        }

        $allowedStatuses = ['active', 'pending', 'inactive'];
        if (! in_array(strtolower($payload['Status']), $allowedStatuses, true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Status must be active, pending, or inactive.',
            ]);
        }

        $payload['Status'] = strtolower($payload['Status']);
        $validCollegeDepartments = $this->getValidCollegeDepartmentImportMap();
        $collegeKey = $this->normalizeImportName($payload['College']);
        $departmentKey = $this->normalizeImportName($payload['Course']);

        if (
            $collegeKey === ''
            || $departmentKey === ''
            || ! isset($validCollegeDepartments[$collegeKey])
            || ! isset($validCollegeDepartments[$collegeKey]['departments'][$departmentKey])
        ) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'College and Department must match the registered college/department list.',
            ]);
        }

        $college = $validCollegeDepartments[$collegeKey];
        $department = $college['departments'][$departmentKey];

        if ($this->alumniModel->findDuplicateProfile(
            $payload['FirstName'],
            $payload['LastName'],
            null,
            (int) $college['id'],
            (int) $department['id']
        )) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'A matching alumni record already exists.',
            ]);
        }

        $insertPayload = $payload;
        unset($insertPayload['College'], $insertPayload['Course']);
        $insertPayload['College_ID'] = (int) $college['id'];
        $insertPayload['Department_ID'] = (int) $department['id'];

        $this->alumniModel->insert($insertPayload);

        $alumniId = $this->alumniModel->getInsertID();

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Alumni record added successfully.',
            'alumnus' => $this->formatAlumnus(array_merge($insertPayload, [
                'id' => (int) $alumniId,
                'College' => (string) $college['name'],
                'Course' => (string) $department['name'],
            ])),
        ]);
    }

    public function import(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $graduationYear = trim((string) $this->request->getPost('graduationYear'));
        if (! preg_match('/^\d{4}$/', $graduationYear)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please select a valid batch year before importing.',
            ]);
        }

        $file = $this->request->getFile('alumniFile');
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please upload a valid Excel file.',
            ]);
        }

        if (strtolower((string) $file->getExtension()) !== 'xlsx') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Only .xlsx Excel files are supported for import.',
            ]);
        }

        try {
            $rows = $this->readXlsxRows($file->getTempName());
        } catch (\Throwable $exception) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Unable to read the uploaded Excel file.',
            ]);
        }

        if ($rows === []) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'The uploaded Excel file is empty.',
            ]);
        }

        $headers = array_map(static fn ($value) => strtolower(trim((string) $value)), array_shift($rows));
        $normalizedHeaders = array_map(static function (string $header): string {
            return str_replace([' ', '_'], '', $header);
        }, $headers);
        $requiredHeaders = ['firstname', 'lastname', 'email', 'graduationyear', 'college'];
        $missingHeaders = array_values(array_diff($requiredHeaders, $normalizedHeaders));
        if (! in_array('department', $normalizedHeaders, true) && ! in_array('course', $normalizedHeaders, true)) {
            $missingHeaders[] = 'department';
        }

        if ($missingHeaders !== []) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Missing required Excel columns: ' . implode(', ', $missingHeaders),
            ]);
        }

        // If the Excel header contains duplicate columns (same name repeated),
        // `array_flip()` would keep only the last index and could make required
        // fields appear empty. So we store all indices per normalized header.
        $headerIndices = [];
        foreach ($normalizedHeaders as $i => $header) {
            $headerIndices[$header][] = $i;
        }

        $errors = [];
        $insertRows = [];
        $seenProfiles = [];
        $validCollegeDepartments = $this->getValidCollegeDepartmentImportMap();

        $getFirstNonEmpty = static function (array $row, array $indices): string {
            foreach ($indices as $idx) {
                $val = trim((string) ($row[$idx] ?? ''));
                if ($val !== '') {
                    return $val;
                }
            }
            return '';
        };

        foreach ($rows as $rowIndex => $row) {
            $isBlank = implode('', array_map(static fn ($value) => trim((string) $value), $row)) === '';
            if ($isBlank) {
                continue;
            }

            $firstName = $getFirstNonEmpty($row, $headerIndices['firstname'] ?? []);
            $lastName = $getFirstNonEmpty($row, $headerIndices['lastname'] ?? []);
            $emailFromSheet = $getFirstNonEmpty($row, $headerIndices['email'] ?? []);
            $rowGraduationYear = $getFirstNonEmpty($row, $headerIndices['graduationyear'] ?? []);
            $college = $getFirstNonEmpty($row, $headerIndices['college'] ?? []);
            $department = $getFirstNonEmpty($row, array_merge($headerIndices['department'] ?? [], $headerIndices['course'] ?? []));
            $excelRowNumber = $rowIndex + 2;

            // Graduation year is still required.
            if ($rowGraduationYear === '' || ! preg_match('/^\d{4}$/', $rowGraduationYear)) {
                $errors[] = "Row {$excelRowNumber}: Graduation Year must be a 4-digit year.";
                continue;
            }

            if ($rowGraduationYear !== $graduationYear) {
                $errors[] = "Row {$excelRowNumber}: Graduation Year must match the selected batch year {$graduationYear}.";
                continue;
            }

            $normalizedCollege = $this->normalizeImportName($college);
            $normalizedDepartment = $this->normalizeImportName($department);
            if (
                $normalizedCollege === ''
                || $normalizedDepartment === ''
                || ! isset($validCollegeDepartments[$normalizedCollege])
                || ! isset($validCollegeDepartments[$normalizedCollege]['departments'][$normalizedDepartment])
            ) {
                $errors[] = "Row {$excelRowNumber}: College and Department must match the registered college/department list. (skipped)";
                continue;
            }

            $college = $validCollegeDepartments[$normalizedCollege];
            $department = $college['departments'][$normalizedDepartment];
            $hasCompleteInfo = $firstName !== '' && $lastName !== '' && $emailFromSheet !== '' && $college['name'] !== '' && $department['name'] !== '';

            $emailForDb = $emailFromSheet;
            $emailIsValid = true;
            if ($emailFromSheet !== '' && ! filter_var($emailFromSheet, FILTER_VALIDATE_EMAIL)) {
                // Treat invalid emails as "missing" and mark record incomplete.
                $emailIsValid = false;
                $hasCompleteInfo = false;
                $emailForDb = null;
            }

            if ($emailForDb === '') {
                $emailForDb = null;
            }

            $profileKey = implode('|', [
                strtolower(trim($firstName)),
                strtolower(trim($lastName)),
                $emailForDb !== null ? strtolower(trim((string) $emailForDb)) : '<null>',
                (string) $college['id'],
                (string) $department['id'],
            ]);

            if (isset($seenProfiles[$profileKey]) || $this->alumniModel->findDuplicateProfile($firstName, $lastName, $emailForDb, (int) $college['id'], (int) $department['id'])) {
                $errors[] = "Row {$excelRowNumber}: matching alumni record already exists. (skipped)";
                continue;
            }

            $seenProfiles[$profileKey] = true;

            $insertRows[] = [
                'alumni_id' => null,
                'FirstName' => $firstName,
                'LastName' => $lastName,
                'Email' => $emailForDb,
                'GraduationYear' => $graduationYear,
                'Status' => $hasCompleteInfo ? 'pending' : 'incomplete',
                'College_ID' => (int) $college['id'],
                'Department_ID' => (int) $department['id'],
            ];
        }

        if ($insertRows === []) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'No alumni rows were found to import.',
                'errors' => $errors,
            ]);
        }

        $this->alumniModel->insertBatch($insertRows);

        return $this->response->setJSON([
            'success' => true,
            'message' => count($insertRows) . ' alumni record(s) imported successfully.',
            'imported' => count($insertRows),
            'skipped' => count($errors),
            'errors' => $errors,
        ]);
    }

    public function export(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $graduationYear = trim((string) $this->request->getGet('graduationYear'));
        if (! preg_match('/^\d{4}$/', $graduationYear)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please provide a valid batch year.',
            ]);
        }

        $college = trim((string) $this->request->getGet('college'));
        $course = trim((string) $this->request->getGet('course'));
        if ($college === '') {
            $college = 'all';
        }
        if ($course === '') {
            $course = 'all';
        }

        $scope = (new \App\Services\AssignmentScopeService())->resolveCurrentUserScope();
        if ($scope !== null && ($scope['valid'] ?? false) !== true) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => (string) ($scope['message'] ?? 'Forbidden.'),
            ]);
        }

        $records = array_values(array_filter(
            $this->alumniModel->getSortedList('last_name_asc', [], $scope),
            static function (array $row) use ($graduationYear, $college, $course): bool {
                if ((string) ($row['GraduationYear'] ?? '') !== $graduationYear) {
                    return false;
                }

                if ($college !== 'all' && strcasecmp((string) ($row['College'] ?? ''), $college) !== 0) {
                    return false;
                }

                if ($course !== 'all' && strcasecmp((string) ($row['Course'] ?? ''), $course) !== 0) {
                    return false;
                }

                return true;
            }
        ));

        if ($records === []) {
            $hasCollegeFilter = $college !== 'all';
            $hasCourseFilter = $course !== 'all';
            $message = 'No alumni records found for this batch.';
            if ($hasCollegeFilter && $hasCourseFilter) {
                $message = 'No alumni records found for this batch and selected college/course.';
            } elseif ($hasCollegeFilter) {
                $message = 'No alumni records found for this batch and selected college.';
            } elseif ($hasCourseFilter) {
                $message = 'No alumni records found for this batch and selected course.';
            }
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => $message,
            ]);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'alumni_export_');
        if ($tempFile === false) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to prepare the export file.',
            ]);
        }

        $xlsxPath = $tempFile . '.xlsx';
        @unlink($tempFile);

        try {
            $this->writeAlumniExportXlsx($xlsxPath, $records);
            $binary = file_get_contents($xlsxPath);
            @unlink($xlsxPath);

            if ($binary === false) {
                throw new \RuntimeException('Unable to read export.');
            }

            return $this->response
                ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->setHeader('Content-Disposition', 'attachment; filename="alumni_batch_' . $graduationYear . '.xlsx"')
                ->setBody($binary);
        } catch (\Throwable $exception) {
            @unlink($xlsxPath);
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to export the Excel file right now.',
            ]);
        }
    }

    private function isAuthorized(): bool
    {
        return session()->has('auth_user');
    }

    private function isStaffAuthorized(): bool
    {
        if (! session()->has('auth_user')) {
            return false;
        }

        return strtolower((string) (session('auth_user.role') ?? '')) === 'head_admin';
    }

    private function renderProtectedView(string $view): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return redirect()->to(base_url('/'));
        }

        $role = strtolower((string) (session('auth_user.role') ?? ''));
        if ($role === 'head_admin') {
            return redirect()->to(base_url('staff/Dashboard.html'));
        }

        return $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setBody(view($view));
    }

    private function formatAlumnus(array $record): array
    {
        return [
            'id'             => (int) ($record['id'] ?? 0),
            'alumniId'       => (string) ($record['alumni_id'] ?? ''),
            'lastName'       => (string) ($record['LastName'] ?? ''),
            'firstName'      => (string) ($record['FirstName'] ?? ''),
            'email'          => (string) ($record['Email'] ?? ''),
            'graduationYear' => (string) ($record['GraduationYear'] ?? ''),
            'status'         => (string) ($record['Status'] ?? ''),
            'college'        => (string) ($record['College'] ?? ''),
            'course'         => (string) ($record['Course'] ?? ''),
            'contactNumber'  => (string) ($record['Contact_Number'] ?? ''),
        ];
    }

    private function getValidCollegeDepartmentImportMap(): array
    {
        $collegeModel = new \App\Models\CollegeModel();
        $departmentModel = new \App\Models\DepartmentModel();

        // Always resolve verified campus from database via AssignmentScopeService (never trust unverified session/client)
        $scope = (new \App\Services\AssignmentScopeService())->resolveCurrentUserScope();
        if ($scope !== null && ($scope['valid'] ?? false) !== true) {
            return [];
        }

        $campusId = $scope !== null ? (int) ($scope['campus_id'] ?? 0) : 0;

        $collegeQuery = $collegeModel->orderBy('College_Name', 'ASC');
        if ($campusId > 0) {
            $collegeQuery->where('Campus_ID', $campusId);
        } elseif ($scope !== null) {
            return [];
        }

        $map = [];
        foreach ($collegeQuery->findAll() as $college) {
            $collegeId = (int) ($college['College_ID'] ?? 0);
            $collegeName = trim((string) ($college['College_Name'] ?? ''));
            $collegeKey = $this->normalizeImportName($collegeName);

            if ($collegeId <= 0 || $collegeKey === '') {
                continue;
            }

            $map[$collegeKey] = [
                'id' => $collegeId,
                'name' => $collegeName,
                'departments' => [],
            ];

            $departments = $departmentModel
                ->where('College_ID', $collegeId)
                ->orderBy('Department_Name', 'ASC')
                ->findAll();

            foreach ($departments as $department) {
                $departmentName = trim((string) ($department['Department_Name'] ?? ''));
                $departmentKey = $this->normalizeImportName($departmentName);

                if ($departmentKey !== '') {
                    $map[$collegeKey]['departments'][$departmentKey] = [
                        'id' => (int) ($department['Department_ID'] ?? 0),
                        'name' => $departmentName,
                    ];
                }
            }
        }

        return $map;
    }

    private function getDisplayNameForId(array $collegeDepartmentMap, string $type, int $id): string
    {
        foreach ($collegeDepartmentMap as $college) {
            if ($type === 'college' && (int) ($college['id'] ?? 0) === $id) {
                return (string) ($college['name'] ?? '');
            }

            foreach (($college['departments'] ?? []) as $department) {
                if ($type === 'department' && (int) ($department['id'] ?? 0) === $id) {
                    return (string) ($department['name'] ?? '');
                }
            }
        }

        return '';
    }

    private function normalizeImportName(string $value): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim($value))) ?? '';
    }

    private function ensureBatchRegistryTable(): void
    {
        $db = \Config\Database::connect();
        $db->query(
            'CREATE TABLE IF NOT EXISTS public.alumni_batches (
                id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                "GraduationYear" integer NOT NULL UNIQUE,
                created_at timestamptz DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    private function ensureBatchDepartmentTable(): void
    {
        $db = \Config\Database::connect();
        $db->query(
            'CREATE TABLE IF NOT EXISTS public.alumni_batch_departments (
                id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                "GraduationYear" integer NOT NULL,
                "College_ID" bigint NOT NULL,
                "Department_ID" bigint NOT NULL,
                created_at timestamptz DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT unique_batch_department UNIQUE ("GraduationYear", "Department_ID")
            )'
        );
    }

    private function ensureMultiCampusBatchSchema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        try {
            $db = \Config\Database::connect();
            $this->ensureBatchRegistryTable();
            $this->ensureBatchDepartmentTable();
            $db->query('ALTER TABLE public.alumni_batches ADD COLUMN IF NOT EXISTS "Campus_ID" bigint NULL');
            $db->query(
                'CREATE TABLE IF NOT EXISTS public.alumni_batch_colleges (
                    id bigint GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                    "Campus_ID" bigint NOT NULL,
                    "GraduationYear" integer NOT NULL,
                    "College_ID" bigint NOT NULL,
                    created_at timestamptz DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT unique_batch_campus_college UNIQUE ("Campus_ID", "GraduationYear", "College_ID")
                )'
            );
        } catch (\Throwable $e) {
            log_message('warning', 'ensureMultiCampusBatchSchema skipped: {msg}', ['msg' => $e->getMessage()]);
        }
    }

    private function attachBatchDepartmentSelections(array &$batchMap, int $campusId = 0): void
    {
        $db = \Config\Database::connect();
        $builder = $db->table('alumni_batch_departments abd')
            ->select('abd."GraduationYear", c."College_Name", d."Department_Name"')
            ->join('colleges c', 'c."College_ID" = abd."College_ID"', 'inner')
            ->join('departments d', 'd."Department_ID" = abd."Department_ID"', 'inner')
            ->orderBy('c."College_Name"', 'ASC')
            ->orderBy('d."Department_Name"', 'ASC');

        if ($campusId > 0) {
            $builder->where('c."Campus_ID"', $campusId);
        }

        $rows = $builder->get()->getResultArray();

        // Also attach colleges linked via alumni_batch_colleges (even if 0 departments yet)
        if ($campusId > 0) {
            try {
                $bcRows = $db->table('alumni_batch_colleges abc')
                    ->select('abc."GraduationYear", c."College_Name"')
                    ->join('colleges c', 'c."College_ID" = abc."College_ID"', 'inner')
                    ->where('abc."Campus_ID"', $campusId)
                    ->where('c."Campus_ID"', $campusId)
                    ->orderBy('c."College_Name"', 'ASC')
                    ->get()
                    ->getResultArray();
                foreach ($bcRows as $bcRow) {
                    $yr = (string) ($bcRow['GraduationYear'] ?? '');
                    $cName = trim((string) ($bcRow['College_Name'] ?? ''));
                    if ($yr === '' || $cName === '') {
                        continue;
                    }
                    if (! isset($batchMap[$yr])) {
                        $batchMap[$yr] = [
                            'graduationYear' => $yr,
                            'alumniCount' => 0,
                            'activeCount' => 0,
                            'colleges' => [],
                            'departments' => [],
                            'departmentSelections' => [],
                        ];
                    }
                    if (! in_array($cName, $batchMap[$yr]['colleges'], true)) {
                        $batchMap[$yr]['colleges'][] = $cName;
                    }
                }
            } catch (\Throwable) {
                // Table may not exist yet
            }
        }

        foreach ($rows as $row) {
            $year = (string) ($row['GraduationYear'] ?? '');
            if ($year === '') {
                continue;
            }

            if (! isset($batchMap[$year])) {
                $batchMap[$year] = [
                    'graduationYear' => $year,
                    'alumniCount' => 0,
                    'activeCount' => 0,
                    'colleges' => [],
                    'departments' => [],
                    'departmentSelections' => [],
                ];
            }

            $collegeName = trim((string) ($row['College_Name'] ?? ''));
            $departmentName = trim((string) ($row['Department_Name'] ?? ''));

            if ($collegeName !== '') {
                $batchMap[$year]['colleges'][] = $collegeName;
            }

            if ($departmentName !== '') {
                $batchMap[$year]['departments'][] = $departmentName;
                $batchMap[$year]['departmentSelections'][] = [
                    'college' => $collegeName,
                    'department' => $departmentName,
                ];
            }
        }

        foreach ($batchMap as &$batch) {
            $batch['colleges'] = array_values(array_unique($batch['colleges'] ?? []));
            $batch['departments'] = array_values(array_unique($batch['departments'] ?? []));
            $batch['departmentSelections'] = array_values($batch['departmentSelections'] ?? []);
        }
        unset($batch);
    }

    private function normalizeIdList($value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_values(array_unique(array_filter(array_map(static function ($item): int {
            return (int) $item;
        }, $values), static fn (int $id): bool => $id > 0)));
    }

    private function getValidBatchDepartments(array $collegeIds, array $departmentIds): array
    {
        $campusId = session('auth_user.campus') ?? session('auth_user.Campus') ?? '';

        if ($campusId === '') {
            return [];
        }

        $db = \Config\Database::connect();

        return $db->table('departments d')
            ->select('d.Department_ID, d.College_ID, d.Department_Name, c.College_Name')
            ->join('colleges c', 'c.College_ID = d.College_ID', 'inner')
            ->where('d.Campus_ID', $campusId)
            ->whereIn('d.College_ID', $collegeIds)
            ->whereIn('d.Department_ID', $departmentIds)
            ->orderBy('c.College_Name', 'ASC')
            ->orderBy('d.Department_Name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function getBatchRegistryYearColumn(): string
    {
        return 'GraduationYear';
    }

    private function readXlsxRows(string $filePath): array
    {
        $sharedStrings = [];
        $sharedStringsXml = $this->getXlsxEntry($filePath, 'xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $sharedStrings = $this->parseSharedStringsXml($sharedStringsXml);
        }

        $firstRows = [];
        for ($sheetNumber = 1; $sheetNumber <= 20; $sheetNumber++) {
            $sheetXml = $this->getXlsxEntry($filePath, "xl/worksheets/sheet{$sheetNumber}.xml");
            if ($sheetXml === false) {
                continue;
            }

            $rows = $this->parseWorksheetRowsXml($sheetXml, $sharedStrings);
            if ($firstRows === []) {
                $firstRows = $rows;
            }

            if ($this->rowsLookLikeAlumniImport($rows)) {
                return $rows;
            }
        }

        if ($firstRows === []) {
            throw new \RuntimeException('Worksheet not found.');
        }

        return $firstRows;
    }

    private function rowsLookLikeAlumniImport(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }

        $headers = array_map(static fn ($value) => str_replace([' ', '_'], '', strtolower(trim((string) $value))), $rows[0]);

        return in_array('firstname', $headers, true)
            && in_array('lastname', $headers, true)
            && in_array('email', $headers, true)
            && in_array('graduationyear', $headers, true)
            && in_array('college', $headers, true)
            && (in_array('department', $headers, true) || in_array('course', $headers, true));
    }

    private function getXlsxEntry(string $filePath, string $entryName): string|false
    {
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) !== true) {
                throw new \RuntimeException('Unable to open file.');
            }

            $contents = $zip->getFromName($entryName);
            $zip->close();

            return $contents;
        }

        return $this->getZipEntryWithoutExtension($filePath, $entryName);
    }

    private function getZipEntryWithoutExtension(string $filePath, string $entryName): string|false
    {
        $data = file_get_contents($filePath);
        if ($data === false) {
            throw new \RuntimeException('Unable to read file.');
        }

        $eocdOffset = strrpos($data, "PK\x05\x06");
        if ($eocdOffset === false) {
            throw new \RuntimeException('Invalid Excel file.');
        }

        $eocd = unpack('vdisk/vstartDisk/ventriesDisk/ventries/VcentralSize/VcentralOffset/vcommentLength', substr($data, $eocdOffset + 4, 18));
        if (! is_array($eocd)) {
            throw new \RuntimeException('Invalid Excel directory.');
        }

        $offset = (int) $eocd['centralOffset'];
        $entries = (int) $eocd['entries'];

        for ($i = 0; $i < $entries; $i++) {
            if (substr($data, $offset, 4) !== "PK\x01\x02") {
                break;
            }

            $header = unpack(
                'vversionMade/vversionNeeded/vflags/vmethod/vmodTime/vmodDate/Vcrc/VcompressedSize/VuncompressedSize/vnameLength/vextraLength/vcommentLength/vdiskStart/vinternalAttrs/VexternalAttrs/VlocalOffset',
                substr($data, $offset + 4, 42)
            );
            if (! is_array($header)) {
                break;
            }

            $nameLength = (int) $header['nameLength'];
            $extraLength = (int) $header['extraLength'];
            $commentLength = (int) $header['commentLength'];
            $name = substr($data, $offset + 46, $nameLength);

            if ($name === $entryName) {
                $localOffset = (int) $header['localOffset'];
                if (substr($data, $localOffset, 4) !== "PK\x03\x04") {
                    throw new \RuntimeException('Invalid Excel local file header.');
                }

                $localHeader = unpack('vversion/vflags/vmethod/vmodTime/vmodDate/Vcrc/VcompressedSize/VuncompressedSize/vnameLength/vextraLength', substr($data, $localOffset + 4, 26));
                if (! is_array($localHeader)) {
                    throw new \RuntimeException('Invalid Excel local file metadata.');
                }

                $dataStart = $localOffset + 30 + (int) $localHeader['nameLength'] + (int) $localHeader['extraLength'];
                $compressed = substr($data, $dataStart, (int) $header['compressedSize']);
                $method = (int) $header['method'];

                if ($method === 0) {
                    return $compressed;
                }

                if ($method === 8) {
                    $inflated = gzinflate($compressed);
                    if ($inflated === false) {
                        throw new \RuntimeException('Unable to decompress Excel worksheet.');
                    }

                    return $inflated;
                }

                throw new \RuntimeException('Unsupported Excel compression method.');
            }

            $offset += 46 + $nameLength + $extraLength + $commentLength;
        }

        return false;
    }

    private function parseSharedStringsXml(string $xml): array
    {
        $document = new \DOMDocument();
        if (! @$document->loadXML($xml)) {
            return [];
        }

        $xpath = new \DOMXPath($document);
        $items = $xpath->query('//*[local-name()="si"]');
        if ($items === false) {
            return [];
        }

        $values = [];
        foreach ($items as $item) {
            $textNodes = $xpath->query('.//*[local-name()="t"]', $item);
            if ($textNodes === false) {
                $values[] = '';
                continue;
            }

            $text = '';
            foreach ($textNodes as $textNode) {
                $text .= $textNode->textContent;
            }
            $values[] = $text;
        }

        return $values;
    }

    private function parseWorksheetRowsXml(string $xml, array $sharedStrings): array
    {
        $document = new \DOMDocument();
        if (! @$document->loadXML($xml)) {
            return [];
        }

        $xpath = new \DOMXPath($document);
        $rowNodes = $xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]');
        if ($rowNodes === false) {
            return [];
        }

        $rows = [];
        foreach ($rowNodes as $rowNode) {
            $rowValues = [];
            $cellNodes = $xpath->query('./*[local-name()="c"]', $rowNode);
            if ($cellNodes === false) {
                continue;
            }

            foreach ($cellNodes as $cellNode) {
                if (! $cellNode instanceof \DOMElement) {
                    continue;
                }

                $reference = (string) $cellNode->getAttribute('r');
                preg_match('/[A-Z]+/', strtoupper($reference), $matches);
                $columnLetters = $matches[0] ?? 'A';
                $columnIndex = $this->columnLettersToIndex($columnLetters);
                $type = (string) $cellNode->getAttribute('t');

                if ($type === 's') {
                    $valueNode = $xpath->query('./*[local-name()="v"]', $cellNode);
                    $sharedIndex = $valueNode !== false && $valueNode->length > 0
                        ? (int) trim($valueNode->item(0)->textContent)
                        : -1;
                    $value = $sharedStrings[$sharedIndex] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = '';
                    $textNodes = $xpath->query('.//*[local-name()="is"]//*[local-name()="t"]', $cellNode);
                    if ($textNodes !== false) {
                        foreach ($textNodes as $textNode) {
                            $value .= $textNode->textContent;
                        }
                    }
                } else {
                    $valueNode = $xpath->query('./*[local-name()="v"]', $cellNode);
                    $value = $valueNode !== false && $valueNode->length > 0
                        ? trim($valueNode->item(0)->textContent)
                        : '';
                }

                $rowValues[$columnIndex] = $value;
            }

            if ($rowValues === []) {
                continue;
            }

            ksort($rowValues);
            $maxColumn = max(array_keys($rowValues));
            $normalized = [];
            for ($index = 0; $index <= $maxColumn; $index++) {
                $normalized[] = $rowValues[$index] ?? '';
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    private function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return max(0, $index - 1);
    }

    private function generateAlumniId(string $graduationYear, int $offset = 0): string
    {
        $count = $this->alumniModel->builder()
            ->where('GraduationYear', $graduationYear)
            ->countAllResults();

        $nextNumber = $count + 1 + $offset;
        $candidate = $graduationYear . sprintf('%04d', $nextNumber);

        // Safety check in case the ID already exists (race condition or manual insert)
        while ($this->alumniModel->builder()
            ->where('alumni_id', $candidate)
            ->limit(1)
            ->get()
            ->getRowArray()) {
            $nextNumber++;
            $candidate = $graduationYear . sprintf('%04d', $nextNumber);
        }

        return $candidate;
    }

    private function writeAlumniExportXlsx(string $filePath, array $records): void
    {
        $rows = [[
            'FirstName',
            'LastName',
            'Email',
            'Graduation Year',
            'Status',
            'College',
            'Course',
        ]];

        foreach ($records as $record) {
            $rows[] = [
                (string) ($record['FirstName'] ?? ''),
                (string) ($record['LastName'] ?? ''),
                (string) ($record['Email'] ?? ''),
                (string) ($record['GraduationYear'] ?? ''),
                (string) ($record['Status'] ?? ''),
                (string) ($record['College'] ?? ''),
                (string) ($record['Course'] ?? ''),
            ];
        }

        $sharedStrings = [];
        $sharedStringIndex = [];
        $sheetRowsXml = '';

        foreach ($rows as $rowNumber => $row) {
            $cellXml = '';
            foreach ($row as $columnIndex => $value) {
                $cellRef = $this->indexToColumnLetters($columnIndex) . ($rowNumber + 1);
                $stringValue = (string) $value;

                if (! array_key_exists($stringValue, $sharedStringIndex)) {
                    $sharedStringIndex[$stringValue] = count($sharedStrings);
                    $sharedStrings[] = $stringValue;
                }

                $cellXml .= '<c r="' . $cellRef . '" t="s"><v>' . $sharedStringIndex[$stringValue] . '</v></c>';
            }

            $sheetRowsXml .= '<row r="' . ($rowNumber + 1) . '">' . $cellXml . '</row>';
        }

        $sharedStringsXmlItems = '';
        foreach ($sharedStrings as $value) {
            $escaped = htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
            $sharedStringsXmlItems .= '<si><t>' . $escaped . '</t></si>';
        }

        $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>
XML;

        $rootRels = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;

        $workbook = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Alumni Batch" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;

        $workbookRels = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>
XML;

        $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetRowsXml . '</sheetData>'
            . '</worksheet>';

        $styles = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styleSheet>
XML;

        $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">'
            . $sharedStringsXmlItems
            . '</sst>';

        $zip = new \ZipArchive();
        if ($zip->open($filePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create zip archive.');
        }

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rootRels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheet);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        $zip->close();
    }

    private function indexToColumnLetters(int $index): string
    {
        $index += 1;
        $letters = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = intdiv($index - $mod, 26);
            $index--;
        }

        return $letters;
    }

    public function profile(): ResponseInterface
    {
        try {
            log_message('info', 'Profile method called');
            
            if (! $this->isAlumniAuthorized()) {
                log_message('error', 'Unauthorized access to profile');
                return $this->response->setStatusCode(401)->setJSON([
                    'success' => false,
                    'message' => 'Unauthorized.',
                ]);
            }

            $currentUser = session('auth_user');
            if (!$currentUser) {
                log_message('error', 'No active session found in profile');
                return $this->response->setStatusCode(401)->setJSON([
                    'success' => false,
                    'message' => 'No active session found.',
                ]);
            }

            log_message('info', 'Current user session: ' . print_r($currentUser, true));

            // Get alumni data with joined college and department display names.
            $alumniData = $this->alumniModel->findByEmail((string) ($currentUser['email'] ?? ''));
            
            if (!$alumniData) {
                log_message('error', 'Alumni data not found for email: ' . $currentUser['email']);
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'message' => 'Alumni profile not found.',
                ]);
            }

            log_message('info', 'Alumni data found: ' . print_r($alumniData, true));

            $alumniDbId = (int) ($alumniData['id'] ?? 0);
            $activePhoto = $alumniDbId > 0 ? $this->alumniFilesModel->getActiveProfilePicture($alumniDbId) : null;
            $hasProfilePicture = is_array($activePhoto);
            $profilePictureUrl = null;
            if ($hasProfilePicture) {
                $storedPhotoPath = (string) ($activePhoto['File_Path'] ?? '');
                $profilePictureUrl = $this->storage->isSupabaseReference($storedPhotoPath)
                    ? $this->storage->resolvePublicUrl($storedPhotoPath)
                    : base_url('alumni/profile-picture');
            }

            $birthMonth = ! empty($alumniData['birth_month']) ? (int) $alumniData['birth_month'] : null;
            $birthDate = ! empty($alumniData['birth_date']) ? (int) $alumniData['birth_date'] : null;
            $birthYear = ! empty($alumniData['birth_year']) ? (int) $alumniData['birth_year'] : null;
            $sexVal = trim((string) ($alumniData['sex'] ?? ''));
            $emContact = trim((string) ($alumniData['emergency_contact'] ?? ''));
            $emName = trim((string) ($alumniData['emergency_contact_name'] ?? ''));

            $validDob = $birthMonth !== null && $birthDate !== null && $birthYear !== null
                && $birthMonth >= 1 && $birthMonth <= 12
                && $birthDate >= 1 && $birthDate <= 31
                && $birthYear >= 1920
                && checkdate($birthMonth, $birthDate, $birthYear);

            $profileData = [
                'alumni_id'              => $alumniData['alumni_id'] ?? '',
                'firstName'              => $currentUser['first_name'] ?? '',
                'lastName'               => $currentUser['last_name'] ?? '',
                'email'                  => $currentUser['email'] ?? '',
                'graduationYear'         => $alumniData['GraduationYear'] ?? '',
                'college'                => $alumniData['College'] ?? '',
                'course'                 => $alumniData['Course'] ?? '',
                'province'               => $alumniData['Province'] ?? '',
                'municipality'           => $alumniData['Municipality'] ?? '',
                'barangay'               => $alumniData['Barangay'] ?? '',
                'contactNumber'          => $alumniData['Contact_Number'] ?? '',
                'sex'                    => $sexVal,
                'birth_month'            => $birthMonth,
                'birth_date'             => $birthDate,
                'birth_year'             => $birthYear,
                'emergency_contact'      => $emContact,
                'emergency_contact_name' => $emName,
                'profile_incomplete'     => ($sexVal === '' || ! $validDob || $emContact === '' || $emName === ''),
                'has_profile_picture'    => $hasProfilePicture,
                'profile_picture_url'    => $profilePictureUrl,
            ];

            log_message('info', 'Profile data prepared: ' . print_r($profileData, true));

            return $this->response->setJSON([
                'success' => true,
                'profile' => $profileData,
            ]);
        } catch (Exception $e) {
            log_message('error', 'Exception in profile method: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Internal server error.',
            ]);
        }
    }

    public function update(): ResponseInterface
    {
        try {
            if (! $this->isAlumniAuthorized()) {
                return $this->response->setStatusCode(401)->setJSON([
                    'success' => false,
                    'message' => 'Unauthorized. Only authenticated alumni can update their own profile.',
                ]);
            }

            $jsonData = $this->request->getJSON(true);
            if (! is_array($jsonData)) {
                $rawInput = $this->request->getRawInput();
                $decoded = json_decode((string) $rawInput, true);
                $jsonData = is_array($decoded) ? $decoded : null;
            }

            if ($jsonData === null) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Invalid JSON data provided.',
                ]);
            }

            $currentUser = session('auth_user');
            if (! is_array($currentUser) || empty($currentUser['email'])) {
                return $this->response->setStatusCode(401)->setJSON([
                    'success' => false,
                    'message' => 'No active session found.',
                ]);
            }

            $sessionEmail = (string) $currentUser['email'];
            $existingAlumni = $this->alumniModel->findByEmail($sessionEmail);
            if (! is_array($existingAlumni) && ! empty($currentUser['account_id'])) {
                $existingAlumni = $this->alumniModel->where('alumni_id', (string) $currentUser['account_id'])->first();
            }

            if (! is_array($existingAlumni) || empty($existingAlumni['id'])) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'message' => 'Alumni profile record not found.',
                ]);
            }

            $email = trim((string) ($jsonData['email'] ?? $sessionEmail));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Please provide a valid email address.',
                ]);
            }

            $alumniUpdate = ['Email' => $email];

            // Validate and merge personal & emergency contact fields if any were submitted
            $personalKeys = ['sex', 'birth_month', 'birth_date', 'birth_year', 'emergency_contact', 'emergency_contact_name'];
            $hasPersonalSubmission = false;
            foreach ($personalKeys as $key) {
                if (array_key_exists($key, $jsonData)) {
                    $hasPersonalSubmission = true;
                    break;
                }
            }

            if ($hasPersonalSubmission) {
                $validation = $this->validatePersonalEmergencyFields($jsonData, $existingAlumni, false);
                if (($validation['valid'] ?? false) !== true) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'success' => false,
                        'message' => (string) ($validation['message'] ?? 'Invalid personal or emergency contact details.'),
                    ]);
                }
                $alumniUpdate = array_merge($alumniUpdate, $validation['fields']);
            }

            $currentFirstName = (string) ($currentUser['first_name'] ?? '');
            $currentLastName = (string) ($currentUser['last_name'] ?? '');

            if (strcasecmp($email, $sessionEmail) !== 0) {
                $this->userAccountModel->builder()->where('Email', $sessionEmail)->update([
                    'Email' => $email,
                ]);
            }

            // Always update strictly by the session-verified alumni DB primary key (never trust client-sent IDs)
            $this->alumniModel->builder()
                ->where('id', (int) $existingAlumni['id'])
                ->update($alumniUpdate);

            session()->set('auth_user', array_merge($currentUser, [
                'first_name' => $currentFirstName,
                'last_name'  => $currentLastName,
                'email'      => $email,
                'name'       => trim($currentFirstName . ' ' . $currentLastName),
            ]));

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'user'    => [
                    'first_name' => $currentFirstName,
                    'last_name'  => $currentLastName,
                    'email'      => $email,
                    'name'       => trim($currentFirstName . ' ' . $currentLastName),
                ],
                'personal' => [
                    'sex'                    => $alumniUpdate['sex'] ?? ($existingAlumni['sex'] ?? ''),
                    'birth_month'            => $alumniUpdate['birth_month'] ?? ($existingAlumni['birth_month'] ?? null),
                    'birth_date'             => $alumniUpdate['birth_date'] ?? ($existingAlumni['birth_date'] ?? null),
                    'birth_year'             => $alumniUpdate['birth_year'] ?? ($existingAlumni['birth_year'] ?? null),
                    'emergency_contact'      => $alumniUpdate['emergency_contact'] ?? ($existingAlumni['emergency_contact'] ?? ''),
                    'emergency_contact_name' => $alumniUpdate['emergency_contact_name'] ?? ($existingAlumni['emergency_contact_name'] ?? ''),
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'AlumniController::update failed: {msg}', ['msg' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to update profile right now. Please try again.',
            ]);
        }
    }

    /**
     * Save required personal and emergency contact details for the authenticated alumni
     * right after login (profile-completion step).
     */
    public function completePersonalInfo(): ResponseInterface
    {
        try {
            if (! $this->isAlumniAuthorized()) {
                return $this->response->setStatusCode(401)->setJSON([
                    'success' => false,
                    'message' => 'Unauthorized. Please log in as an alumni account.',
                ]);
            }

            $input = $this->request->getJSON(true);
            if (! is_array($input)) {
                $input = $this->request->getPost();
            }
            if (! is_array($input)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Invalid request payload.',
                ]);
            }

            $currentUser = session('auth_user');
            $sessionEmail = trim((string) ($currentUser['email'] ?? ''));
            $existingAlumni = $sessionEmail !== '' ? $this->alumniModel->findByEmail($sessionEmail) : null;
            if (! is_array($existingAlumni) && ! empty($currentUser['account_id'])) {
                $existingAlumni = $this->alumniModel->where('alumni_id', (string) $currentUser['account_id'])->first();
            }

            if (! is_array($existingAlumni) || empty($existingAlumni['id'])) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'message' => 'Your alumni record could not be found. Please contact the administrator.',
                ]);
            }

            $validation = $this->validatePersonalEmergencyFields($input, $existingAlumni, true);
            if (($validation['valid'] ?? false) !== true) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => (string) ($validation['message'] ?? 'Please complete all required personal information fields.'),
                ]);
            }

            // Update existing authenticated alumni row only (never create a new record or trust client IDs)
            $this->alumniModel->builder()
                ->where('id', (int) $existingAlumni['id'])
                ->update($validation['fields']);

            return $this->response->setJSON([
                'success'  => true,
                'message'  => 'Personal information saved successfully.',
                'redirect' => base_url('alumni/Main.html'),
                'personal' => $validation['fields'],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'AlumniController::completePersonalInfo failed: {msg}', ['msg' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to save personal information right now. Please try again.',
            ]);
        }
    }

    /**
     * Validates Sex, Birth Month/Date/Year (including leap years & no future dates),
     * Emergency Contact Name, and Emergency Contact Number (PH 09/+639 & international).
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existing
     * @return array{valid: bool, message?: string, fields?: array<string, mixed>}
     */
    private function validatePersonalEmergencyFields(array $input, array $existing = [], bool $requireAll = true): array
    {
        $fields = [];

        // 1. Sex
        $hasSex = array_key_exists('sex', $input);
        $rawSex = $hasSex ? trim((string) ($input['sex'] ?? '')) : trim((string) ($existing['sex'] ?? ''));
        if ($requireAll || $hasSex) {
            $sexMap = [
                'male'   => 'Male',
                'female' => 'Female',
            ];
            $normalizedSex = $sexMap[strtolower($rawSex)] ?? null;
            if ($normalizedSex === null) {
                return [
                    'valid'   => false,
                    'message' => 'Please select a valid Sex option (Male or Female).',
                ];
            }
            $fields['sex'] = $normalizedSex;
        }

        // 2. Birth Month, Birth Date, Birth Year
        $hasBirthPart = array_key_exists('birth_month', $input)
            || array_key_exists('birth_date', $input)
            || array_key_exists('birth_year', $input);

        if ($requireAll || $hasBirthPart) {
            $rawMonth = array_key_exists('birth_month', $input) ? $input['birth_month'] : ($existing['birth_month'] ?? null);
            $rawDate = array_key_exists('birth_date', $input) ? $input['birth_date'] : ($existing['birth_date'] ?? null);
            $rawYear = array_key_exists('birth_year', $input) ? $input['birth_year'] : ($existing['birth_year'] ?? null);

            if (
                $rawMonth === null || $rawMonth === ''
                || $rawDate === null || $rawDate === ''
                || $rawYear === null || $rawYear === ''
                || ! ctype_digit((string) $rawMonth)
                || ! ctype_digit((string) $rawDate)
                || ! ctype_digit((string) $rawYear)
            ) {
                return [
                    'valid'   => false,
                    'message' => 'Please select a complete Birth Month, Birth Date, and Birth Year.',
                ];
            }

            $month = (int) $rawMonth;
            $day = (int) $rawDate;
            $year = (int) $rawYear;
            $currentYear = (int) date('Y');

            if ($month < 1 || $month > 12) {
                return [
                    'valid'   => false,
                    'message' => 'Birth Month must be between January (1) and December (12).',
                ];
            }

            if ($year < 1920 || $year > $currentYear) {
                return [
                    'valid'   => false,
                    'message' => "Birth Year must be between 1920 and {$currentYear}.",
                ];
            }

            if (! checkdate($month, $day, $year)) {
                return [
                    'valid'   => false,
                    'message' => 'The selected birthdate is not a valid calendar date.',
                ];
            }

            $dobIso = sprintf('%04d-%02d-%02d', $year, $month, $day);
            if ($dobIso > date('Y-m-d')) {
                return [
                    'valid'   => false,
                    'message' => 'Birthdate cannot be in the future.',
                ];
            }

            $fields['birth_month'] = $month;
            $fields['birth_date'] = $day;
            $fields['birth_year'] = $year;
        }

        // 3. Emergency Contact Name
        $hasEmName = array_key_exists('emergency_contact_name', $input);
        $rawEmName = $hasEmName
            ? trim((string) ($input['emergency_contact_name'] ?? ''))
            : trim((string) ($existing['emergency_contact_name'] ?? ''));

        if ($requireAll || $hasEmName) {
            if ($rawEmName === '') {
                return [
                    'valid'   => false,
                    'message' => 'Please enter a valid Emergency Contact Name.',
                ];
            }
            if (mb_strlen($rawEmName) > 30) {
                return [
                    'valid'   => false,
                    'message' => 'Emergency Contact Name must be 30 characters or fewer.',
                ];
            }
            $fields['emergency_contact_name'] = preg_replace('/\s+/', ' ', $rawEmName) ?? $rawEmName;
        }

        // 4. Emergency Contact Number (PH 09XXXXXXXXX, +639XXXXXXXXX, or valid international +E.164, max 16 chars)
        $hasEmContact = array_key_exists('emergency_contact', $input);
        $rawEmContact = $hasEmContact
            ? trim((string) ($input['emergency_contact'] ?? ''))
            : trim((string) ($existing['emergency_contact'] ?? ''));

        if ($requireAll || $hasEmContact) {
            $cleanedPhone = preg_replace('/[\s\-().]+/', '', $rawEmContact) ?? '';
            if (strlen($cleanedPhone) > 16) {
                return [
                    'valid'   => false,
                    'message' => 'Emergency Contact Number must be 16 characters or fewer.',
                ];
            }
            $isValidPhLocal = (bool) preg_match('/^09\d{9}$/', $cleanedPhone);
            $isValidPhIntl = (bool) preg_match('/^\+?639\d{9}$/', $cleanedPhone);
            $isValidIntl = (bool) preg_match('/^\+[1-9]\d{7,14}$/', $cleanedPhone);

            if (! $isValidPhLocal && ! $isValidPhIntl && ! $isValidIntl) {
                return [
                    'valid'   => false,
                    'message' => 'Please enter a valid Emergency Contact Number (e.g., 09171234567, +639171234567, or a valid international number).',
                ];
            }

            $fields['emergency_contact'] = $cleanedPhone;
        }

        return [
            'valid'  => true,
            'fields' => $fields,
        ];
    }

    private function isAlumniAuthorized(): bool
    {
        $user = session('auth_user');
        if (!$user) {
            return false;
        }

        $role = strtolower((string) ($user['role'] ?? ''));
        return $role === 'alumni';
    }

    public function profilePage(): ResponseInterface
    {
        if (! $this->isAlumniAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        return $this->response->setBody(view('HTML/Alumni/Profile.html'));
    }

    // ── Profile Picture Upload ────────────────────────────────────────────────

    public function uploadProfilePicture(): ResponseInterface
    {
        if (! $this->isAlumniAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $file = $this->request->getFile('profile_picture');

        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please select a valid image file.',
            ]);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (! in_array($file->getMimeType(), $allowedMimes, true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Only JPG, PNG, WEBP, and GIF images are allowed.',
            ]);
        }

        $maxBytes = 5 * 1024 * 1024; // 5 MB
        if ($file->getSize() > $maxBytes) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'File size must not exceed 5 MB.',
            ]);
        }

        $currentUser = session('auth_user');
        $alumniData  = $this->alumniModel->findByEmail((string) ($currentUser['email'] ?? ''));

        if (! is_array($alumniData)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Alumni record not found.',
            ]);
        }

        $alumniDbId = (int) ($alumniData['id'] ?? 0);

        $storedName   = $file->getRandomName();
        $originalName = $file->getClientName();
        $mimeType     = $file->getMimeType();
        $fileSize     = $file->getSize();

        if ($this->storage->isConfigured()) {
            $uploadedRef = $this->storage->uploadUploadedFile($file, 'profile_pictures/' . $storedName);
            if ($uploadedRef === null) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => 'Unable to upload profile picture to Supabase Storage. Please try again.',
                ]);
            }
            $storedPath = $uploadedRef;
        } else {
            $uploadDir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'profile_pictures';
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0750, true);
            }
            $file->move($uploadDir, $storedName);
            $storedPath = 'uploads/profile_pictures/' . $storedName;
        }

        // Delete previous profile picture file (if any)
        $previous = $this->alumniFilesModel->getActiveProfilePicture($alumniDbId);
        if (is_array($previous) && ! empty($previous['File_Path'])) {
            $oldPath = (string) $previous['File_Path'];
            if ($this->storage->isSupabaseReference($oldPath)) {
                $this->storage->deleteReference($oldPath);
            } else {
                $oldFile = WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $oldPath);
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
        }

        // Insert metadata row
        $this->alumniFilesModel->insert([
            'Alumni_ID'     => $alumniDbId,
            'File_Type'     => 'profile_picture',
            'Original_Name' => $originalName,
            'Stored_Name'   => $storedName,
            'File_Path'     => $storedPath,
            'Mime_Type'     => $mimeType,
            'File_Size'     => $fileSize,
        ]);

        $responseUrl = $this->storage->isSupabaseReference($storedPath)
            ? $this->storage->resolvePublicUrl($storedPath)
            : base_url('alumni/profile-picture');

        return $this->response->setStatusCode(201)->setJSON([
            'success'             => true,
            'message'             => 'Profile picture uploaded successfully.',
            'profile_picture_url' => $responseUrl,
        ]);
    }

    public function serveProfilePicture(): ResponseInterface
    {
        if (! $this->isAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        // Resolve alumni DB id: from query param or own session
        $requestedId = (int) ($this->request->getGet('alumni_id') ?? 0);

        if ($requestedId > 0) {
            $currentRole = normalize_role((string) (session('auth_user.role') ?? ''));
            if ($currentRole === 'alumni') {
                $ownAlumni = $this->alumniModel->findByEmail((string) (session('auth_user.email') ?? ''));
                if ((int) ($ownAlumni['id'] ?? 0) !== $requestedId) {
                    return $this->response->setStatusCode(403)->setJSON([
                        'success' => false,
                        'message' => 'Forbidden.',
                    ]);
                }
            } elseif (! is_developer_role($currentRole)) {
                $scopeService = new \App\Services\AssignmentScopeService();
                $scope = $scopeService->resolveCurrentUserScope();
                if ($scope !== null && ($scope['valid'] ?? false) !== true) {
                    return $this->response->setStatusCode(403)->setJSON([
                        'success' => false,
                        'message' => 'Forbidden.',
                    ]);
                }
                if ($scope !== null) {
                    $targetAlumni = $this->alumniModel->find($requestedId);
                    if (! is_array($targetAlumni) || ! $scopeService->isAlumniInScope($targetAlumni, $scope)) {
                        return $this->response->setStatusCode(403)->setJSON([
                            'success' => false,
                            'message' => 'Forbidden.',
                        ]);
                    }
                }
            }
            $alumniDbId = $requestedId;
        } else {
            $currentUser = session('auth_user');
            $alumniData  = $this->alumniModel->findByEmail((string) ($currentUser['email'] ?? ''));
            $alumniDbId  = (int) ($alumniData['id'] ?? 0);
        }

        if ($alumniDbId <= 0) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Profile picture not found.',
            ]);
        }

        $row = $this->alumniFilesModel->getActiveProfilePicture($alumniDbId);
        if (! is_array($row)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Profile picture not found.',
            ]);
        }

        $storedPath = (string) ($row['File_Path'] ?? '');
        if ($this->storage->isSupabaseReference($storedPath)) {
            $publicUrl = $this->storage->resolvePublicUrl($storedPath);
            if ($publicUrl !== null) {
                return redirect()->to($publicUrl);
            }
        }

        $filePath = WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $storedPath);
        if (! is_file($filePath)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Profile picture file is missing.',
            ]);
        }

        $mimeType = (string) ($row['Mime_Type'] ?? 'image/jpeg');

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody((string) file_get_contents($filePath));
    }

    /**
     * Head Admin ONLY: Return campus-scoped alumni Digital ID data (Front & Back).
     */
    public function digitalId(int $alumniDbId): ResponseInterface
    {
        $resolved = $this->resolveHeadAdminDigitalIdData($alumniDbId);
        if (($resolved['success'] ?? false) !== true) {
            return $this->response
                ->setStatusCode((int) ($resolved['status'] ?? 403))
                ->setJSON([
                    'success' => false,
                    'message' => (string) ($resolved['message'] ?? 'Forbidden.'),
                ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'alumni'  => $resolved['data'],
        ]);
    }

    /**
     * Head Admin ONLY: Download print-ready Back-to-Back Digital ID PDF (Front & Back at CR80 85.6mm x 53.98mm).
     * Supports ?mode=sheet (both sides on one sheet) and ?mode=duplex (2-page centered duplex layout).
     */
    public function downloadDigitalId(int $alumniDbId): ResponseInterface
    {
        $resolved = $this->resolveHeadAdminDigitalIdData($alumniDbId, true);
        if (($resolved['success'] ?? false) !== true) {
            return $this->response
                ->setStatusCode((int) ($resolved['status'] ?? 403))
                ->setJSON([
                    'success' => false,
                    'message' => (string) ($resolved['message'] ?? 'Forbidden.'),
                ]);
        }

        $data = $resolved['data'];
        if (($data['is_complete'] ?? false) !== true) {
            $missing = is_array($data['missing_fields'] ?? null) ? $data['missing_fields'] : [];
            $missingText = $missing !== [] ? (' Missing: ' . implode(', ', $missing) . '.') : '';
            return $this->response->setStatusCode(422)->setJSON([
                'success'        => false,
                'message'        => 'Cannot export Digital ID as PDF because the alumni information is incomplete.' . $missingText,
                'missing_fields' => $missing,
            ]);
        }

        $mode = strtolower(trim((string) ($this->request->getGet('mode') ?? 'sheet')));
        if (! in_array($mode, ['sheet', 'duplex'], true)) {
            $mode = 'sheet';
        }

        $html = $this->buildDigitalIdPdfHtml($data, $mode);

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('dpi', 300);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $safeId = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($data['alumni_id'] ?: $alumniDbId));
        $filename = 'Alumni_Digital_ID_' . $safeId . '_' . ($mode === 'duplex' ? 'Duplex' : 'Front_Back') . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setBody((string) $dompdf->output());
    }

    /**
     * Strictly resolves an alumni record for Head Admin Digital ID generation,
     * enforcing server-side campus scope via AssignmentScopeService.
     *
     * @return array{success: bool, status?: int, message?: string, data?: array<string, mixed>}
     */
    private function resolveHeadAdminDigitalIdData(int $alumniDbId, bool $embedImagesForPdf = false): array
    {
        if (! $this->isStaffAuthorized()) {
            return [
                'success' => false,
                'status'  => 401,
                'message' => 'Unauthorized. Head Admin access required.',
            ];
        }

        if ($alumniDbId <= 0) {
            return [
                'success' => false,
                'status'  => 400,
                'message' => 'Invalid alumni record ID.',
            ];
        }

        $scopeService = new \App\Services\AssignmentScopeService();
        $scope = $scopeService->resolveCurrentUserScope();
        if ($scope === null || ($scope['valid'] ?? false) !== true || ($scope['role'] ?? '') !== 'head_admin') {
            return [
                'success' => false,
                'status'  => 403,
                'message' => (string) ($scope['message'] ?? 'Forbidden. Valid Head Admin campus assignment required.'),
            ];
        }

        $db = \Config\Database::connect();
        $row = $db->table('alumni a')
            ->select('a.id, a.alumni_id, a."FirstName", a."LastName", a."Email", a."GraduationYear", a."Status", a."College_ID", a."Department_ID", a."Province", a."Municipality", a."Barangay", a."Contact_Number", a.sex, a.birth_month, a.birth_date, a.birth_year, a.emergency_contact, a.emergency_contact_name, c."College_Name", c."Campus_ID", d."Department_Name", cmp."Campus" AS "Campus_Name"')
            ->join('colleges c', 'c."College_ID" = a."College_ID"', 'left')
            ->join('departments d', 'd."Department_ID" = a."Department_ID"', 'left')
            ->join('campuses cmp', 'cmp."Campus_ID" = c."Campus_ID"', 'left')
            ->where('a.id', $alumniDbId)
            ->get()
            ->getRowArray();

        if (! is_array($row)) {
            return [
                'success' => false,
                'status'  => 404,
                'message' => 'Alumni record not found.',
            ];
        }

        // Strict server-side campus isolation check
        if (! $scopeService->isAlumniInScope($row, $scope)) {
            return [
                'success' => false,
                'status'  => 403,
                'message' => 'Forbidden. You can only view or generate Digital IDs for alumni within your assigned campus.',
            ];
        }

        $firstName = trim((string) ($row['FirstName'] ?? ''));
        $lastName = trim((string) ($row['LastName'] ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);
        $initials = strtoupper(
            ($firstName !== '' ? mb_substr($firstName, 0, 1) : '') .
            ($lastName !== '' ? mb_substr($lastName, 0, 1) : '')
        ) ?: 'GV';

        $addressParts = array_values(array_filter([
            trim((string) ($row['Barangay'] ?? '')),
            trim((string) ($row['Municipality'] ?? '')),
            trim((string) ($row['Province'] ?? '')),
        ], static fn (string $part): bool => $part !== ''));
        $homeAddress = $addressParts !== [] ? implode(', ', $addressParts) : '';

        // Optional personal details: prefer direct public.alumni columns, fallback to latest survey response
        $extra = $this->extractOptionalSurveyPersonalDetails(trim((string) ($row['Email'] ?? '')));
        $dbSex = trim((string) ($row['sex'] ?? ''));
        $dbBirthMonth = (int) ($row['birth_month'] ?? 0);
        $dbBirthDate = (int) ($row['birth_date'] ?? 0);
        $dbBirthYear = (int) ($row['birth_year'] ?? 0);
        $dbBirthdateFormatted = ($dbBirthMonth >= 1 && $dbBirthMonth <= 12 && $dbBirthDate >= 1 && $dbBirthDate <= 31 && $dbBirthYear >= 1920 && checkdate($dbBirthMonth, $dbBirthDate, $dbBirthYear))
            ? ($dbBirthMonth . '/' . $dbBirthDate . '/' . $dbBirthYear)
            : '';
        $dbEmName = trim((string) ($row['emergency_contact_name'] ?? ''));
        $dbEmContact = trim((string) ($row['emergency_contact'] ?? ''));

        $resolvedSex = $dbSex !== '' ? $dbSex : $extra['sex'];
        $resolvedBirthdate = $dbBirthdateFormatted !== '' ? $dbBirthdateFormatted : $extra['birthdate'];
        $resolvedEmName = $dbEmName !== '' ? $dbEmName : $extra['emergency_contact_name'];
        $resolvedEmNumber = $dbEmContact !== '' ? $dbEmContact : $extra['emergency_contact_number'];

        // Resolve alumni photograph via existing AlumniFilesModel + SupabaseStorageService
        $activePhoto = $this->alumniFilesModel->getActiveProfilePicture($alumniDbId);
        $hasProfilePicture = is_array($activePhoto);
        $profilePictureUrl = null;
        $profilePictureDataUri = null;

        if ($hasProfilePicture) {
            $storedPath = (string) ($activePhoto['File_Path'] ?? '');
            $mimeType = (string) ($activePhoto['Mime_Type'] ?? 'image/jpeg');

            if ($this->storage->isSupabaseReference($storedPath)) {
                $profilePictureUrl = $this->storage->resolvePublicUrl($storedPath);
                if ($embedImagesForPdf && $profilePictureUrl !== null) {
                    $profilePictureDataUri = $this->fetchImageAsDataUri($profilePictureUrl, $mimeType);
                }
            } else {
                $profilePictureUrl = base_url('alumni/profile-picture?alumni_id=' . $alumniDbId);
                if ($embedImagesForPdf) {
                    $localPath = WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $storedPath);
                    if (is_file($localPath)) {
                        $bytes = @file_get_contents($localPath);
                        if (is_string($bytes) && $bytes !== '') {
                            $profilePictureDataUri = 'data:' . $mimeType . ';base64,' . base64_encode($bytes);
                        }
                    }
                }
            }
        }

        // Resolve permanent official university logo asset
        $logoFile = FCPATH . 'images' . DIRECTORY_SEPARATOR . 'ustp-alumni-logo.jpg';
        $logoDataUri = null;
        if ($embedImagesForPdf && is_file($logoFile)) {
            $logoBytes = @file_get_contents($logoFile);
            if (is_string($logoBytes) && $logoBytes !== '') {
                $logoDataUri = 'data:image/jpeg;base64,' . base64_encode($logoBytes);
            }
        }

        $payloadData = [
            'id'                       => (int) ($row['id'] ?? 0),
            'alumni_id'                => trim((string) ($row['alumni_id'] ?? '')),
            'first_name'               => $firstName,
            'last_name'                => $lastName,
            'full_name'                => $fullName,
            'initials'                 => $initials,
            'email'                    => trim((string) ($row['Email'] ?? '')),
            'graduation_year'          => trim((string) ($row['GraduationYear'] ?? '')),
            'status'                   => trim((string) ($row['Status'] ?? 'pending')),
            'college'                  => trim((string) ($row['College_Name'] ?? '')),
            'course'                   => trim((string) ($row['Department_Name'] ?? '')),
            'campus'                   => trim((string) ($row['Campus_Name'] ?? '')),
            'contact_number'           => trim((string) ($row['Contact_Number'] ?? '')),
            'barangay'                 => trim((string) ($row['Barangay'] ?? '')),
            'municipality'             => trim((string) ($row['Municipality'] ?? '')),
            'province'                 => trim((string) ($row['Province'] ?? '')),
            'home_address'             => $homeAddress,
            'sex'                      => $resolvedSex,
            'birthdate'                => $resolvedBirthdate,
            'emergency_contact_name'   => $resolvedEmName,
            'emergency_contact_number' => $resolvedEmNumber,
            'membership_label'         => 'LIFETIME MEMBER',
            'has_profile_picture'      => $hasProfilePicture,
            'profile_picture_url'      => $profilePictureUrl,
            'profile_picture_data_uri' => $profilePictureDataUri,
            'logo_url'                 => base_url('images/ustp-alumni-logo.jpg'),
            'logo_data_uri'            => $logoDataUri,
        ];

        $missingFields = $this->getMissingDigitalIdFields($payloadData);
        $payloadData['is_complete'] = ($missingFields === []);
        $payloadData['missing_fields'] = $missingFields;

        return [
            'success' => true,
            'data'    => $payloadData,
        ];
    }

    /**
     * Checks whether all required Front & Back Digital ID fields are populated.
     *
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private function getMissingDigitalIdFields(array $data): array
    {
        $missing = [];

        if (trim((string) ($data['full_name'] ?? '')) === '') {
            $missing[] = 'Full Name';
        }
        if (trim((string) ($data['alumni_id'] ?? '')) === '') {
            $missing[] = 'Alumni ID Number';
        }
        if (trim((string) ($data['graduation_year'] ?? '')) === '') {
            $missing[] = 'Batch Year';
        }
        if (($data['has_profile_picture'] ?? false) !== true) {
            $missing[] = 'Alumni Photograph';
        }
        if (trim((string) ($data['sex'] ?? '')) === '') {
            $missing[] = 'Sex';
        }
        if (trim((string) ($data['birthdate'] ?? '')) === '') {
            $missing[] = 'Birthdate';
        }
        if (trim((string) ($data['course'] ?? '')) === '') {
            $missing[] = 'Degree / Program';
        }
        if (trim((string) ($data['contact_number'] ?? '')) === '') {
            $missing[] = 'Contact Number';
        }
        if (trim((string) ($data['home_address'] ?? '')) === '') {
            $missing[] = 'Home Address';
        }
        if (trim((string) ($data['emergency_contact_name'] ?? '')) === '') {
            $missing[] = 'Emergency Contact Name';
        }
        if (trim((string) ($data['emergency_contact_number'] ?? '')) === '') {
            $missing[] = 'Emergency Contact Number';
        }

        return $missing;
    }

    /**
     * Best-effort lookup of optional personal fields (sex, birthdate, emergency contact)
     * from the alumnus's most recent survey response, without modifying any table.
     *
     * @return array{sex: string, birthdate: string, emergency_contact_name: string, emergency_contact_number: string}
     */
    private function extractOptionalSurveyPersonalDetails(string $email): array
    {
        $result = [
            'sex'                      => '',
            'birthdate'                => '',
            'emergency_contact_name'   => '',
            'emergency_contact_number' => '',
        ];

        if ($email === '') {
            return $result;
        }

        try {
            $db = \Config\Database::connect();
            $latestResp = $db->table('responses')
                ->select('id')
                ->where('LOWER(user_email)', strtolower($email))
                ->orderBy('submitted_at', 'DESC')
                ->limit(1)
                ->get()
                ->getRowArray();

            if (! is_array($latestResp) || empty($latestResp['id'])) {
                return $result;
            }

            $answers = $db->table('answers a')
                ->select('a.answer_text, c.choice_text, q.question_text')
                ->join('choices c', 'c.id = a.choice_id', 'left')
                ->join('questions q', 'q.id = a.question_id', 'left')
                ->where('a.response_id', (int) $latestResp['id'])
                ->get()
                ->getResultArray();

            foreach ($answers as $ans) {
                $qText = strtolower(trim((string) ($ans['question_text'] ?? '')));
                $val = trim((string) ($ans['choice_text'] ?: $ans['answer_text'] ?: ''));
                if ($qText === '' || $val === '') {
                    continue;
                }

                if ($result['sex'] === '' && (str_contains($qText, 'sex') || str_contains($qText, 'gender'))) {
                    $result['sex'] = $val;
                } elseif ($result['birthdate'] === '' && (str_contains($qText, 'birth') || str_contains($qText, 'dob'))) {
                    $result['birthdate'] = $val;
                } elseif ($result['emergency_contact_number'] === '' && str_contains($qText, 'emergency') && (str_contains($qText, 'number') || str_contains($qText, 'phone') || str_contains($qText, 'mobile') || str_contains($qText, 'contact no'))) {
                    $result['emergency_contact_number'] = $val;
                } elseif ($result['emergency_contact_name'] === '' && str_contains($qText, 'emergency')) {
                    $result['emergency_contact_name'] = $val;
                }
            }
        } catch (\Throwable $e) {
            // Non-blocking optional metadata lookup
        }

        return $result;
    }

    private function fetchImageAsDataUri(string $url, string $fallbackMime = 'image/jpeg'): ?string
    {
        try {
            $ctx = stream_context_create([
                'http' => ['timeout' => 5],
                'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $bytes = @file_get_contents($url, false, $ctx);
            if (! is_string($bytes) || $bytes === '') {
                return null;
            }
            $mime = $fallbackMime;
            if (function_exists('finfo_open')) {
                $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $detected = @finfo_buffer($finfo, $bytes);
                    @finfo_close($finfo);
                    if (is_string($detected) && str_starts_with($detected, 'image/')) {
                        $mime = $detected;
                    }
                }
            }
            return 'data:' . $mime . ';base64,' . base64_encode($bytes);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Build print-ready HTML for Dompdf containing exact CR80 (85.6mm x 53.98mm)
     * Front & Back USTP Alumni Association Digital ID cards.
     *
     * @param array<string, mixed> $data
     */
    private function buildDigitalIdPdfHtml(array $data, string $mode = 'sheet'): string
    {
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $fullName = $esc(strtoupper((string) ($data['full_name'] ?: 'ALUMNI MEMBER')));
        $alumniId = $esc((string) ($data['alumni_id'] ?: '—'));
        $batchText = $esc('BATCH ' . ($data['graduation_year'] ?: '—'));
        $initials = $esc((string) ($data['initials'] ?: 'GV'));
        $sex = $esc((string) ($data['sex'] ?: '—'));
        $birthdate = $esc((string) ($data['birthdate'] ?: '—'));
        $course = $esc(strtoupper((string) ($data['course'] ?: '—')));
        $contact = $esc((string) ($data['contact_number'] ?: '—'));
        $address = $esc((string) ($data['home_address'] ?: '—'));
        $emName = $esc((string) ($data['emergency_contact_name'] ?: '—'));
        $emNum = $esc((string) ($data['emergency_contact_number'] ?: '—'));

        $photoSrc = (string) ($data['profile_picture_data_uri'] ?? '');
        $logoSrc = (string) ($data['logo_data_uri'] ?? '');

        $photoHtml = $photoSrc !== ''
            ? '<img src="' . $photoSrc . '" alt="Alumni Photo" style="width:24mm;height:25mm;object-fit:cover;display:block;border-radius:2.2mm;">'
            : '<div style="width:24mm;height:25mm;line-height:25mm;text-align:center;font-size:16pt;font-weight:bold;color:#475569;background:#e2e8f0;border-radius:2.2mm;">' . $initials . '</div>';

        $logoHtml = $logoSrc !== ''
            ? '<img src="' . $logoSrc . '" alt="USTP Alumni Association Logo" style="width:17.5mm;height:17.5mm;object-fit:contain;display:block;">'
            : '';

        $signatureSvg = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 140 52" width="140" height="52">'
            . '<path d="M22,38 C28,12 42,6 44,18 C46,28 32,36 30,26 C28,18 46,16 56,14 C54,28 50,42 54,44 C58,46 66,22 72,20 C76,18 70,36 78,34 C84,32 90,22 96,24 C100,25 94,35 104,33 C112,31 118,27 128,26" fill="none" stroke="#3f4650" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M40,42 C62,38 88,36 118,39" fill="none" stroke="#3f4650" stroke-width="1.3" stroke-linecap="round"/>'
            . '</svg>'
        );

        $frontCard = '
        <div class="id-card front-card">
            <div class="front-blob-bl"></div>
            <div class="front-dot-yellow"></div>
            <div class="front-dot-slate"></div>
            <div class="front-slate-shape"></div>
            <div class="front-corner-br"></div>

            <div class="front-photo-frame">' . $photoHtml . '</div>
            <div class="front-logo-wrap">' . $logoHtml . '</div>

            <div class="front-text-block">
                <div class="front-name">' . $fullName . '</div>
                <div class="front-id-num">' . $alumniId . '</div>
                <div class="front-member">LIFETIME MEMBER</div>
                <div class="front-batch">' . $batchText . '</div>
            </div>
        </div>';

        $backCard = '
        <div class="id-card back-card">
            <div class="back-accent-tl"></div>
            <div class="back-accent-br"></div>

            <div class="back-header-left">PERSONAL DETAILS</div>
            <div class="back-header-right">
                THE BEARER WHOSE PICTURE APPEARS HEREIN<br>
                IS A BONA FIDE MEMBER OF USTPAA-CLAVERIA
            </div>

            <div class="back-details">
                <div class="back-row"><span class="back-lbl">SEX:</span> ' . $sex . '</div>
                <div class="back-row"><span class="back-lbl">BIRTHDATE:</span> ' . $birthdate . '</div>
                <div class="back-row"><span class="back-lbl">DEGREE/PROGRAM:</span> ' . $course . '</div>
                <div class="back-row"><span class="back-lbl">CONTACT NUMBER:</span> ' . $contact . '</div>
                <div class="back-row"><span class="back-lbl">HOME ADDRESS:</span></div>
                <div class="back-row back-indent">' . $address . '</div>
                <div class="back-row back-em-lbl">IN CASE OF EMERGENCY, PLEASE CONTACT:</div>
                <div class="back-row back-em-val">' . $emName . '</div>
                <div class="back-row back-em-val">' . $emNum . '</div>
            </div>

            <div class="back-sig-block">
                <img src="' . $signatureSvg . '" alt="Signature" style="width:22mm;height:8.5mm;display:block;margin:0 auto 0.5mm auto;">
                <div class="back-sig-name">GERUM O. SALATAN</div>
                <div class="back-sig-title">USTPAA-CLAVERIA PRESIDENT</div>
            </div>
        </div>';

        $bodyContent = $mode === 'duplex'
            ? '<div class="duplex-page">' . $frontCard . '</div><div class="duplex-page page-break">' . $backCard . '</div>'
            : '<div class="sheet-wrap"><div class="sheet-slot">' . $frontCard . '</div><div class="sheet-gap"></div><div class="sheet-slot">' . $backCard . '</div></div>';

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    @page { size: A4 portrait; margin: 14mm; }
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; font-family: "DejaVu Sans", Arial, sans-serif; background: #ffffff; }
    .sheet-wrap { width: 100%; text-align: center; padding-top: 8mm; }
    .sheet-slot { display: inline-block; padding: 0; border: 0.2mm dashed #cbd5e1; border-radius: 3.4mm; }
    .sheet-gap { height: 12mm; }
    .duplex-page { width: 100%; text-align: center; padding-top: 20mm; }
    .page-break { page-break-before: always; }

    .id-card {
        position: relative;
        width: 85.6mm;
        height: 53.98mm;
        background: #ffffff;
        border: 0.25mm solid #d8dee6;
        border-radius: 3.18mm;
        overflow: hidden;
        text-align: left;
        margin: 0 auto;
    }

    /* Front Card Elements */
    .front-blob-bl {
        position: absolute;
        left: -10mm;
        bottom: -8mm;
        width: 34mm;
        height: 36mm;
        background: #f9ebaa;
        border-radius: 50%;
    }
    .front-dot-yellow {
        position: absolute;
        left: 25.5mm;
        top: 1.8mm;
        width: 5.5mm;
        height: 5.5mm;
        background: #f7e69b;
        border-radius: 50%;
    }
    .front-dot-slate {
        position: absolute;
        left: 28.5mm;
        top: 5.2mm;
        width: 4.2mm;
        height: 4.2mm;
        background: #92a1b6;
        border-radius: 50%;
    }
    .front-slate-shape {
        position: absolute;
        left: 34mm;
        top: -9mm;
        width: 32mm;
        height: 29mm;
        background: #8f9fb4;
        border-radius: 6.5mm;
        transform: rotate(-16deg);
    }
    .front-corner-br {
        position: absolute;
        right: -8mm;
        bottom: -8mm;
        width: 22mm;
        height: 20mm;
        background: #f1d86e;
        border-radius: 50%;
    }
    .front-photo-frame {
        position: absolute;
        left: 5.2mm;
        top: 7.5mm;
        width: 24.6mm;
        height: 25.6mm;
        border: 0.35mm solid #8f9db1;
        border-radius: 2.5mm;
        background: #ffffff;
        padding: 0.25mm;
        overflow: hidden;
    }
    .front-logo-wrap {
        position: absolute;
        right: 4.5mm;
        top: 4.8mm;
        width: 17.5mm;
        height: 17.5mm;
        background: #ffffff;
    }
    .front-text-block {
        position: absolute;
        left: 26mm;
        right: 5mm;
        bottom: 6.2mm;
        text-align: center;
        color: #4f5763;
    }
    .front-name {
        font-size: 7.8pt;
        font-weight: bold;
        letter-spacing: 0.25pt;
        line-height: 1.2;
        margin-bottom: 1.2mm;
    }
    .front-id-num {
        font-size: 6.8pt;
        font-weight: bold;
        letter-spacing: 0.4pt;
        margin-bottom: 1.4mm;
    }
    .front-member {
        font-size: 7.6pt;
        font-weight: bold;
        letter-spacing: 0.35pt;
        margin-bottom: 1mm;
    }
    .front-batch {
        font-size: 6.4pt;
        font-weight: bold;
        letter-spacing: 0.3pt;
    }

    /* Back Card Elements */
    .back-accent-tl {
        position: absolute;
        left: -7mm;
        top: -7mm;
        width: 18mm;
        height: 18mm;
        background: #b5c1d0;
        border-radius: 50%;
    }
    .back-accent-br {
        position: absolute;
        right: -7mm;
        bottom: 7mm;
        width: 14mm;
        height: 18mm;
        background: #8a95a0;
        border-radius: 50%;
    }
    .back-header-left {
        position: absolute;
        left: 7mm;
        top: 5.2mm;
        font-size: 7.6pt;
        font-weight: bold;
        letter-spacing: 1.4pt;
        color: #5a6470;
    }
    .back-header-right {
        position: absolute;
        right: 4.5mm;
        top: 4.2mm;
        text-align: right;
        font-size: 4.3pt;
        font-weight: bold;
        line-height: 1.35;
        color: #5f6975;
    }
    .back-details {
        position: absolute;
        left: 7mm;
        right: 30mm;
        top: 11.2mm;
        color: #55606d;
        font-size: 5.5pt;
        line-height: 1.42;
    }
    .back-row {
        margin-bottom: 0.85mm;
        font-weight: bold;
        word-wrap: break-word;
    }
    .back-lbl {
        color: #4f5966;
    }
    .back-indent {
        padding-left: 2.2mm;
        font-weight: bold;
    }
    .back-em-lbl {
        margin-top: 1.2mm;
        font-size: 4.8pt;
        letter-spacing: 0.2pt;
    }
    .back-em-val {
        padding-left: 2.2mm;
        font-size: 5.5pt;
    }
    .back-sig-block {
        position: absolute;
        right: 5mm;
        bottom: 4.8mm;
        width: 28mm;
        text-align: center;
        color: #4f5966;
    }
    .back-sig-name {
        font-size: 5.5pt;
        font-weight: bold;
        letter-spacing: 0.2pt;
    }
    .back-sig-title {
        font-size: 4.2pt;
        font-weight: bold;
        letter-spacing: 0.15pt;
        margin-top: 0.3mm;
    }
</style>
</head>
<body>' . $bodyContent . '</body>
</html>';
    }
}
