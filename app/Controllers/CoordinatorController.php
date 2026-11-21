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

    // ── API: Get alumni scoped strictly to the coordinator's assigned department ──
    public function getAlumni(): ResponseInterface
    {
        if (! $this->isCoordinatorAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $ctx = $this->resolveCoordinatorContext();
        $deptName = $ctx['department'];
        $deptId = $ctx['department_id'];

        // A coordinator without an assigned department must not receive records from other departments
        if ($ctx['role'] === 'coordinator' && $deptName === '' && $deptId <= 0) {
            return $this->response->setJSON([
                'success'    => true,
                'department' => '',
                'total'      => 0,
                'alumni'     => [],
            ]);
        }

        $sort = (string) ($this->request->getGet('sort') ?? 'last_name_asc');
        $search = mb_substr(trim((string) ($this->request->getGet('search') ?? '')), 0, 100);

        [$column, $direction] = $this->mapSort($sort);

        $builder = $this->alumniModel->builder()
            ->select('alumni.id, alumni.alumni_id, alumni."FirstName", alumni."LastName", alumni."Email", alumni."GraduationYear", alumni."Status", alumni."Contact_Number", alumni."Province", alumni."Municipality", alumni."Barangay", c."College_Name" AS "College", d."Department_Name" AS "Course"')
            ->join('colleges c', 'c."College_ID" = alumni."College_ID"', 'left')
            ->join('departments d', 'd."Department_ID" = alumni."Department_ID"', 'left');

        // Scope strictly by the coordinator's assigned department (ignore any client-sent overrides)
        if ($deptId > 0 && $deptName !== '') {
            $builder->groupStart()
                ->where('alumni."Department_ID"', $deptId)
                ->orWhere('LOWER(d."Department_Name")', strtolower($deptName))
                ->groupEnd();
        } elseif ($deptId > 0) {
            $builder->where('alumni."Department_ID"', $deptId);
        } elseif ($deptName !== '') {
            $builder->where('LOWER(d."Department_Name")', strtolower($deptName));
        }

        // Optional text search across name, email, alumni_id
        if ($search !== '') {
            $builder->groupStart()
                ->like('alumni."FirstName"', $search)
                ->orLike('alumni."LastName"', $search)
                ->orLike('alumni."Email"', $search)
                ->orLike('alumni.alumni_id', $search)
                ->groupEnd();
        }

        $records = $builder->orderBy($column, $direction)->limit(2000)->get()->getResultArray();

        return $this->response->setJSON([
            'success'       => true,
            'department'    => $deptName,
            'department_id' => $deptId,
            'college'       => $ctx['college'],
            'total'         => count($records),
            'alumni'        => array_map([$this, 'formatAlumnus'], $records),
        ]);
    }

    // ── API: Get dashboard analytics strictly scoped to the coordinator's department ──
    public function dashboardData(): ResponseInterface
    {
        if (! $this->isCoordinatorAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $ctx = $this->resolveCoordinatorContext();
        $deptName = $ctx['department'];
        $deptId = $ctx['department_id'];
        $batchFilter = trim((string) ($this->request->getGet('batch') ?? 'all'));

        $db = \Config\Database::connect();

        // Strictly require an assigned department for coordinator accounts
        if ($ctx['role'] === 'coordinator' && $deptName === '' && $deptId <= 0) {
            return $this->response->setJSON([
                'success'          => true,
                'department'       => '',
                'college'          => $ctx['college'],
                'batches'          => [],
                'totalGraduates'   => 0,
                'tracedCount'      => 0,
                'tracedRate'       => 0,
                'employedCount'    => 0,
                'unemployedCount'  => 0,
                'notTrackedCount'  => 0,
                'employmentRate'   => 0,
                'yearCohorts'      => ['labels' => [], 'employed' => [], 'unemployed' => [], 'notTracked' => []],
                'statusBreakdown'  => ['counts' => [0, 0, 0], 'pcts' => ['0%', '0%', '0%']],
                'surveySummary'    => ['totalResponses' => 0, 'reviewedCount' => 0, 'pendingCount' => 0, 'needsRevisionCount' => 0, 'responseRate' => 0],
                'paymentSummary'   => ['totalTransactions' => 0, 'pendingCount' => 0, 'verifiedCount' => 0],
            ]);
        }

        $alumniBuilder = $db->table('alumni a')
            ->select('a.id, a.alumni_id, a."Email", a."GraduationYear", a."Status", a."Department_ID", d."Department_Name"')
            ->join('departments d', 'd."Department_ID" = a."Department_ID"', 'left');

        if ($deptId > 0 && $deptName !== '') {
            $alumniBuilder->groupStart()
                ->where('a."Department_ID"', $deptId)
                ->orWhere('LOWER(d."Department_Name")', strtolower($deptName))
                ->groupEnd();
        } elseif ($deptId > 0) {
            $alumniBuilder->where('a."Department_ID"', $deptId);
        } elseif ($deptName !== '') {
            $alumniBuilder->where('LOWER(d."Department_Name")', strtolower($deptName));
        }

        $allDeptAlumni = $alumniBuilder->get()->getResultArray();

        $batchSet = [];
        foreach ($allDeptAlumni as $row) {
            $yr = trim((string) ($row['GraduationYear'] ?? ''));
            if ($yr !== '') {
                $batchSet[$yr] = $yr;
            }
        }
        $batches = array_values($batchSet);
        rsort($batches);

        // Apply optional graduation batch filter (never department filter)
        $scopedAlumni = ($batchFilter !== '' && strtolower($batchFilter) !== 'all')
            ? array_values(array_filter($allDeptAlumni, static fn (array $r): bool => trim((string) ($r['GraduationYear'] ?? '')) === $batchFilter))
            : $allDeptAlumni;

        $emailToYear = [];
        $deptEmails = [];
        $deptAlumniIds = [];
        foreach ($scopedAlumni as $a) {
            $deptAlumniIds[] = (int) ($a['id'] ?? 0);
            $email = strtolower(trim((string) ($a['Email'] ?? '')));
            if ($email !== '') {
                $deptEmails[$email] = true;
                $emailToYear[$email] = trim((string) ($a['GraduationYear'] ?? 'Unlisted')) ?: 'Unlisted';
            }
        }

        // Map survey responses from alumni in this department
        $statusByEmail = [];
        $totalResponses = 0;
        $reviewedCount = 0;
        $pendingReviewCount = 0;
        $needsRevisionCount = 0;

        if (! empty($deptEmails)) {
            $respRows = $db->table('responses sr')
                ->select('sr.id, sr.user_email, sr.review_status, sr.submitted_at')
                ->orderBy('sr.submitted_at', 'DESC')
                ->get()
                ->getResultArray();

            $matchingResponseIds = [];
            foreach ($respRows as $rr) {
                $em = strtolower(trim((string) ($rr['user_email'] ?? '')));
                if ($em !== '' && isset($deptEmails[$em])) {
                    $totalResponses++;
                    $rev = strtolower(trim((string) ($rr['review_status'] ?? 'pending')));
                    if ($rev === 'reviewed') {
                        $reviewedCount++;
                    } elseif ($rev === 'needs_revision') {
                        $needsRevisionCount++;
                    } else {
                        $pendingReviewCount++;
                    }
                    if (! isset($statusByEmail[$em])) {
                        $statusByEmail[$em] = (int) $rr['id'];
                        $matchingResponseIds[] = (int) $rr['id'];
                    }
                }
            }

            $empByRespId = [];
            if (! empty($matchingResponseIds)) {
                $ansRows = $db->table('answers a')
                    ->select('a.response_id, a.answer_text, c.choice_text, q.question_text')
                    ->join('choices c', 'c.id = a.choice_id', 'left')
                    ->join('questions q', 'q.id = a.question_id', 'left')
                    ->whereIn('a.response_id', $matchingResponseIds)
                    ->get()
                    ->getResultArray();

                foreach ($ansRows as $ans) {
                    $qText = strtolower(trim((string) ($ans['question_text'] ?? '')));
                    $val = trim((string) ($ans['choice_text'] ?: $ans['answer_text'] ?: ''));
                    if (str_contains($qText, 'employ') || str_contains($qText, 'status') || str_contains($qText, 'job') || str_contains($qText, 'work')) {
                        if (stripos($val, 'unemployed') !== false) {
                            $empByRespId[(int) $ans['response_id']] = 'Unemployed';
                        } elseif (stripos($val, 'employed') !== false) {
                            $empByRespId[(int) $ans['response_id']] = 'Employed';
                        }
                    }
                }
            }

            foreach ($statusByEmail as $em => $rId) {
                $statusByEmail[$em] = $empByRespId[$rId] ?? 'Employed';
            }
        }

        $employedCount = 0;
        $unemployedCount = 0;
        $notTrackedCount = 0;
        $cohortBuckets = [];

        foreach ($scopedAlumni as $a) {
            $yr = trim((string) ($a['GraduationYear'] ?? '')) ?: 'Unlisted';
            if (! isset($cohortBuckets[$yr])) {
                $cohortBuckets[$yr] = ['employed' => 0, 'unemployed' => 0, 'notTracked' => 0];
            }

            $em = strtolower(trim((string) ($a['Email'] ?? '')));
            if ($em !== '' && isset($statusByEmail[$em])) {
                if ($statusByEmail[$em] === 'Unemployed') {
                    $unemployedCount++;
                    $cohortBuckets[$yr]['unemployed']++;
                } else {
                    $employedCount++;
                    $cohortBuckets[$yr]['employed']++;
                }
            } else {
                $notTrackedCount++;
                $cohortBuckets[$yr]['notTracked']++;
            }
        }

        ksort($cohortBuckets);
        $totalGraduates = count($scopedAlumni);
        $tracedCount = $employedCount + $unemployedCount;
        $tracedRate = $totalGraduates > 0 ? round(($tracedCount / $totalGraduates) * 100, 1) : 0.0;
        $employmentRate = $tracedCount > 0 ? round(($employedCount / $tracedCount) * 100, 1) : 0.0;
        $responseRate = $totalGraduates > 0 ? round(($tracedCount / $totalGraduates) * 100, 1) : 0.0;

        $pctEmployed = $totalGraduates > 0 ? round(($employedCount / $totalGraduates) * 100, 1) : 0.0;
        $pctUnemployed = $totalGraduates > 0 ? round(($unemployedCount / $totalGraduates) * 100, 1) : 0.0;
        $pctNotTracked = $totalGraduates > 0 ? round(($notTrackedCount / $totalGraduates) * 100, 1) : 0.0;

        // Payments summary scoped to this department
        $paymentModel = new \App\Models\PaymentModel();
        $deptPayments = $paymentModel->getFilteredPayments([
            'department_id'   => $deptId > 0 ? $deptId : null,
            'department_name' => $deptName !== '' ? $deptName : null,
            'coordinator_user_id' => $ctx['user_id'] > 0 ? $ctx['user_id'] : null,
        ]);
        $pendingPayCount = 0;
        $verifiedPayCount = 0;
        foreach ($deptPayments as $dp) {
            if (($dp['status'] ?? '') === 'pending') {
                $pendingPayCount++;
            } elseif (($dp['status'] ?? '') === 'verified') {
                $verifiedPayCount++;
            }
        }

        return $this->response->setJSON([
            'success'         => true,
            'department'      => $deptName,
            'college'         => $ctx['college'],
            'batches'         => $batches,
            'totalGraduates'  => $totalGraduates,
            'tracedCount'     => $tracedCount,
            'tracedRate'      => $tracedRate,
            'employedCount'   => $employedCount,
            'unemployedCount' => $unemployedCount,
            'notTrackedCount' => $notTrackedCount,
            'employmentRate'  => $employmentRate,
            'yearCohorts'     => [
                'labels'     => array_keys($cohortBuckets),
                'employed'   => array_column($cohortBuckets, 'employed'),
                'unemployed' => array_column($cohortBuckets, 'unemployed'),
                'notTracked' => array_column($cohortBuckets, 'notTracked'),
            ],
            'statusBreakdown' => [
                'counts' => [$employedCount, $unemployedCount, $notTrackedCount],
                'pcts'   => [
                    number_format($pctEmployed, 1) . '%',
                    number_format($pctUnemployed, 1) . '%',
                    number_format($pctNotTracked, 1) . '%',
                ],
            ],
            'surveySummary'   => [
                'totalResponses'     => $totalResponses,
                'reviewedCount'      => $reviewedCount,
                'pendingCount'       => $pendingReviewCount,
                'needsRevisionCount' => $needsRevisionCount,
                'responseRate'       => $responseRate,
            ],
            'paymentSummary'  => [
                'totalTransactions' => count($deptPayments),
                'pendingCount'      => $pendingPayCount,
                'verifiedCount'     => $verifiedPayCount,
            ],
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

        $ctx = $this->resolveCoordinatorContext();

        return $this->response->setJSON([
            'success'       => true,
            'name'          => $ctx['name'],
            'email'         => $ctx['email'],
            'department'    => $ctx['department'],
            'department_id' => $ctx['department_id'],
            'college'       => $ctx['college'],
            'college_id'    => $ctx['college_id'],
            'role'          => $ctx['role'],
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

    private function resolveCoordinatorContext(): array
    {
        $sessionUser = session('auth_user') ?? [];
        $email = strtolower(trim((string) ($sessionUser['email'] ?? '')));
        $role = strtolower(trim((string) ($sessionUser['role'] ?? 'coordinator')));
        $userId = (int) ($sessionUser['id'] ?? $sessionUser['ID'] ?? 0);

        $accountModel = new \App\Models\UserAccountModel();
        $account = $email !== '' ? $accountModel->findByEmail($email) : null;

        $name = trim((string) ($sessionUser['name'] ?? ''));
        $department = trim((string) ($sessionUser['department'] ?? ''));
        $college = '';

        if (is_array($account)) {
            $accName = trim((string) ($account['First_Name'] ?? '') . ' ' . (string) ($account['Last_Name'] ?? ''));
            if ($accName !== '') {
                $name = $accName;
            }
            if (! empty($account['id'])) {
                $userId = (int) $account['id'];
            }
            $accDept = trim((string) ($account['Department'] ?? ''));
            if ($accDept !== '') {
                $department = $accDept;
            }
            $college = trim((string) ($account['College'] ?? ''));
        }

        $departmentId = 0;
        $collegeId = 0;
        $db = \Config\Database::connect();

        if ($department !== '') {
            $deptRow = $db->table('departments')
                ->where('LOWER("Department_Name")', strtolower($department))
                ->get()
                ->getRowArray();
            if (is_array($deptRow)) {
                $departmentId = (int) ($deptRow['Department_ID'] ?? 0);
                $collegeId = (int) ($deptRow['College_ID'] ?? 0);
                $department = trim((string) ($deptRow['Department_Name'] ?? $department));
            }
        }

        if ($collegeId <= 0 && $college !== '') {
            $colRow = $db->table('colleges')
                ->where('LOWER("College_Name")', strtolower($college))
                ->get()
                ->getRowArray();
            if (is_array($colRow)) {
                $collegeId = (int) ($colRow['College_ID'] ?? 0);
                $college = trim((string) ($colRow['College_Name'] ?? $college));
            }
        } elseif ($collegeId > 0 && $college === '') {
            $colRow = $db->table('colleges')
                ->where('"College_ID"', $collegeId)
                ->get()
                ->getRowArray();
            if (is_array($colRow)) {
                $college = trim((string) ($colRow['College_Name'] ?? ''));
            }
        }

        return [
            'user_id'       => $userId,
            'name'          => $name !== '' ? $name : ($email !== '' ? $email : 'Coordinator'),
            'email'         => $email,
            'role'          => $role,
            'department'    => $department,
            'department_id' => $departmentId,
            'college'       => $college,
            'college_id'    => $collegeId,
        ];
    }

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
