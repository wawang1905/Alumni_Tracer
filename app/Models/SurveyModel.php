<?php

namespace App\Models;

use CodeIgniter\Model;

class SurveyModel extends Model
{
    protected $table = 'surveys';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'title',
        'description',
        'status',
        'allow_multiple_responses',
        'current_batch',
        'open_datetime',
        'close_datetime',
        'survey_state',
    ];
}
