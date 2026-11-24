<?php

namespace App\Services;

use App\Models\CampusModel;
use App\Models\CollegeModel;
use App\Models\DepartmentModel;
use App\Models\UserAccountModel;
use CodeIgniter\Database\BaseBuilder;

/**
 * Centralized Campus, College, and Department assignment verification and
 * data-scoping service for Head Admins and Coordinators.
 *
 * Hierarchy:
 * - Head Admin:  Assigned Campus -> All Colleges in Campus -> All Departments in Those Colleges
 * - Coordinator: Assigned Campus -> Assigned College -> Assigned Department
 * - Developer:   Unrestricted (returns null from resolveCurrentUserScope)
 */
class AssignmentScopeService
{
    private CampusModel $campusModel;
    private CollegeModel $collegeModel;
    private DepartmentModel $departmentModel;
    private UserAccountModel $userAccountModel;

    public function __construct()
    {
        $this->campusModel = new CampusModel();
        $this->collegeModel = new CollegeModel();
        $this->departmentModel = new DepartmentModel();
        $this->userAccountModel = new UserAccountModel();
    }

    /**
     * Validate a Head Admin's campus assignment against the database.
     *
     * @param array<string, mixed> $userRow
     * @return array<string, mixed>
     */
    public function validateHeadAdminAssignment(array $userRow): array
    {
        $rawCampus = trim((string) ($userRow['Campus'] ?? ''));
        if ($rawCampus === '') {
            return [
                'valid'   => false,
                'code'    => 'missing_campus',
                'message' => 'Your account has not been assigned to a campus. Please contact the Developer.',
            ];
        }

        $campusRow = $this->findCampusRecord($rawCampus);
        if (! is_array($campusRow)) {
            return [
                'valid'   => false,
                'code'    => 'invalid_campus',
                'message' => 'Your assigned campus is invalid or no longer exists. Please contact the Developer.',
            ];
        }

        $campusId = (int) ($campusRow['Campus_ID'] ?? 0);
        if ($campusId <= 0) {
            return [
                'valid'   => false,
                'code'    => 'invalid_campus',
                'message' => 'Your assigned campus is invalid or no longer exists. Please contact the Developer.',
            ];
        }

        $colleges = $this->collegeModel->findByCampusId($campusId);
        $collegeIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): int => (int) ($row['College_ID'] ?? 0),
            $colleges
        ))));

        $departments = $this->departmentModel->findByCampusId($campusId);
        $departmentIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): int => (int) ($row['Department_ID'] ?? 0),
            $departments
        ))));

        return [
            'valid'           => true,
            'role'            => 'head_admin',
            'campus_id'       => $campusId,
            'campus_name'     => trim((string) ($campusRow['Campus'] ?? '')),
            'college_id'      => null,
            'college_name'    => '',
            'department_id'   => null,
            'department_name' => '',
            'college_ids'     => $collegeIds,
            'department_ids'  => $departmentIds,
        ];
    }

    /**
     * Validate a Coordinator's Campus -> College -> Department assignment hierarchy.
     *
     * @param array<string, mixed> $userRow
     * @return array<string, mixed>
     */
    public function validateCoordinatorAssignment(array $userRow): array
    {
        $rawCampus = trim((string) ($userRow['Campus'] ?? ''));
        if ($rawCampus === '') {
            return [
                'valid'   => false,
                'code'    => 'missing_campus',
                'message' => 'Your account has not been assigned to a campus. Please contact your Head Admin or Developer.',
            ];
        }

        $campusRow = $this->findCampusRecord($rawCampus);
        if (! is_array($campusRow) || (int) ($campusRow['Campus_ID'] ?? 0) <= 0) {
            return [
                'valid'   => false,
                'code'    => 'invalid_campus',
                'message' => 'Your assigned campus is invalid or no longer exists. Please contact your Head Admin or Developer.',
            ];
        }
        $campusId = (int) $campusRow['Campus_ID'];

        $rawCollege = trim((string) ($userRow['College'] ?? ''));
        if ($rawCollege === '') {
            return [
                'valid'   => false,
                'code'    => 'missing_college',
                'message' => 'Your account has not been assigned to a college. Please contact your Head Admin.',
            ];
        }

        // First look for the college within the assigned campus; if not found, check globally to give a precise hierarchy error
        $collegeRow = $this->findCollegeRecord($rawCollege, $campusId);
        if (! is_array($collegeRow)) {
            $anyCollege = $this->findCollegeRecord($rawCollege, null);
            if (is_array($anyCollege)) {
                return [
                    'valid'   => false,
                    'code'    => 'college_campus_mismatch',
                    'message' => 'Your assigned college does not belong to your assigned campus. Please contact your Head Admin.',
                ];
            }

            return [
                'valid'   => false,
                'code'    => 'invalid_college',
                'message' => 'Your assigned college is invalid or no longer exists. Please contact your Head Admin.',
            ];
        }

        $collegeId = (int) ($collegeRow['College_ID'] ?? 0);
        if ($collegeId <= 0 || (int) ($collegeRow['Campus_ID'] ?? 0) !== $campusId) {
            return [
                'valid'   => false,
                'code'    => 'college_campus_mismatch',
                'message' => 'Your assigned college does not belong to your assigned campus. Please contact your Head Admin.',
            ];
        }

        // Gather all departments belonging to the coordinator's assigned college on this campus.
        $deptRows = $this->departmentModel->builder()
            ->where('"Campus_ID"', $campusId)
            ->where('"College_ID"', $collegeId)
            ->get()
            ->getResultArray();

        $departmentIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): int => (int) ($row['Department_ID'] ?? 0),
            $deptRows
        ))));

        // Department assignment is no longer required for coordinators; preserve legacy department metadata
        // only when it matches a valid department inside the assigned college.
        $rawDepartment = trim((string) ($userRow['Department'] ?? ''));
        $deptId = null;
        $deptName = '';
        if ($rawDepartment !== '') {
            $deptRow = $this->findDepartmentRecord($rawDepartment, $collegeId, $campusId);
            if (is_array($deptRow)) {
                $matchedDeptId = (int) ($deptRow['Department_ID'] ?? 0);
                if ($matchedDeptId > 0) {
                    $deptId = $matchedDeptId;
                    $deptName = trim((string) ($deptRow['Department_Name'] ?? ''));
                }
            }
        }

        return [
            'valid'           => true,
            'role'            => 'coordinator',
            'campus_id'       => $campusId,
            'campus_name'     => trim((string) ($campusRow['Campus'] ?? '')),
            'college_id'      => $collegeId,
            'college_name'    => trim((string) ($collegeRow['College_Name'] ?? '')),
            'department_id'   => $deptId,
            'department_name' => $deptName,
            'college_ids'     => [$collegeId],
            'department_ids'  => $departmentIds,
        ];
    }

    /**
     * Resolve and revalidate the current logged-in user's organizational scope
     * from the database.
     *
     * Returns null for Developer and Alumni roles (not subject to Head Admin/Coordinator scoping).
     * Returns ['valid' => false, 'message' => ...] if a Head Admin or Coordinator has an invalid/missing assignment.
     *
     * @return array<string, mixed>|null
     */
    public function resolveCurrentUserScope(): ?array
    {
        helper('role');

        $authUser = session('auth_user');
        if (! is_array($authUser)) {
            return null;
        }

        $role = normalize_role((string) ($authUser['role'] ?? ''));
        if (is_developer_role($role) || $role === 'alumni' || $role === '') {
            return null;
        }

        $email = strtolower(trim((string) ($authUser['email'] ?? '')));
        if ($email === '') {
            return [
                'valid'   => false,
                'code'    => 'missing_account',
                'message' => 'Unable to verify your account assignment. Please log in again.',
            ];
        }

        $userRow = $this->userAccountModel->findByEmail($email);
        if (! is_array($userRow)) {
            return [
                'valid'   => false,
                'code'    => 'missing_account',
                'message' => 'Your account could not be found. Please contact the Developer.',
            ];
        }

        $dbRole = normalize_role((string) ($userRow['Role'] ?? $role));
        if ($dbRole === 'coordinator') {
            $scope = $this->validateCoordinatorAssignment($userRow);
        } elseif (is_staff_role($dbRole)) {
            $scope = $this->validateHeadAdminAssignment($userRow);
        } else {
            return null;
        }

        if (($scope['valid'] ?? false) === true) {
            $this->syncScopeToSession($authUser, $scope);
        }

        return $scope;
    }

    /**
     * Apply Head Admin or Coordinator organizational scope to an alumni Query Builder.
     * Assumes $alumniAlias is the alumni table alias, and optional $collegeAlias / $deptAlias
     * are joined when provided.
     *
     * @param array<string, mixed> $scope
     */
    public function applyAlumniScope(
        BaseBuilder $builder,
        array $scope,
        string $alumniAlias = 'alumni',
        ?string $collegeAlias = 'c',
        ?string $deptAlias = 'd'
    ): void {
        if (($scope['valid'] ?? false) !== true) {
            $builder->where('1 = 0', null, false);
            return;
        }

        $role = (string) ($scope['role'] ?? '');
        $campusId = (int) ($scope['campus_id'] ?? 0);

        if ($role === 'coordinator') {
            $collegeId = (int) ($scope['college_id'] ?? 0);

            if ($campusId <= 0 || $collegeId <= 0) {
                $builder->where('1 = 0', null, false);
                return;
            }

            $builder->where("{$alumniAlias}.\"College_ID\"", $collegeId);
            if ($collegeAlias !== null && $collegeAlias !== '') {
                $builder->where("{$collegeAlias}.\"Campus_ID\"", $campusId);
            }
            return;
        }

        if ($role === 'head_admin') {
            if ($campusId <= 0) {
                $builder->where('1 = 0', null, false);
                return;
            }

            $collegeIds = $scope['college_ids'] ?? [];
            if ($collegeAlias !== null && $collegeAlias !== '') {
                $builder->where("{$collegeAlias}.\"Campus_ID\"", $campusId);
            } elseif (is_array($collegeIds) && $collegeIds !== []) {
                $builder->whereIn("{$alumniAlias}.\"College_ID\"", $collegeIds);
            } else {
                $builder->where('1 = 0', null, false);
            }
        }
    }

    /**
     * Check whether an alumni record belongs to the given verified scope.
     *
     * @param array<string, mixed> $alumniRow
     * @param array<string, mixed> $scope
     */
    public function isAlumniInScope(array $alumniRow, array $scope): bool
    {
        if (($scope['valid'] ?? false) !== true) {
            return false;
        }

        $alumniCollegeId = (int) ($alumniRow['College_ID'] ?? $alumniRow['college_id'] ?? 0);
        $alumniDeptId = (int) ($alumniRow['Department_ID'] ?? $alumniRow['department_id'] ?? 0);

        if (($scope['role'] ?? '') === 'coordinator') {
            return $alumniCollegeId > 0
                && $alumniCollegeId === (int) ($scope['college_id'] ?? 0);
        }

        if (($scope['role'] ?? '') === 'head_admin') {
            $allowedColleges = $scope['college_ids'] ?? [];
            $allowedDepts = $scope['department_ids'] ?? [];

            if ($alumniCollegeId > 0 && in_array($alumniCollegeId, $allowedColleges, true)) {
                return true;
            }
            if ($alumniDeptId > 0 && in_array($alumniDeptId, $allowedDepts, true)) {
                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * Check whether a college ID is within the verified scope.
     *
     * @param array<string, mixed> $scope
     */
    public function isCollegeInScope(int $collegeId, array $scope): bool
    {
        if (($scope['valid'] ?? false) !== true || $collegeId <= 0) {
            return false;
        }

        return in_array($collegeId, $scope['college_ids'] ?? [], true);
    }

    /**
     * Check whether a department ID (and optionally college ID) is within the verified scope.
     *
     * @param array<string, mixed> $scope
     */
    public function isDepartmentInScope(int $departmentId, array $scope, ?int $collegeId = null): bool
    {
        if (($scope['valid'] ?? false) !== true || $departmentId <= 0) {
            return false;
        }

        if (! in_array($departmentId, $scope['department_ids'] ?? [], true)) {
            return false;
        }

        if ($collegeId !== null && ! $this->isCollegeInScope($collegeId, $scope)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCampusRecord(string $rawCampus): ?array
    {
        if (ctype_digit($rawCampus) && (int) $rawCampus > 0) {
            return $this->campusModel->findById((int) $rawCampus);
        }

        $row = $this->campusModel
            ->where('LOWER("Campus")', strtolower($rawCampus))
            ->first();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCollegeRecord(string $rawCollege, ?int $campusId): ?array
    {
        $builder = $this->collegeModel->builder();
        if (ctype_digit($rawCollege) && (int) $rawCollege > 0) {
            $builder->where('"College_ID"', (int) $rawCollege);
        } else {
            $builder->where('LOWER("College_Name")', strtolower($rawCollege));
        }

        if ($campusId !== null && $campusId > 0) {
            $builder->where('"Campus_ID"', $campusId);
        }

        $row = $builder->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDepartmentRecord(string $rawDepartment, ?int $collegeId, ?int $campusId): ?array
    {
        $builder = $this->departmentModel->builder();
        if (ctype_digit($rawDepartment) && (int) $rawDepartment > 0) {
            $builder->where('"Department_ID"', (int) $rawDepartment);
        } else {
            $builder->where('LOWER("Department_Name")', strtolower($rawDepartment));
        }

        if ($collegeId !== null && $collegeId > 0) {
            $builder->where('"College_ID"', $collegeId);
        }

        if ($campusId !== null && $campusId > 0) {
            $builder->where('"Campus_ID"', $campusId);
        }

        $row = $builder->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * Keep session assignment metadata synced with verified database values.
     *
     * @param array<string, mixed> $authUser
     * @param array<string, mixed> $scope
     */
    private function syncScopeToSession(array $authUser, array $scope): void
    {
        $updated = $authUser;
        $updated['campus'] = (string) ($scope['campus_id'] ?? '');
        $updated['campus_id'] = (int) ($scope['campus_id'] ?? 0);
        $updated['campus_name'] = (string) ($scope['campus_name'] ?? '');

        if (($scope['role'] ?? '') === 'coordinator') {
            $updated['college'] = (string) ($scope['college_name'] ?? '');
            $updated['college_id'] = (int) ($scope['college_id'] ?? 0);
            $updated['department'] = (string) ($scope['department_name'] ?? '');
            $updated['department_id'] = ! empty($scope['department_id']) ? (int) $scope['department_id'] : null;
        } else {
            $updated['college_id'] = null;
            $updated['department_id'] = null;
        }

        if ($updated !== $authUser) {
            session()->set('auth_user', $updated);
        }
    }
}
