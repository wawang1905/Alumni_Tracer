<?php

namespace App\Models;

use CodeIgniter\Model;

class StaffModel extends Model
{
    protected $table = 'staffs';
    protected $primaryKey = 'Staff_ID';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'Campus_ID',
        'College_ID',
        'Department_ID',
        'First_Name',
        'Middle_Name',
        'Last_Name',
        'Email',
        'Contact_Number',
        'Position',
        'Role',
        'Status',
    ];

    public function findById(int $id): ?array
    {
        $staff = $this->where('Staff_ID', $id)->first();
        return is_array($staff) ? $staff : null;
    }

    public function findByCampusId(int $campusId): array
    {
        return $this->where('Campus_ID', $campusId)
            ->orderBy('Last_Name', 'ASC')
            ->findAll();
    }

    public function findByCollegeId(int $collegeId): array
    {
        return $this->where('College_ID', $collegeId)
            ->orderBy('Last_Name', 'ASC')
            ->findAll();
    }

    public function findByDepartmentId(int $departmentId): array
    {
        return $this->where('Department_ID', $departmentId)
            ->orderBy('Last_Name', 'ASC')
            ->findAll();
    }

    public function findCoordinatorsByCampusId(int $campusId): array
    {
        return $this->where('Campus_ID', $campusId)
            ->where('Role', 'Coordinator')
            ->orderBy('Last_Name', 'ASC')
            ->findAll();
    }

    public function findCoordinatorsByCollegeId(int $collegeId): array
    {
        return $this->where('College_ID', $collegeId)
            ->where('Role', 'Coordinator')
            ->orderBy('Last_Name', 'ASC')
            ->findAll();
    }

    public function findCoordinatorsByDepartmentId(int $departmentId): array
    {
        return $this->where('Department_ID', $departmentId)
            ->where('Role', 'Coordinator')
            ->orderBy('Last_Name', 'ASC')
            ->findAll();
    }
}
