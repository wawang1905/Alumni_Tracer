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
        if (isset($data['data']) && array_key_exists('allow_multiple_responses', $data['data'])) {
            $data['data']['allow_multiple_responses'] = filter_var($data['data']['allow_multiple_responses'], FILTER_VALIDATE_BOOLEAN);
        }

        return $data;
    }
}
