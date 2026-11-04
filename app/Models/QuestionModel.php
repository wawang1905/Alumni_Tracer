<?php

namespace App\Models;

use CodeIgniter\Model;

class QuestionModel extends Model
{
    protected $table = 'questions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'category_id',
        'question_text',
        'question_type',
        'required',
        'analytics_key',
        'rating_min',
        'rating_max',
        'rating_min_label',
        'rating_max_label',
    ];
}
