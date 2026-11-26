<?php

namespace App\Models;

use CodeIgniter\Model;

class AlumniModel extends Model
{
    protected $DBGroup = 'default';
    protected $table = 'alumni';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'alumni_id',
        'FirstName',
        'LastName',
        'Email',
        'GraduationYear',
        'Status',
        'College_ID',
        'Department_ID',
        'Province',
        'Municipality',
        'Barangay',
        'Contact_Number',
        'sex',
        'birth_month',
        'birth_date',
        'birth_year',
        'emergency_contact',
        'emergency_contact_name',
    ];

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed>|null $scope Verified scope from AssignmentScopeService
     */
    public function getSortedList(string $sort = 'last_name_asc', array $filters = [], ?array $scope = null): array
    {
        [$column, $direction] = $this->mapSort($sort);

        $builder = $this->builder()
            ->select('alumni.id, alumni.alumni_id, alumni.FirstName, alumni.LastName, alumni.Email, alumni.GraduationYear, alumni.Status, alumni.College_ID, alumni.Department_ID, alumni.Province, alumni.Municipality, alumni.Barangay, alumni.Contact_Number, c.College_Name AS College, d.Department_Name AS Course')
            ->join('colleges c', 'c.College_ID = alumni.College_ID', 'left')
            ->join('departments d', 'd.Department_ID = alumni.Department_ID', 'left');

        if ($scope !== null) {
            (new \App\Services\AssignmentScopeService())->applyAlumniScope($builder, $scope, 'alumni', 'c', 'd');
        }

        $year = trim((string) ($filters['year'] ?? $filters['graduationYear'] ?? ''));
        if ($year !== '' && strtolower($year) !== 'all') {
            $builder->where('alumni.GraduationYear', $year);
        }

        $college = trim((string) ($filters['college'] ?? ''));
        if ($college !== '' && strtolower($college) !== 'all') {
            $builder->where('LOWER(c."College_Name")', strtolower($college));
        }

        $course = trim((string) ($filters['course'] ?? ''));
        if ($course !== '' && strtolower($course) !== 'all') {
            $builder->where('LOWER(d."Department_Name")', strtolower($course));
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && strtolower($status) !== 'all') {
            $builder->where('LOWER(alumni."Status"::text)', strtolower($status));
        }

        return $builder
            ->orderBy($column, $direction)
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string, mixed>|null $scope Verified scope from AssignmentScopeService
     * @return array{totalAlumni: int, totalColleges: int, totalCourses: int, totalBatches: int}
     */
    public function getOverallCounts(?array $scope = null): array
    {
        $builder = $this->builder()
            ->select('COUNT(alumni.id) AS total_alumni, COUNT(DISTINCT alumni."College_ID") AS total_colleges, COUNT(DISTINCT alumni."Department_ID") AS total_courses, COUNT(DISTINCT alumni."GraduationYear") AS total_batches', false)
            ->join('colleges c', 'c."College_ID" = alumni."College_ID"', 'left')
            ->join('departments d', 'd."Department_ID" = alumni."Department_ID"', 'left');

        if ($scope !== null) {
            (new \App\Services\AssignmentScopeService())->applyAlumniScope($builder, $scope, 'alumni', 'c', 'd');
        }

        $row = $builder->get()->getRowArray();

        return [
            'totalAlumni'   => (int) ($row['total_alumni'] ?? 0),
            'totalColleges' => (int) ($row['total_colleges'] ?? 0),
            'totalCourses'  => (int) ($row['total_courses'] ?? 0),
            'totalBatches'  => (int) ($row['total_batches'] ?? 0),
        ];
    }

    public function findByEmail(string $email): ?array
    {
        $record = $this->builder()
            ->select('alumni.id, alumni.alumni_id, alumni.FirstName, alumni.LastName, alumni.Email, alumni.GraduationYear, alumni.Status, alumni.College_ID, alumni.Department_ID, alumni.Province, alumni.Municipality, alumni.Barangay, alumni.Contact_Number, alumni.sex, alumni.birth_month, alumni.birth_date, alumni.birth_year, alumni.emergency_contact, alumni.emergency_contact_name, c.College_Name AS College, d.Department_Name AS Course')
            ->join('colleges c', 'c.College_ID = alumni.College_ID', 'left')
            ->join('departments d', 'd.Department_ID = alumni.Department_ID', 'left')
            ->where('Email', $email)
            ->get()
            ->getRowArray();

        return is_array($record) ? $record : null;
    }

    public function findDuplicateProfile(
        string $firstName,
        string $lastName,
        ?string $email,
        int $collegeId,
        int $departmentId
    ): ?array {
        $builder = $this->builder()
            ->select('alumni.id, alumni.alumni_id, alumni.FirstName, alumni.LastName, alumni.Email, alumni.GraduationYear, alumni.Status, alumni.College_ID, alumni.Department_ID, alumni.Province, alumni.Municipality, alumni.Barangay, alumni.Contact_Number, c.College_Name AS College, d.Department_Name AS Course')
            ->join('colleges c', 'c.College_ID = alumni.College_ID', 'left')
            ->join('departments d', 'd.Department_ID = alumni.Department_ID', 'left')
            ->where('LOWER("FirstName")', strtolower(trim($firstName)))
            ->where('LOWER("LastName")', strtolower(trim($lastName)))
            ->where('alumni.College_ID', $collegeId)
            ->where('alumni.Department_ID', $departmentId);

        if ($email === null || trim($email) === '') {
            $builder->where('"Email" IS NULL', null, false);
        } else {
            $builder->where('LOWER("Email")', strtolower(trim($email)));
        }

        $record = $builder->get()->getRowArray();

        return is_array($record) ? $record : null;
    }

    /**
     * @param array<string, mixed>|null $scope Verified scope from AssignmentScopeService
     */
    public function getBatchSummaries(?array $scope = null): array
    {
        $builder = $this->builder()
            ->select('alumni."GraduationYear"')
            ->selectCount('alumni.id', 'alumni_count')
            ->select("SUM(CASE WHEN LOWER(alumni.\"Status\"::text) = 'active' THEN 1 ELSE 0 END) AS active_count", false)
            ->join('colleges c', 'c."College_ID" = alumni."College_ID"', 'left')
            ->join('departments d', 'd."Department_ID" = alumni."Department_ID"', 'left')
            ->where('alumni."GraduationYear" IS NOT NULL', null, false);

        if ($scope !== null) {
            (new \App\Services\AssignmentScopeService())->applyAlumniScope($builder, $scope, 'alumni', 'c', 'd');
        }

        return $builder
            ->groupBy('alumni."GraduationYear"')
            ->orderBy('alumni."GraduationYear"', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getSignupOptions(): array
    {
        $rows = $this->builder()
            ->select('c.College_Name AS College, d.Department_Name AS Course')
            ->join('colleges c', 'c.College_ID = alumni.College_ID', 'inner')
            ->join('departments d', 'd.Department_ID = alumni.Department_ID', 'inner')
            ->where('alumni."College_ID" IS NOT NULL', null, false)
            ->where('alumni."Department_ID" IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        $options = [];
        foreach ($rows as $row) {
            $college = trim((string) ($row['College'] ?? ''));
            $course = trim((string) ($row['Course'] ?? ''));

            if ($college === '' || $course === '') {
                continue;
            }

            if (! array_key_exists($college, $options)) {
                $options[$college] = [];
            }

            if (! in_array($course, $options[$college], true)) {
                $options[$college][] = $course;
            }
        }

        ksort($options, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($options as &$courses) {
            sort($courses, SORT_NATURAL | SORT_FLAG_CASE);
        }
        unset($courses);

        return $options;
    }

    private function mapSort(string $sort): array
    {
        return match ($sort) {
            'first_name_asc' => ['FirstName', 'ASC'],
            'first_name_desc' => ['FirstName', 'DESC'],
            'year_desc' => ['GraduationYear', 'DESC'],
            'year_asc' => ['GraduationYear', 'ASC'],
            'status_asc' => ['Status', 'ASC'],
            'college_asc' => ['c.College_Name', 'ASC'],
            'course_asc' => ['d.Department_Name', 'ASC'],
            default => ['LastName', 'ASC'],
        };
    }

    /**
     * Generate a unique, official Alumni ID number (e.g. 20250001)
     */
    public function generateUniqueAlumniId(?string $graduationYear = null): string
    {
        $year = (!empty($graduationYear) && is_numeric($graduationYear)) ? (string) $graduationYear : date('Y');

        // Check existing alumni IDs for this graduation year
        $existing = $this->builder()
            ->select('alumni_id')
            ->like('alumni_id', $year, 'after')
            ->orderBy('alumni_id', 'DESC')
            ->get()
            ->getResultArray();

        $maxSeq = 0;
        foreach ($existing as $row) {
            $val = preg_replace('/[^0-9]/', '', (string) ($row['alumni_id'] ?? ''));
            if (str_starts_with($val, $year) && strlen($val) >= strlen($year) + 4) {
                $seq = (int) substr($val, strlen($year));
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $nextSeq = $maxSeq + 1;
        $candidate = $year . sprintf('%04d', $nextSeq);

        // Safety check to ensure uniqueness
        while ($this->where('alumni_id', $candidate)->countAllResults() > 0) {
            $nextSeq++;
            $candidate = $year . sprintf('%04d', $nextSeq);
        }

        return $candidate;
    }

    /**
     * Assign a unique Alumni ID number to a specific alumni record
     * Also syncs with user_accounts.account_id if an account exists for their email.
     */
    public function assignAlumniId(int $alumniId): ?string
    {
        $alumni = $this->find($alumniId);
        if (!$alumni) {
            return null;
        }

        // If alumni already has a non-empty alumni_id, return it
        $currentId = trim((string) ($alumni['alumni_id'] ?? ''));
        if ($currentId !== '') {
            return $currentId;
        }

        // Generate new unique ID based on graduation year or current year
        $gradYear = !empty($alumni['GraduationYear']) ? (string) $alumni['GraduationYear'] : null;
        $generatedId = $this->generateUniqueAlumniId($gradYear);

        // Update alumni record with generated alumni ID
        $this->update($alumniId, [
            'alumni_id' => $generatedId,
            'Status' => 'active',
        ]);

        // Keep user_accounts.account_id in sync if an account exists for this email
        if (!empty($alumni['Email'])) {
            $this->db->table('user_accounts')
                ->where('LOWER("Email")', strtolower(trim($alumni['Email'])))
                ->update(['account_id' => $generatedId]);
        }

        return $generatedId;
    }
}
