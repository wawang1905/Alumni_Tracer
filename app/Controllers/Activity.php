<?php

namespace App\Controllers;

use App\Models\Activity_Model;
use CodeIgniter\HTTP\ResponseInterface;
use DateTime;

class Activity extends BaseController
{
    private Activity_Model $activityModel;

    public function __construct()
    {
        $this->activityModel = new Activity_Model();
    }

    public function getRecent(): ResponseInterface
    {
        $user = $this->requireAuthUser();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $limit = (int) ($this->request->getGet('limit') ?? 10);
        $limit = max(1, min($limit, 50));

        $filters = [
            'category'  => $this->request->getGet('category'),
            'user_type' => $this->request->getGet('user_type'),
            'search'    => $this->request->getGet('search'),
        ];

        $activities = $this->activityModel->getRecentActivities($limit, 0, $filters);
        $activities = $this->scopeActivitiesForCurrentUser($activities);

        return $this->response->setJSON([
            'success'    => true,
            'activities' => array_map(fn (array $activity): array => $this->formatActivity($activity), $activities),
        ]);
    }

    public function getStats(): ResponseInterface
    {
        $user = $this->requireAuthUser();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'stats'   => $this->activityModel->getActivityStats(),
        ]);
    }

    public function getActivities(): ResponseInterface
    {
        $user = $this->requireAuthUser();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $limit = max(1, min((int) ($this->request->getGet('limit') ?? 20), 100));
        $offset = ($page - 1) * $limit;

        $filters = [
            'category'  => $this->request->getGet('category'),
            'user_type' => $this->request->getGet('user_type'),
            'date_from' => $this->request->getGet('date_from'),
            'date_to'   => $this->request->getGet('date_to'),
            'search'    => $this->request->getGet('search'),
        ];

        $activities = $this->activityModel->getRecentActivities($limit, $offset, $filters);
        $activities = $this->scopeActivitiesForCurrentUser($activities);
        $total = $this->activityModel->getTotalCount($filters);

        return $this->response->setJSON([
            'success'     => true,
            'activities'  => array_map(fn (array $activity): array => $this->formatActivity($activity), $activities),
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int) ceil($total / $limit),
        ]);
    }

    /**
     * Restrict activity log visibility for Head Admins (assigned campus users only)
     * and Coordinators (own department / own account only). Developers remain unrestricted.
     *
     * @param array<int, array<string, mixed>> $activities
     * @return array<int, array<string, mixed>>
     */
    private function scopeActivitiesForCurrentUser(array $activities): array
    {
        $scope = (new \App\Services\AssignmentScopeService())->resolveCurrentUserScope();
        if ($scope === null) {
            return $activities;
        }
        if (($scope['valid'] ?? false) !== true) {
            return [];
        }

        $currentEmail = strtolower(trim((string) (session('auth_user.email') ?? '')));
        $campusId = (int) ($scope['campus_id'] ?? 0);
        $allowedEmails = [];

        $db = \Config\Database::connect();
        if (($scope['role'] ?? '') === 'head_admin' && $campusId > 0) {
            $userRows = $db->table('user_accounts')
                ->select('Email')
                ->where('Campus', $campusId)
                ->get()
                ->getResultArray();
            foreach ($userRows as $row) {
                $em = strtolower(trim((string) ($row['Email'] ?? '')));
                if ($em !== '') {
                    $allowedEmails[$em] = true;
                }
            }
            $alumniRows = $db->table('alumni a')
                ->select('a."Email"')
                ->join('colleges c', 'c."College_ID" = a."College_ID"', 'inner')
                ->where('c."Campus_ID"', $campusId)
                ->get()
                ->getResultArray();
            foreach ($alumniRows as $row) {
                $em = strtolower(trim((string) ($row['Email'] ?? '')));
                if ($em !== '') {
                    $allowedEmails[$em] = true;
                }
            }
        } elseif (($scope['role'] ?? '') === 'coordinator') {
            $collegeId = (int) ($scope['college_id'] ?? 0);
            $alumniRows = $db->table('alumni')
                ->select('"Email"')
                ->where('"College_ID"', $collegeId)
                ->get()
                ->getResultArray();
            foreach ($alumniRows as $row) {
                $em = strtolower(trim((string) ($row['Email'] ?? '')));
                if ($em !== '') {
                    $allowedEmails[$em] = true;
                }
            }
        }

        if ($currentEmail !== '') {
            $allowedEmails[$currentEmail] = true;
        }

        return array_values(array_filter($activities, static function (array $act) use ($allowedEmails): bool {
            $details = is_string($act['details'] ?? null) ? (json_decode($act['details'], true) ?: []) : (is_array($act['details'] ?? null) ? $act['details'] : []);
            $actorEmail = strtolower(trim((string) ($details['user_email'] ?? $details['email'] ?? '')));
            if ($actorEmail === '') {
                return false;
            }
            return isset($allowedEmails[$actorEmail]);
        }));
    }

    public function log(): ResponseInterface
    {
        $user = $this->requireAuthUser();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $action = trim((string) $this->request->getPost('action'));
        $category = trim((string) ($this->request->getPost('category') ?? 'general'));
        $userType = trim((string) ($this->request->getPost('user_type') ?? 'staff'));
        $detailsRaw = $this->request->getPost('details');
        $details = null;

        if ($detailsRaw !== null && $detailsRaw !== '') {
            $decoded = json_decode((string) $detailsRaw, true);
            $details = is_array($decoded) ? $decoded : ['value' => (string) $detailsRaw];
        }

        if ($action === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Activity action is required.',
            ]);
        }

        $logged = $this->activityModel->logActivity(
            $user,
            $action,
            $category,
            $details,
            $userType,
            (string) $this->request->getIPAddress(),
            (string) $this->request->getUserAgent()
        );

        return $this->response->setJSON([
            'success' => $logged,
        ]);
    }

    public function delete(): ResponseInterface
    {
        $user = $this->requireAuthUser();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $id = (int) ($this->request->getPost('id') ?? 0);

        return $this->response->setJSON([
            'success' => $id > 0 ? $this->activityModel->deleteActivity($id) : false,
        ]);
    }

    public function export(): ResponseInterface
    {
        $user = $this->requireAuthUser();
        if ($user === null) {
            return redirect()->to(base_url('/'));
        }

        $filters = [
            'date_from' => $this->request->getGet('date_from'),
            'date_to'   => $this->request->getGet('date_to'),
        ];

        $rawActivities = $this->scopeActivitiesForCurrentUser($this->activityModel->exportToCsv($filters));
        $activities = array_map(
            fn (array $activity): array => $this->formatActivity($activity),
            $rawActivities
        );

        $filename = 'activities_' . date('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return $this->response->setStatusCode(500)->setBody('Unable to export activities.');
        }

        fputcsv($handle, ['ID', 'Action', 'Category', 'Description', 'User', 'Email', 'IP Address', 'Date']);

        foreach ($activities as $activity) {
            fputcsv($handle, [
                $activity['id'],
                $activity['action'],
                $activity['category'],
                $activity['description'],
                $activity['user_name'],
                $activity['user_email'],
                $activity['ip_address'],
                $activity['created_at'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $this->response
            ->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }

    private function requireAuthUser(): ?array
    {
        $user = session('auth_user');
        return is_array($user) ? $user : null;
    }

    private function formatActivity(array $activity): array
    {
        $details = [];
        if (! empty($activity['details'])) {
            $decoded = json_decode((string) $activity['details'], true);
            $details = is_array($decoded) ? $decoded : [];
        }

        $firstName = trim((string) ($details['user_first_name'] ?? ''));
        $lastName = trim((string) ($details['user_last_name'] ?? ''));
        $userName = trim($firstName . ' ' . $lastName) ?: ((string) ($details['user_name'] ?? 'System'));
        $createdAt = (string) ($activity['created_at'] ?? '');
        $activityDate = (string) ($details['activity_date'] ?? '');
        $activityTime = (string) ($details['activity_time'] ?? '');

        if (($activityDate === '' || $activityTime === '') && $createdAt !== '') {
            $timestamp = strtotime($createdAt);
            if ($timestamp !== false) {
                $activityDate = $activityDate !== '' ? $activityDate : date('Y-m-d', $timestamp);
                $activityTime = $activityTime !== '' ? $activityTime : date('H:i:s', $timestamp);
            }
        }

        return [
            'id'            => (int) ($activity['id'] ?? 0),
            'action'        => (string) ($activity['action'] ?? ''),
            'category'      => (string) ($activity['category'] ?? ''),
            'description'   => (string) ($activity['description'] ?? ''),
            'created_at'    => $createdAt,
            'activity_date' => $activityDate,
            'activity_time' => $activityTime,
            'readable_time' => $this->timeElapsedString($createdAt),
            'user_name'     => $userName,
            'user_email'    => (string) ($details['user_email'] ?? ''),
            'details'       => $details,
            'ip_address'    => (string) ($activity['ip_address'] ?? ''),
            'user_type'     => (string) ($activity['user_type'] ?? 'staff'),
        ];
    }

    private function timeElapsedString(string $datetime): string
    {
        if ($datetime === '') {
            return 'just now';
        }

        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = (int) floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = [
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];

        foreach ($string as $key => &$value) {
            if ($diff->$key) {
                $value = $diff->$key . ' ' . $value . ($diff->$key > 1 ? 's' : '');
            } else {
                unset($string[$key]);
            }
        }

        $string = array_slice($string, 0, 1);

        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}
