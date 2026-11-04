<?php

namespace App\Models;

use CodeIgniter\Model;

class Alumni_Model extends Model
{
    protected $table = 'alumni';
    protected $primaryKey = 'id';
    protected $allowedFields = ['alumni_id', 'FirstName', 'LastName', 'Email', 'GraduationYear', 'Status', 'College_ID', 'Department_ID'];

    // Get total alumni count
    public function getTotalAlumni()
    {
        return $this->countAll();
    }

    // Get active alumni count
    public function getActiveAlumni()
    {
        return $this->where('Status', 'active')->countAllResults();
    }
}
