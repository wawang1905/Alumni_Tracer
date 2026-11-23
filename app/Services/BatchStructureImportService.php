<?php

namespace App\Services;

use App\Models\CollegeModel;
use App\Models\DepartmentModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Handles CSV / Excel (.xlsx, .xls) parsing, validation, preview, template generation,
 * and transaction-safe imports for:
 *  1. Batch Colleges + Departments
 *  2. Departments under a single verified College
 */
class BatchStructureImportService
{
    public const MAX_FILE_SIZE_BYTES = 5242880; // 5 MB
    public const MAX_IMPORT_ROWS = 2000;
    private const ALLOWED_EXTENSIONS = ['csv', 'xlsx', 'xls'];

    /**
     * Validate and read spreadsheet/CSV rows into a normalized 2D string array.
     *
     * @return array{ok: bool, error?: string, rows?: array<int, array<int, string>>}
     */
    public function parseUploadedFile(?UploadedFile $file): array
    {
        if ($file === null || ! $file->isValid()) {
            return ['ok' => false, 'error' => 'Please select a valid CSV or Excel (.xlsx, .xls) file to upload.'];
        }

        if ($file->getSize() <= 0) {
            return ['ok' => false, 'error' => 'The uploaded file is empty.'];
        }

        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            return ['ok' => false, 'error' => 'File size exceeds the 5 MB limit.'];
        }

