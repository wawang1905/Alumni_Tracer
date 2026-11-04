<?php

namespace App\Models;

use CodeIgniter\Model;

class UserAccountModel extends Model
{
    protected $table = 'user_accounts';
    protected $primaryKey = 'Email';
    protected $returnType = 'array';
    protected $useAutoIncrement = false;
    protected $allowedFields = [
        'First_Name',
        'Last_Name',
        'Role',
        'Email',
        'College',
        'Department',
        'Campus',
        'account_id',
        'Password',
    ];

    public function findByEmail(string $email): ?array
    {
        $user = $this->where('Email', $email)->first();

        return is_array($user) ? $user : null;
    }

    public function findByAccountId(string $accountId): ?array
    {
        $user = $this->where('account_id', $accountId)->first();

        return is_array($user) ? $user : null;
    }

    public function getDistinctColleges(): array
    {
        $rows = $this->builder()
            ->select('College')
            ->where('"College" IS NOT NULL', null, false)
            ->where('College !=', '')
            ->groupBy('College')
            ->orderBy('College', 'ASC')
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_filter(array_map(fn (array $row): string => trim((string) ($row['College'] ?? '')), $rows))));
    }

    public function createFromSignup(array $data): bool
    {
        return $this->insert([
            'First_Name' => $data['first_name'],
            'Last_Name'  => $data['last_name'],
            'Role'       => $data['role'] ?? 'alumni',
            'Email'      => $data['email'],
            'Department' => $data['department'],
            'Password'   => $data['password'],
        ]) !== false;
    }
}
