<?php

namespace App\Models;

use CodeIgniter\Model;

class Activity_Model extends Model
{
    protected $DBGroup = 'default';
    protected $table = 'activities';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'user_id',
        'user_type',
        'action',
        'category',
        'description',
        'details',
        'ip_address',
        'user_agent',
    ];

    public function logActivity(?array $user, string $action, string $category = 'general', ?array $details = null, string $userType = 'staff', string $ipAddress = '', string $userAgent = ''): bool
    {
        $details = $details ?? [];
        $activityDateTime = date('Y-m-d H:i:s');
        $details['activity_date'] = (string) ($details['activity_date'] ?? date('Y-m-d'));
        $details['activity_time'] = (string) ($details['activity_time'] ?? date('H:i:s'));
        $details['activity_datetime'] = (string) ($details['activity_datetime'] ?? $activityDateTime);
        $details['user_name'] = trim((string) (($user['name'] ?? '') ?: (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))));
        $details['user_first_name'] = (string) ($user['first_name'] ?? '');
        $details['user_last_name'] = (string) ($user['last_name'] ?? '');
        $details['user_email'] = (string) ($user['email'] ?? '');
        $details['user_role'] = (string) ($user['role'] ?? '');

        $data = [
            'user_id'     => 0,
            'user_type'   => $userType,
            'action'      => $action,
            'category'    => $category,
            'description' => $this->generateDescription($action, $details),
            'details'     => json_encode($details),
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent,
        ];

        return $this->insert($data) !== false;
    }

    public function getRecentActivities(int $limit = 10, int $offset = 0, array $filters = []): array
    {
        $builder = $this->builder();
        $builder->select('*');
        $this->applyFilters($builder, $filters);
        $builder->orderBy('created_at', 'DESC');
        $builder->limit($limit, $offset);

        return $builder->get()->getResultArray();
    }

    public function getTotalCount(array $filters = []): int
    {
        $builder = $this->builder();
        $this->applyFilters($builder, $filters);

        return $builder->countAllResults();
    }

    public function getActivitiesByDateRange(string $startDate, string $endDate): array
    {
        return $this->builder()
            ->where('DATE(created_at) >=', $startDate)
            ->where('DATE(created_at) <=', $endDate)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function getActivityStats(): array
    {
        $stats = [];

        $stats['total'] = $this->builder()->countAllResults();

        $stats['by_category'] = $this->builder()
            ->select('category, COUNT(*) as count')
            ->groupBy('category')
            ->get()
            ->getResultArray();

        $date7DaysAgo = date('Y-m-d', strtotime('-7 days'));
        $stats['last_7_days'] = $this->builder()
            ->where('DATE(created_at) >=', $date7DaysAgo)
            ->countAllResults();

        $today = date('Y-m-d');
        $stats['today'] = $this->builder()
            ->where('DATE(created_at)', $today)
            ->countAllResults();

        $stats['by_user_type'] = $this->builder()
            ->select('user_type, COUNT(*) as count')
            ->groupBy('user_type')
            ->get()
            ->getResultArray();

        return $stats;
    }

    public function deleteOldActivities(int $days = 90): bool
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return $this->builder()->where('created_at <', $cutoffDate)->delete();
    }

    public function getActivity(int $id): ?array
    {
        $activity = $this->find($id);
        return is_array($activity) ? $activity : null;
    }

    public function deleteActivity(int $id): bool
    {
        return $this->delete($id);
    }

    public function exportToCsv(array $filters = []): array
    {
        $builder = $this->builder();
        $builder->select('*');

        if (! empty($filters['date_from'])) {
            $builder->where('DATE(created_at) >=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $builder->where('DATE(created_at) <=', $filters['date_to']);
        }

        return $builder
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function generateDescription(string $action, array $details): string
    {
        if (! empty($details['name'])) {
            return $action . ': ' . $details['name'];
        }

        if (! empty($details['email'])) {
            return $action . ': ' . $details['email'];
        }

        return $action;
    }

    private function applyFilters($builder, array $filters): void
    {
        if (! empty($filters['category']) && $filters['category'] !== 'all') {
            $builder->where('category', $filters['category']);
        }

        if (! empty($filters['user_type']) && $filters['user_type'] !== 'all') {
            $builder->where('user_type', $filters['user_type']);
        }

        if (! empty($filters['date_from'])) {
            $builder->where('DATE(created_at) >=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $builder->where('DATE(created_at) <=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $builder->groupStart()
                ->like('action', $filters['search'])
                ->orLike('description', $filters['search'])
                ->orLike('details', $filters['search'])
                ->groupEnd();
        }
    }
}
