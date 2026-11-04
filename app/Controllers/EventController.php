<?php

namespace App\Controllers;

use App\Models\EventModel;
use CodeIgniter\HTTP\ResponseInterface;

class EventController extends BaseController
{
    private EventModel $eventModel;

    public function __construct()
    {
        $this->eventModel = new EventModel();
    }

    public function list(): ResponseInterface
    {
        $filter = $this->request->getGet('filter');
        $search = trim((string) $this->request->getGet('search'));

        if ($search !== '') {
            $events = $this->eventModel->searchEvents($search);
        } elseif ($filter === 'upcoming') {
            $events = $this->eventModel->getUpcomingEvents();
        } elseif ($filter === 'past') {
            $events = $this->eventModel->getPastEvents();
        } elseif ($filter === 'active') {
            $events = $this->eventModel->getEventsByStatus('active');
        } else {
            $events = $this->eventModel->getAllEventsOrdered();
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $events,
        ]);
    }

    public function get(int $id): ResponseInterface
    {
        $event = $this->eventModel->find($id);
        if (! $event) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Event not found.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $event,
        ]);
    }

    public function create(): ResponseInterface
    {
        $payload = [
            'name' => trim((string) $this->request->getPost('name')),
            'date' => (string) $this->request->getPost('date'),
            'time' => (string) $this->request->getPost('time'),
            'location' => trim((string) $this->request->getPost('location')),
            'status' => trim((string) ($this->request->getPost('status') ?: 'Active')),
        ];

        $rules = [
            'name' => 'required|min_length[3]|max_length[200]',
            'date' => 'required',
            'time' => 'required',
            'location' => 'required|min_length[3]|max_length[200]',
            'status' => 'permit_empty|max_length[200]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $this->validator?->getErrors() ?? [],
            ]);
        }

        $insertData = [
            'Name' => $payload['name'],
            'Date' => $payload['date'],
            'time' => $payload['time'],
            'Location' => $payload['location'],
            'Status' => $payload['status'] !== '' ? $payload['status'] : 'Active',
        ];

        $this->eventModel->insert($insertData);
        $newId = (int) $this->eventModel->getInsertID();

        return $this->response->setStatusCode(201)->setJSON([
            'success' => true,
            'message' => 'Event created successfully.',
            'data' => [
                'id' => $newId,
                'name' => $insertData['Name'],
                'date' => $insertData['Date'],
                'time' => $insertData['time'],
                'location' => $insertData['Location'],
                'status' => $insertData['Status'],
                'datetime' => $insertData['Date'] . 'T' . $insertData['time'],
            ],
        ]);
    }

    public function update(int $id): ResponseInterface
    {
        $existing = $this->eventModel->find($id);
        if (! $existing) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Event not found.',
            ]);
        }

        $raw = $this->request->getRawInput();
        $payload = [
            'name' => trim((string) ($raw['name'] ?? $existing['Name'] ?? '')),
            'date' => (string) ($raw['date'] ?? $existing['Date'] ?? ''),
            'time' => (string) ($raw['time'] ?? $existing['time'] ?? ''),
            'location' => trim((string) ($raw['location'] ?? $existing['Location'] ?? '')),
            'status' => trim((string) ($raw['status'] ?? $existing['Status'] ?? 'Active')),
        ];

        $rules = [
            'name' => 'required|min_length[3]|max_length[200]',
            'date' => 'required',
            'time' => 'required',
            'location' => 'required|min_length[3]|max_length[200]',
            'status' => 'permit_empty|max_length[50]',
        ];

        if (! $this->validateData($payload, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $this->validator?->getErrors() ?? [],
            ]);
        }

        $updateData = [
            'Name' => $payload['name'],
            'Date' => $payload['date'],
            'time' => $payload['time'],
            'Location' => $payload['location'],
            'Status' => $payload['status'] !== '' ? $payload['status'] : 'Active',
        ];

        $this->eventModel->update($id, $updateData);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Event updated successfully.',
            'data' => [
                'id' => $id,
                'name' => $updateData['Name'],
                'date' => $updateData['Date'],
                'time' => $updateData['time'],
                'location' => $updateData['Location'],
                'status' => $updateData['Status'],
                'datetime' => $updateData['Date'] . 'T' . $updateData['time'],
            ],
        ]);
    }

    public function delete(int $id): ResponseInterface
    {
        if (! $this->eventModel->find($id)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Event not found.',
            ]);
        }

        $this->eventModel->delete($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Event deleted successfully.',
        ]);
    }

    public function getUpcoming(): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => true,
            'data' => $this->eventModel->getUpcomingEvents(),
        ]);
    }

    public function getPast(): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => true,
            'data' => $this->eventModel->getPastEvents(),
        ]);
    }

    public function getStatistics(): ResponseInterface
    {
        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'total' => $this->eventModel->getTotalEventsCount(),
                'upcoming' => $this->eventModel->getUpcomingEventsCount(),
                'past' => $this->eventModel->getPastEventsCount(),
            ],
        ]);
    }
}
