<?php

namespace App\Models;

use CodeIgniter\Model;

class ChoiceModel extends Model
{
    protected $table = 'choices';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'question_id',
        'choice_text',
    ];
}
