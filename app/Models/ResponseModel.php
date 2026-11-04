<?php

namespace App\Models;

use CodeIgniter\Model;

class ResponseModel extends Model
{
    protected $table = 'responses';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'survey_id',
        'user_email',
        'submitted_at',
        'survey_number',
        'user_name',
        'user_id',
    ];
}
