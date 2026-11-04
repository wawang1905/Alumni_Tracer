<?php

namespace App\Models;

use CodeIgniter\Model;

class DepartmentModel extends Model
{
    protected $table = 'departments';
    protected $primaryKey = 'Department_ID';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'Campus_ID',
        'College_ID',
        'Department_Name',
        'Abbreviation',
        'Coordinator_Name',
    ];

    public function findById(int $id): ?array
    {
        $department = $this->where('Department_ID', $id)->first();
        return is_array($department) ? $department : null;
    }

    public function findByCampusId(int $campusId): array
    {
        return $this->where('Campus_ID', $campusId)
            ->orderBy('Department_Name', 'ASC')
            ->findAll();
    }

    public function findByCollegeId(int $collegeId): array
    {
        return $this->where('College_ID', $collegeId)
            ->orderBy('Department_Name', 'ASC')
            ->findAll();
    }
}
