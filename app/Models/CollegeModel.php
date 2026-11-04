<?php

namespace App\Models;

use CodeIgniter\Model;

class CollegeModel extends Model
{
    protected $table = 'colleges';
    protected $primaryKey = 'College_ID';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'Campus_ID',
        'College_Name',
        'Abbreviation',
    ];

    public function findById(int $id): ?array
    {
        $college = $this->where('College_ID', $id)->first();
        return is_array($college) ? $college : null;
    }

    public function findByCampusId(int $campusId): array
    {
        return $this->where('Campus_ID', $campusId)
            ->orderBy('College_Name', 'ASC')
            ->findAll();
    }
}
