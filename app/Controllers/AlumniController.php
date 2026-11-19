<?php

namespace App\Controllers;

use App\Models\AlumniFilesModel;
use App\Models\AlumniModel;
use App\Models\NewsModel;
use App\Models\UserAccountModel;
use CodeIgniter\HTTP\ResponseInterface;

class AlumniController extends BaseController
{
    private AlumniModel $alumniModel;
    private NewsModel $newsModel;
    private UserAccountModel $userAccountModel;
    private AlumniFilesModel $alumniFilesModel;

    public function __construct()
    {
        $this->alumniModel      = new AlumniModel();
        $this->newsModel        = new NewsModel();
        $this->userAccountModel = new UserAccountModel();
        $this->alumniFilesModel = new AlumniFilesModel();
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
                'image_url' => ! empty($item['image_path']) ? base_url(str_replace('\\', '/', (string) $item['image_path'])) : null,
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

        $sort = (string) ($this->request->getGet('sort') ?? 'last_name_asc');
        $filters = [
            'year'           => $this->request->getGet('year'),
            'graduationYear' => $this->request->getGet('graduationYear'),
            'college'        => $this->request->getGet('college'),
            'course'         => $this->request->getGet('course'),
            'status'         => $this->request->getGet('status'),
        ];
        $records = $this->alumniModel->getSortedList($sort, $filters);

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

        $batchMap = [];
        foreach ($this->alumniModel->getBatchSummaries() as $batch) {
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

        try {
            $registered = $db->table('alumni_batches')
                ->select($yearColumn)
                ->orderBy($yearColumn, 'DESC')
                ->get()
                ->getResultArray();
        } catch (\Throwable) {
            $this->ensureBatchRegistryTable();
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
            $this->attachBatchDepartmentSelections($batchMap);
        } catch (\Throwable) {
            $this->ensureBatchDepartmentTable();
        }

        $batches = array_values($batchMap);
        usort($batches, static function (array $a, array $b): int {
            return strcmp((string) ($b['graduationYear'] ?? ''), (string) ($a['graduationYear'] ?? ''));
        });

        $summary = $this->alumniModel->getOverallCounts();
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

        $graduationYear = trim((string) $this->request->getPost('graduationYear'));
        if (! preg_match('/^\d{4}$/', $graduationYear)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please enter a valid 4-digit batch year.',
            ]);
        }

        $selectedCollegeIds = $this->normalizeIdList($this->request->getPost('colleges'));
        $selectedDepartmentIds = $this->normalizeIdList($this->request->getPost('departments'));

