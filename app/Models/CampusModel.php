<?php

namespace App\Models;

use CodeIgniter\Model;

class CampusModel extends Model
{
    protected $table = 'campuses';
    protected $primaryKey = 'Campus_ID';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'School_Name',
        'Abbreviation',
        'Campus',
        'Address',
        'Alumni_Head_Email',
    ];

    public function findById(int $id): ?array
    {
        $campus = $this->where('Campus_ID', $id)->first();
        return is_array($campus) ? $campus : null;
    }
}