        $ext = strtolower((string) $file->getClientExtension());
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['ok' => false, 'error' => 'Unsupported file type. Please upload a .csv, .xlsx, or .xls file.'];
        }

        $tempPath = $file->getTempName();
        if (! is_file($tempPath) || ! is_readable($tempPath)) {
            return ['ok' => false, 'error' => 'Unable to read the uploaded file.'];
        }

        // Verify actual file content matches extension
        if ($ext === 'xlsx') {
            $magic = file_get_contents($tempPath, false, null, 0, 4);
            if ($magic !== "PK\x03\x04") {
                return ['ok' => false, 'error' => 'Invalid .xlsx file content. Please upload a genuine Excel file.'];
            }
        }

        try {
            $reader = IOFactory::createReaderForFile($tempPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tempPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rawRows = $sheet->toArray('', true, false, false);
        } catch (\Throwable $e) {
            log_message('warning', 'BatchStructureImportService parse failed: {msg}', ['msg' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Could not parse the uploaded file. Make sure it is a valid CSV or Excel spreadsheet.'];
        }

        $rows = [];
        foreach ($rawRows as $rawRow) {
            if (! is_array($rawRow)) {
                continue;
            }
            $normalizedRow = array_map(static fn ($cell): string => trim((string) ($cell ?? '')), $rawRow);
            $hasValue = false;
            foreach ($normalizedRow as $cell) {
                if ($cell !== '') {
                    $hasValue = true;
                    break;
                }
            }
            if ($hasValue) {
                $rows[] = $normalizedRow;
            }
            if (count($rows) > self::MAX_IMPORT_ROWS + 1) {
                return ['ok' => false, 'error' => 'File exceeds the maximum of ' . self::MAX_IMPORT_ROWS . ' rows per import.'];
            }
        }

        if (count($rows) < 2) {
            return ['ok' => false, 'error' => 'The file must include a header row and at least one data row.'];
        }

        return ['ok' => true, 'rows' => $rows];
    }

    /**
     * Analyze and preview Colleges + Departments rows against the campus's existing records.
     *
     * @param array<int, array<int, string>> $rows
     * @return array<string, mixed>
     */
    public function analyzeCollegesAndDepartments(array $rows, int $campusId): array
    {
        $headers = array_map([$this, 'normalizeHeader'], $rows[0] ?? []);
        $colNameIdx = $this->findColumnIndex($headers, ['college name', 'college', 'collegename']);
        $colAbbrIdx = $this->findColumnIndex($headers, ['college abbreviation', 'college abbr', 'college code', 'collegeabbreviation']);
        $deptNameIdx = $this->findColumnIndex($headers, ['department name', 'department', 'course', 'program', 'departmentname']);
        $deptAbbrIdx = $this->findColumnIndex($headers, ['department abbreviation', 'department abbr', 'department code', 'dept abbreviation', 'dept abbr', 'departmentabbreviation']);

        if ($colNameIdx === null) {
            return [
                'ok' => false,
                'error' => 'Missing required column "College Name". Please use the downloadable sample template.',
            ];
        }

        $collegeModel = new CollegeModel();
        $departmentModel = new DepartmentModel();

        $existingCollegesByKey = [];
        foreach ($collegeModel->findByCampusId($campusId) as $cRow) {
            $key = $this->normalizeName((string) ($cRow['College_Name'] ?? ''));
            if ($key !== '') {
                $existingCollegesByKey[$key] = $cRow;
            }
        }

        $existingDeptsByCollegeAndKey = [];
        foreach ($departmentModel->findByCampusId($campusId) as $dRow) {
            $cid = (int) ($dRow['College_ID'] ?? 0);
            $dKey = $this->normalizeName((string) ($dRow['Department_Name'] ?? ''));
            if ($cid > 0 && $dKey !== '') {
                $existingDeptsByCollegeAndKey[$cid][$dKey] = $dRow;
            }
        }

        $groupedColleges = [];
        $seenPairKeys = [];
        $rowResults = [];
        $counts = [
            'total_rows'          => 0,
            'valid_rows'          => 0,
            'invalid_rows'        => 0,
            'duplicate_rows'      => 0,
            'new_colleges'        => 0,
            'existing_colleges'   => 0,
            'new_departments'     => 0,
            'existing_departments'=> 0,
        ];

        for ($i = 1; $i < count($rows); $i++) {
            $rowNumber = $i + 1;
            $row = $rows[$i];
            $counts['total_rows']++;

            $collegeName = trim((string) ($row[$colNameIdx] ?? ''));
            $collegeAbbr = $colAbbrIdx !== null ? trim((string) ($row[$colAbbrIdx] ?? '')) : '';
            $deptName = $deptNameIdx !== null ? trim((string) ($row[$deptNameIdx] ?? '')) : '';
            $deptAbbr = $deptAbbrIdx !== null ? trim((string) ($row[$deptAbbrIdx] ?? '')) : '';

            $errors = [];
            if ($collegeName === '') {
                $errors[] = 'College Name is required.';
            } elseif (mb_strlen($collegeName) > 150) {
                $errors[] = 'College Name exceeds 150 characters.';
            }
            if (mb_strlen($collegeAbbr) > 20) {
                $errors[] = 'College Abbreviation exceeds 20 characters.';
            }
            if ($deptName !== '' && mb_strlen($deptName) > 150) {
                $errors[] = 'Department Name exceeds 150 characters.';
            }
            if (mb_strlen($deptAbbr) > 20) {
                $errors[] = 'Department Abbreviation exceeds 20 characters.';
            }

            if ($errors !== []) {
                $counts['invalid_rows']++;
                $rowResults[] = [
                    'row'                  => $rowNumber,
                    'collegeName'          => $collegeName,
                    'collegeAbbreviation'  => $collegeAbbr,
                    'departmentName'       => $deptName,
                    'departmentAbbreviation' => $deptAbbr,
                    'status'               => 'invalid',
                    'message'              => implode(' ', $errors),
                ];
                continue;
            }

            $cKey = $this->normalizeName($collegeName);
            $dKey = $deptName !== '' ? $this->normalizeName($deptName) : '';
            $pairKey = $cKey . '|' . $dKey;

            if ($dKey !== '' && isset($seenPairKeys[$pairKey])) {
                $counts['duplicate_rows']++;
                $rowResults[] = [
                    'row'                  => $rowNumber,
                    'collegeName'          => $collegeName,
                    'collegeAbbreviation'  => $collegeAbbr,
                    'departmentName'       => $deptName,
                    'departmentAbbreviation' => $deptAbbr,
                    'status'               => 'duplicate_in_file',
                    'message'              => 'Duplicate college/department pair in this file (will be skipped).',
                ];
                continue;
            }
            if ($dKey !== '') {
                $seenPairKeys[$pairKey] = true;
            }

            $existingCollege = $existingCollegesByKey[$cKey] ?? null;
            $collegeStatus = is_array($existingCollege) ? 'existing' : 'new';

            if (! isset($groupedColleges[$cKey])) {
                $groupedColleges[$cKey] = [
                    'collegeName'         => is_array($existingCollege) ? (string) $existingCollege['College_Name'] : $collegeName,
                    'collegeAbbreviation' => is_array($existingCollege) ? (string) ($existingCollege['Abbreviation'] ?? '') : $collegeAbbr,
                    'collegeId'           => is_array($existingCollege) ? (int) $existingCollege['College_ID'] : null,
                    'status'              => $collegeStatus,
                    'departments'         => [],
                ];
                if ($collegeStatus === 'new') {
                    $counts['new_colleges']++;
                } else {
                    $counts['existing_colleges']++;
                }
            }

            $deptStatus = 'none';
            if ($dKey !== '') {
                $existingDept = is_array($existingCollege)
                    ? ($existingDeptsByCollegeAndKey[(int) $existingCollege['College_ID']][$dKey] ?? null)
                    : null;
                $deptStatus = is_array($existingDept) ? 'existing' : 'new';
                $groupedColleges[$cKey]['departments'][] = [
                    'departmentName'         => is_array($existingDept) ? (string) $existingDept['Department_Name'] : $deptName,
                    'departmentAbbreviation' => is_array($existingDept) ? (string) ($existingDept['Abbreviation'] ?? '') : $deptAbbr,
                    'departmentId'           => is_array($existingDept) ? (int) $existingDept['Department_ID'] : null,
                    'status'                 => $deptStatus,
                ];
                if ($deptStatus === 'new') {
                    $counts['new_departments']++;
                } else {
                    $counts['existing_departments']++;
                }
            }

            $counts['valid_rows']++;
            $statusLabel = ($collegeStatus === 'existing' && ($deptStatus === 'existing' || $deptStatus === 'none'))
                ? 'existing'
                : 'new';

            $rowResults[] = [
                'row'                    => $rowNumber,
                'collegeName'            => $collegeName,
                'collegeAbbreviation'    => $collegeAbbr,
                'departmentName'         => $deptName,
                'departmentAbbreviation' => $deptAbbr,
                'status'                 => $statusLabel,
                'message'                => $statusLabel === 'existing'
                    ? 'Already exists in campus (will be linked to this batch without duplicating).'
                    : 'Will be created and linked to this batch.',
            ];
        }

        return [
            'ok'       => true,
            'colleges' => array_values($groupedColleges),
            'rows'     => $rowResults,
            'counts'   => $counts,
        ];
    }

    /**
     * Execute transaction-safe import of analyzed Colleges + Departments for a Batch.
     *
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    public function commitCollegesAndDepartments(array $analysis, int $campusId, int $graduationYear): array
    {
        $db = \Config\Database::connect();
        $collegeModel = new CollegeModel();
        $departmentModel = new DepartmentModel();

        $summary = [
            'colleges_created'     => 0,
            'colleges_reused'      => 0,
            'departments_created'  => 0,
            'departments_reused'   => 0,
            'invalid_rows'         => (int) ($analysis['counts']['invalid_rows'] ?? 0),
            'duplicate_rows'       => (int) ($analysis['counts']['duplicate_rows'] ?? 0),
        ];

        $db->transBegin();
        try {
            foreach ($analysis['colleges'] as $cItem) {
                $collegeId = $cItem['collegeId'];
                if ($collegeId === null || $collegeId <= 0) {
                    $existing = $collegeModel
                        ->where('Campus_ID', $campusId)
                        ->where('LOWER("College_Name")', strtolower($cItem['collegeName']))
                        ->first();
                    if (is_array($existing)) {
                        $collegeId = (int) $existing['College_ID'];
                        $summary['colleges_reused']++;
                    } else {
                        $collegeId = (int) $collegeModel->insert([
                            'Campus_ID'    => $campusId,
                            'College_Name' => $cItem['collegeName'],
                            'Abbreviation' => $cItem['collegeAbbreviation'],
                        ]);
                        $summary['colleges_created']++;
                    }
                } else {
                    $summary['colleges_reused']++;
                }

                // Link college to batch (idempotent)
                $this->linkBatchCollege($db, $campusId, $graduationYear, $collegeId);

                foreach ($cItem['departments'] as $dItem) {
                    $deptId = $dItem['departmentId'];
                    if ($deptId === null || $deptId <= 0) {
                        $existingDept = $departmentModel
                            ->where('Campus_ID', $campusId)
                            ->where('College_ID', $collegeId)
                            ->where('LOWER("Department_Name")', strtolower($dItem['departmentName']))
                            ->first();
                        if (is_array($existingDept)) {
                            $deptId = (int) $existingDept['Department_ID'];
                            $summary['departments_reused']++;
                        } else {
                            $deptId = (int) $departmentModel->insert([
                                'Campus_ID'        => $campusId,
                                'College_ID'       => $collegeId,
                                'Department_Name'  => $dItem['departmentName'],
                                'Abbreviation'     => $dItem['departmentAbbreviation'],
                                'Coordinator_Name' => '',
                            ]);
                            $summary['departments_created']++;
                        }
                    } else {
                        $summary['departments_reused']++;
                    }

                    $this->linkBatchDepartment($db, $graduationYear, $collegeId, $deptId);
                }
            }

            if ($db->transStatus() === false) {
                $db->transRollback();
                throw new \RuntimeException('Transaction failed while importing colleges and departments.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        return $summary;
    }

    /**
     * Analyze and preview Departments-only rows restricted strictly to one verified College.
     *
     * @param array<int, array<int, string>> $rows
     * @return array<string, mixed>
     */
    public function analyzeDepartmentsForCollege(array $rows, int $campusId, int $collegeId): array
    {
        $headers = array_map([$this, 'normalizeHeader'], $rows[0] ?? []);
        $deptNameIdx = $this->findColumnIndex($headers, ['department name', 'department', 'course', 'program', 'departmentname']);
        $deptAbbrIdx = $this->findColumnIndex($headers, ['department abbreviation', 'department abbr', 'department code', 'abbreviation', 'abbr', 'code', 'departmentabbreviation']);

        if ($deptNameIdx === null) {
            return [
                'ok' => false,
                'error' => 'Missing required column "Department Name". Please use the downloadable department template.',
            ];
        }

        $departmentModel = new DepartmentModel();
        $existingByKey = [];
        foreach ($departmentModel->where('Campus_ID', $campusId)->where('College_ID', $collegeId)->findAll() as $dRow) {
            $key = $this->normalizeName((string) ($dRow['Department_Name'] ?? ''));
            if ($key !== '') {
                $existingByKey[$key] = $dRow;
            }
        }

        $departments = [];
        $seenKeys = [];
        $rowResults = [];
        $counts = [
            'total_rows'           => 0,
            'valid_rows'           => 0,
            'invalid_rows'         => 0,
            'duplicate_rows'       => 0,
            'new_departments'      => 0,
            'existing_departments' => 0,
        ];

        for ($i = 1; $i < count($rows); $i++) {
            $rowNumber = $i + 1;
            $row = $rows[$i];
            $counts['total_rows']++;

            $deptName = trim((string) ($row[$deptNameIdx] ?? ''));
            $deptAbbr = $deptAbbrIdx !== null ? trim((string) ($row[$deptAbbrIdx] ?? '')) : '';

            $errors = [];
            if ($deptName === '') {
                $errors[] = 'Department Name is required.';
            } elseif (mb_strlen($deptName) > 150) {
                $errors[] = 'Department Name exceeds 150 characters.';
            }
            if (mb_strlen($deptAbbr) > 20) {
                $errors[] = 'Department Abbreviation exceeds 20 characters.';
            }

            if ($errors !== []) {
                $counts['invalid_rows']++;
                $rowResults[] = [
                    'row'                    => $rowNumber,
                    'departmentName'         => $deptName,
                    'departmentAbbreviation' => $deptAbbr,
                    'status'                 => 'invalid',
                    'message'                => implode(' ', $errors),
                ];
                continue;
            }

            $key = $this->normalizeName($deptName);
            if (isset($seenKeys[$key])) {
                $counts['duplicate_rows']++;
                $rowResults[] = [
                    'row'                    => $rowNumber,
                    'departmentName'         => $deptName,
                    'departmentAbbreviation' => $deptAbbr,
                    'status'                 => 'duplicate_in_file',
                    'message'                => 'Duplicate department in this file (will be skipped).',
                ];
                continue;
            }
            $seenKeys[$key] = true;

            $existing = $existingByKey[$key] ?? null;
            $status = is_array($existing) ? 'existing' : 'new';
            if ($status === 'new') {
                $counts['new_departments']++;
            } else {
                $counts['existing_departments']++;
            }

            $departments[] = [
                'departmentName'         => is_array($existing) ? (string) $existing['Department_Name'] : $deptName,
                'departmentAbbreviation' => is_array($existing) ? (string) ($existing['Abbreviation'] ?? '') : $deptAbbr,
                'departmentId'           => is_array($existing) ? (int) $existing['Department_ID'] : null,
                'status'                 => $status,
            ];

            $counts['valid_rows']++;
            $rowResults[] = [
                'row'                    => $rowNumber,
                'departmentName'         => $deptName,
                'departmentAbbreviation' => $deptAbbr,
                'status'                 => $status,
                'message'                => $status === 'existing'
                    ? 'Already exists under this college (will be linked to this batch).'
                    : 'Will be created under this college and linked to this batch.',
            ];
        }

        return [
            'ok'          => true,
            'departments' => $departments,
            'rows'        => $rowResults,
            'counts'      => $counts,
        ];
    }

    /**
     * Execute transaction-safe import of analyzed Departments under one verified College.
     *
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    public function commitDepartmentsForCollege(array $analysis, int $campusId, int $graduationYear, int $collegeId): array
    {
        $db = \Config\Database::connect();
        $departmentModel = new DepartmentModel();

        $summary = [
            'departments_created' => 0,
            'departments_reused'  => 0,
            'invalid_rows'        => (int) ($analysis['counts']['invalid_rows'] ?? 0),
            'duplicate_rows'      => (int) ($analysis['counts']['duplicate_rows'] ?? 0),
        ];

        $db->transBegin();
        try {
            $this->linkBatchCollege($db, $campusId, $graduationYear, $collegeId);

            foreach ($analysis['departments'] as $dItem) {
                $deptId = $dItem['departmentId'];
                if ($deptId === null || $deptId <= 0) {
                    $existing = $departmentModel
                        ->where('Campus_ID', $campusId)
                        ->where('College_ID', $collegeId)
                        ->where('LOWER("Department_Name")', strtolower($dItem['departmentName']))
                        ->first();
                    if (is_array($existing)) {
                        $deptId = (int) $existing['Department_ID'];
                        $summary['departments_reused']++;
                    } else {
                        $deptId = (int) $departmentModel->insert([
                            'Campus_ID'        => $campusId,
                            'College_ID'       => $collegeId,
                            'Department_Name'  => $dItem['departmentName'],
                            'Abbreviation'     => $dItem['departmentAbbreviation'],
                            'Coordinator_Name' => '',
                        ]);
                        $summary['departments_created']++;
                    }
                } else {
                    $summary['departments_reused']++;
                }

                $this->linkBatchDepartment($db, $graduationYear, $collegeId, $deptId);
            }

            if ($db->transStatus() === false) {
                $db->transRollback();
                throw new \RuntimeException('Transaction failed while importing departments.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        return $summary;
    }

    /**
     * Generate a downloadable CSV or XLSX template as binary string.
     *
     * @param array<int, array<int, string>> $rows
     * @return array{content: string, mime: string, ext: string}
     */
    public function buildTemplate(array $rows, string $format = 'xlsx'): array
    {
        $format = strtolower(trim($format)) === 'csv' ? 'csv' : 'xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows, null, 'A1');

        $stream = fopen('php://temp', 'r+');
        if ($format === 'csv') {
            $writer = new Csv($spreadsheet);
            $writer->save($stream);
            $mime = 'text/csv; charset=utf-8';
        } else {
            foreach (range('A', $sheet->getHighestColumn()) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
            $writer = new Xlsx($spreadsheet);
            $writer->save($stream);
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return ['content' => $content, 'mime' => $mime, 'ext' => $format];
    }

    public function linkBatchCollege($db, int $campusId, int $graduationYear, int $collegeId): void
    {
        $exists = $db->table('alumni_batch_colleges')
            ->select('id')
            ->where('"Campus_ID"', $campusId)
            ->where('"GraduationYear"', $graduationYear)
            ->where('"College_ID"', $collegeId)
            ->get()
            ->getRowArray();

        if (! is_array($exists)) {
            $db->table('alumni_batch_colleges')->insert([
                'Campus_ID'      => $campusId,
                'GraduationYear' => $graduationYear,
                'College_ID'     => $collegeId,
            ]);
        }
    }

    public function linkBatchDepartment($db, int $graduationYear, int $collegeId, int $departmentId): void
    {
        $exists = $db->table('alumni_batch_departments')
            ->select('id')
            ->where('"GraduationYear"', $graduationYear)
            ->where('"Department_ID"', $departmentId)
            ->get()
            ->getRowArray();

        if (! is_array($exists)) {
            $db->table('alumni_batch_departments')->insert([
                'GraduationYear' => $graduationYear,
                'College_ID'     => $collegeId,
                'Department_ID'  => $departmentId,
            ]);
        }
    }

    private function normalizeHeader(string $header): string
    {
        $clean = strtolower(trim(preg_replace('/[\x{FEFF}_\-]+/u', ' ', $header) ?? $header));
        return preg_replace('/\s+/', ' ', $clean) ?? $clean;
    }

    private function normalizeName(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string> $candidates
     */
    private function findColumnIndex(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $idx = array_search($candidate, $headers, true);
            if ($idx !== false) {
                return (int) $idx;
            }
        }
        return null;
    }
}
