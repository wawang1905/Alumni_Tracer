<?php

namespace App\Models;

use CodeIgniter\Model;

class AnswerModel extends Model
{
    protected $table = 'answers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'response_id',
        'question_id',
        'choice_id',
        'answer_text',
        'answer_image_path',
    ];
}
