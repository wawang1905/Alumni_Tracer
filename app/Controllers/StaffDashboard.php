<?php

namespace App\Controllers;

use App\Models\Alumni_Model;
use App\Models\EventModel;

class StaffDashboard extends BaseController
{
    protected $alumniModel;
    protected $eventModel;

    public function __construct()
    {
        $this->alumniModel = new Alumni_Model();
        $this->eventModel = new EventModel();
    }

    public function index()
    {
        return view('Dashboard');
    }

    // API endpoint to get dashboard data
    public function getDashboardData()
    {
        try {
            $scopeService = new \App\Services\AssignmentScopeService();
            $scope = $scopeService->resolveCurrentUserScope();

            if ($scope !== null && ($scope['valid'] ?? false) !== true) {
                return $this->response->setStatusCode(403)->setJSON([
                    'success' => false,
                    'message' => (string) ($scope['message'] ?? 'Forbidden.'),
                ]);
            }

            if ($scope !== null) {
                $db = \Config\Database::connect();
                $totalBuilder = $db->table('alumni')
                    ->join('colleges c', 'c."College_ID" = alumni."College_ID"', 'left')
                    ->join('departments d', 'd."Department_ID" = alumni."Department_ID"', 'left');
                $scopeService->applyAlumniScope($totalBuilder, $scope, 'alumni', 'c', 'd');
                $totalAlumni = $totalBuilder->countAllResults();

                $activeBuilder = $db->table('alumni')
                    ->join('colleges c', 'c."College_ID" = alumni."College_ID"', 'left')
                    ->join('departments d', 'd."Department_ID" = alumni."Department_ID"', 'left')
                    ->where('LOWER(alumni."Status"::text)', 'active');
                $scopeService->applyAlumniScope($activeBuilder, $scope, 'alumni', 'c', 'd');
                $activeAlumni = $activeBuilder->countAllResults();
            } else {
                $totalAlumni = $this->alumniModel->getTotalAlumni();
                $activeAlumni = $this->alumniModel->getActiveAlumni();
            }

            $data = [
                'totalAlumni' => $totalAlumni,
                'activeAlumni' => $activeAlumni,
                'upcomingEvents' => $this->eventModel->getUpcomingEventsCount()
            ];

            return $this->response->setJSON($data);

        } catch (\Throwable $e) {
            log_message('error', 'StaffDashboard::getDashboardData failed: {msg}', ['msg' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Unable to load dashboard data.'
            ]);
        }
    }
}