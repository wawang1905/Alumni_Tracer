<?php

namespace App\Models;

use CodeIgniter\Model;

class EventModel extends Model
{
    protected $table = 'events';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['Name', 'Date', 'time', 'Location', 'Status'];

    private function baseSelect()
    {
        return $this->select([
            'id',
            'Name AS name',
            'Date AS date',
            'time AS time',
            'Location AS location',
            'Status AS status',
            'CONCAT("Date", \'T\', time) AS datetime',
        ]);
    }

    private function applyUpcomingFilter($builder)
    {
        $today = date('Y-m-d');
        $nowTime = date('H:i:s');

        return $builder->groupStart()
            ->where('Date >', $today)
            ->orGroupStart()
                ->where('Date', $today)
                ->where('time >=', $nowTime)
            ->groupEnd()
        ->groupEnd();
    }

    private function applyPastFilter($builder)
    {
        $today = date('Y-m-d');
        $nowTime = date('H:i:s');

        return $builder->groupStart()
            ->where('Date <', $today)
            ->orGroupStart()
                ->where('Date', $today)
                ->where('time <', $nowTime)
            ->groupEnd()
        ->groupEnd();
    }

    // Get upcoming events count
    public function getUpcomingEventsCount()
    {
        return $this->applyUpcomingFilter($this)->countAllResults();
    }

    // Get all events ordered by date (new method)
    public function getAllEventsOrdered()
    {
        return $this->baseSelect()
                    ->orderBy('Date', 'ASC')
                    ->orderBy('time', 'ASC')
                    ->findAll();
    }

    // Get upcoming events (new method)
    public function getUpcomingEvents($limit = null)
    {
        $query = $this->applyUpcomingFilter($this->baseSelect())
                      ->orderBy('Date', 'ASC')
                      ->orderBy('time', 'ASC');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->findAll();
    }

    // Get past events (new method)
    public function getPastEvents($limit = null)
    {
        $query = $this->applyPastFilter($this->baseSelect())
                      ->orderBy('Date', 'DESC')
                      ->orderBy('time', 'DESC');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->findAll();
    }

    // Get past events count (new method)
    public function getPastEventsCount()
    {
        return $this->applyPastFilter($this)->countAllResults();
    }

    // Get events by status (new method)
    public function getEventsByStatus($status)
    {
        return $this->baseSelect()
                    ->where('Status', $status)
                    ->orderBy('Date', 'ASC')
                    ->orderBy('time', 'ASC')
                    ->findAll();
    }

    // Search events by name or location (new method)
    public function searchEvents($keyword)
    {
        return $this->baseSelect()
                    ->groupStart()
                    ->like('Name', $keyword)
                    ->orLike('Location', $keyword)
                    ->groupEnd()
                    ->orderBy('Date', 'ASC')
                    ->orderBy('time', 'ASC')
                    ->findAll();
    }

    // Get upcoming events for dashboard (new method)
    public function getUpcomingEventsForDashboard($limit = 5)
    {
        return $this->applyUpcomingFilter($this->baseSelect())
                    ->orderBy('Date', 'ASC')
                    ->orderBy('time', 'ASC')
                    ->limit($limit)
                    ->findAll();
    }

    // Get total events count (new method)
    public function getTotalEventsCount()
    {
        return $this->countAllResults();
    }
}
