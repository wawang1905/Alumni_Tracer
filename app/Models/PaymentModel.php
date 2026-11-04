<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentModel extends Model
{
    protected $DBGroup = 'default';
    protected $table = 'payments';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'transaction_ref',
        'alumni_id',
        'student_number',
        'payer_name',
        'payer_email',
        'payer_contact',
        'campus_id',
        'college_id',
        'payment_type',
        'payment_purpose_id',
        'description',
        'amount',
        'payment_method',
        'channel_reference',
        'status',
        'proof_file',
        'submitted_by_role',
        'coordinator_id',
        'notes',
        'verified_by',
        'verified_at',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Get payments with optional filters
     */
    public function getFilteredPayments(array $filters = []): array
    {
        $builder = $this->db->table($this->table . ' p');
        $builder->select('
            p.*,
            pp.name AS payment_purpose_name,
            pp.code AS payment_purpose_code,
            c.College_Name AS college_name,
            c.Abbreviation AS college_abbr,
            cmp.Campus AS campus_name,
            ua.First_Name AS verifier_first_name,
            ua.Last_Name AS verifier_last_name
        ');
        $builder->join('payment_purposes pp', 'pp.id = p.payment_purpose_id', 'left');
        $builder->join('colleges c', 'c.College_ID = p.college_id', 'left');
        $builder->join('campuses cmp', 'cmp.Campus_ID = p.campus_id', 'left');
        $builder->join('user_accounts ua', 'ua.id = p.verified_by', 'left');

        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $builder->where('p.status', $filters['status']);
        }

        // Payment Purpose ID filter
        if (!empty($filters['payment_purpose_id']) && $filters['payment_purpose_id'] !== 'all') {
            $builder->where('p.payment_purpose_id', (int) $filters['payment_purpose_id']);
        }

        // Payment Type filter (supports purpose ID if numeric or code string)
        if (!empty($filters['payment_type']) && $filters['payment_type'] !== 'all') {
            if (is_numeric($filters['payment_type'])) {
                $builder->where('p.payment_purpose_id', (int) $filters['payment_type']);
            } else {
                $builder->where('p.payment_type', $filters['payment_type']);
            }
        }

        // College filter
        if (!empty($filters['college_id']) && $filters['college_id'] !== 'all') {
            $builder->where('p.college_id', (int) $filters['college_id']);
        }

        // Campus filter
        if (!empty($filters['campus_id']) && $filters['campus_id'] !== 'all') {
            $builder->where('p.campus_id', (int) $filters['campus_id']);
        }

        // Payment method filter
        if (!empty($filters['payment_method']) && $filters['payment_method'] !== 'all') {
            $builder->where('p.payment_method', $filters['payment_method']);
        }

        // Search query
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $builder->groupStart()
                ->like('p.transaction_ref', $search)
                ->orLike('p.payer_name', $search)
                ->orLike('p.student_number', $search)
                ->orLike('p.payer_email', $search)
                ->orLike('p.channel_reference', $search)
                ->orLike('p.description', $search)
                ->orLike('pp.name', $search)
                ->orLike('pp.code', $search)
                ->groupEnd();
        }

        // Date filters
        if (!empty($filters['date_from'])) {
            $builder->where('DATE(p.created_at) >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $builder->where('DATE(p.created_at) <=', $filters['date_to']);
        }

        $builder->orderBy('p.created_at', 'DESC');

        if (!empty($filters['limit'])) {
            $builder->limit((int) $filters['limit'], (int) ($filters['offset'] ?? 0));
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Get payments formatted specifically for Excel Report generation
     * Includes joined payment purposes, campus, college, and staff/user verifier names.
     */
    public function getPaymentsForExcelReport(array $filters = []): array
    {
        $builder = $this->db->table($this->table . ' p');
        $builder->select('
            p.transaction_ref,
            p.created_at,
            p.payer_name,
            p.student_number,
            p.alumni_id,
            cmp.Campus AS campus_name,
            c.College_Name AS college_name,
            p.payment_type,
            p.payment_purpose_id,
            pp.name AS payment_purpose_name,
            pp.code AS payment_purpose_code,
            p.amount,
            p.payment_method,
            p.channel_reference,
            p.status,
            p.submitted_by_role,
            p.verified_by,
            p.verified_at,
            p.notes,
            ua.First_Name AS ua_first_name,
            ua.Last_Name AS ua_last_name,
            st.First_Name AS st_first_name,
            st.Last_Name AS st_last_name
        ');

        // Joins with payment_purposes, campuses, colleges, user_accounts, and staffs
        $builder->join('payment_purposes pp', 'pp.id = p.payment_purpose_id', 'left');
        $builder->join('campuses cmp', 'cmp.Campus_ID = p.campus_id', 'left');
        $builder->join('colleges c', 'c.College_ID = p.college_id', 'left');
        $builder->join('user_accounts ua', 'ua.id = p.verified_by', 'left');
        $builder->join('staffs st', 'st.Staff_ID = p.verified_by', 'left');

        // Date Range: From
        if (!empty($filters['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
            $builder->where('DATE(p.created_at) >=', $filters['date_from']);
        }

        // Date Range: To
        if (!empty($filters['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
            $builder->where('DATE(p.created_at) <=', $filters['date_to']);
        }

        // Campus Filter
        if (!empty($filters['campus_id']) && $filters['campus_id'] !== 'all' && is_numeric($filters['campus_id'])) {
            $builder->where('p.campus_id', (int) $filters['campus_id']);
        }

        // College Filter
        if (!empty($filters['college_id']) && $filters['college_id'] !== 'all' && is_numeric($filters['college_id'])) {
            $builder->where('p.college_id', (int) $filters['college_id']);
        }

        // Payment Purpose ID Filter
        if (!empty($filters['payment_purpose_id']) && $filters['payment_purpose_id'] !== 'all') {
            $builder->where('p.payment_purpose_id', (int) $filters['payment_purpose_id']);
        }

        // Payment Type Filter
        if (!empty($filters['payment_type']) && $filters['payment_type'] !== 'all') {
            if (is_numeric($filters['payment_type'])) {
                $builder->where('p.payment_purpose_id', (int) $filters['payment_type']);
            } else {
                $builder->where('p.payment_type', trim((string) $filters['payment_type']));
            }
        }

        // Payment Method Filter
        if (!empty($filters['payment_method']) && $filters['payment_method'] !== 'all') {
            $builder->where('p.payment_method', trim((string) $filters['payment_method']));
        }

        // Payment Status Filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $builder->where('p.status', trim((string) $filters['status']));
        }

        // Submitted / Recorded By Role Filter
        if (!empty($filters['submitted_by_role']) && $filters['submitted_by_role'] !== 'all') {
            $roleFilter = trim((string) $filters['submitted_by_role']);
            if ($roleFilter === 'head_admin') {
                $builder->groupStart()
                    ->where('p.submitted_by_role', 'head_admin')
                    ->orWhere('p.submitted_by_role', 'admin')
                    ->groupEnd();
            } else {
                $builder->where('p.submitted_by_role', $roleFilter);
            }
        }

        $builder->orderBy('p.created_at', 'DESC');

        return $builder->get()->getResultArray();
    }

    /**
     * Get aggregate statistics for payments
     */
    public function getPaymentStatistics(?int $campus_id = null): array
    {
        $builder = $this->db->table($this->table . ' p');
        $builder->select('p.amount, p.status, p.payment_type, p.payment_purpose_id, pp.code AS purpose_code');
        $builder->join('payment_purposes pp', 'pp.id = p.payment_purpose_id', 'left');

        if ($campus_id !== null) {
            $builder->where('p.campus_id', $campus_id);
        }

        $allPayments = $builder->get()->getResultArray();

        $totalRevenue = 0.00;
        $pendingRevenue = 0.00;
        $pendingCount = 0;
        $verifiedCount = 0;
        $rejectedCount = 0;
        $alumniIdCount = 0;
        $eventsCount = 0;
        $membershipsCount = 0;
        $servicesCount = 0;

        foreach ($allPayments as $payment) {
            $amt = (float) $payment['amount'];
            $st = $payment['status'];
            $type = !empty($payment['purpose_code']) ? $payment['purpose_code'] : $payment['payment_type'];

            if ($st === 'verified') {
                $totalRevenue += $amt;
                $verifiedCount++;
            } elseif ($st === 'pending') {
                $pendingRevenue += $amt;
                $pendingCount++;
            } elseif ($st === 'rejected') {
                $rejectedCount++;
            }

            if ($type === 'alumni_id') {
                $alumniIdCount++;
            } elseif ($type === 'event_fee') {
                $eventsCount++;
            } elseif ($type === 'membership') {
                $membershipsCount++;
            } else {
                $servicesCount++;
            }
        }

        return [
            'total_revenue' => $totalRevenue,
            'pending_revenue' => $pendingRevenue,
            'total_transactions' => count($allPayments),
            'verified_count' => $verifiedCount,
            'pending_count' => $pendingCount,
            'rejected_count' => $rejectedCount,
            'alumni_id_count' => $alumniIdCount,
            'events_count' => $eventsCount,
            'memberships_count' => $membershipsCount,
            'services_count' => $servicesCount,
        ];
    }

    /**
     * Generate unique transaction reference
     */
    public function generateTransactionRef(): string
    {
        $year = date('Y');
        $random = mt_rand(10000, 99999);
        $ref = "TXN-{$year}-{$random}";

        // Ensure uniqueness
        while ($this->where('transaction_ref', $ref)->countAllResults() > 0) {
            $random = mt_rand(10000, 99999);
            $ref = "TXN-{$year}-{$random}";
        }

        return $ref;
    }
}
