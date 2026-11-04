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
        $data = [
            'totalAlumni' => $this->alumniModel->getTotalAlumni(),
            'activeAlumni' => $this->alumniModel->getActiveAlumni(),
            'upcomingEvents' => $this->eventModel->getUpcomingEventsCount()
        ];

        return $this->response->setJSON($data);

    } catch (\Throwable $e) {
        return $this->response->setJSON([
            'error' => $e->getMessage()
        ]);
    }
}
}