        if ($selectedCollegeIds === []) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please select at least one college.',
            ]);
        }

        if ($selectedDepartmentIds === []) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please select at least one department.',
            ]);
        }

        $validDepartments = $this->getValidBatchDepartments($selectedCollegeIds, $selectedDepartmentIds);
        if (count($validDepartments) !== count($selectedDepartmentIds)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Please choose departments that belong to the selected college(s).',
            ]);
        }

        $db = \Config\Database::connect();
        $yearColumn = $this->getBatchRegistryYearColumn();
        $existing = $db->table('alumni_batches')
            ->select('id')
            ->where($yearColumn, $graduationYear)
            ->get()
            ->getRowArray();

        if (! $existing) {
            $db->table('alumni_batches')->insert([
                $yearColumn => $graduationYear,
            ]);
        }

        $db->table('alumni_batch_departments')
            ->where('GraduationYear', $graduationYear)
            ->delete();

        $batchRows = array_map(static fn (array $department): array => [
            'GraduationYear' => $graduationYear,
            'College_ID' => (int) $department['College_ID'],
            'Department_ID' => (int) $department['Department_ID'],
        ], $validDepartments);

        if ($batchRows !== []) {
            $db->table('alumni_batch_departments')->insertBatch($batchRows);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Batch year is ready.',
            'batch' => [
                'graduationYear' => $graduationYear,
                'alumniCount' => 0,
                'activeCount' => 0,
                'colleges' => array_values(array_unique(array_map(static fn (array $row): string => (string) $row['College_Name'], $validDepartments))),
                'departments' => array_map(static fn (array $row): string => (string) $row['Department_Name'], $validDepartments),
                'departmentSelections' => array_map(static fn (array $row): array => [
                    'college' => (string) $row['College_Name'],
                    'department' => (string) $row['Department_Name'],
                ], $validDepartments),
            ],
        ]);
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

        $db = \Config\Database::connect();
        $yearColumn = $this->getBatchRegistryYearColumn();

        // Check if batch exists in registry
        $existing = $db->table('alumni_batches')
            ->select('id')
            ->where($yearColumn, $year)
            ->get()
            ->getRowArray();

        if (! $existing) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Batch not found.',
            ]);
        }

        // Check if there are alumni records in this batch
        $alumniCount = $db->table('alumni')
            ->where('GraduationYear', $year)
            ->countAllResults();

        if ($alumniCount > 0) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Cannot delete batch: ' . $alumniCount . ' alumni record(s) exist in this batch. Please remove or reassign all alumni before deleting.',
            ]);
        }

        // Delete the batch from registry (no alumni records to delete)
        $db->table('alumni_batch_departments')
            ->where('GraduationYear', $year)
            ->delete();

        $db->table('alumni_batches')
            ->where($yearColumn, $year)
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

        $records = array_values(array_filter(
            $this->alumniModel->getSortedList('last_name_asc'),
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
        $campusId = session('auth_user.campus') ?? session('auth_user.Campus') ?? '';

        $collegeQuery = $collegeModel->orderBy('College_Name', 'ASC');
        if ((string) $campusId !== '') {
            $collegeQuery->where('Campus_ID', $campusId);
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

    private function attachBatchDepartmentSelections(array &$batchMap): void
    {
        $db = \Config\Database::connect();
        $builder = $db->table('alumni_batch_departments abd')
            ->select('abd.GraduationYear, c.College_Name, d.Department_Name')
            ->join('colleges c', 'c.College_ID = abd.College_ID', 'left')
            ->join('departments d', 'd.Department_ID = abd.Department_ID', 'left')
            ->orderBy('c.College_Name', 'ASC')
            ->orderBy('d.Department_Name', 'ASC');

        $rows = $builder->get()->getResultArray();

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
            $profilePictureUrl = $hasProfilePicture ? base_url('alumni/profile-picture') : null;

            $profileData = [
                'alumni_id'           => $alumniData['alumni_id'] ?? '',
                'firstName'           => $currentUser['first_name'] ?? '',
                'lastName'            => $currentUser['last_name'] ?? '',
                'email'               => $currentUser['email'] ?? '',
                'graduationYear'      => $alumniData['GraduationYear'] ?? '',
                'college'             => $alumniData['College'] ?? '',
                'course'              => $alumniData['Course'] ?? '',
                'province'            => $alumniData['Province'] ?? '',
                'municipality'        => $alumniData['Municipality'] ?? '',
                'barangay'            => $alumniData['Barangay'] ?? '',
                'contactNumber'       => $alumniData['Contact_Number'] ?? '',
                'has_profile_picture' => $hasProfilePicture,
                'profile_picture_url' => $profilePictureUrl,
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
            $jsonData = $this->request->getJSON();
            
            if ($jsonData === null) {
                $rawInput = $this->request->getRawInput();
                $jsonData = json_decode($rawInput, false);
            }
            
            if ($jsonData === null) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Invalid JSON data provided.',
                ]);
            }
            
            $email = trim((string) ($jsonData->email ?? ''));

            if (!$email) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Please provide an email address.',
                ]);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => 'Please provide a valid email address.',
                ]);
            }

            $currentUser = session('auth_user');
            if (!$currentUser) {
                return $this->response->setStatusCode(401)->setJSON([
                    'success' => false,
                    'message' => 'No active session found.',
                ]);
            }

            $currentFirstName = (string) ($currentUser['first_name'] ?? '');
            $currentLastName = (string) ($currentUser['last_name'] ?? '');

            $this->userAccountModel->builder()->where('Email', $currentUser['email'])->update([
                'Email' => $email,
            ]);

            $this->alumniModel->builder()->where('Email', $currentUser['email'])->update([
                'Email' => $email,
            ]);

            session()->set('auth_user', array_merge($currentUser, [
                'first_name' => $currentFirstName,
                'last_name' => $currentLastName,
                'email' => $email,
                'name' => trim($currentFirstName . ' ' . $currentLastName),
            ]));

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'user' => [
                    'first_name' => $currentFirstName,
                    'last_name' => $currentLastName,
                    'email' => $email,
                    'name' => trim($currentFirstName . ' ' . $currentLastName),
                ],
            ]);
        } catch (Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage(),
            ]);
        }
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

        // Store in writable/ — NOT accessible via public URL
        $uploadDir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'profile_pictures';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }

        $storedName   = $file->getRandomName();
        $relativePath = 'uploads/profile_pictures/' . $storedName;

        // Capture metadata BEFORE move() — temp file is gone after move
        $originalName = $file->getClientName();
        $mimeType     = $file->getMimeType();
        $fileSize     = $file->getSize();

        $file->move($uploadDir, $storedName);

        // Delete previous profile picture file (if any)
        $previous = $this->alumniFilesModel->getActiveProfilePicture($alumniDbId);
        if (is_array($previous) && ! empty($previous['File_Path'])) {
            $oldFile = WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, (string) $previous['File_Path']);
            if (is_file($oldFile)) {
                @unlink($oldFile);
            }
        }

        // Insert metadata row
        $this->alumniFilesModel->insert([
            'Alumni_ID'     => $alumniDbId,
            'File_Type'     => 'profile_picture',
            'Original_Name' => $originalName,
            'Stored_Name'   => $storedName,
            'File_Path'     => $relativePath,
            'Mime_Type'     => $mimeType,
            'File_Size'     => $fileSize,
        ]);

        return $this->response->setStatusCode(201)->setJSON([
            'success'             => true,
            'message'             => 'Profile picture uploaded successfully.',
            'profile_picture_url' => base_url('alumni/profile-picture'),
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

        $filePath = WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, (string) ($row['File_Path'] ?? ''));
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
}
