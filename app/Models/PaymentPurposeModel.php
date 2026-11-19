<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentPurposeModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'payment_purposes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'code',
        'name',
        'description',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get all active payment purposes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActivePurposes(): array
    {
        return $this->where('is_active', true)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Find an active payment purpose by its primary key ID.
     */
    public function findActiveById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return $this->where('id', $id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Find a payment purpose by its unique code.
     */
    public function findByCode(string $code): ?array
    {
        $cleanCode = strtolower(trim($code));
        if ($cleanCode === '') {
            return null;
        }

        return $this->where('code', $cleanCode)->first();
    }

    /**
     * Find an active payment purpose by its unique code.
     */
    public function findActiveByCode(string $code): ?array
    {
        $cleanCode = strtolower(trim($code));
        if ($cleanCode === '') {
            return null;
        }

        return $this->where('code', $cleanCode)
            ->where('is_active', true)
            ->first();
    }
}
