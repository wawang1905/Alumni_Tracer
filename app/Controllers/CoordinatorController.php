<?php

namespace App\Controllers;

use App\Models\AlumniModel;
use App\Models\DepartmentModel;
use CodeIgniter\HTTP\ResponseInterface;

class CoordinatorController extends BaseController
{
    private AlumniModel $alumniModel;
    private DepartmentModel $departmentModel;

    public function __construct()
    {
        $this->alumniModel     = new AlumniModel();
        $this->departmentModel = new DepartmentModel();
    }

    public function dashboard(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Coordinator/Dashboard.html');
    }

    public function alumni(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Coordinator/Alumni.html');
    }

    public function reports(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Coordinator/Reports.html');
    }

    public function alumniId(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Coordinator/AlumniID.html');
    }

    public function events(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Coordinator/Events.html');
    }

    public function payments(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Coordinator/Payments.html');
    }

    public function communication(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Coordinator/Communication.html');
    }

    public function surveyResponses(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Coordinator/SurveyResponses.html');
    }

    public function surveyResponseDetails(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Coordinator/SurveyResponseDetails.html');
    }

    // ── API: Get alumni scoped to the coordinator's department ──────────────
    public function getAlumni(): ResponseInterface
    {
        if (! $this->isCoordinatorAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $deptName = trim((string) (session('auth_user.department') ?? ''));

        $sort = (string) ($this->request->getGet('sort') ?? 'last_name_asc');
        $search = trim((string) ($this->request->getGet('search') ?? ''));

        [$column, $direction] = $this->mapSort($sort);

        $builder = $this->alumniModel->builder()
            ->select('alumni.id, alumni.alumni_id, alumni.FirstName, alumni.LastName, alumni.Email, alumni.GraduationYear, alumni.Status, alumni.Contact_Number, alumni.Province, alumni.Municipality, alumni.Barangay, c.College_Name AS College, d.Department_Name AS Course')
            ->join('colleges c', 'c.College_ID = alumni.College_ID', 'left')
            ->join('departments d', 'd.Department_ID = alumni.Department_ID', 'left');

        // Scope by the coordinator's department name
        if ($deptName !== '') {
            $builder->where('LOWER(d."Department_Name")', strtolower($deptName));
        }

        // Optional text search across name, email, alumni_id
        if ($search !== '') {
            $builder->groupStart()
                ->like('alumni.FirstName', $search)
                ->orLike('alumni.LastName', $search)
                ->orLike('alumni.Email', $search)
                ->orLike('alumni.alumni_id', $search)
                ->groupEnd();
        }

        $records = $builder->orderBy($column, $direction)->get()->getResultArray();

        return $this->response->setJSON([
            'success'    => true,
            'department' => $deptName,
            'total'      => count($records),
            'alumni'     => array_map([$this, 'formatAlumnus'], $records),
        ]);
    }

    // ── API: Get coordinator session info ───────────────────────────────────
    public function sessionInfo(): ResponseInterface
    {
        if (! $this->isCoordinatorAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $user = session('auth_user');
        return $this->response->setJSON([
            'success'    => true,
            'name'       => $user['name'] ?? '',
            'email'      => $user['email'] ?? '',
            'department' => $user['department'] ?? '',
        ]);
    }

    public function commonCss(): ResponseInterface
    {
        $css = file_get_contents(APPPATH . 'Views/HTML/Coordinator/CoordinatorCommon.css');

        return $this->response
            ->setHeader('Content-Type', 'text/css')
            ->setBody($css);
    }

    public function commonJs(): ResponseInterface
    {
        $js = file_get_contents(APPPATH . 'Views/HTML/Coordinator/CoordinatorCommon.js');

        return $this->response
            ->setHeader('Content-Type', 'application/javascript')
            ->setBody($js);
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function isCoordinatorAuthorized(): bool
    {
        if (! session()->has('auth_user')) {
            return false;
        }

        $role = strtolower((string) (session('auth_user.role') ?? ''));
        return in_array($role, ['coordinator', 'head_admin'], true);
    }

    private function formatAlumnus(array $row): array
    {
        return [
            'id'             => (int) ($row['id'] ?? 0),
            'alumni_id'      => (string) ($row['alumni_id'] ?? ''),
            'first_name'     => (string) ($row['FirstName'] ?? ''),
            'last_name'      => (string) ($row['LastName'] ?? ''),
            'full_name'      => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
            'email'          => (string) ($row['Email'] ?? ''),
            'graduation_year'=> (string) ($row['GraduationYear'] ?? ''),
            'status'         => (string) ($row['Status'] ?? ''),
            'contact'        => (string) ($row['Contact_Number'] ?? ''),
            'province'       => (string) ($row['Province'] ?? ''),
            'municipality'   => (string) ($row['Municipality'] ?? ''),
            'barangay'       => (string) ($row['Barangay'] ?? ''),
            'college'        => (string) ($row['College'] ?? ''),
            'course'         => (string) ($row['Course'] ?? ''),
        ];
    }

    private function mapSort(string $sort): array
    {
        return match ($sort) {
            'first_name_asc'  => ['FirstName', 'ASC'],
            'first_name_desc' => ['FirstName', 'DESC'],
            'year_desc'       => ['GraduationYear', 'DESC'],
            'year_asc'        => ['GraduationYear', 'ASC'],
            'status_asc'      => ['Status', 'ASC'],
            default           => ['LastName', 'ASC'],
        };
    }

    private function renderProtectedView(string $view): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return redirect()->to(base_url('/'));
        }

        $role = strtolower((string) (session('auth_user.role') ?? ''));
        if (! in_array($role, ['coordinator', 'head_admin'], true)) {
            helper('role');
            return redirect()->to(login_redirect_for_role($role));
        }

        return $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setBody(view($view));
    }
}
