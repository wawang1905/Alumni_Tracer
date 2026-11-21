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
            COALESCE(NULLIF(p.student_number, \'\'), a.alumni_id, NULLIF(ua_payer.account_id, \'\')) AS resolved_student_number,
            pp.name AS payment_purpose_name,
            pp.code AS payment_purpose_code,
            COALESCE(c."College_Name", ac."College_Name", NULLIF(ua_payer."College", \'\')) AS college_name,
            c.Abbreviation AS college_abbr,
            COALESCE(ad."Department_Name", NULLIF(ua_payer."Department", \'\')) AS department_name,
            cmp.Campus AS campus_name,
            ua.First_Name AS verifier_first_name,
            ua.Last_Name AS verifier_last_name
        ');
        $builder->join('payment_purposes pp', 'pp.id = p.payment_purpose_id', 'left');
        $builder->join('colleges c', 'c.College_ID = p.college_id', 'left');
        $builder->join('alumni a', 'a.id = p.alumni_id', 'left');
        $builder->join('colleges ac', 'ac.College_ID = a.College_ID', 'left');
        $builder->join('departments ad', 'ad.Department_ID = a.Department_ID', 'left');
        $builder->join('user_accounts ua_payer', 'LOWER(ua_payer."Email") = LOWER(p.payer_email)', 'left');
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

        // Strict Department filter (used to isolate coordinator records to their assigned department)
        $deptIdFilter = !empty($filters['department_id']) ? (int) $filters['department_id'] : 0;
        $deptNameFilter = trim((string) ($filters['department_name'] ?? ''));
        $coordUserIdFilter = !empty($filters['coordinator_user_id']) ? (int) $filters['coordinator_user_id'] : 0;
        if ($deptIdFilter > 0 || $deptNameFilter !== '') {
            $builder->groupStart();
            if ($deptIdFilter > 0) {
                $builder->where('a."Department_ID"', $deptIdFilter);
            }
            if ($deptNameFilter !== '') {
                $builder->orWhere('LOWER(ad."Department_Name")', strtolower($deptNameFilter));
                $builder->orWhere('LOWER(ua_payer."Department")', strtolower($deptNameFilter));
            }
            if ($coordUserIdFilter > 0) {
                $builder->orGroupStart()
                    ->where('p.alumni_id IS NULL', null, false)
                    ->where('p.coordinator_id', $coordUserIdFilter)
                    ->groupEnd();
            }
            $builder->groupEnd();
        }

        // Payment method filter
        if (!empty($filters['payment_method']) && $filters['payment_method'] !== 'all') {
            $builder->where('p.payment_method', $filters['payment_method']);
        }

        // Search query
        if (!empty($filters['search'])) {
            $search = mb_substr(trim((string) $filters['search']), 0, 100);
            if ($search !== '') {
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
        }

        // Date filters (strictly validated YYYY-MM-DD)
        $dateFrom = $this->normalizeIsoDate($filters['date_from'] ?? null);
        if ($dateFrom !== null) {
            $builder->where('DATE(p.created_at) >=', $dateFrom);
        }
        $dateTo = $this->normalizeIsoDate($filters['date_to'] ?? null);
        if ($dateTo !== null) {
            $builder->where('DATE(p.created_at) <=', $dateTo);
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
            COALESCE(NULLIF(p.student_number, \'\'), a.alumni_id, NULLIF(ua_payer.account_id, \'\')) AS student_number,
            p.alumni_id,
            cmp.Campus AS campus_name,
            COALESCE(c."College_Name", ac."College_Name", NULLIF(ua_payer."College", \'\')) AS college_name,
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

        // Joins with payment_purposes, campuses, colleges, alumni, user_accounts, and staffs
        $builder->join('payment_purposes pp', 'pp.id = p.payment_purpose_id', 'left');
        $builder->join('campuses cmp', 'cmp.Campus_ID = p.campus_id', 'left');
        $builder->join('colleges c', 'c.College_ID = p.college_id', 'left');
        $builder->join('alumni a', 'a.id = p.alumni_id', 'left');
        $builder->join('colleges ac', 'ac.College_ID = a.College_ID', 'left');
        $builder->join('user_accounts ua_payer', 'LOWER(ua_payer."Email") = LOWER(p.payer_email)', 'left');
        $builder->join('user_accounts ua', 'ua.id = p.verified_by', 'left');
        $builder->join('staffs st', 'st.Staff_ID = p.verified_by', 'left');

        // Date Range: From
        $dateFrom = $this->normalizeIsoDate($filters['date_from'] ?? null);
        if ($dateFrom !== null) {
            $builder->where('DATE(p.created_at) >=', $dateFrom);
        }

        // Date Range: To
        $dateTo = $this->normalizeIsoDate($filters['date_to'] ?? null);
        if ($dateTo !== null) {
            $builder->where('DATE(p.created_at) <=', $dateTo);
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
    public function getPaymentStatistics(?int $campus_id = null, array $departmentScope = []): array
    {
        $builder = $this->db->table($this->table . ' p');
        $builder->select('p.amount, p.status, p.payment_type, p.payment_purpose_id, pp.code AS purpose_code');
        $builder->join('payment_purposes pp', 'pp.id = p.payment_purpose_id', 'left');

        if ($campus_id !== null) {
            $builder->where('p.campus_id', $campus_id);
        }

        $deptIdFilter = (int) ($departmentScope['department_id'] ?? 0);
        $deptNameFilter = trim((string) ($departmentScope['department_name'] ?? ''));
        $coordUserIdFilter = (int) ($departmentScope['coordinator_user_id'] ?? 0);
        if ($deptIdFilter > 0 || $deptNameFilter !== '') {
            $builder->join('alumni a', 'a.id = p.alumni_id', 'left');
            $builder->join('departments ad', 'ad.Department_ID = a.Department_ID', 'left');
            $builder->join('user_accounts ua_payer', 'LOWER(ua_payer."Email") = LOWER(p.payer_email)', 'left');
            $builder->groupStart();
            if ($deptIdFilter > 0) {
                $builder->where('a."Department_ID"', $deptIdFilter);
            }
            if ($deptNameFilter !== '') {
                $builder->orWhere('LOWER(ad."Department_Name")', strtolower($deptNameFilter));
                $builder->orWhere('LOWER(ua_payer."Department")', strtolower($deptNameFilter));
            }
            if ($coordUserIdFilter > 0) {
                $builder->orGroupStart()
                    ->where('p.alumni_id IS NULL', null, false)
                    ->where('p.coordinator_id', $coordUserIdFilter)
                    ->groupEnd();
            }
            $builder->groupEnd();
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

    private function normalizeIsoDate(mixed $input): ?string
    {
        $raw = trim((string) ($input ?? ''));
        if ($raw === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        return ($dt !== false && $dt->format('Y-m-d') === $raw) ? $raw : null;
    }
}
