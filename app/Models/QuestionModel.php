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

    protected $beforeInsert = ['castBooleans'];
    protected $beforeUpdate = ['castBooleans'];

    /**
     * PostgreSQL boolean columns strictly require boolean types, not integers (0 or 1).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function castBooleans(array $data): array
    {
        if (isset($data['data']) && array_key_exists('required', $data['data'])) {
            $data['data']['required'] = filter_var($data['data']['required'], FILTER_VALIDATE_BOOLEAN);
        }

        return $data;
    }
}
