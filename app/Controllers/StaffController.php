<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class StaffController extends BaseController
{
    private string $pythonApiUrl = 'http://localhost:5000';
    private string $chartsDir = WRITEPATH . '../charts/';

    /**
     * Generate charts via Python API
     */
    private function generateCharts(array $chartKeys = ['status', 'year', 'college', 'course']): array
    {
        $chartUrls = [];
        $chartKeys = array_values(array_unique(array_filter($chartKeys, static fn ($key) => in_array($key, ['status', 'year', 'college', 'course'], true))));
        
        try {
            $chartEndpoints = [
                'status' => '/api/charts/status',
                'year' => '/api/charts/year-distribution',
                'college' => '/api/charts/college-distribution',
                'course' => '/api/charts/course-distribution',
            ];

            foreach ($chartKeys as $chartKey) {
                $response = @file_get_contents($this->pythonApiUrl . ($chartEndpoints[$chartKey] ?? ''));
                if ($response === false) {
                    continue;
                }

                $data = json_decode($response, true);
                if (isset($data['chart_path'])) {
                    $chartUrls[$chartKey] = $this->pythonApiUrl . '/charts/' . basename($data['chart_path']);
                }
            }
        } catch (\Exception $e) {
            // Charts failed to generate, continue without them
        }
        
        return $chartUrls;
    }

    /**
     * Download chart image and convert to base64 for embedding
     */
    private function getChartBase64(string $url): ?string
    {
        try {
            $imageData = @file_get_contents($url);
            if ($imageData === false) {
                return null;
            }
            $base64 = base64_encode($imageData);
            $mimeType = 'image/png';
            return "data:{$mimeType};base64,{$base64}";
        } catch (\Exception $e) {
            return null;
        }
    }
    public function dashboard(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/Dashboard.html');
    }

    public function alumniManagement(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/AlumniManagement.html');
    }

    public function alumniBatchDetails(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/AlumniBatchDetails.html');
    }

    public function alumniCollegeDetails(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/AlumniCollegeDetails.html');
    }

    public function alumniCourseDetails(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/AlumniCourseDetails.html');
    }

    public function events(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/Events.html');
    }

    public function news(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/News.html');
    }

    public function reports(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/Reports.html');
    }

    public function previewCount(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        try {
            $raw = $this->request->getJSON(true);
            if (! is_array($raw)) {
                $rawInput = $this->request->getRawInput();
                $raw = is_string($rawInput) ? json_decode($rawInput, true) : [];
            }
            if (! is_array($raw)) {
                $raw = $this->request->getPost();
            }

            $surveyId = (int) ($raw['survey_id'] ?? $raw['surveyId'] ?? 0);
            if ($surveyId <= 0) {
                return $this->response->setJSON([
                    'success' => true,
                    'count' => 0,
                ]);
            }

            $filters = is_array($raw['filters'] ?? null) ? $raw['filters'] : [];
            $programGroups = $this->getAlumniProgramGroups($filters, $surveyId);
            $totalAlumni = array_sum(array_map(static fn (array $g): int => (int) ($g['total'] ?? 0), $programGroups));

            return $this->response->setJSON([
                'success' => true,
                'count' => $totalAlumni,
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error calculating preview count: ' . $e->getMessage(),
                'count' => 0,
            ]);
        }
    }

    public function generate(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        try {
            $jsonData = $this->request->getJSON(true);
            if (! is_array($jsonData)) {
                $rawInput = $this->request->getRawInput();
                $jsonData = is_string($rawInput) ? json_decode($rawInput, true) : [];
            }
            if (! is_array($jsonData) || empty($jsonData)) {
                $jsonData = $this->request->getPost();
            }

            if (! is_array($jsonData) || empty($jsonData)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Invalid data provided for report generation.',
                ]);
            }

            // Check if this is a survey report request
            $surveyId = (int) ($jsonData['survey_id'] ?? $jsonData['surveyId'] ?? 0);
            if ($surveyId > 0) {
                return $this->generateSurveyReport($jsonData);
            }

            // Fallback for legacy alumni general report
            return $this->generateLegacyAlumniReport($jsonData);

        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error generating report: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Survey-Based Multi-Step Custom Report Generator
     */
    private function generateSurveyReport(array $payload): ResponseInterface
    {
        $db = \Config\Database::connect();
        $surveyId = (int) ($payload['survey_id'] ?? $payload['surveyId'] ?? 0);

        $survey = $db->table('surveys')->where('id', $surveyId)->get()->getRowArray();
        if (! is_array($survey)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Selected survey was not found.',
            ]);
        }

        $selectedQuestionIds = array_values(array_filter(array_map('intval', (array) ($payload['selected_questions'] ?? $payload['selectedQuestions'] ?? []))));

        // Fetch selected questions with their categories if any were selected
        $questionRows = [];
        if (! empty($selectedQuestionIds)) {
            $questionRows = $db->table('questions q')
                ->select('q.id, q.category_id, q.question_text, q.question_type, q.rating_min, q.rating_max, q.rating_min_label, q.rating_max_label, cat.category_name, cat.order_number as cat_order, cat.description as cat_desc')
                ->join('categories cat', 'cat.id = q.category_id', 'left')
                ->whereIn('q.id', $selectedQuestionIds)
                ->where('q.question_type !=', 'image')
                ->orderBy('cat.order_number', 'ASC')
                ->orderBy('q.id', 'ASC')
                ->get()
                ->getResultArray();
        } else {
            $questionRows = $db->table('questions q')
                ->select('q.id, q.category_id, q.question_text, q.question_type, q.rating_min, q.rating_max, q.rating_min_label, q.rating_max_label, cat.category_name, cat.order_number as cat_order, cat.description as cat_desc')
                ->join('categories cat', 'cat.id = q.category_id', 'inner')
                ->where('cat.survey_id', $surveyId)
                ->where('q.question_type !=', 'image')
                ->orderBy('cat.order_number', 'ASC')
                ->orderBy('q.id', 'ASC')
                ->get()
                ->getResultArray();
        }

        // Extra defensive filter for questions that accept images
        $questionRows = array_values(array_filter($questionRows, static function (array $row): bool {
            return strtolower(trim((string) ($row['question_type'] ?? ''))) !== 'image';
        }));

        // Group questions under categories
        $categoriesMap = [];
        $selectedQuestions = [];
        foreach ($questionRows as $row) {
            $catId = (int) ($row['category_id'] ?? 0);
            $catName = trim((string) ($row['category_name'] ?? 'General Information'));
            if ($catName === '') $catName = 'General Information';

            if (! isset($categoriesMap[$catId])) {
                $categoriesMap[$catId] = [
                    'id' => $catId,
                    'name' => $catName,
                    'description' => (string) ($row['cat_desc'] ?? ''),
                    'questions' => [],
                ];
            }

            $qItem = [
                'id' => (int) $row['id'],
                'categoryId' => $catId,
                'categoryName' => $catName,
                'text' => (string) ($row['question_text'] ?? ''),
                'type' => (string) ($row['question_type'] ?? 'text'),
                'rating_min' => isset($row['rating_min']) ? (int) $row['rating_min'] : null,
                'rating_max' => isset($row['rating_max']) ? (int) $row['rating_max'] : null,
                'rating_min_label' => (string) ($row['rating_min_label'] ?? ''),
                'rating_max_label' => (string) ($row['rating_max_label'] ?? ''),
            ];

            $categoriesMap[$catId]['questions'][] = $qItem;
            $selectedQuestions[] = $qItem;
        }

        $selectedCategories = array_values($categoriesMap);
        $filters = is_array($payload['filters'] ?? null) ? $payload['filters'] : [];
        $matchingResponses = $this->getMatchingSurveyResponses($surveyId, $filters, array_column($selectedQuestions, 'id'));
        $programGroups = $this->getAlumniProgramGroups($filters, $surveyId, $matchingResponses);

        // Compile Choice & Response Statistics (Category -> Question -> Choices & number of responses)
        $selectedCategories = $this->buildCategoryQuestionChoiceStatistics(
            $db,
            $selectedCategories,
            $selectedQuestions,
            $matchingResponses
        );

        $reportType = strtolower(trim((string) ($payload['report_type'] ?? $payload['reportType'] ?? 'alumni_tracer')));
        if (! in_array($reportType, ['alumni_tracer', 'graphs', 'raw_numbers'], true)) {
            $reportType = 'alumni_tracer';
        }

        $format = strtolower(trim((string) ($payload['format'] ?? 'pdf')));
        $cleanTitle = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim((string) ($survey['title'] ?? 'survey')));

        $typePrefixMap = [
            'alumni_tracer' => 'Alumni_Tracer_Report_',
            'graphs' => 'Survey_Graphs_Report_',
            'raw_numbers' => 'Survey_Raw_Numbers_Report_',
        ];
        $prefix = $typePrefixMap[$reportType] ?? 'Survey_Report_';
        $filename = $prefix . $cleanTitle . '_' . date('Y-m-d_His');

        switch ($reportType) {
            case 'graphs':
                switch ($format) {
                    case 'csv':
                    case 'excel':
                    case 'xlsx':
                        $file = $this->generateSurveyGraphsSpreadsheetReport($survey, $selectedCategories, $selectedQuestions, $matchingResponses, $filename, $filters);
                        $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                        $extension = 'xlsx';
                        break;
                    case 'docx':
                    case 'word':
                        $file = $this->generateSurveyGraphsDocxReport($survey, $selectedCategories, $selectedQuestions, $matchingResponses, $filename, $filters);
                        $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                        $extension = 'docx';
                        break;
                    case 'pdf':
                    default:
                        $file = $this->generateSurveyGraphsPdfReport($survey, $selectedCategories, $selectedQuestions, $matchingResponses, $filename, $filters);
                        $mimeType = 'application/pdf';
                        $extension = 'pdf';
                        break;
                }
                break;

            case 'raw_numbers':
                switch ($format) {
                    case 'csv':
                    case 'excel':
                    case 'xlsx':
                        $file = $this->generateSurveyRawNumbersSpreadsheetReport($survey, $selectedCategories, $selectedQuestions, $matchingResponses, $filename, $filters);
                        $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                        $extension = 'xlsx';
                        break;
                    case 'docx':
                    case 'word':
                        $file = $this->generateSurveyRawNumbersDocxReport($survey, $selectedCategories, $selectedQuestions, $matchingResponses, $filename, $filters);
                        $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                        $extension = 'docx';
                        break;
                    case 'pdf':
                    default:
                        $file = $this->generateSurveyRawNumbersPdfReport($survey, $selectedCategories, $selectedQuestions, $matchingResponses, $filename, $filters);
                        $mimeType = 'application/pdf';
                        $extension = 'pdf';
                        break;
                }
                break;

            case 'alumni_tracer':
            default:
                switch ($format) {
                    case 'csv':
                    case 'excel':
                    case 'xlsx':
                        $file = $this->generateSurveyCsvReport($survey, $selectedCategories, $selectedQuestions, $matchingResponses, $filename, $filters, $programGroups);
                        $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                        $extension = 'xlsx';
                        break;
                    case 'docx':
                    case 'word':
                        $file = $this->generateSurveyDocxReport($survey, $selectedCategories, $selectedQuestions, $matchingResponses, $filename, $filters, $programGroups);
                        $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                        $extension = 'docx';
                        break;
                    case 'pdf':
                    default:
                        $file = $this->generateSurveyPdfReport($survey, $selectedCategories, $selectedQuestions, $matchingResponses, $filename, $filters, $programGroups);
                        $mimeType = 'application/pdf';
                        $extension = 'pdf';
                        break;
                }
                break;
        }

        return $this->response
            ->download($file, null)
            ->setFileName($filename . '.' . $extension)
            ->setHeader('Content-Type', $mimeType);
    }

    /**
     * Filters responses by system and survey answer criteria
     * @return array<int, array<string, mixed>>
     */
    private function getMatchingSurveyResponses(int $surveyId, array $filters, array $selectedQuestionIds = []): array
    {
        $db = \Config\Database::connect();

        // 1. Fetch raw responses for the survey
        $responseRows = $db->table('responses')
            ->where('survey_id', $surveyId)
            ->orderBy('submitted_at', 'DESC')
            ->get()
            ->getResultArray();

        if (empty($responseRows)) {
            return [];
        }

        // 2. Fetch all alumni indexed by lowercase email with college and department joins
        $alumniRows = $db->table('alumni a')
            ->select('a.*, c.Abbreviation as college_abbr, c.College_Name, d.Department_Name, d.Abbreviation as dept_abbr')
            ->join('colleges c', 'c.College_ID = a.College_ID', 'left')
            ->join('departments d', 'd.Department_ID = a.Department_ID', 'left')
            ->get()
            ->getResultArray();
        $alumniByEmail = [];
        foreach ($alumniRows as $al) {
            $emailKey = strtolower(trim((string) ($al['Email'] ?? '')));
            if ($emailKey !== '') {
                $alumniByEmail[$emailKey] = $al;
            }
        }

        // Extract system filter values
        $filterYear = trim((string) ($filters['graduation_year'] ?? $filters['graduationYear'] ?? ''));
        $filterCollege = trim((string) ($filters['college'] ?? ''));
        $filterCourse = trim((string) ($filters['course'] ?? $filters['department'] ?? ''));
        $filterReviewStatus = strtolower(trim((string) ($filters['review_status'] ?? $filters['reviewStatus'] ?? 'all')));
        $filterDateFrom = trim((string) ($filters['date_from'] ?? $filters['dateFrom'] ?? ''));
        $filterDateTo = trim((string) ($filters['date_to'] ?? $filters['dateTo'] ?? ''));
        $filterSearch = strtolower(trim((string) ($filters['search_query'] ?? $filters['searchQuery'] ?? '')));

        // Answer filters: [question_id => [choice_id / choice_text]]
        $answerFilters = is_array($filters['answer_filters'] ?? $filters['answerFilters'] ?? null)
            ? ($filters['answer_filters'] ?? $filters['answerFilters'])
            : [];

        // 3. Pre-fetch answers for this survey's responses
        $responseIds = array_map(static fn ($r) => (int) $r['id'], $responseRows);
        $answerRows = [];
        if (! empty($responseIds)) {
            $answerRows = $db->table('answers a')
                ->select('a.id, a.response_id, a.question_id, a.choice_id, a.answer_text, a.answer_image_path, c.choice_text')
                ->join('choices c', 'c.id = a.choice_id', 'left')
                ->whereIn('a.response_id', $responseIds)
                ->get()
                ->getResultArray();
        }

        $answersByResponseId = [];
        foreach ($answerRows as $ans) {
            $rId = (int) $ans['response_id'];
            $qId = (int) $ans['question_id'];
            $value = $ans['choice_text'] ?? $ans['answer_text'] ?? '';
            if (trim((string) ($ans['answer_image_path'] ?? '')) !== '') {
                $value = basename($ans['answer_image_path']);
            }
            $answersByResponseId[$rId][$qId] = [
                'id' => (int) $ans['id'],
                'choice_id' => $ans['choice_id'] !== null ? (int) $ans['choice_id'] : null,
                'choice_text' => (string) ($ans['choice_text'] ?? ''),
                'value' => (string) $value,
                'answer_text' => (string) ($ans['answer_text'] ?? ''),
            ];
        }

        // 4. Filter responses
        $matching = [];
        foreach ($responseRows as $resp) {
            $respId = (int) $resp['id'];
            $email = strtolower(trim((string) ($resp['user_email'] ?? '')));
            $alumni = $alumniByEmail[$email] ?? null;

            $alumniName = '';
            if (is_array($alumni)) {
                $alumniName = trim(($alumni['FirstName'] ?? '') . ' ' . ($alumni['LastName'] ?? ''));
            }
            if ($alumniName === '') {
                $alumniName = trim((string) ($resp['user_name'] ?? '')) ?: ($email ?: 'Alumni Respondent');
            }

            $collegeId = is_array($alumni) && ! empty($alumni['College_ID']) ? (int) $alumni['College_ID'] : 0;
            $collegeAbbr = is_array($alumni) ? trim((string) ($alumni['college_abbr'] ?? '')) : '';
            $collegeName = is_array($alumni) ? trim((string) ($alumni['College_Name'] ?? '')) : '';
            $college = $collegeAbbr ?: ($collegeName ?: 'CET');
            $deptId = is_array($alumni) && ! empty($alumni['Department_ID']) ? (int) $alumni['Department_ID'] : 0;
            $course = is_array($alumni) ? trim((string) ($alumni['Department_Name'] ?? '')) : '';
            $deptAbbr = is_array($alumni) ? trim((string) ($alumni['dept_abbr'] ?? '')) : '';
            $gradYear = is_array($alumni) ? trim((string) ($alumni['GraduationYear'] ?? '')) : '';
            $alumniRecordId = is_array($alumni) ? trim((string) ($alumni['alumni_id'] ?? '')) : '';
            $reviewStatus = strtolower(trim((string) ($resp['review_status'] ?? 'pending')));
            $submittedAt = (string) ($resp['submitted_at'] ?? '');

            // System filter checks
            if ($filterYear !== '' && strcasecmp($gradYear, $filterYear) !== 0) {
                continue;
            }
            if ($filterCollege !== '' && strcasecmp($college, $filterCollege) !== 0 && strcasecmp($collegeName, $filterCollege) !== 0) {
                continue;
            }
            if ($filterCourse !== '' && strcasecmp($course, $filterCourse) !== 0 && strcasecmp($deptAbbr, $filterCourse) !== 0) {
                continue;
            }
            if ($filterReviewStatus !== '' && $filterReviewStatus !== 'all' && $reviewStatus !== $filterReviewStatus) {
                continue;
            }
            if ($filterDateFrom !== '' && $submittedAt !== '') {
                if (strtotime($submittedAt) < strtotime($filterDateFrom . ' 00:00:00')) {
                    continue;
                }
            }
            if ($filterDateTo !== '' && $submittedAt !== '') {
                if (strtotime($submittedAt) > strtotime($filterDateTo . ' 23:59:59')) {
                    continue;
                }
            }
            if ($filterSearch !== '') {
                $searchable = strtolower($alumniName . ' ' . $email . ' ' . $alumniRecordId . ' ' . $college . ' ' . $course);
                if (! str_contains($searchable, $filterSearch)) {
                    continue;
                }
            }

            // Answer filter checks
            if (! empty($answerFilters)) {
                $passedAnswerFilters = true;
                foreach ($answerFilters as $targetQId => $allowedChoices) {
                    $targetQId = (int) $targetQId;
                    $allowedChoices = is_array($allowedChoices) ? array_map('strval', $allowedChoices) : [strval($allowedChoices)];
                    $allowedChoices = array_filter(array_map('trim', $allowedChoices));
                    if (empty($allowedChoices)) {
                        continue;
                    }

                    $ans = $answersByResponseId[$respId][$targetQId] ?? null;
                    if ($ans === null) {
                        $passedAnswerFilters = false;
                        break;
                    }

                    $val = trim((string) ($ans['value'] ?? ''));
                    $cText = trim((string) ($ans['choice_text'] ?? ''));
                    $cId = $ans['choice_id'] !== null ? strval($ans['choice_id']) : '';

                    $matchesChoice = in_array($val, $allowedChoices, true)
                        || in_array($cText, $allowedChoices, true)
                        || ($cId !== '' && in_array($cId, $allowedChoices, true));

                    if (! $matchesChoice) {
                        $passedAnswerFilters = false;
                        break;
                    }
                }

                if (! $passedAnswerFilters) {
                    continue;
                }
            }

            // Format answers for selected questions
            $respAnswers = [];
            foreach ($selectedQuestionIds as $qId) {
                $qAns = $answersByResponseId[$respId][$qId] ?? null;
                $respAnswers[$qId] = $qAns !== null ? $qAns['value'] : '—';
            }

            $matching[] = [
                'id' => $respId,
                'name' => $alumniName,
                'email' => $email,
                'alumniId' => $alumniRecordId,
                'college_id' => $collegeId,
                'college' => $college ?: '—',
                'college_abbr' => $collegeAbbr ?: ($college !== '—' ? $college : 'CET'),
                'college_name' => $collegeName ?: ($college ?: 'CET'),
                'department_id' => $deptId,
                'course' => $course ?: '—',
                'dept_abbr' => $deptAbbr ?: ($course ?: '—'),
                'graduationYear' => $gradYear ?: '—',
                'reviewStatus' => $reviewStatus ?: 'pending',
                'submittedAt' => $submittedAt,
                'answers' => $respAnswers,
            ];
        }

        return $matching;
    }

    /**
     * Fetches the full College -> Department hierarchy from the database.
     */
    private function getCollegeDepartmentHierarchy(\CodeIgniter\Database\BaseConnection $db): array
    {
        $collegeRows = $db->table('colleges')->orderBy('College_ID', 'ASC')->get()->getResultArray();
        $deptRows = $db->table('departments')->orderBy('College_ID', 'ASC')->orderBy('Department_ID', 'ASC')->get()->getResultArray();

        $hierarchy = [];
        $collegeIdToKey = [];

        foreach ($collegeRows as $cRow) {
            $cId = (int) ($cRow['College_ID'] ?? 0);
            $abbr = trim((string) ($cRow['Abbreviation'] ?? ''));
            $name = trim((string) ($cRow['College_Name'] ?? ''));
            $key = $abbr !== '' ? $abbr : ($name !== '' ? $name : 'COL_' . $cId);

            $hierarchy[$key] = [
                'id' => $cId,
                'key' => $key,
                'abbr' => $abbr !== '' ? $abbr : $key,
                'name' => $name !== '' ? $name : $key,
                'departments' => [],
            ];
            if ($cId > 0) {
                $collegeIdToKey[$cId] = $key;
            }
        }

        if (empty($hierarchy)) {
            $hierarchy = [
                'CET' => [
                    'id' => 1,
                    'key' => 'CET',
                    'abbr' => 'CET',
                    'name' => 'College of Engineering',
                    'departments' => [],
                ],
                'CA' => [
                    'id' => 2,
                    'key' => 'CA',
                    'abbr' => 'CA',
                    'name' => 'College of Agriculture',
                    'departments' => [],
                ],
            ];
        }

        foreach ($deptRows as $dRow) {
            $dId = (int) ($dRow['Department_ID'] ?? 0);
            $cId = (int) ($dRow['College_ID'] ?? 0);
            $dAbbr = trim((string) ($dRow['Abbreviation'] ?? ''));
            $dName = trim((string) ($dRow['Department_Name'] ?? ''));
            if ($dName === '' && $dAbbr === '') {
                continue;
            }

            $colKey = $collegeIdToKey[$cId] ?? array_key_first($hierarchy);
            $deptKey = $dId > 0 ? 'dept_' . $dId : ('dept_' . md5($dAbbr . '|' . $dName));
            $label = ($dAbbr !== '' && $dName !== '' && strcasecmp($dAbbr, $dName) !== 0)
                ? ($dAbbr . ' — ' . $dName)
                : ($dName !== '' ? $dName : $dAbbr);

            $hierarchy[$colKey]['departments'][$deptKey] = [
                'id' => $dId,
                'key' => $deptKey,
                'abbr' => $dAbbr !== '' ? $dAbbr : $dName,
                'name' => $dName !== '' ? $dName : $dAbbr,
                'label' => $label,
            ];
        }

        // Ensure every college has at least one department row
        foreach ($hierarchy as $colKey => &$colInfo) {
            if (empty($colInfo['departments'])) {
                $fallbackKey = 'dept_gen_' . $colKey;
                $colInfo['departments'][$fallbackKey] = [
                    'id' => 0,
                    'key' => $fallbackKey,
                    'abbr' => $colInfo['abbr'],
                    'name' => 'All Programs',
                    'label' => $colInfo['abbr'] . ' — All Programs',
                ];
            }
        }
        unset($colInfo);

        return $hierarchy;
    }

    /**
     * Resolves a record's (collegeKey, deptKey) against the College -> Department hierarchy.
     */
    private function resolveCollegeAndDepartmentKeys(
        array $hierarchy,
        int $collegeId,
        string $collegeAbbrOrName,
        int $deptId,
        string $deptName,
        string $deptAbbr = ''
    ): array {
        $resolvedColKey = null;

        if ($collegeId > 0) {
            foreach ($hierarchy as $cKey => $cInfo) {
                if ((int) ($cInfo['id'] ?? 0) === $collegeId) {
                    $resolvedColKey = $cKey;
                    break;
                }
            }
        }

        if ($resolvedColKey === null && $collegeAbbrOrName !== '' && $collegeAbbrOrName !== '—') {
            if (isset($hierarchy[$collegeAbbrOrName])) {
                $resolvedColKey = $collegeAbbrOrName;
            } else {
                foreach ($hierarchy as $cKey => $cInfo) {
                    if (
                        strcasecmp((string) ($cInfo['abbr'] ?? ''), $collegeAbbrOrName) === 0
                        || strcasecmp((string) ($cInfo['name'] ?? ''), $collegeAbbrOrName) === 0
                    ) {
                        $resolvedColKey = $cKey;
                        break;
                    }
                }
            }
        }

        // Also check if deptId belongs to a known college in the hierarchy
        if ($deptId > 0) {
            foreach ($hierarchy as $cKey => $cInfo) {
                foreach ($cInfo['departments'] as $dKey => $dInfo) {
                    if ((int) ($dInfo['id'] ?? 0) === $deptId) {
                        return [$cKey, $dKey];
                    }
                }
            }
        }

        if ($resolvedColKey === null) {
            $resolvedColKey = (string) array_key_first($hierarchy);
        }

        $depts = $hierarchy[$resolvedColKey]['departments'] ?? [];
        $resolvedDeptKey = null;

        if ($deptId > 0 && isset($depts['dept_' . $deptId])) {
            $resolvedDeptKey = 'dept_' . $deptId;
        }

        if ($resolvedDeptKey === null && ($deptName !== '' || $deptAbbr !== '')) {
            foreach ($depts as $dKey => $dInfo) {
                if (
                    ($deptName !== '' && $deptName !== '—' && (
                        strcasecmp((string) ($dInfo['name'] ?? ''), $deptName) === 0
                        || strcasecmp((string) ($dInfo['abbr'] ?? ''), $deptName) === 0
                        || strcasecmp((string) ($dInfo['label'] ?? ''), $deptName) === 0
                    ))
                    || ($deptAbbr !== '' && $deptAbbr !== '—' && (
                        strcasecmp((string) ($dInfo['abbr'] ?? ''), $deptAbbr) === 0
                        || strcasecmp((string) ($dInfo['name'] ?? ''), $deptAbbr) === 0
                    ))
                ) {
                    $resolvedDeptKey = $dKey;
                    break;
                }
            }
        }

        if ($resolvedDeptKey === null) {
            $resolvedDeptKey = (string) array_key_first($depts);
        }

        return [$resolvedColKey, $resolvedDeptKey];
    }

    /**
     * Builds Choice and Response Statistics for Category-Question Hierarchy
     * Format:
     * Category
     *    Question
     *      Choices      number of responses
     *      College -> Department breakdown matrix
     */
    private function buildCategoryQuestionChoiceStatistics(\CodeIgniter\Database\BaseConnection $db, array $selectedCategories, array $selectedQuestions, array $matchingResponses): array
    {
        $selectedQIds = array_values(array_filter(array_map('intval', array_column($selectedQuestions, 'id'))));
        if (empty($selectedQIds)) {
            return $selectedCategories;
        }

        $hierarchy = $this->getCollegeDepartmentHierarchy($db);
        $allCollegeAbbrs = [];
        foreach ($hierarchy as $cKey => $cInfo) {
            $allCollegeAbbrs[$cKey] = $cInfo['abbr'];
        }

        $choiceColorPalette = [
            '#1e4a76', // Navy
            '#dc2626', // Crimson
            '#0d9488', // Teal
            '#d97706', // Amber
            '#7c3aed', // Purple
            '#2563eb', // Blue
            '#db2777', // Pink
            '#475569', // Slate
        ];

        // Response to (college, department) mapping
        $responseCollegeMap = [];
        $responseLocationMap = [];
        foreach ($matchingResponses as $mr) {
            $rId = (int) ($mr['id'] ?? 0);
            [$colKey, $deptKey] = $this->resolveCollegeAndDepartmentKeys(
                $hierarchy,
                (int) ($mr['college_id'] ?? 0),
                trim((string) ($mr['college_abbr'] ?? $mr['college'] ?? '')),
                (int) ($mr['department_id'] ?? 0),
                trim((string) ($mr['course'] ?? '')),
                trim((string) ($mr['dept_abbr'] ?? ''))
            );
            $responseCollegeMap[$rId] = $colKey;
            $responseLocationMap[$rId] = [
                'college' => $colKey,
                'dept' => $deptKey,
            ];
        }

        // 1. Fetch predefined choices for all selected questions
        $choiceRows = $db->table('choices')
            ->select('id, question_id, choice_text')
            ->whereIn('question_id', $selectedQIds)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $choicesByQuestion = [];
        foreach ($choiceRows as $cRow) {
            $qId = (int) $cRow['question_id'];
            $choicesByQuestion[$qId][] = [
                'id' => (int) $cRow['id'],
                'text' => trim((string) ($cRow['choice_text'] ?? '')),
            ];
        }

        // 2. Fetch all raw answer rows for matching responses
        $matchingResponseIds = array_values(array_filter(array_map('intval', array_column($matchingResponses, 'id'))));
        $rawAnswers = [];
        if (! empty($matchingResponseIds)) {
            $rawAnswers = $db->table('answers a')
                ->select('a.response_id, a.question_id, a.choice_id, a.answer_text, c.choice_text')
                ->join('choices c', 'c.id = a.choice_id', 'left')
                ->whereIn('a.response_id', $matchingResponseIds)
                ->whereIn('a.question_id', $selectedQIds)
                ->get()
                ->getResultArray();
        }

        $defaultColKey = (string) array_key_first($hierarchy);
        $defaultDeptKey = (string) array_key_first($hierarchy[$defaultColKey]['departments']);

        $answersByQId = [];
        foreach ($rawAnswers as $row) {
            $qId = (int) $row['question_id'];
            $rId = (int) $row['response_id'];
            $loc = $responseLocationMap[$rId] ?? ['college' => $defaultColKey, 'dept' => $defaultDeptKey];
            $answersByQId[$qId][] = [
                'choice_id' => $row['choice_id'] !== null ? (int) $row['choice_id'] : null,
                'choice_text' => trim((string) ($row['choice_text'] ?? '')),
                'answer_text' => trim((string) ($row['answer_text'] ?? '')),
                'value' => trim((string) ($row['choice_text'] ?? $row['answer_text'] ?? '')),
                'college' => $loc['college'],
                'dept' => $loc['dept'],
            ];
        }

        // 3. For each category and each question, compile choice statistics & College -> Department Matrix
        foreach ($selectedCategories as &$category) {
            foreach ($category['questions'] as &$question) {
                $qId = (int) $question['id'];
                $qType = strtolower(trim((string) ($question['type'] ?? 'text')));
                $qAnswers = $answersByQId[$qId] ?? [];
                $choicesList = [];
                $choiceDeptTally = []; // [choiceText][colKey][deptKey] => int

                if ($qType === 'multiple_choice') {
                    $definedChoices = $choicesByQuestion[$qId] ?? [];
                    $choiceCounts = [];
                    $choiceColleges = [];
                    $choiceIdToText = [];

                    foreach ($definedChoices as $dc) {
                        $cText = $dc['text'];
                        $choiceCounts[$cText] = 0;
                        $choiceColleges[$cText] = array_fill_keys(array_keys($allCollegeAbbrs), 0);
                        $choiceIdToText[$dc['id']] = $cText;
                    }

                    foreach ($qAnswers as $ans) {
                        $col = $ans['college'] ?? $defaultColKey;
                        $dept = $ans['dept'] ?? $defaultDeptKey;
                        $targetText = '';
                        if ($ans['choice_id'] !== null && isset($choiceIdToText[$ans['choice_id']])) {
                            $targetText = $choiceIdToText[$ans['choice_id']];
                        } elseif ($ans['choice_text'] !== '' && isset($choiceCounts[$ans['choice_text']])) {
                            $targetText = $ans['choice_text'];
                        } elseif ($ans['value'] !== '') {
                            $targetText = $ans['value'];
                        }

                        if ($targetText !== '') {
                            $choiceCounts[$targetText] = ($choiceCounts[$targetText] ?? 0) + 1;
                            $choiceColleges[$targetText][$col] = ($choiceColleges[$targetText][$col] ?? 0) + 1;
                            $choiceDeptTally[$targetText][$col][$dept] = ($choiceDeptTally[$targetText][$col][$dept] ?? 0) + 1;
                        }
                    }

                    if (empty($choiceCounts)) {
                        $choiceCounts['No choices configured'] = 0;
                        $choiceColleges['No choices configured'] = array_fill_keys(array_keys($allCollegeAbbrs), 0);
                    }

                    foreach ($choiceCounts as $cText => $count) {
                        $colDetails = [];
                        foreach ($allCollegeAbbrs as $colAbbr) {
                            $colCnt = (int) ($choiceColleges[$cText][$colAbbr] ?? 0);
                            $colPct = $count > 0 ? round(($colCnt / $count) * 100, 1) : 0.0;
                            $colDetails[$colAbbr] = [
                                'abbr' => $colAbbr,
                                'count' => $colCnt,
                                'percentage' => $colPct,
                                'formatted' => $colAbbr . ' - ' . number_format($colCnt) . ' - ' . number_format($colPct, 1) . '%',
                            ];
                        }
                        $choicesList[] = [
                            'choice' => $cText,
                            'count' => $count,
                            'colleges' => $colDetails,
                        ];
                    }
                } elseif ($qType === 'rating') {
                    $definedChoices = $choicesByQuestion[$qId] ?? [];
                    if (! empty($definedChoices)) {
                        $choiceCounts = [];
                        $choiceColleges = [];
                        foreach ($definedChoices as $dc) {
                            $choiceCounts[$dc['text']] = 0;
                            $choiceColleges[$dc['text']] = array_fill_keys(array_keys($allCollegeAbbrs), 0);
                        }
                        foreach ($qAnswers as $ans) {
                            $col = $ans['college'] ?? $defaultColKey;
                            $dept = $ans['dept'] ?? $defaultDeptKey;
                            $val = $ans['choice_text'] !== '' ? $ans['choice_text'] : $ans['value'];
                            if (isset($choiceCounts[$val])) {
                                $choiceCounts[$val]++;
                                $choiceColleges[$val][$col] = ($choiceColleges[$val][$col] ?? 0) + 1;
                                $choiceDeptTally[$val][$col][$dept] = ($choiceDeptTally[$val][$col][$dept] ?? 0) + 1;
                            } elseif ($val !== '') {
                                $choiceCounts[$val] = ($choiceCounts[$val] ?? 0) + 1;
                                $choiceColleges[$val][$col] = ($choiceColleges[$val][$col] ?? 0) + 1;
                                $choiceDeptTally[$val][$col][$dept] = ($choiceDeptTally[$val][$col][$dept] ?? 0) + 1;
                            }
                        }
                        foreach ($choiceCounts as $cText => $count) {
                            $colDetails = [];
                            foreach ($allCollegeAbbrs as $colAbbr) {
                                $colCnt = (int) ($choiceColleges[$cText][$colAbbr] ?? 0);
                                $colPct = $count > 0 ? round(($colCnt / $count) * 100, 1) : 0.0;
                                $colDetails[$colAbbr] = [
                                    'abbr' => $colAbbr,
                                    'count' => $colCnt,
                                    'percentage' => $colPct,
                                    'formatted' => $colAbbr . ' - ' . number_format($colCnt) . ' - ' . number_format($colPct, 1) . '%',
                                ];
                            }
                            $choicesList[] = [
                                'choice' => $cText,
                                'count' => $count,
                                'colleges' => $colDetails,
                            ];
                        }
                    } else {
                        $min = ! empty($question['rating_min']) ? (int) $question['rating_min'] : 1;
                        $max = ! empty($question['rating_max']) ? (int) $question['rating_max'] : 5;
                        $minLabel = trim((string) ($question['rating_min_label'] ?? ''));
                        $maxLabel = trim((string) ($question['rating_max_label'] ?? ''));

                        $scaleCounts = [];
                        $scaleColleges = [];
                        $scoreToLabel = [];
                        for ($s = $min; $s <= $max; $s++) {
                            $label = (string) $s;
                            if ($s === $min && $minLabel !== '') {
                                $label .= ' (' . $minLabel . ')';
                            } elseif ($s === $max && $maxLabel !== '') {
                                $label .= ' (' . $maxLabel . ')';
                            }
                            $scaleCounts[$label] = 0;
                            $scaleColleges[$label] = array_fill_keys(array_keys($allCollegeAbbrs), 0);
                            $scoreToLabel[$s] = $label;
                        }

                        foreach ($qAnswers as $ans) {
                            $col = $ans['college'] ?? $defaultColKey;
                            $dept = $ans['dept'] ?? $defaultDeptKey;
                            $numVal = is_numeric($ans['value']) ? (int) $ans['value'] : null;
                            if ($numVal !== null && isset($scoreToLabel[$numVal])) {
                                $lbl = $scoreToLabel[$numVal];
                                $scaleCounts[$lbl] = ($scaleCounts[$lbl] ?? 0) + 1;
                                $scaleColleges[$lbl][$col] = ($scaleColleges[$lbl][$col] ?? 0) + 1;
                                $choiceDeptTally[$lbl][$col][$dept] = ($choiceDeptTally[$lbl][$col][$dept] ?? 0) + 1;
                            } elseif ($ans['value'] !== '') {
                                $lbl = $ans['value'];
                                $scaleCounts[$lbl] = ($scaleCounts[$lbl] ?? 0) + 1;
                                $scaleColleges[$lbl][$col] = ($scaleColleges[$lbl][$col] ?? 0) + 1;
                                $choiceDeptTally[$lbl][$col][$dept] = ($choiceDeptTally[$lbl][$col][$dept] ?? 0) + 1;
                            }
                        }

                        foreach ($scaleCounts as $label => $cnt) {
                            $colDetails = [];
                            foreach ($allCollegeAbbrs as $colAbbr) {
                                $colCnt = (int) ($scaleColleges[$label][$colAbbr] ?? 0);
                                $colPct = $cnt > 0 ? round(($colCnt / $cnt) * 100, 1) : 0.0;
                                $colDetails[$colAbbr] = [
                                    'abbr' => $colAbbr,
                                    'count' => $colCnt,
                                    'percentage' => $colPct,
                                    'formatted' => $colAbbr . ' - ' . number_format($colCnt) . ' - ' . number_format($colPct, 1) . '%',
                                ];
                            }
                            $choicesList[] = [
                                'choice' => $label,
                                'count' => $cnt,
                                'colleges' => $colDetails,
                            ];
                        }
                    }
                } else {
                    // Open-ended / text questions
                    if (empty($qAnswers)) {
                        $choicesList[] = [
                            'choice' => 'No responses submitted',
                            'count' => 0,
                            'colleges' => [],
                        ];
                    } else {
                        $textCounts = [];
                        $textColleges = [];
                        foreach ($qAnswers as $ans) {
                            $val = trim((string) $ans['value']);
                            $col = $ans['college'] ?? $defaultColKey;
                            $dept = $ans['dept'] ?? $defaultDeptKey;
                            if ($val !== '' && $val !== '—') {
                                $textCounts[$val] = ($textCounts[$val] ?? 0) + 1;
                                $textColleges[$val][$col] = ($textColleges[$val][$col] ?? 0) + 1;
                                $choiceDeptTally[$val][$col][$dept] = ($choiceDeptTally[$val][$col][$dept] ?? 0) + 1;
                            }
                        }

                        if (empty($textCounts)) {
                            $choicesList[] = [
                                'choice' => 'No responses submitted',
                                'count' => 0,
                                'colleges' => [],
                            ];
                        } else {
                            arsort($textCounts);
                            foreach ($textCounts as $tText => $cnt) {
                                $colDetails = [];
                                foreach ($allCollegeAbbrs as $colAbbr) {
                                    $colCnt = (int) ($textColleges[$tText][$colAbbr] ?? 0);
                                    $colPct = $cnt > 0 ? round(($colCnt / $cnt) * 100, 1) : 0.0;
                                    $colDetails[$colAbbr] = [
                                        'abbr' => $colAbbr,
                                        'count' => $colCnt,
                                        'percentage' => $colPct,
                                        'formatted' => $colAbbr . ' - ' . number_format($colCnt) . ' - ' . number_format($colPct, 1) . '%',
                                    ];
                                }
                                $choicesList[] = [
                                    'choice' => $tText,
                                    'count' => $cnt,
                                    'colleges' => $colDetails,
                                ];
                            }
                        }
                    }
                }

                $qTotalResponses = array_sum(array_column($choicesList, 'count'));
                foreach ($choicesList as $cIdx => &$cItem) {
                    $cCount = (int) ($cItem['count'] ?? 0);
                    $cItem['percentage'] = $qTotalResponses > 0 ? round(($cCount / $qTotalResponses) * 100, 1) : 0.0;
                    $cItem['percentage_formatted'] = number_format($cItem['percentage'], 1) . '%';
                    $cItem['color'] = $choiceColorPalette[$cIdx % count($choiceColorPalette)];
                }
                unset($cItem);

                // Build College -> Department Breakdown Matrix for this question
                $collegeMatrix = [];
                foreach ($hierarchy as $colKey => $colInfo) {
                    $colTotal = 0;
                    $colItems = [];
                    foreach ($choicesList as $cIdx => $cItem) {
                        $cLabel = (string) ($cItem['choice'] ?? '');
                        $cColor = (string) ($cItem['color'] ?? $choiceColorPalette[$cIdx % count($choiceColorPalette)]);
                        $cColCnt = 0;
                        foreach ($colInfo['departments'] as $dKey => $_dInfo) {
                            $cColCnt += (int) ($choiceDeptTally[$cLabel][$colKey][$dKey] ?? 0);
                        }
                        $colTotal += $cColCnt;
                        $colItems[$cLabel] = [
                            'label' => $cLabel,
                            'count' => $cColCnt,
                            'percentage' => 0.0,
                            'color' => $cColor,
                        ];
                    }
                    foreach ($colItems as &$ci) {
                        $ci['percentage'] = $colTotal > 0 ? round(($ci['count'] / $colTotal) * 100, 1) : 0.0;
                    }
                    unset($ci);

                    $deptMatrix = [];
                    foreach ($colInfo['departments'] as $dKey => $dInfo) {
                        $dTotal = 0;
                        $dItems = [];
                        foreach ($choicesList as $cIdx => $cItem) {
                            $cLabel = (string) ($cItem['choice'] ?? '');
                            $cColor = (string) ($cItem['color'] ?? $choiceColorPalette[$cIdx % count($choiceColorPalette)]);
                            $dCnt = (int) ($choiceDeptTally[$cLabel][$colKey][$dKey] ?? 0);
                            $dTotal += $dCnt;
                            $dItems[] = [
                                'label' => $cLabel,
                                'count' => $dCnt,
                                'percentage' => 0.0,
                                'color' => $cColor,
                            ];
                        }
                        foreach ($dItems as &$di) {
                            $di['percentage'] = $dTotal > 0 ? round(($di['count'] / $dTotal) * 100, 1) : 0.0;
                        }
                        unset($di);

                        $deptMatrix[$dKey] = [
                            'id' => $dInfo['id'],
                            'key' => $dKey,
                            'abbr' => $dInfo['abbr'],
                            'name' => $dInfo['name'],
                            'label' => $dInfo['label'],
                            'total' => $dTotal,
                            'percentage_of_all' => $qTotalResponses > 0 ? round(($dTotal / $qTotalResponses) * 100, 1) : 0.0,
                            'percentage_of_college' => $colTotal > 0 ? round(($dTotal / $colTotal) * 100, 1) : 0.0,
                            'items' => $dItems,
                        ];
                    }

                    $collegeMatrix[$colKey] = [
                        'id' => $colInfo['id'],
                        'key' => $colKey,
                        'abbr' => $colInfo['abbr'],
                        'name' => $colInfo['name'],
                        'total' => $colTotal,
                        'percentage' => $qTotalResponses > 0 ? round(($colTotal / $qTotalResponses) * 100, 1) : 0.0,
                        'items' => array_values($colItems),
                        'departments' => $deptMatrix,
                    ];
                }

                $question['choiceStats'] = $choicesList;
                $question['totalResponses'] = $qTotalResponses;
                $question['collegeMatrix'] = $collegeMatrix;
            }
            unset($question);
        }
        unset($category);

        return $selectedCategories;
    }

    /**
     * Generates Excel (.xlsx) Export for Graduate Tracer Report per Program
     */
    private function generateSurveyCsvReport(array $survey, array $selectedCategories, array $selectedQuestions, array $matchingResponses, string $filename, array $filters, array $programGroups = []): string
    {
        $filepath = WRITEPATH . 'uploads/' . $filename . '.xlsx';
        if (! is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0777, true);
        }

        if (empty($programGroups)) {
            $programGroups = $this->getAlumniProgramGroups($filters, (int) ($survey['id'] ?? 0), $matchingResponses);
        }

        return $this->writeProgramAlumniOutcomeSpreadsheet($filepath, $programGroups);
    }

    /**
     * Generates PDF Export for Graduate Tracer Report per Program using Dompdf
     */
    private function generateSurveyPdfReport(array $survey, array $selectedCategories, array $selectedQuestions, array $matchingResponses, string $filename, array $filters, array $programGroups = []): string
    {
        $filepath = WRITEPATH . 'uploads/' . $filename . '.pdf';
        if (! is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0777, true);
        }

        if (empty($programGroups)) {
            $programGroups = $this->getAlumniProgramGroups($filters, (int) ($survey['id'] ?? 0), $matchingResponses);
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>
            @page { margin: 8mm 6mm; }
            body { font-family: Tahoma, Arial, Helvetica, sans-serif; font-size: 10pt; color: #000000; line-height: 1.15; }
            .outcome-header-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; border: 1px solid #000000; font-family: Tahoma, Arial, Helvetica, sans-serif; font-size: 10pt; color: #000000; table-layout: fixed; }
            .outcome-header-table tr { height: 15px; }
            .outcome-header-table tr.row-instructions { height: 75px; }
            .outcome-header-table td { padding: 1px 4px; border: 1px solid #d9d9d9; vertical-align: top; line-height: 1.15; }
            .outcome-header-table tr.row-highlight td { background-color: #ffe599; border-top: 1px solid #000000; border-bottom: 1px solid #000000; }
            .outcome-header-table td.col-label { width: 23.84%; text-align: right; font-weight: bold; white-space: nowrap; border-left: 1px solid #000000; }
            .outcome-header-table td.col-value { width: 76.16%; text-align: left; border-right: 1px solid #000000; }

            .tracer-outcome-table { width: 100%; border-collapse: collapse; font-family: Tahoma, Arial, Helvetica, sans-serif; font-size: 10pt; color: #000000; }
            .tracer-outcome-table th, .tracer-outcome-table td { border: 1px solid #000000; padding: 1px 4px; vertical-align: middle; word-wrap: break-word; line-height: 1.15; }
            .tracer-outcome-table tr.tr-num { height: 15px; }
            .tracer-outcome-table tr.tr-title { height: 30px; }
            .tracer-outcome-table tr.tr-banner { height: 15px; }
            .tracer-outcome-table tr.tr-data { height: 15px; }
            .tracer-outcome-table tr.tr-sum { height: 15px; }
            .tracer-outcome-table th.th-num { text-align: center; font-weight: normal; background-color: #ffffff; height: 15px; padding: 1px 2px; }
            .tracer-outcome-table th.th-title { text-align: center; font-weight: bold; background-color: #ffffff; height: 30px; padding: 2px 4px; }
            .tracer-outcome-table .th-sub { font-weight: normal; font-style: italic; font-size: 10pt; }
            .tracer-outcome-table td.td-campus-banner { background-color: #dbe5f1; font-weight: bold; text-align: left; height: 15px; padding: 1px 4px; text-transform: uppercase; }
            .tracer-outcome-table td.td-prog { width: 23.84%; vertical-align: top; text-align: left; padding: 1px 4px; border-left: 1px solid #000000; border-right: 1px solid #000000; }
            .tracer-outcome-table td.td-seq { width: 2.67%; text-align: center; padding: 1px 1px; white-space: nowrap; }
            .tracer-outcome-table td.td-grad-name { width: 18.49%; text-align: left; padding: 1px 4px; }
            .tracer-outcome-table td.td-status { width: 15.58%; text-align: center; padding: 1px 2px; }
            .tracer-outcome-table td.td-emp { width: 29.19%; text-align: left; padding: 1px 4px; }
            .tracer-outcome-table td.td-doc-cell { width: 10.23%; text-align: left; border-color: #e5e7eb; border-right: 1px solid #000000; padding: 1px 4px; }
            .tracer-outcome-table td.td-yellow-top { background-color: #ffff99; font-weight: bold; text-align: center; border-bottom: none; vertical-align: bottom; padding: 1px 2px 0 2px; height: 15px; }
            .tracer-outcome-table td.td-yellow-bottom { background-color: #ffff99; font-weight: bold; text-align: center; border-top: none; vertical-align: top; padding: 0 2px 1px 2px; height: 15px; }
            .tracer-outcome-table td.td-yellow-merged { background-color: #ffff99; font-weight: bold; text-align: center; vertical-align: middle; height: 30px; padding: 1px 2px; }
            .tracer-outcome-table td.td-yellow-cell { background-color: #ffff99; font-weight: bold; text-align: center; height: 15px; padding: 1px 2px; }
            .tracer-outcome-table td.td-sum-e { width: 4.19%; }
            .tracer-outcome-table td.td-sum-ue { width: 4.19%; }
            .tracer-outcome-table td.td-sum-nt { width: 7.21%; }
            .tracer-outcome-table td.td-black-top { width: 29.19%; background-color: #000000; border-bottom: none; height: 15px; }
            .tracer-outcome-table td.td-black-bottom { width: 29.19%; background-color: #000000; border-top: none; height: 15px; }
        </style></head><body>';

        // Institutional Outcome Indicator Header Block
        $html .= $this->buildInstitutionalOutcomeHeaderHtml();

        // Graduate Tracer Outcome Table per Program
        $html .= $this->buildProgramAlumniOutcomeTableHtml($programGroups);

        $html .= '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        file_put_contents($filepath, $dompdf->output());
        return $filepath;
    }

    /**
     * Generates Word DOCX Document for Graduate Tracer Report per Program
     */
    private function escapeWordXmlText(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        // Strip illegal XML 1.0 control characters except \t, \n, \r
        $clean = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $text);
        return htmlspecialchars((string) $clean, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function generateSurveyDocxReport(array $survey, array $selectedCategories, array $selectedQuestions, array $matchingResponses, string $filename, array $filters, array $programGroups = []): string
    {
        $filepath = WRITEPATH . 'uploads/' . $filename . '.docx';
        if (! is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0777, true);
        }

        if (empty($programGroups)) {
            $programGroups = $this->getAlumniProgramGroups($filters, (int) ($survey['id'] ?? 0), $matchingResponses);
        }

        \PhpOffice\PhpWord\Settings::setZipClass(\PhpOffice\PhpWord\Settings::PCLZIP);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Tahoma');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'pageSizeW' => 16838,
            'pageSizeH' => 11906,
            'marginTop' => 567,
            'marginRight' => 567,
            'marginBottom' => 567,
            'marginLeft' => 567,
        ]);

        // Institutional Outcome Indicator Header Block
        $this->addInstitutionalOutcomeHeaderToWordSection($phpWord, $section);
        $section->addTextBreak(1);

        // Graduate Tracer Outcome Table per Program
        $this->addProgramAlumniOutcomeTableToWordSection($phpWord, $section, $programGroups);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);
        return $filepath;
    }

    /**
     * Compiles Graduate Tracer Status Overview & College -> Department Distribution Breakdown
     * Statuses: Employed, Unemployed, Not Tracked
     */
    private function compileTracerStatusOverview(array $survey, array $matchingResponses, array $filters): array
    {
        $db = \Config\Database::connect();
        $hierarchy = $this->getCollegeDepartmentHierarchy($db);

        $collegeList = [];
        foreach ($hierarchy as $cKey => $cInfo) {
            $collegeList[$cKey] = $cInfo['name'];
        }

        // 2. Fetch alumni cohort matching demographic filters
        $alumniBuilder = $db->table('alumni a')
            ->select('a.id, a.alumni_id, a.FirstName, a.LastName, a.Email, a.College_ID, a.Department_ID, a.GraduationYear, a.Status, c.Abbreviation as college_abbr, c.College_Name, d.Department_Name, d.Abbreviation as dept_abbr')
            ->join('colleges c', 'c.College_ID = a.College_ID', 'left')
            ->join('departments d', 'd.Department_ID = a.Department_ID', 'left');

        $filterYear = trim((string) ($filters['graduation_year'] ?? $filters['graduationYear'] ?? ''));
        $filterCollege = trim((string) ($filters['college'] ?? ''));
        $filterCourse = trim((string) ($filters['course'] ?? $filters['department'] ?? ''));
        $filterReviewStatus = strtolower(trim((string) ($filters['review_status'] ?? $filters['reviewStatus'] ?? 'all')));

        if ($filterYear !== '') {
            $alumniBuilder->where('a.GraduationYear', $filterYear);
        }
        if ($filterCollege !== '') {
            $alumniBuilder->groupStart()
                ->where('c.Abbreviation', $filterCollege)
                ->orWhere('c.College_Name', $filterCollege)
                ->groupEnd();
        }
        if ($filterCourse !== '') {
            $alumniBuilder->groupStart()
                ->where('d.Department_Name', $filterCourse)
                ->orWhere('d.Abbreviation', $filterCourse)
                ->groupEnd();
        }
        if ($filterReviewStatus !== '' && $filterReviewStatus !== 'all') {
            $alumniBuilder->where('LOWER(a.Status)', $filterReviewStatus);
        }

        $cohortAlumni = $alumniBuilder->get()->getResultArray();
        if (empty($cohortAlumni)) {
            $cohortAlumni = $db->table('alumni a')
                ->select('a.id, a.alumni_id, a.FirstName, a.LastName, a.Email, a.College_ID, a.Department_ID, a.GraduationYear, a.Status, c.Abbreviation as college_abbr, c.College_Name, d.Department_Name, d.Abbreviation as dept_abbr')
                ->join('colleges c', 'c.College_ID = a.College_ID', 'left')
                ->join('departments d', 'd.Department_ID = a.Department_ID', 'left')
                ->get()
                ->getResultArray();
        }

        // 3. Map responses to status:
        //    - Employed (E): respondent chose "Employed"
        //    - Unemployed (UE): respondent answered the survey and chose any other choice (did not choose "Employed")
        //    - Not Tracked (NT): account did not respond/answer the survey
        $matchingResponseIds = array_values(array_filter(array_map('intval', array_column($matchingResponses, 'id'))));
        $statusByResponseId = [];
        if (! empty($matchingResponseIds)) {
            $answers = $db->table('answers a')
                ->select('a.response_id, a.question_id, a.choice_id, a.answer_text, c.choice_text, q.question_text')
                ->join('choices c', 'c.id = a.choice_id', 'left')
                ->join('questions q', 'q.id = a.question_id', 'left')
                ->whereIn('a.response_id', $matchingResponseIds)
                ->get()
                ->getResultArray();

            $answersByResp = [];
            foreach ($answers as $ans) {
                $rId = (int) ($ans['response_id'] ?? 0);
                if ($rId > 0) {
                    $answersByResp[$rId][] = $ans;
                }
            }

            foreach ($matchingResponseIds as $rId) {
                $respAnswers = $answersByResp[$rId] ?? [];
                $choseEmployed = false;
                $foundEmploymentQuestion = false;

                foreach ($respAnswers as $ans) {
                    $val = trim((string) ($ans['choice_text'] ?: $ans['answer_text'] ?: ''));
                    if ($val === '') {
                        continue;
                    }
                    $qText = strtolower(trim((string) ($ans['question_text'] ?? '')));
                    if (str_contains($qText, 'employ') || str_contains($qText, 'status') || str_contains($qText, 'job') || str_contains($qText, 'work') || str_contains($qText, 'occupation')) {
                        $foundEmploymentQuestion = true;
                        if (stripos($val, 'employed') !== false && stripos($val, 'unemployed') === false && stripos($val, 'not employed') === false) {
                            $choseEmployed = true;
                            break;
                        }
                    }
                }

                // Fallback: if no question text explicitly matched employment keywords, check any choice text for "Employed"
                if (! $foundEmploymentQuestion) {
                    foreach ($respAnswers as $ans) {
                        $val = trim((string) ($ans['choice_text'] ?: $ans['answer_text'] ?: ''));
                        if ($val !== '' && stripos($val, 'employed') !== false && stripos($val, 'unemployed') === false && stripos($val, 'not employed') === false) {
                            $choseEmployed = true;
                            break;
                        }
                    }
                }

                $statusByResponseId[$rId] = $choseEmployed ? 'Employed' : 'Unemployed';
            }
        }

        $statusByEmail = [];
        foreach ($matchingResponses as $mr) {
            $email = strtolower(trim((string) ($mr['email'] ?? '')));
            if ($email === '' || isset($statusByEmail[$email])) continue;
            $rId = (int) ($mr['id'] ?? 0);
            $status = $statusByResponseId[$rId] ?? 'Unemployed';
            $statusByEmail[$email] = $status;
        }

        // 4. Aggregate cohort into breakdown and College -> Department matrix
        $breakdown = [
            'Employed' => ['label' => 'Employed', 'total' => 0, 'colleges' => array_fill_keys(array_keys($collegeList), 0)],
            'Unemployed' => ['label' => 'Unemployed', 'total' => 0, 'colleges' => array_fill_keys(array_keys($collegeList), 0)],
            'Not Tracked' => ['label' => 'Not Tracked', 'total' => 0, 'colleges' => array_fill_keys(array_keys($collegeList), 0)],
        ];

        $statusDeptTally = [
            'Employed' => [],
            'Unemployed' => [],
            'Not Tracked' => [],
        ];

        $matchedCohortEmails = [];
        foreach ($cohortAlumni as $a) {
            $email = strtolower(trim((string) ($a['Email'] ?? '')));
            [$colKey, $deptKey] = $this->resolveCollegeAndDepartmentKeys(
                $hierarchy,
                (int) ($a['College_ID'] ?? 0),
                trim((string) ($a['college_abbr'] ?? $a['College_Name'] ?? '')),
                (int) ($a['Department_ID'] ?? 0),
                trim((string) ($a['Department_Name'] ?? '')),
                trim((string) ($a['dept_abbr'] ?? ''))
            );

            if ($email !== '' && isset($statusByEmail[$email])) {
                $st = $statusByEmail[$email];
                $matchedCohortEmails[$email] = true;
            } else {
                $st = 'Not Tracked';
            }

            $breakdown[$st]['total']++;
            $breakdown[$st]['colleges'][$colKey] = ($breakdown[$st]['colleges'][$colKey] ?? 0) + 1;
            $statusDeptTally[$st][$colKey][$deptKey] = ($statusDeptTally[$st][$colKey][$deptKey] ?? 0) + 1;
        }

        foreach ($statusByEmail as $email => $st) {
            if (! isset($matchedCohortEmails[$email])) {
                $respCollege = (string) array_key_first($hierarchy);
                $respDept = (string) array_key_first($hierarchy[$respCollege]['departments']);
                foreach ($matchingResponses as $mr) {
                    if (strtolower(trim((string) ($mr['email'] ?? ''))) === $email) {
                        [$respCollege, $respDept] = $this->resolveCollegeAndDepartmentKeys(
                            $hierarchy,
                            (int) ($mr['college_id'] ?? 0),
                            trim((string) ($mr['college_abbr'] ?? $mr['college'] ?? '')),
                            (int) ($mr['department_id'] ?? 0),
                            trim((string) ($mr['course'] ?? '')),
                            trim((string) ($mr['dept_abbr'] ?? ''))
                        );
                        break;
                    }
                }
                $breakdown[$st]['total']++;
                $breakdown[$st]['colleges'][$respCollege] = ($breakdown[$st]['colleges'][$respCollege] ?? 0) + 1;
                $statusDeptTally[$st][$respCollege][$respDept] = ($statusDeptTally[$st][$respCollege][$respDept] ?? 0) + 1;
            }
        }

        $totalAlumni = array_sum(array_column($breakdown, 'total'));
        $slices = [];
        $statusColors = [
            'Employed' => '#1e4a76',
            'Unemployed' => '#dc2626',
            'Not Tracked' => '#64748b',
        ];

        foreach ($breakdown as $stKey => &$stInfo) {
            $stTotal = (int) $stInfo['total'];
            $stPct = $totalAlumni > 0 ? round(($stTotal / $totalAlumni) * 100, 1) : 0.0;
            $stInfo['percentage'] = $stPct;
            $stInfo['color'] = $statusColors[$stKey] ?? '#0284c7';

            $collegeDetails = [];
            foreach ($stInfo['colleges'] as $colAbbr => $colCount) {
                $colPct = $stTotal > 0 ? round(($colCount / $stTotal) * 100, 1) : 0.0;
                $collegeDetails[$colAbbr] = [
                    'abbr' => $colAbbr,
                    'name' => $collegeList[$colAbbr] ?? $colAbbr,
                    'count' => $colCount,
                    'percentage' => $colPct,
                    'formatted' => $colAbbr . ' - ' . number_format($colCount) . ' - ' . number_format($colPct, 1) . '%',
                ];
            }
            $stInfo['college_breakdown'] = $collegeDetails;

            $slices[] = [
                'label' => $stKey,
                'value' => $stTotal,
                'percentage' => $stPct,
                'color' => $statusColors[$stKey] ?? '#0284c7',
            ];
        }
        unset($stInfo);

        // Build College -> Department Breakdown Matrix for Tracer Status Overview
        $collegeMatrix = [];
        foreach ($hierarchy as $colKey => $colInfo) {
            $colTotal = 0;
            $colItems = [];
            foreach ($statusColors as $stKey => $stColor) {
                $stColCnt = 0;
                foreach ($colInfo['departments'] as $dKey => $_dInfo) {
                    $stColCnt += (int) ($statusDeptTally[$stKey][$colKey][$dKey] ?? 0);
                }
                $colTotal += $stColCnt;
                $colItems[$stKey] = [
                    'label' => $stKey,
                    'count' => $stColCnt,
                    'percentage' => 0.0,
                    'color' => $stColor,
                ];
            }
            foreach ($colItems as &$ci) {
                $ci['percentage'] = $colTotal > 0 ? round(($ci['count'] / $colTotal) * 100, 1) : 0.0;
            }
            unset($ci);

            $deptMatrix = [];
            foreach ($colInfo['departments'] as $dKey => $dInfo) {
                $dTotal = 0;
                $dItems = [];
                foreach ($statusColors as $stKey => $stColor) {
                    $dCnt = (int) ($statusDeptTally[$stKey][$colKey][$dKey] ?? 0);
                    $dTotal += $dCnt;
                    $dItems[] = [
                        'label' => $stKey,
                        'count' => $dCnt,
                        'percentage' => 0.0,
                        'color' => $stColor,
                    ];
                }
                foreach ($dItems as &$di) {
                    $di['percentage'] = $dTotal > 0 ? round(($di['count'] / $dTotal) * 100, 1) : 0.0;
                }
                unset($di);

                $deptMatrix[$dKey] = [
                    'id' => $dInfo['id'],
                    'key' => $dKey,
                    'abbr' => $dInfo['abbr'],
                    'name' => $dInfo['name'],
                    'label' => $dInfo['label'],
                    'total' => $dTotal,
                    'percentage_of_all' => $totalAlumni > 0 ? round(($dTotal / $totalAlumni) * 100, 1) : 0.0,
                    'percentage_of_college' => $colTotal > 0 ? round(($dTotal / $colTotal) * 100, 1) : 0.0,
                    'items' => $dItems,
                ];
            }

            $collegeMatrix[$colKey] = [
                'id' => $colInfo['id'],
                'key' => $colKey,
                'abbr' => $colInfo['abbr'],
                'name' => $colInfo['name'],
                'total' => $colTotal,
                'percentage' => $totalAlumni > 0 ? round(($colTotal / $totalAlumni) * 100, 1) : 0.0,
                'items' => array_values($colItems),
                'departments' => $deptMatrix,
            ];
        }

        return [
            'total_alumni' => $totalAlumni,
            'total_responses' => count($matchingResponses),
            'colleges' => array_keys($collegeList),
            'breakdown' => $breakdown,
            'slices' => $slices,
            'college_matrix' => $collegeMatrix,
        ];
    }

    /**
     * Generates a high-clarity Donut/Pie Chart PNG file with slices, center total badge, and right-side legend
     */
    private function generateGdPieChartFile(array $slices, int $width = 360, int $height = 195): string
    {
        $tempPath = WRITEPATH . 'uploads/pie_' . uniqid('', true) . '.png';
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0777, true);
        }

        // Render at 2x resolution for smooth anti-aliased edges
        $scale = 2;
        $rw = $width * $scale;
        $rh = $height * $scale;

        $canvas = imagecreatetruecolor($rw, $rh);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $rw, $rh, $white);

        $total = array_sum(array_column($slices, 'value'));
        $cx = (int) ($rh * 0.50);
        $cy = (int) ($rh * 0.50);
        $diameter = (int) ($rh * 0.80);
        $innerDiameter = (int) ($diameter * 0.46);

        $defaultPalette = [
            [30, 74, 118],   // #1e4a76 Navy
            [220, 38, 38],   // #dc2626 Crimson
            [13, 148, 136],  // #0d9488 Teal
            [217, 119, 6],   // #d97706 Amber
            [124, 58, 237],  // #7c3aed Purple
            [37, 99, 235],   // #2563eb Blue
            [219, 39, 119],  // #db2777 Pink
            [100, 116, 139], // #64748b Slate
        ];

        $shadowColor = imagecolorallocate($canvas, 226, 232, 240);
        imagefilledellipse($canvas, $cx, $cy + 4, $diameter + 6, $diameter + 6, $shadowColor);

        if ($total <= 0) {
            $gray = imagecolorallocate($canvas, 203, 213, 225);
            imagefilledellipse($canvas, $cx, $cy, $diameter, $diameter, $gray);
        } else {
            $startAngle = 0.0;
            foreach ($slices as $idx => $s) {
                $val = (float) ($s['value'] ?? 0);
                if ($val <= 0) continue;
                $degrees = ($val / $total) * 360.0;
                $endAngle = $startAngle + $degrees;

                $rgb = $defaultPalette[$idx % count($defaultPalette)];
                if (! empty($s['color']) && preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $s['color'], $m)) {
                    $rgb = [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
                }

                $color = imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]);
                $sAng = (int) round($startAngle);
                $eAng = (int) round($endAngle);
                if ($eAng > $sAng) {
                    imagefilledarc($canvas, $cx, $cy, $diameter, $diameter, $sAng, $eAng, $color, IMG_ARC_PIE);
                }
                $startAngle = $endAngle;
            }
        }

        // Donut center cutout
        imagefilledellipse($canvas, $cx, $cy, $innerDiameter, $innerDiameter, $white);
        $ringBorder = imagecolorallocate($canvas, 226, 232, 240);
        imageellipse($canvas, $cx, $cy, $innerDiameter, $innerDiameter, $ringBorder);

        // Downsample 2x canvas onto final image
        $img = imagecreatetruecolor($width, $height);
        $finalWhite = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, $width, $height, $finalWhite);
        imagecopyresampled($img, $canvas, 0, 0, 0, 0, $width, $height, $rw, $rh);
        imagedestroy($canvas);

        // Center total text inside donut hole
        $fcx = (int) ($height * 0.50);
        $fcy = (int) ($height * 0.50);
        $navyText = imagecolorallocate($img, 30, 74, 118);
        $mutedText = imagecolorallocate($img, 100, 116, 139);
        $totalStr = number_format((int) $total);
        $lblStr = 'TOTAL';
        imagestring($img, 1, $fcx - (int) (strlen($lblStr) * 2.5), $fcy - 11, $lblStr, $mutedText);
        imagestring($img, 4, $fcx - (int) (strlen($totalStr) * 4), $fcy - 1, $totalStr, $navyText);

        // Right-side legend on final image
        $legendX = (int) ($height * 1.02);
        $legendY = 16;
        $textColor = imagecolorallocate($img, 15, 23, 42);
        $subColor = imagecolorallocate($img, 71, 85, 105);

        foreach ($slices as $idx => $s) {
            $val = (float) ($s['value'] ?? 0);
            $label = (string) ($s['label'] ?? '');
            $pct = $total > 0 ? round(($val / $total) * 100, 1) : 0.0;

            $rgb = $defaultPalette[$idx % count($defaultPalette)];
            if (! empty($s['color']) && preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $s['color'], $m)) {
                $rgb = [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
            }

            $boxColor = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
            imagefilledrectangle($img, $legendX, $legendY + 2, $legendX + 10, $legendY + 12, $boxColor);

            $shortLabel = strlen($label) > 20 ? substr($label, 0, 19) . '.' : $label;
            imagestring($img, 3, $legendX + 15, $legendY, $shortLabel, $textColor);
            imagestring($img, 2, $legendX + 15, $legendY + 13, number_format($val) . ' (' . number_format($pct, 1) . '%)', $subColor);

            $legendY += 31;
            if ($legendY > $height - 22) {
                break;
            }
        }

        imagepng($img, $tempPath);
        imagedestroy($img);
        return $tempPath;
    }

    /**
     * Reads a file and returns data:image/png;base64,... URI
     */
    private function getFileAsBase64Png(string $filePath): string
    {
        if (! file_exists($filePath)) {
            return '';
        }
        $data = file_get_contents($filePath);
        return 'data:image/png;base64,' . base64_encode($data);
    }

    /**
     * Renders the Top Centered GRAPH Showcase HTML block (Pie/Donut Chart + Overall Distribution Bars) for PDF
     */
    private function renderPdfTopGraphShowcaseHtml(string $pieBase64, array $slices, int $totalCount, string $unitLabel = 'Responses'): string
    {
        $html = '<div class="graph-top-showcase">';
        $html .= '<div class="graph-top-header">GRAPH &mdash; OVERALL VISUAL DISTRIBUTION (' . number_format($totalCount) . ' ' . htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8') . ')</div>';
        $html .= '<table class="graph-top-table"><tr>';

        // Left/Center: Donut / Pie Chart Image
        $html .= '<td class="graph-top-pie-cell">';
        if ($pieBase64 !== '') {
            $html .= '<img src="' . $pieBase64 . '" style="width: 245px; height: auto;" />';
        }
        $html .= '</td>';

        // Right: Overall Distribution Progress Bars & Summary
        $html .= '<td class="graph-top-bars-cell">';
        $html .= '<div class="graph-bars-heading">Overall Distribution Summary</div>';
        $html .= '<table class="overall-bars-table">';

        foreach ($slices as $s) {
            $lbl = htmlspecialchars((string) ($s['label'] ?? 'Option'), ENT_QUOTES, 'UTF-8');
            $val = (int) ($s['value'] ?? 0);
            $pct = (float) ($s['percentage'] ?? ($totalCount > 0 ? round(($val / $totalCount) * 100, 1) : 0.0));
            $color = htmlspecialchars((string) ($s['color'] ?? '#1e4a76'), ENT_QUOTES, 'UTF-8');
            $barWidth = max(0, min(100, (int) round($pct)));

            $html .= '<tr>';
            $html .= '<td class="ob-label-cell"><span class="color-dot" style="background-color: ' . $color . ';"></span>' . $lbl . '</td>';
            $html .= '<td class="ob-bar-cell">';
            $html .= '<table class="progress-track-table"><tr>';
            if ($barWidth > 0) {
                $html .= '<td style="width: ' . $barWidth . '%; background-color: ' . $color . '; height: 7px; padding: 0;"></td>';
            }
            if ($barWidth < 100) {
                $html .= '<td style="width: ' . (100 - $barWidth) . '%; background-color: #e2e8f0; height: 7px; padding: 0;"></td>';
            }
            $html .= '</tr></table>';
            $html .= '</td>';
            $html .= '<td class="ob-stat-cell"><strong>' . number_format($val) . '</strong> (' . number_format($pct, 1) . '%)</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        $html .= '</td>';
        $html .= '</tr></table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Renders the Bottom Multi-Column College | College | College & Department Breakdown Matrix HTML for PDF
     * Format:
     *   College 1       | College 2       | College 3
     *   Department 1    | Department 1    | Department 1
     *   Department 2    | Department 2    | Department 2
     */
    private function renderPdfCollegeDepartmentMatrixHtml(array $collegeMatrix, int $grandTotal, string $unitLabel = 'Responses'): string
    {
        if (empty($collegeMatrix)) {
            return '';
        }

        $html = '';
        // Render one column per college (stacked vertically, never side-by-side)
        $collegeChunks = array_chunk($collegeMatrix, 1, true);

        foreach ($collegeChunks as $chunk) {
            $colCount = count($chunk);
            $colWidthPct = round(100 / max(1, $colCount), 2);

            $html .= '<table class="college-dept-matrix">';

            // Row 1: College | College | College Headers
            $html .= '<tr class="matrix-college-row">';
            foreach ($chunk as $colKey => $colData) {
                $cAbbr = htmlspecialchars((string) ($colData['abbr'] ?? $colKey), ENT_QUOTES, 'UTF-8');
                $cName = htmlspecialchars((string) ($colData['name'] ?? $colKey), ENT_QUOTES, 'UTF-8');
                $cTotal = (int) ($colData['total'] ?? 0);
                $cPct = (float) ($colData['percentage'] ?? 0.0);

                // Build compact summary of choices/statuses for this college header
                $summaryParts = [];
                foreach (($colData['items'] ?? []) as $cItem) {
                    $iLbl = htmlspecialchars((string) ($cItem['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $iCnt = (int) ($cItem['count'] ?? 0);
                    $iPct = (float) ($cItem['percentage'] ?? 0.0);
                    $summaryParts[] = $iLbl . ': <strong>' . number_format($iCnt) . '</strong> (' . number_format($iPct, 1) . '%)';
                }

                $html .= '<td class="matrix-college-cell" style="width: ' . $colWidthPct . '%;">';
                $html .= '<div class="mc-title">' . $cAbbr . ' &mdash; ' . $cName . '</div>';
                $html .= '<div class="mc-sub">College Total: <strong>' . number_format($cTotal) . ' ' . htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8') . '</strong> (' . number_format($cPct, 1) . '% of Overall)</div>';
                if (! empty($summaryParts)) {
                    $html .= '<div class="mc-summary-strip">' . implode(' &nbsp;&bull;&nbsp; ', $summaryParts) . '</div>';
                }
                $html .= '</td>';
            }
            $html .= '</tr>';

            // Prepare aligned Department rows underneath each College column
            $deptLists = [];
            $maxDeptRows = 1;
            foreach ($chunk as $colKey => $colData) {
                $depts = array_values($colData['departments'] ?? []);
                $deptLists[$colKey] = $depts;
                if (count($depts) > $maxDeptRows) {
                    $maxDeptRows = count($depts);
                }
            }

            // Rows 2..N: Department | Department | Department
            for ($r = 0; $r < $maxDeptRows; $r++) {
                $html .= '<tr class="matrix-dept-row">';
                foreach ($chunk as $colKey => $colData) {
                    $dept = $deptLists[$colKey][$r] ?? null;
                    if ($dept !== null) {
                        $dAbbr = htmlspecialchars((string) ($dept['abbr'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $dName = htmlspecialchars((string) ($dept['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                        $dTitle = ($dAbbr !== '' && $dName !== '' && strcasecmp($dAbbr, $dName) !== 0)
                            ? ('<strong>' . $dAbbr . '</strong> &mdash; ' . $dName)
                            : ('<strong>' . ($dName ?: $dAbbr) . '</strong>');
                        $dTotal = (int) ($dept['total'] ?? 0);
                        $dPctAll = (float) ($dept['percentage_of_all'] ?? 0.0);
                        $dPctCol = (float) ($dept['percentage_of_college'] ?? 0.0);
                        $items = $dept['items'] ?? [];

                        $html .= '<td class="matrix-dept-cell" style="width: ' . $colWidthPct . '%;">';

                        // Department header line
                        $html .= '<table class="dept-hdr-line"><tr>';
                        $html .= '<td class="dh-name">' . $dTitle . '</td>';
                        $html .= '<td class="dh-badge"><span>' . number_format($dTotal) . ' (' . number_format($dPctAll, 1) . '%)</span></td>';
                        $html .= '</tr></table>';
                        $html .= '<div class="dept-share-meta">College Share: <strong>' . number_format($dPctCol, 1) . '%</strong> &nbsp;|&nbsp; Overall Share: <strong>' . number_format($dPctAll, 1) . '%</strong></div>';

                        // Stacked proportional bar for department items
                        $html .= '<table class="dept-stacked-bar"><tr>';
                        if ($dTotal > 0) {
                            $usedPct = 0;
                            foreach ($items as $it) {
                                $itCnt = (int) ($it['count'] ?? 0);
                                if ($itCnt <= 0) continue;
                                $itPct = max(1, (int) round(($itCnt / $dTotal) * 100));
                                if ($usedPct + $itPct > 100) {
                                    $itPct = max(1, 100 - $usedPct);
                                }
                                $usedPct += $itPct;
                                $itColor = htmlspecialchars((string) ($it['color'] ?? '#1e4a76'), ENT_QUOTES, 'UTF-8');
                                $html .= '<td style="width: ' . $itPct . '%; background-color: ' . $itColor . '; height: 5px; padding: 0;"></td>';
                            }
                            if ($usedPct < 100) {
                                $html .= '<td style="width: ' . (100 - $usedPct) . '%; background-color: #e2e8f0; height: 5px; padding: 0;"></td>';
                            }
                        } else {
                            $html .= '<td style="width: 100%; background-color: #e2e8f0; height: 5px; padding: 0;"></td>';
                        }
                        $html .= '</tr></table>';

                        // Item breakdown rows inside department cell
                        $html .= '<table class="dept-items-table">';
                        foreach ($items as $it) {
                            $itLbl = htmlspecialchars((string) ($it['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $itCnt = (int) ($it['count'] ?? 0);
                            $itPct = (float) ($it['percentage'] ?? 0.0);
                            $itColor = htmlspecialchars((string) ($it['color'] ?? '#1e4a76'), ENT_QUOTES, 'UTF-8');

                            $html .= '<tr>';
                            $html .= '<td class="di-lbl"><span class="color-dot" style="background-color: ' . $itColor . ';"></span>' . $itLbl . '</td>';
                            $html .= '<td class="di-val"><strong>' . number_format($itCnt) . '</strong> (' . number_format($itPct, 1) . '%)</td>';
                            $html .= '</tr>';
                        }
                        $html .= '</table>';

                        $html .= '</td>';
                    } else {
                        $html .= '<td class="matrix-dept-cell is-empty" style="width: ' . $colWidthPct . '%;">';
                        $html .= '<div class="dept-empty-dash">&mdash;</div>';
                        $html .= '</td>';
                    }
                }
                $html .= '</tr>';
            }

            $html .= '</table>';
        }

        return $html;
    }

    /**
     * Generates PDF Export for Survey Graphs Report using the Top GRAPH + Multi-Column College & Department Breakdown format
     */
    private function generateSurveyGraphsPdfReport(array $survey, array $selectedCategories, array $selectedQuestions, array $matchingResponses, string $filename, array $filters): string
    {
        $filepath = WRITEPATH . 'uploads/' . $filename . '.pdf';
        if (! is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0777, true);
        }

        $tempFilesToClean = [];

        // 1. Compile Graduate Tracer Overview
        $tracerOverview = $this->compileTracerStatusOverview($survey, $matchingResponses, $filters);
        $overviewPieFile = $this->generateGdPieChartFile($tracerOverview['slices'], 360, 195);
        $tempFilesToClean[] = $overviewPieFile;
        $overviewPieBase64 = $this->getFileAsBase64Png($overviewPieFile);

        $institutionName = htmlspecialchars($this->getInstitutionHeaderName(), ENT_QUOTES, 'UTF-8');
        $surveyTitle = htmlspecialchars((string) ($survey['title'] ?? 'Survey Report'), ENT_QUOTES, 'UTF-8');
        $filterSummary = htmlspecialchars($this->buildFilterSummaryString($filters) ?: 'All survey respondents (No filters applied)', ENT_QUOTES, 'UTF-8');
        $totalRespondents = count($matchingResponses);
        $totalQuestions = count($selectedQuestions);
        $totalCohortAlumni = (int) ($tracerOverview['total_alumni'] ?? $totalRespondents);
        $generatedAt = date('F d, Y h:i A');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>
            @page { margin: 9mm 9mm; }
            body { font-family: Helvetica, Arial, sans-serif; font-size: 8pt; color: #1e293b; line-height: 1.32; margin: 0; padding: 0; }
            .header-banner { border-bottom: 2px solid #1e4a76; padding-bottom: 6px; margin-bottom: 8px; }
            .inst-name { font-size: 12.5pt; font-weight: bold; color: #1e4a76; text-transform: uppercase; margin-bottom: 2px; }
            .doc-title { font-size: 10.5pt; font-weight: bold; color: #0f172a; margin-bottom: 2px; letter-spacing: 0.02em; }
            .survey-title { font-size: 8.5pt; color: #475569; font-weight: 600; }

            .meta-kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
            .kpi-box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 6px; text-align: center; }
            .kpi-value { font-size: 11pt; font-weight: bold; color: #1e4a76; margin-bottom: 1px; }
            .kpi-label { font-size: 6.5pt; font-weight: bold; color: #64748b; text-transform: uppercase; }

            .meta-info-box { background: #f1f5f9; border-left: 3px solid #1e4a76; padding: 4px 8px; font-size: 7.5pt; margin-bottom: 10px; }

            /* Unified Card Container */
            .report-graph-card { border: 1px solid #cbd5e1; border-radius: 5px; margin-bottom: 14px; background: #ffffff; }
            .card-main-banner { background: #1e4a76; color: #ffffff; padding: 6px 10px; font-size: 9pt; font-weight: bold; }

            .category-banner { background: #0f172a; color: #ffffff; padding: 5px 10px; font-size: 9pt; font-weight: bold; text-transform: uppercase; border-radius: 4px; margin-top: 12px; margin-bottom: 8px; letter-spacing: 0.03em; }

            .q-card-header { background: #f8fafc; border-bottom: 1px solid #cbd5e1; padding: 6px 10px; }
            .q-num { font-weight: bold; color: #1e4a76; font-size: 9pt; margin-right: 4px; }
            .q-text { font-weight: bold; color: #0f172a; font-size: 8.5pt; }
            .q-badge { display: inline-block; background: #e0e7ff; color: #3730a3; font-size: 6.5pt; font-weight: bold; padding: 1px 5px; border-radius: 4px; margin-left: 5px; text-transform: uppercase; }
            .q-meta-line { font-size: 7pt; color: #64748b; margin-top: 2px; }

            /* Top Centered GRAPH Showcase Block */
            .graph-top-showcase { background: #ffffff; border-bottom: 2px solid #cbd5e1; page-break-inside: avoid; }
            .graph-top-header { background: #eff6ff; color: #1e4a76; text-align: center; font-weight: bold; font-size: 7.5pt; letter-spacing: 0.06em; padding: 4px 8px; border-bottom: 1px solid #dbeafe; text-transform: uppercase; }
            .graph-top-table { width: 100%; border-collapse: collapse; }
            .graph-top-pie-cell { width: 46%; padding: 8px 10px; text-align: center; vertical-align: middle; border-right: 1px dashed #e2e8f0; }
            .graph-top-bars-cell { width: 54%; padding: 8px 12px; vertical-align: middle; }
            .graph-bars-heading { font-size: 7.5pt; font-weight: bold; color: #334155; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.03em; }

            .overall-bars-table { width: 100%; border-collapse: collapse; }
            .overall-bars-table td { padding: 2.5px 3px; vertical-align: middle; font-size: 7.5pt; }
            .ob-label-cell { width: 38%; color: #0f172a; font-weight: 600; }
            .ob-bar-cell { width: 36%; }
            .ob-stat-cell { width: 26%; text-align: right; color: #1e4a76; white-space: nowrap; }
            .progress-track-table { width: 100%; border-collapse: collapse; border-radius: 3px; overflow: hidden; }

            .color-dot { display: inline-block; width: 7px; height: 7px; border-radius: 2px; margin-right: 4px; vertical-align: middle; }

            /* Bottom Multi-Column College | College | College & Department Matrix */
            .college-dept-matrix { width: 100%; border-collapse: collapse; table-layout: fixed; }
            .matrix-college-row td.matrix-college-cell { background: #1e3a5f; color: #ffffff; padding: 6px 8px; vertical-align: top; border-right: 1px solid #335c85; border-bottom: 2px solid #0f172a; text-align: center; }
            .matrix-college-row td.matrix-college-cell:last-child { border-right: none; }
            .mc-title { font-size: 8.5pt; font-weight: bold; color: #ffffff; margin-bottom: 2px; }
            .mc-sub { font-size: 7pt; color: #bae6fd; margin-bottom: 3px; }
            .mc-summary-strip { font-size: 6.5pt; color: #f8fafc; background: #152c4a; padding: 2px 5px; border-radius: 3px; display: inline-block; }

            .matrix-dept-row td.matrix-dept-cell { padding: 6px 8px; vertical-align: top; border-right: 1px solid #cbd5e1; border-bottom: 1px solid #cbd5e1; background: #ffffff; }
            .matrix-dept-row td.matrix-dept-cell:last-child { border-right: none; }
            .matrix-dept-row td.matrix-dept-cell.is-empty { background: #f8fafc; text-align: center; vertical-align: middle; }
            .dept-empty-dash { color: #cbd5e1; font-size: 11pt; font-weight: bold; padding: 12px 0; }

            .dept-hdr-line { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
            .dept-hdr-line td { padding: 0; vertical-align: top; }
            .dh-name { font-size: 7.5pt; color: #0f172a; padding-right: 4px; }
            .dh-badge { text-align: right; white-space: nowrap; }
            .dh-badge span { display: inline-block; background: #eff6ff; color: #1e4a76; border: 1px solid #bfdbfe; font-size: 6.5pt; font-weight: bold; padding: 1px 4px; border-radius: 3px; }
            .dept-share-meta { font-size: 6.5pt; color: #64748b; margin-bottom: 4px; }

            .dept-stacked-bar { width: 100%; border-collapse: collapse; margin-bottom: 4px; }

            .dept-items-table { width: 100%; border-collapse: collapse; }
            .dept-items-table td { padding: 1.5px 0; font-size: 7pt; border-bottom: 1px dotted #f1f5f9; }
            .dept-items-table tr:last-child td { border-bottom: none; }
            .di-lbl { color: #334155; }
            .di-val { text-align: right; color: #0f172a; white-space: nowrap; }

            .mean-pill { margin: 5px 8px 6px 8px; padding: 4px 8px; background: #eff6ff; border-left: 3px solid #2563eb; font-size: 7.5pt; color: #1e40af; }
            .footer-note { font-size: 7pt; color: #94a3b8; text-align: center; margin-top: 16px; border-top: 1px solid #e2e8f0; padding-top: 5px; }
        </style></head><body>';

        $html .= '<div class="header-banner">
            <div class="inst-name">' . $institutionName . '</div>
            <div class="doc-title">SURVEY RESULTS &amp; GRAPHICAL ANALYSIS REPORT</div>
            <div class="survey-title">Survey: ' . $surveyTitle . '</div>
        </div>';

        $html .= '<table class="meta-kpi-table"><tr>
            <td style="width: 25%; padding-right: 4px;"><div class="kpi-box"><div class="kpi-value">' . number_format($totalCohortAlumni) . '</div><div class="kpi-label">Total Alumni Cohort</div></div></td>
            <td style="width: 25%; padding: 0 2px;"><div class="kpi-box"><div class="kpi-value">' . number_format($totalRespondents) . '</div><div class="kpi-label">Total Survey Responses</div></div></td>
            <td style="width: 25%; padding: 0 2px;"><div class="kpi-box"><div class="kpi-value">' . number_format($totalQuestions) . '</div><div class="kpi-label">Questions Analyzed</div></div></td>
            <td style="width: 25%; padding-left: 4px;"><div class="kpi-box"><div class="kpi-value">Visual</div><div class="kpi-label">Report Type: Graphs</div></div></td>
        </tr></table>';

        $html .= '<div class="meta-info-box">
            <strong>Generated On:</strong> ' . $generatedAt . ' &nbsp;|&nbsp;
            <strong>Filter Scope:</strong> ' . $filterSummary . '
        </div>';

        // 1. Graduate Tracer Status Overview Card: Top GRAPH + Bottom College | College -> Department Matrix
        $html .= '<div class="report-graph-card">';
        $html .= '<div class="card-main-banner">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="color: #ffffff; font-weight: bold; font-size: 8.5pt; text-transform: uppercase;">Graduate Tracer Status &amp; College / Department Breakdown</td>
                    <td style="color: #cbd5e1; font-size: 7pt; text-align: right; font-weight: normal;">Cohort Denominator: ' . number_format($totalCohortAlumni) . ' Alumni</td>
                </tr>
            </table>
        </div>';

        $html .= $this->renderPdfTopGraphShowcaseHtml($overviewPieBase64, $tracerOverview['slices'], $totalCohortAlumni, 'Alumni');
        $html .= $this->renderPdfCollegeDepartmentMatrixHtml($tracerOverview['college_matrix'] ?? [], $totalCohortAlumni, 'Alumni');
        $html .= '</div>';

        // 2. Categorical Question Hierarchy: Top GRAPH + Bottom College | College -> Department Matrix
        $globalQIdx = 1;

        foreach ($selectedCategories as $cat) {
            $catName = htmlspecialchars((string) ($cat['name'] ?? 'Category'), ENT_QUOTES, 'UTF-8');
            $questions = $cat['questions'] ?? [];
            if (empty($questions)) continue;

            $html .= '<div class="category-banner">' . $catName . '</div>';

            foreach ($questions as $q) {
                $qText = htmlspecialchars((string) ($q['text'] ?? 'Question'), ENT_QUOTES, 'UTF-8');
                $qType = strtolower(trim((string) ($q['type'] ?? 'text')));
                $choiceStats = $q['choiceStats'] ?? [];
                $collegeMatrix = $q['collegeMatrix'] ?? [];
                $qTotalResponses = (int) ($q['totalResponses'] ?? 0);
                $respRate = $totalRespondents > 0 ? round(($qTotalResponses / $totalRespondents) * 100, 1) : 0.0;

                $typeBadge = match ($qType) {
                    'multiple_choice' => 'Multiple Choice',
                    'rating' => 'Rating Scale',
                    default => 'Open Response',
                };

                $html .= '<div class="report-graph-card">';
                $html .= '<div class="q-card-header">';
                $html .= '<span class="q-num">Q' . $globalQIdx . '.</span> <span class="q-text">' . $qText . '</span> <span class="q-badge">' . $typeBadge . '</span>';
                $html .= '<div class="q-meta-line">Total Answers Gathered: <strong>' . number_format($qTotalResponses) . '</strong> (' . number_format($respRate, 1) . '% Response Rate)</div>';
                $html .= '</div>';

                if (! empty($choiceStats)) {
                    $qSlices = [];
                    $sumWeighted = 0;
                    $sumCount = 0;

                    foreach ($choiceStats as $cs) {
                        $qSlices[] = [
                            'label' => $cs['choice'],
                            'value' => $cs['count'],
                            'percentage' => $cs['percentage'] ?? 0.0,
                            'color' => $cs['color'] ?? '#1e4a76',
                        ];

                        if ($qType === 'rating') {
                            if (preg_match('/^\s*(\d+)/', (string) ($cs['choice'] ?? ''), $m)) {
                                $sumWeighted += ((int) $m[1] * (int) $cs['count']);
                                $sumCount += (int) $cs['count'];
                            }
                        }
                    }

                    $qPieFile = $this->generateGdPieChartFile($qSlices, 360, 185);
                    $tempFilesToClean[] = $qPieFile;
                    $qPieBase64 = $this->getFileAsBase64Png($qPieFile);

                    // Top GRAPH section
                    $html .= $this->renderPdfTopGraphShowcaseHtml($qPieBase64, $qSlices, $qTotalResponses, 'Responses');

                    // Bottom College | College | College -> Department Matrix
                    $html .= $this->renderPdfCollegeDepartmentMatrixHtml($collegeMatrix, $qTotalResponses, 'Responses');

                    if ($qType === 'rating' && $sumCount > 0) {
                        $meanRating = round($sumWeighted / $sumCount, 2);
                        $maxRating = ! empty($q['rating_max']) ? (int) $q['rating_max'] : 5;
                        $html .= '<div class="mean-pill"><strong>Rating Mean Score:</strong> ' . number_format($meanRating, 2) . ' / ' . $maxRating . ' &nbsp;|&nbsp; <em>Total Rated Responses: ' . number_format($sumCount) . '</em></div>';
                    }
                } else {
                    $html .= '<div style="padding: 10px; font-style: italic; color: #94a3b8;">No response choices recorded for this question.</div>';
                }

                $html .= '</div>';
                $globalQIdx++;
            }
        }

        $html .= '<div class="footer-note">Confidential — System Generated Survey Graphical Analysis Report — ' . $institutionName . '</div>';
        $html .= '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($filepath, $dompdf->output());

        // Clean up temporary generated PNG files
        foreach ($tempFilesToClean as $tf) {
            if (file_exists($tf)) {
                @unlink($tf);
            }
        }

        return $filepath;
    }

    /**
     * Helper to add the Top GRAPH + Multi-Column College & Department Breakdown table to a Word section
     */
    private function addWordGraphAndCollegeDeptMatrix(
        \PhpOffice\PhpWord\Element\Section $section,
        string $pieFile,
        array $slices,
        array $collegeMatrix,
        int $grandTotal,
        string $unitLabel = 'Responses'
    ): void {
        // 1. Top Centered GRAPH Table (Pie Chart + Overall Summary)
        $graphTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'CBD5E1', 'cellMargin' => 80]);
        $graphTable->addRow();
        $topBannerCell = $graphTable->addCell(9600, ['gridSpan' => 2, 'bgColor' => 'EFF6FF']);
        $topBannerCell->addText(
            'GRAPH — OVERALL VISUAL DISTRIBUTION (' . number_format($grandTotal) . ' ' . strtoupper($unitLabel) . ')',
            ['bold' => true, 'size' => 8.5, 'color' => '1E4A76'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 20]
        );

        $graphTable->addRow();
        $pieCell = $graphTable->addCell(4600, ['bgColor' => 'FFFFFF', 'valign' => 'center']);
        if (file_exists($pieFile)) {
            $pieCell->addImage($pieFile, [
                'width' => 225,
                'height' => 122,
                'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
            ]);
        }

        $summaryCell = $graphTable->addCell(5000, ['bgColor' => 'F8FAFC', 'valign' => 'center']);
        $summaryCell->addText('Overall Distribution Summary', ['bold' => true, 'size' => 8.5, 'color' => '1E4A76'], ['spaceAfter' => 40]);
        foreach ($slices as $s) {
            $lbl = (string) ($s['label'] ?? 'Option');
            $val = (int) ($s['value'] ?? 0);
            $pct = (float) ($s['percentage'] ?? ($grandTotal > 0 ? round(($val / $grandTotal) * 100, 1) : 0.0));
            $summaryCell->addText(
                '• ' . $lbl . ': ' . number_format($val) . ' (' . number_format($pct, 1) . '%)',
                ['bold' => true, 'size' => 8.5, 'color' => '0F172A'],
                ['spaceAfter' => 25]
            );
        }

        // 2. Bottom Single-Column College -> Department Breakdown Table (one college per column block)
        if (empty($collegeMatrix)) {
            return;
        }

        $collegeChunks = array_chunk($collegeMatrix, 1, true);
        foreach ($collegeChunks as $chunk) {
            $colCount = count($chunk);
            $cellWidth = (int) floor(9600 / max(1, $colCount));

            $matrixTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'CBD5E1', 'cellMargin' => 70]);

            // Row 1: College Headers (College | College | College)
            $matrixTable->addRow();
            foreach ($chunk as $colKey => $colData) {
                $cAbbr = (string) ($colData['abbr'] ?? $colKey);
                $cName = (string) ($colData['name'] ?? $colKey);
                $cTotal = (int) ($colData['total'] ?? 0);
                $cPct = (float) ($colData['percentage'] ?? 0.0);

                $colHdrCell = $matrixTable->addCell($cellWidth, ['bgColor' => '1E3A5F']);
                $colHdrCell->addText(
                    $cAbbr . ' — ' . $cName,
                    ['bold' => true, 'size' => 9, 'color' => 'FFFFFF'],
                    ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 20]
                );
                $colHdrCell->addText(
                    'College Total: ' . number_format($cTotal) . ' (' . number_format($cPct, 1) . '%)',
                    ['size' => 8, 'color' => 'BAE6FD'],
                    ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 10]
                );
            }

            // Prepare aligned Department rows under each College column
            $deptLists = [];
            $maxDeptRows = 1;
            foreach ($chunk as $colKey => $colData) {
                $depts = array_values($colData['departments'] ?? []);
                $deptLists[$colKey] = $depts;
                if (count($depts) > $maxDeptRows) {
                    $maxDeptRows = count($depts);
                }
            }

            // Rows 2..N: Department | Department | Department
            for ($r = 0; $r < $maxDeptRows; $r++) {
                $matrixTable->addRow();
                foreach ($chunk as $colKey => $colData) {
                    $dept = $deptLists[$colKey][$r] ?? null;
                    if ($dept !== null) {
                        $dAbbr = (string) ($dept['abbr'] ?? '');
                        $dName = (string) ($dept['name'] ?? '');
                        $dTitle = ($dAbbr !== '' && $dName !== '' && strcasecmp($dAbbr, $dName) !== 0)
                            ? ($dAbbr . ' — ' . $dName)
                            : ($dName ?: $dAbbr);
                        $dTotal = (int) ($dept['total'] ?? 0);
                        $dPctAll = (float) ($dept['percentage_of_all'] ?? 0.0);
                        $dPctCol = (float) ($dept['percentage_of_college'] ?? 0.0);

                        $dCell = $matrixTable->addCell($cellWidth, ['bgColor' => 'FFFFFF']);
                        $dCell->addText($dTitle, ['bold' => true, 'size' => 8.5, 'color' => '0F172A'], ['spaceAfter' => 15]);
                        $dCell->addText(
                            'Total: ' . number_format($dTotal) . ' (' . number_format($dPctAll, 1) . '% Overall | ' . number_format($dPctCol, 1) . '% of College)',
                            ['italic' => true, 'size' => 7.5, 'color' => '1E4A76'],
                            ['spaceAfter' => 30]
                        );

                        foreach (($dept['items'] ?? []) as $it) {
                            $itLbl = (string) ($it['label'] ?? '');
                            $itCnt = (int) ($it['count'] ?? 0);
                            $itPct = (float) ($it['percentage'] ?? 0.0);
                            $dCell->addText(
                                '  • ' . $itLbl . ': ' . number_format($itCnt) . ' (' . number_format($itPct, 1) . '%)',
                                ['size' => 8, 'color' => '334155'],
                                ['spaceAfter' => 10]
                            );
                        }
                    } else {
                        $emptyCell = $matrixTable->addCell($cellWidth, ['bgColor' => 'F8FAFC', 'valign' => 'center']);
                        $emptyCell->addText('—', ['bold' => true, 'size' => 9, 'color' => 'CBD5E1'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                    }
                }
            }
        }
    }

    /**
     * Generates Word DOCX Export for Survey Graphs Report with Top GRAPH and Multi-Column College & Department Breakdown
     */
    private function generateSurveyGraphsDocxReport(array $survey, array $selectedCategories, array $selectedQuestions, array $matchingResponses, string $filename, array $filters): string
    {
        $filepath = WRITEPATH . 'uploads/' . $filename . '.docx';
        if (! is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0777, true);
        }

        $tempFilesToClean = [];

        // 1. Compile Graduate Tracer Overview
        $tracerOverview = $this->compileTracerStatusOverview($survey, $matchingResponses, $filters);
        $overviewPieFile = $this->generateGdPieChartFile($tracerOverview['slices'], 360, 195);
        $tempFilesToClean[] = $overviewPieFile;

        \PhpOffice\PhpWord\Settings::setZipClass(\PhpOffice\PhpWord\Settings::PCLZIP);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Tahoma');
        $phpWord->setDefaultFontSize(9.5);

        $section = $phpWord->addSection([
            'orientation' => 'portrait',
            'marginTop' => 640,
            'marginRight' => 640,
            'marginBottom' => 640,
            'marginLeft' => 640,
        ]);

        $institutionName = $this->getInstitutionHeaderName();
        $surveyTitle = (string) ($survey['title'] ?? 'Survey Report');
        $filterSummary = $this->buildFilterSummaryString($filters) ?: 'All survey respondents (No filters applied)';
        $totalRespondents = count($matchingResponses);
        $totalCohortAlumni = (int) ($tracerOverview['total_alumni'] ?? $totalRespondents);

        // Document Title
        $section->addText($institutionName, ['bold' => true, 'size' => 13, 'color' => '1E4A76'], ['spaceAfter' => 40]);
        $section->addText('SURVEY RESULTS & GRAPHICAL ANALYSIS REPORT', ['bold' => true, 'size' => 11.5, 'color' => '0F172A'], ['spaceAfter' => 40]);
        $section->addText('Survey: ' . $surveyTitle, ['bold' => true, 'size' => 9.5, 'color' => '475569'], ['spaceAfter' => 100]);

        // Metadata box
        $metaTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'CBD5E1', 'cellMargin' => 70]);
        $metaTable->addRow();
        $metaTable->addCell(4800, ['bgColor' => 'F8FAFC'])->addText('Total Alumni Cohort: ' . number_format($totalCohortAlumni), ['bold' => true, 'size' => 9]);
        $metaTable->addCell(4800, ['bgColor' => 'F8FAFC'])->addText('Total Responses: ' . number_format($totalRespondents) . ' | Graphs Report', ['bold' => true, 'size' => 9]);
        $metaTable->addRow();
        $metaCell = $metaTable->addCell(9600, ['gridSpan' => 2, 'bgColor' => 'F1F5F9']);
        $metaCell->addText('Filters: ' . $filterSummary, ['size' => 8.5, 'color' => '475569']);
        $section->addTextBreak(1);

        // 1. Graduate Tracer Status Overview: Top GRAPH + College | College -> Department Matrix
        $section->addText(
            'GRADUATE TRACER STATUS & COLLEGE / DEPARTMENT BREAKDOWN',
            ['bold' => true, 'size' => 10, 'color' => 'FFFFFF'],
            ['shading' => ['fill' => '1E4A76'], 'spaceBefore' => 80, 'spaceAfter' => 40]
        );
        $this->addWordGraphAndCollegeDeptMatrix(
            $section,
            $overviewPieFile,
            $tracerOverview['slices'],
            $tracerOverview['college_matrix'] ?? [],
            $totalCohortAlumni,
            'Alumni'
        );
        $section->addTextBreak(1);

        // 2. Questions: Top GRAPH + College | College -> Department Matrix
        $globalQIdx = 1;

        foreach ($selectedCategories as $cat) {
            $catName = (string) ($cat['name'] ?? 'Category');
            $questions = $cat['questions'] ?? [];
            if (empty($questions)) continue;

            $section->addText(
                strtoupper($catName),
                ['bold' => true, 'size' => 10.5, 'color' => 'FFFFFF'],
                ['shading' => ['fill' => '0F172A'], 'spaceBefore' => 140, 'spaceAfter' => 80]
            );

            foreach ($questions as $q) {
                $qText = (string) ($q['text'] ?? 'Question');
                $choiceStats = $q['choiceStats'] ?? [];
                $collegeMatrix = $q['collegeMatrix'] ?? [];
                $qTotalResponses = (int) ($q['totalResponses'] ?? 0);
                $respRate = $totalRespondents > 0 ? round(($qTotalResponses / $totalRespondents) * 100, 1) : 0.0;

                $section->addText('Q' . $globalQIdx . '. ' . $qText, ['bold' => true, 'size' => 9.5, 'color' => '0F172A'], ['spaceBefore' => 100, 'spaceAfter' => 20]);
                $section->addText(
                    'Total Answers Gathered: ' . number_format($qTotalResponses) . ' (' . number_format($respRate, 1) . '% Response Rate)',
                    ['italic' => true, 'size' => 8, 'color' => '64748B'],
                    ['spaceAfter' => 40]
                );

                if (! empty($choiceStats)) {
                    $qSlices = [];
                    foreach ($choiceStats as $cs) {
                        $qSlices[] = [
                            'label' => $cs['choice'],
                            'value' => $cs['count'],
                            'percentage' => $cs['percentage'] ?? 0.0,
                            'color' => $cs['color'] ?? '#1e4a76',
                        ];
                    }
                    $qPieFile = $this->generateGdPieChartFile($qSlices, 360, 185);
                    $tempFilesToClean[] = $qPieFile;

                    $this->addWordGraphAndCollegeDeptMatrix(
                        $section,
                        $qPieFile,
                        $qSlices,
                        $collegeMatrix,
                        $qTotalResponses,
                        'Responses'
                    );
                }
                $globalQIdx++;
            }
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);

        // Clean up temporary generated PNG files
        foreach ($tempFilesToClean as $tf) {
            if (file_exists($tf)) {
                @unlink($tf);
            }
        }

        return $filepath;
    }

    /**
     * Helper to write a Top GRAPH + Multi-Column College | College -> Department Breakdown block into an Excel Worksheet
     */
    private function writeExcelGraphAndCollegeDeptBlock(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int &$row,
        string $blockTitle,
        string $pieFile,
        array $slices,
        array $collegeMatrix,
        int $grandTotal,
        string $unitLabel = 'Responses'
    ): void {
        $collegeList = array_values($collegeMatrix);
        // Single-column layout per college across 4 sheet columns (A:D)
        $totalSheetCols = 4;
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalSheetCols);

        // 1. Section Title Banner
        $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
        $sheet->setCellValue("A{$row}", $blockTitle . ' (Total: ' . number_format($grandTotal) . ' ' . $unitLabel . ')');
        $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E4A76'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        // 2. Top GRAPH Sub-banner
        $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
        $sheet->setCellValue("A{$row}", 'GRAPH — OVERALL VISUAL DISTRIBUTION');
        $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1E4A76'], 'size' => 9],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EFF6FF'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);
        $row++;

        // Embed Pie Chart on Left (Col A-B) and Overall Choice Summary Table on Right (Col C-D)
        $graphStartRow = $row;
        if (file_exists($pieFile)) {
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setName($blockTitle);
            $drawing->setPath($pieFile);
            $drawing->setCoordinates("A{$graphStartRow}");
            $drawing->setOffsetX(10);
            $drawing->setOffsetY(4);
            $drawing->setWidth(240);
            $drawing->setHeight(130);
            $drawing->setWorksheet($sheet);
        }

        $sheet->setCellValue("C{$row}", 'Option / Status');
        $sheet->setCellValue("D{$row}", 'Overall Count & Share');
        $sheet->getStyle("C{$row}:D{$row}")->getFont()->setBold(true)->getColor()->setRGB('1E4A76');
        $sRow = $row + 1;
        foreach ($slices as $s) {
            $lbl = (string) ($s['label'] ?? 'Option');
            $val = (int) ($s['value'] ?? 0);
            $pct = (float) ($s['percentage'] ?? ($grandTotal > 0 ? round(($val / $grandTotal) * 100, 1) : 0.0));
            $sheet->setCellValue("C{$sRow}", $lbl);
            $sheet->setCellValue("D{$sRow}", number_format($val) . ' (' . number_format($pct, 1) . '%)');
            $sRow++;
        }

        $row = max($graphStartRow + 7, $sRow + 1);

        // 3. Bottom Single-Column College -> Department Stack (one college after another vertically)
        if (empty($collegeList)) {
            return;
        }

        foreach ($collegeList as $colData) {
            $cAbbr = (string) ($colData['abbr'] ?? '');
            $cName = (string) ($colData['name'] ?? '');
            $cTotal = (int) ($colData['total'] ?? 0);
            $cPct = (float) ($colData['percentage'] ?? 0.0);

            $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
            $sheet->setCellValue("A{$row}", "{$cAbbr} — {$cName}\nCollege Total: " . number_format($cTotal) . ' (' . number_format($cPct, 1) . '%)');
            $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A5F'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1'],
                    ],
                ],
            ]);
            $sheet->getRowDimension($row)->setRowHeight(30);
            $row++;

            $depts = array_values($colData['departments'] ?? []);
            if (! empty($depts)) {
                foreach ($depts as $dept) {
                    $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");

                    $dAbbr = (string) ($dept['abbr'] ?? '');
                    $dName = (string) ($dept['name'] ?? '');
                    $dTitle = ($dAbbr !== '' && $dName !== '' && strcasecmp($dAbbr, $dName) !== 0)
                        ? ($dAbbr . ' — ' . $dName)
                        : ($dName ?: $dAbbr);
                    $dTotal = (int) ($dept['total'] ?? 0);
                    $dPctAll = (float) ($dept['percentage_of_all'] ?? 0.0);
                    $dPctCol = (float) ($dept['percentage_of_college'] ?? 0.0);

                    $lines = [
                        $dTitle,
                        'Total: ' . number_format($dTotal) . ' (' . number_format($dPctAll, 1) . '% Overall | ' . number_format($dPctCol, 1) . '% College)',
                    ];
                    foreach (($dept['items'] ?? []) as $it) {
                        $itLbl = (string) ($it['label'] ?? '');
                        $itCnt = (int) ($it['count'] ?? 0);
                        $itPct = (float) ($it['percentage'] ?? 0.0);
                        $lines[] = '• ' . $itLbl . ': ' . number_format($itCnt) . ' (' . number_format($itPct, 1) . '%)';
                    }

                    $sheet->setCellValue("A{$row}", implode("\n", $lines));
                    $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->applyFromArray([
                        'font' => ['size' => 8.5, 'color' => ['rgb' => '0F172A']],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                            'wrapText' => true,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                'color' => ['rgb' => 'CBD5E1'],
                            ],
                        ],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(max(36, count($lines) * 14));
                    $row++;
                }
            } else {
                $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
                $sheet->setCellValue("A{$row}", '—');
                $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'CBD5E1']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => 'CBD5E1'],
                        ],
                    ],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(36);
                $row++;
            }
        }

        $row += 2;
    }

    /**
     * Generates Excel Spreadsheet Export for Survey Graphs Report with Top GRAPH and Multi-Column College & Department Breakdown
     */
    private function generateSurveyGraphsSpreadsheetReport(array $survey, array $selectedCategories, array $selectedQuestions, array $matchingResponses, string $filename, array $filters): string
    {
        $filepath = WRITEPATH . 'uploads/' . $filename . '.xlsx';
        if (! is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0777, true);
        }

        $tempFilesToClean = [];

        // 1. Compile Graduate Tracer Overview
        $tracerOverview = $this->compileTracerStatusOverview($survey, $matchingResponses, $filters);
        $overviewPieFile = $this->generateGdPieChartFile($tracerOverview['slices'], 360, 195);
        $tempFilesToClean[] = $overviewPieFile;

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(9.5);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Graphical Analysis');

        $institutionName = $this->getInstitutionHeaderName();
        $surveyTitle = (string) ($survey['title'] ?? 'Survey Report');
        $filterSummary = $this->buildFilterSummaryString($filters) ?: 'All survey respondents (No filters applied)';
        $totalRespondents = count($matchingResponses);
        $totalCohortAlumni = (int) ($tracerOverview['total_alumni'] ?? $totalRespondents);

        // Title Block
        $sheet->setCellValue('A1', $institutionName);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1E4A76');

        $sheet->setCellValue('A2', 'SURVEY RESULTS & GRAPHICAL ANALYSIS REPORT');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

        $sheet->setCellValue('A3', 'Survey: ' . $surveyTitle . ' | Total Alumni Cohort: ' . number_format($totalCohortAlumni) . ' | Total Responses: ' . number_format($totalRespondents) . ' | Generated: ' . date('Y-m-d H:i'));
        $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('475569');

        $sheet->setCellValue('A4', 'Filters: ' . $filterSummary);
        $sheet->getStyle('A4')->getFont()->setSize(8.5)->getColor()->setRGB('64748B');

        $row = 6;

        // 1. Graduate Tracer Status Overview Block (Top GRAPH + College | College -> Department Matrix)
        $this->writeExcelGraphAndCollegeDeptBlock(
            $sheet,
            $row,
            'GRADUATE TRACER STATUS & COLLEGE / DEPARTMENT BREAKDOWN',
            $overviewPieFile,
            $tracerOverview['slices'],
            $tracerOverview['college_matrix'] ?? [],
            $totalCohortAlumni,
            'Alumni'
        );

        // 2. Questions Blocks (Top GRAPH + College | College -> Department Matrix)
        $globalQIdx = 1;
        foreach ($selectedCategories as $cat) {
            $catName = (string) ($cat['name'] ?? 'Category');
            $questions = $cat['questions'] ?? [];
            if (empty($questions)) continue;

            foreach ($questions as $q) {
                $qText = (string) ($q['text'] ?? 'Question');
                $choiceStats = $q['choiceStats'] ?? [];
                $collegeMatrix = $q['collegeMatrix'] ?? [];
                $qTotalResponses = (int) ($q['totalResponses'] ?? 0);

                $qSlices = [];
                foreach ($choiceStats as $cs) {
                    $qSlices[] = [
                        'label' => $cs['choice'],
                        'value' => $cs['count'],
                        'percentage' => $cs['percentage'] ?? 0.0,
                        'color' => $cs['color'] ?? '#1e4a76',
                    ];
                }

                $qPieFile = $this->generateGdPieChartFile($qSlices, 360, 185);
                $tempFilesToClean[] = $qPieFile;

                $this->writeExcelGraphAndCollegeDeptBlock(
                    $sheet,
                    $row,
                    "[{$catName}] Q{$globalQIdx}. {$qText}",
                    $qPieFile,
                    $qSlices,
                    $collegeMatrix,
                    $qTotalResponses,
                    'Responses'
                );

                $globalQIdx++;
            }
        }

        $totalCols = 4;
        for ($c = 1; $c <= $totalCols; $c++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($colLetter)->setWidth(24);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);

        // Clean up temporary generated PNG files
        foreach ($tempFilesToClean as $tf) {
            if (file_exists($tf)) {
                @unlink($tf);
            }
        }

        return $filepath;
    }

    /**
     * Generates PDF Export for Survey Raw Response Counts & Frequency Report
     */
    private function generateSurveyRawNumbersPdfReport(array $survey, array $selectedCategories, array $selectedQuestions, array $matchingResponses, string $filename, array $filters): string
    {
        $filepath = WRITEPATH . 'uploads/' . $filename . '.pdf';
        if (! is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0777, true);
        }

        $institutionName = htmlspecialchars($this->getInstitutionHeaderName(), ENT_QUOTES, 'UTF-8');
        $surveyTitle = htmlspecialchars((string) ($survey['title'] ?? 'Survey Report'), ENT_QUOTES, 'UTF-8');
        $filterSummary = htmlspecialchars($this->buildFilterSummaryString($filters) ?: 'All survey respondents (No filters applied)', ENT_QUOTES, 'UTF-8');
        $totalRespondents = count($matchingResponses);
        $totalQuestions = count($selectedQuestions);
        $totalCategories = count($selectedCategories);
        $generatedAt = date('F d, Y h:i A');

        // Calculate grand total raw answers across all questions
        $grandTotalRawAnswers = 0;
        foreach ($selectedCategories as $cat) {
            foreach (($cat['questions'] ?? []) as $q) {
                $grandTotalRawAnswers += (int) ($q['totalResponses'] ?? 0);
            }
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>
            @page { margin: 12mm 10mm; }
            body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #1e293b; line-height: 1.35; margin: 0; padding: 0; }
            .header-banner { border-bottom: 2px solid #1e4a76; padding-bottom: 8px; margin-bottom: 12px; }
            .inst-name { font-size: 13pt; font-weight: bold; color: #1e4a76; text-transform: uppercase; margin-bottom: 2px; }
            .doc-title { font-size: 11pt; font-weight: bold; color: #0f172a; margin-bottom: 2px; }
            .doc-sub { font-size: 8.5pt; color: #64748b; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; }
            .survey-title { font-size: 9.5pt; color: #475569; font-weight: 600; }
            
            .meta-kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
            .kpi-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 10px; text-align: center; }
            .kpi-value { font-size: 13pt; font-weight: bold; color: #1e4a76; margin-bottom: 1px; }
            .kpi-label { font-size: 7.5pt; font-weight: bold; color: #64748b; text-transform: uppercase; }

            .meta-info-box { background: #f1f5f9; border-left: 3px solid #1e4a76; padding: 5px 8px; font-size: 8pt; margin-bottom: 14px; }

            .category-banner { background: #1e4a76; color: #ffffff; padding: 5px 8px; font-size: 9.5pt; font-weight: bold; text-transform: uppercase; border-radius: 4px; margin-top: 14px; margin-bottom: 10px; }
            
            .q-card { border: 1px solid #cbd5e1; border-radius: 5px; margin-bottom: 14px; page-break-inside: avoid; background: #ffffff; }
            .q-card-header { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 6px 8px; }
            .q-num { font-weight: bold; color: #1e4a76; margin-right: 4px; }
            .q-text { font-weight: bold; color: #0f172a; font-size: 9pt; }
            .q-badge { display: inline-block; background: #fef3c7; color: #92400e; font-size: 7pt; font-weight: bold; padding: 1px 5px; border-radius: 6px; margin-left: 4px; text-transform: uppercase; }

            .raw-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
            .raw-table th { background: #f1f5f9; color: #334155; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; padding: 5px 6px; border-bottom: 1.5px solid #cbd5e1; border-top: 1px solid #e2e8f0; }
            .raw-table td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
            .raw-table tr.total-row td { background-color: #f8fafc; font-weight: bold; border-top: 1.5px solid #1e4a76; border-bottom: 1px solid #cbd5e1; color: #0f172a; }
            
            .td-opt-num { width: 8%; text-align: center; color: #64748b; font-weight: bold; }
            .td-choice-text { width: 44%; color: #1e293b; }
            .td-raw-count { width: 16%; text-align: center; font-weight: bold; font-size: 9pt; color: #1e4a76; }
            .td-pct-share { width: 16%; text-align: right; font-weight: bold; color: #0f172a; padding-right: 6px; }
            .td-resp-rate { width: 16%; text-align: right; color: #475569; padding-right: 6px; }

            .summary-matrix-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-top: 16px; margin-bottom: 16px; page-break-inside: avoid; }
            .summary-matrix-table th { background: #1e4a76; color: #ffffff; font-size: 8pt; font-weight: bold; text-transform: uppercase; padding: 5px 6px; border: 1px solid #1e4a76; text-align: left; }
            .summary-matrix-table td { padding: 4px 6px; border: 1px solid #cbd5e1; vertical-align: middle; }
            .summary-matrix-table tr:nth-child(even) td { background-color: #f8fafc; }
            .summary-matrix-table tr.grand-total-row td { background-color: #e0f2fe; font-weight: bold; border-top: 2px solid #1e4a76; color: #0f172a; }

            .footer-note { font-size: 7.5pt; color: #94a3b8; text-align: center; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 5px; }
        </style></head><body>';

        $html .= '<div class="header-banner">
            <div class="inst-name">' . $institutionName . '</div>
            <div class="doc-title">SURVEY RAW RESPONSE FREQUENCY &amp; TOTALS REPORT</div>
            <div class="doc-sub">Detailed Count of Every Answer Choice Gathered per Survey Question</div>
            <div class="survey-title">Survey: ' . $surveyTitle . '</div>
        </div>';

        $html .= '<table class="meta-kpi-table"><tr>
            <td style="width: 25%; padding-right: 6px;"><div class="kpi-box"><div class="kpi-value">' . number_format($totalRespondents) . '</div><div class="kpi-label">Survey Respondents</div></div></td>
            <td style="width: 25%; padding: 0 3px;"><div class="kpi-box"><div class="kpi-value">' . number_format($totalQuestions) . '</div><div class="kpi-label">Questions Evaluated</div></div></td>
            <td style="width: 25%; padding: 0 3px;"><div class="kpi-box"><div class="kpi-value">' . number_format($grandTotalRawAnswers) . '</div><div class="kpi-label">Total Answers Gathered</div></div></td>
            <td style="width: 25%; padding-left: 6px;"><div class="kpi-box"><div class="kpi-value">Raw Counts</div><div class="kpi-label">Report Type: Raw Numbers</div></div></td>
        </tr></table>';

        $html .= '<div class="meta-info-box">
            <strong>Generated On:</strong> ' . $generatedAt . ' &nbsp;|&nbsp; 
            <strong>Filter Scope:</strong> ' . $filterSummary . '
        </div>';

        $globalQIdx = 1;
        $summaryMatrixRows = [];

        foreach ($selectedCategories as $cat) {
            $catName = htmlspecialchars((string) ($cat['name'] ?? 'Category'), ENT_QUOTES, 'UTF-8');
            $questions = $cat['questions'] ?? [];
            if (empty($questions)) continue;

            $html .= '<div class="category-banner">' . $catName . '</div>';

            foreach ($questions as $q) {
                $qText = htmlspecialchars((string) ($q['text'] ?? 'Question'), ENT_QUOTES, 'UTF-8');
                $qType = strtolower(trim((string) ($q['type'] ?? 'text')));
                $choiceStats = $q['choiceStats'] ?? [];
                $qTotalResponses = (int) ($q['totalResponses'] ?? 0);
                $respRate = $totalRespondents > 0 ? round(($qTotalResponses / $totalRespondents) * 100, 1) : 0.0;

                $typeBadge = match ($qType) {
                    'multiple_choice' => 'Multiple Choice',
                    'rating' => 'Rating Scale',
                    default => 'Open Response',
                };

                $summaryMatrixRows[] = [
                    'qNum' => 'Q' . $globalQIdx,
                    'catName' => $catName,
                    'qText' => $qText,
                    'type' => $typeBadge,
                    'choicesCount' => count($choiceStats),
                    'totalAnswers' => $qTotalResponses,
                    'respRate' => $respRate,
                ];

                $html .= '<div class="q-card">';
                $html .= '<div class="q-card-header">';
                $html .= '<span class="q-num">Q' . $globalQIdx . '.</span> <span class="q-text">' . $qText . '</span> <span class="q-badge">' . $typeBadge . '</span>';
                $html .= '</div>';

                if (! empty($choiceStats)) {
                    $html .= '<table class="raw-table">';
                    $html .= '<thead><tr>
                        <th class="td-opt-num">Opt #</th>
                        <th class="td-choice-text">Answer Choice / Response Value</th>
                        <th class="td-raw-count">Raw Total Answers Gathered</th>
                        <th class="td-pct-share">Share of Question (%)</th>
                        <th class="td-resp-rate">Response Rate vs Alumni (%)</th>
                    </tr></thead>';
                    $html .= '<tbody>';

                    $optIdx = 1;
                    $sumCount = 0;

                    foreach ($choiceStats as $cItem) {
                        $cText = htmlspecialchars((string) ($cItem['choice'] ?? 'Option'), ENT_QUOTES, 'UTF-8');
                        $count = (int) ($cItem['count'] ?? 0);
                        $pct = (float) ($cItem['percentage'] ?? 0.0);
                        $rateVsAlumni = $totalRespondents > 0 ? round(($count / $totalRespondents) * 100, 1) : 0.0;
                        $sumCount += $count;

                        $html .= '<tr>';
                        $html .= '<td class="td-opt-num">' . $optIdx . '</td>';
                        $html .= '<td class="td-choice-text">' . $cText . '</td>';
                        $html .= '<td class="td-raw-count">' . number_format($count) . '</td>';
                        $html .= '<td class="td-pct-share">' . number_format($pct, 1) . '%</td>';
                        $html .= '<td class="td-resp-rate">' . number_format($rateVsAlumni, 1) . '%</td>';
                        $html .= '</tr>';

                        $optIdx++;
                    }

                    $totalCoverageRate = $totalRespondents > 0 ? round(($sumCount / $totalRespondents) * 100, 1) : 0.0;

                    $html .= '<tr class="total-row">';
                    $html .= '<td class="td-opt-num">Σ</td>';
                    $html .= '<td class="td-choice-text">TOTAL ANSWERS GATHERED FOR THIS QUESTION</td>';
                    $html .= '<td class="td-raw-count">' . number_format($sumCount) . '</td>';
                    $html .= '<td class="td-pct-share">100.0%</td>';
                    $html .= '<td class="td-resp-rate">' . number_format($totalCoverageRate, 1) . '%</td>';
                    $html .= '</tr>';

                    $html .= '</tbody></table>';
                } else {
                    $html .= '<div style="padding: 10px; font-style: italic; color: #94a3b8;">No response choices recorded for this question.</div>';
                }

                $html .= '</div>';
                $globalQIdx++;
            }
        }

        // Questions Grand Summary Matrix
        if (! empty($summaryMatrixRows)) {
            $html .= '<div class="category-banner" style="background:#0f172a; margin-top:20px;">SURVEY QUESTIONS RAW TOTALS SUMMARY</div>';
            $html .= '<table class="summary-matrix-table">';
            $html .= '<thead><tr>
                <th style="width:7%; text-align:center;">Q#</th>
                <th style="width:20%;">Category</th>
                <th style="width:38%;">Question Text</th>
                <th style="width:13%;">Type</th>
                <th style="width:11%; text-align:center;">Raw Total Answers</th>
                <th style="width:11%; text-align:right;">Response Rate</th>
            </tr></thead><tbody>';

            $totalChoicesAll = 0;
            $totalAnswersAll = 0;

            foreach ($summaryMatrixRows as $sm) {
                $totalChoicesAll += (int) $sm['choicesCount'];
                $totalAnswersAll += (int) $sm['totalAnswers'];

                $html .= '<tr>';
                $html .= '<td style="text-align:center; font-weight:bold; color:#1e4a76;">' . $sm['qNum'] . '</td>';
                $html .= '<td>' . $sm['catName'] . '</td>';
                $html .= '<td>' . $sm['qText'] . '</td>';
                $html .= '<td>' . $sm['type'] . '</td>';
                $html .= '<td style="text-align:center; font-weight:bold;">' . number_format($sm['totalAnswers']) . '</td>';
                $html .= '<td style="text-align:right; font-weight:bold; color:#1e4a76;">' . number_format($sm['respRate'], 1) . '%</td>';
                $html .= '</tr>';
            }

            $overallAvgRate = count($summaryMatrixRows) > 0 ? round(array_sum(array_column($summaryMatrixRows, 'respRate')) / count($summaryMatrixRows), 1) : 0.0;

            $html .= '<tr class="grand-total-row">';
            $html .= '<td colspan="4" style="text-align:right; text-transform:uppercase;">GRAND TOTAL ANSWERS GATHERED ACROSS ALL QUESTIONS:</td>';
            $html .= '<td style="text-align:center; font-size:10pt; color:#1e4a76;">' . number_format($totalAnswersAll) . '</td>';
            $html .= '<td style="text-align:right; font-size:9.5pt; color:#1e4a76;">' . number_format($overallAvgRate, 1) . '% (Avg)</td>';
            $html .= '</tr>';

            $html .= '</tbody></table>';
        }

        $html .= '<div class="footer-note">Confidential — System Generated Survey Raw Frequency &amp; Totals Report — ' . $institutionName . '</div>';
        $html .= '</body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($filepath, $dompdf->output());
        return $filepath;
    }

    /**
     * Generates Word DOCX Export for Survey Raw Response Counts & Frequency Report
     */
    private function generateSurveyRawNumbersDocxReport(array $survey, array $selectedCategories, array $selectedQuestions, array $matchingResponses, string $filename, array $filters): string
    {
        $filepath = WRITEPATH . 'uploads/' . $filename . '.docx';
        if (! is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0777, true);
        }

        \PhpOffice\PhpWord\Settings::setZipClass(\PhpOffice\PhpWord\Settings::PCLZIP);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Tahoma');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection([
            'orientation' => 'portrait',
            'marginTop' => 720,
            'marginRight' => 720,
            'marginBottom' => 720,
            'marginLeft' => 720,
        ]);

        $institutionName = $this->getInstitutionHeaderName();
        $surveyTitle = (string) ($survey['title'] ?? 'Survey Report');
        $filterSummary = $this->buildFilterSummaryString($filters) ?: 'All survey respondents (No filters applied)';
        $totalRespondents = count($matchingResponses);

        // Document Title
        $section->addText($institutionName, ['bold' => true, 'size' => 13, 'color' => '1E4A76'], ['spaceAfter' => 60]);
        $section->addText('SURVEY RAW RESPONSE FREQUENCY & TOTALS REPORT', ['bold' => true, 'size' => 12, 'color' => '0F172A'], ['spaceAfter' => 40]);
        $section->addText('Detailed Count of Every Answer Choice Gathered per Survey Question', ['italic' => true, 'size' => 9.5, 'color' => '64748B'], ['spaceAfter' => 60]);
        $section->addText('Survey: ' . $surveyTitle, ['bold' => true, 'size' => 10, 'color' => '475569'], ['spaceAfter' => 120]);

        // Metadata box
        $metaTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'CBD5E1', 'cellMargin' => 80]);
        $metaTable->addRow();
        $metaTable->addCell(4500, ['bgColor' => 'F8FAFC'])->addText('Total Survey Respondents: ' . number_format($totalRespondents), ['bold' => true, 'size' => 9.5]);
        $metaTable->addCell(4500, ['bgColor' => 'F8FAFC'])->addText('Report Type: Raw Numbers & Totals', ['bold' => true, 'size' => 9.5]);
        $metaTable->addRow();
        $metaCell = $metaTable->addCell(9000, ['gridSpan' => 2, 'bgColor' => 'F1F5F9']);
        $metaCell->addText('Filters: ' . $filterSummary, ['size' => 8.5, 'color' => '475569']);
        $section->addTextBreak(1);

        $globalQIdx = 1;

        foreach ($selectedCategories as $cat) {
            $catName = (string) ($cat['name'] ?? 'Category');
            $questions = $cat['questions'] ?? [];
            if (empty($questions)) continue;

            $section->addText(strtoupper($catName), ['bold' => true, 'size' => 11, 'color' => 'FFFFFF'], ['shading' => ['fill' => '1E4A76'], 'spaceBefore' => 180, 'spaceAfter' => 100]);

            foreach ($questions as $q) {
                $qText = (string) ($q['text'] ?? 'Question');
                $choiceStats = $q['choiceStats'] ?? [];
                $qTotalResponses = (int) ($q['totalResponses'] ?? 0);

                $section->addText('Q' . $globalQIdx . '. ' . $qText, ['bold' => true, 'size' => 10, 'color' => '0F172A'], ['spaceBefore' => 120, 'spaceAfter' => 40]);

                if (! empty($choiceStats)) {
                    $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'E2E8F0', 'cellMargin' => 60]);
                    
                    // Table Header
                    $table->addRow();
                    $table->addCell(800, ['bgColor' => 'F1F5F9'])->addText('Opt #', ['bold' => true, 'size' => 8.5, 'color' => '334155'], ['alignment' => Jc::CENTER]);
                    $table->addCell(3800, ['bgColor' => 'F1F5F9'])->addText('Answer Choice / Response', ['bold' => true, 'size' => 8.5, 'color' => '334155']);
                    $table->addCell(1600, ['bgColor' => 'F1F5F9'])->addText('Raw Answers Gathered', ['bold' => true, 'size' => 8.5, 'color' => '334155'], ['alignment' => Jc::CENTER]);
                    $table->addCell(1400, ['bgColor' => 'F1F5F9'])->addText('Share (%)', ['bold' => true, 'size' => 8.5, 'color' => '334155'], ['alignment' => Jc::CENTER]);
                    $table->addCell(1400, ['bgColor' => 'F1F5F9'])->addText('Response Rate (%)', ['bold' => true, 'size' => 8.5, 'color' => '334155'], ['alignment' => Jc::CENTER]);

                    $optIdx = 1;
                    $sumCount = 0;

                    foreach ($choiceStats as $cItem) {
                        $cText = (string) ($cItem['choice'] ?? '');
                        $cnt = (int) ($cItem['count'] ?? 0);
                        $pct = (float) ($cItem['percentage'] ?? 0.0);
                        $rateVsAlumni = $totalRespondents > 0 ? round(($cnt / $totalRespondents) * 100, 1) : 0.0;
                        $sumCount += $cnt;

                        $table->addRow();
                        $table->addCell(800)->addText((string) $optIdx, ['size' => 8.5], ['alignment' => Jc::CENTER]);
                        $table->addCell(3800)->addText($cText, ['size' => 8.5]);
                        $table->addCell(1600)->addText(number_format($cnt), ['bold' => true, 'size' => 8.5, 'color' => '1E4A76'], ['alignment' => Jc::CENTER]);
                        $table->addCell(1400)->addText(number_format($pct, 1) . '%', ['bold' => true, 'size' => 8.5], ['alignment' => Jc::CENTER]);
                        $table->addCell(1400)->addText(number_format($rateVsAlumni, 1) . '%', ['size' => 8.5], ['alignment' => Jc::CENTER]);

                        $optIdx++;
                    }

                    // Total Row
                    $totalCoverage = $totalRespondents > 0 ? round(($sumCount / $totalRespondents) * 100, 1) : 0.0;
                    $table->addRow();
                    $table->addCell(800, ['bgColor' => 'F8FAFC'])->addText('Σ', ['bold' => true, 'size' => 8.5], ['alignment' => Jc::CENTER]);
                    $table->addCell(3800, ['bgColor' => 'F8FAFC'])->addText('TOTAL ANSWERS GATHERED FOR QUESTION', ['bold' => true, 'size' => 8.5]);
                    $table->addCell(1600, ['bgColor' => 'F8FAFC'])->addText(number_format($sumCount), ['bold' => true, 'size' => 9, 'color' => '1E4A76'], ['alignment' => Jc::CENTER]);
                    $table->addCell(1400, ['bgColor' => 'F8FAFC'])->addText('100.0%', ['bold' => true, 'size' => 8.5], ['alignment' => Jc::CENTER]);
                    $table->addCell(1400, ['bgColor' => 'F8FAFC'])->addText(number_format($totalCoverage, 1) . '%', ['bold' => true, 'size' => 8.5], ['alignment' => Jc::CENTER]);
                }
                $globalQIdx++;
            }
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);
        return $filepath;
    }

    /**
     * Generates Excel Spreadsheet Export for Survey Raw Response Counts & Frequency Report
     */
    private function generateSurveyRawNumbersSpreadsheetReport(array $survey, array $selectedCategories, array $selectedQuestions, array $matchingResponses, string $filename, array $filters): string
    {
        $filepath = WRITEPATH . 'uploads/' . $filename . '.xlsx';
        if (! is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0777, true);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(10);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Raw Frequency Counts');

        $institutionName = $this->getInstitutionHeaderName();
        $surveyTitle = (string) ($survey['title'] ?? 'Survey Report');
        $filterSummary = $this->buildFilterSummaryString($filters) ?: 'All survey respondents (No filters applied)';
        $totalRespondents = count($matchingResponses);

        // Title Block
        $sheet->setCellValue('A1', $institutionName);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1E4A76');

        $sheet->setCellValue('A2', 'SURVEY RAW RESPONSE FREQUENCY & TOTALS REPORT');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);

        $sheet->setCellValue('A3', 'Detailed Count of Every Answer Choice Gathered per Survey Question');
        $sheet->getStyle('A3')->getFont()->setSize(9.5)->setItalic(true)->getColor()->setRGB('475569');

        $sheet->setCellValue('A4', 'Survey: ' . $surveyTitle . ' | Total Respondents: ' . number_format($totalRespondents) . ' | Generated: ' . date('Y-m-d H:i'));
        $sheet->getStyle('A4')->getFont()->setSize(9)->getColor()->setRGB('475569');

        $sheet->setCellValue('A5', 'Filters: ' . $filterSummary);
        $sheet->getStyle('A5')->getFont()->setSize(8.5)->getColor()->setRGB('64748B');

        // Header Row
        $headerRow = 7;
        $headers = ['Category', 'Q#', 'Question Text', 'Question Type', 'Opt #', 'Answer Choice / Option', 'Raw Answers Gathered (Count)', 'Question Total Answers', 'Share of Question (%)', 'Response Rate vs All Alumni (%)'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        foreach ($headers as $idx => $hdr) {
            $sheet->setCellValue($cols[$idx] . $headerRow, $hdr);
        }

        $sheet->getStyle("A{$headerRow}:J{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9.5],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E4A76'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        $row = 8;
        $globalQIdx = 1;

        foreach ($selectedCategories as $cat) {
            $catName = (string) ($cat['name'] ?? 'Category');
            $questions = $cat['questions'] ?? [];
            if (empty($questions)) continue;

            foreach ($questions as $q) {
                $qText = (string) ($q['text'] ?? 'Question');
                $qType = strtolower(trim((string) ($q['type'] ?? 'text')));
                $choiceStats = $q['choiceStats'] ?? [];
                $qTotalResponses = (int) ($q['totalResponses'] ?? 0);

                $optIdx = 1;
                foreach ($choiceStats as $cItem) {
                    $cText = (string) ($cItem['choice'] ?? '');
                    $cnt = (int) ($cItem['count'] ?? 0);
                    $pct = (float) ($cItem['percentage'] ?? 0.0);
                    $rateVsAlumni = $totalRespondents > 0 ? ($cnt / $totalRespondents) : 0.0;

                    $sheet->setCellValue("A{$row}", $catName);
                    $sheet->setCellValue("B{$row}", 'Q' . $globalQIdx);
                    $sheet->setCellValue("C{$row}", $qText);
                    $sheet->setCellValue("D{$row}", ucfirst(str_replace('_', ' ', $qType)));
                    $sheet->setCellValue("E{$row}", $optIdx);
                    $sheet->setCellValue("F{$row}", $cText);
                    $sheet->setCellValue("G{$row}", $cnt);
                    $sheet->setCellValue("H{$row}", $qTotalResponses);
                    $sheet->setCellValue("I{$row}", $pct / 100);
                    $sheet->setCellValue("J{$row}", $rateVsAlumni);

                    $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$row}")->getFont()->setBold(true)->getColor()->setRGB('1E4A76');
                    $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
                    $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
                    $sheet->getStyle("J{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

                    $row++;
                    $optIdx++;
                }

                // Question Subtotal Row
                $sheet->setCellValue("A{$row}", $catName);
                $sheet->setCellValue("B{$row}", 'Q' . $globalQIdx);
                $sheet->setCellValue("C{$row}", $qText);
                $sheet->setCellValue("D{$row}", 'Total');
                $sheet->setCellValue("E{$row}", 'Σ');
                $sheet->setCellValue("F{$row}", 'TOTAL RAW ANSWERS GATHERED');
                $sheet->setCellValue("G{$row}", $qTotalResponses);
                $sheet->setCellValue("H{$row}", $qTotalResponses);
                $sheet->setCellValue("I{$row}", 1.0);
                $sheet->setCellValue("J{$row}", $totalRespondents > 0 ? ($qTotalResponses / $totalRespondents) : 0.0);

                $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E0F2FE'],
                    ],
                ]);
                $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
                $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE_00);
                $sheet->getStyle("J{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

                $row += 2; // Spacing
                $globalQIdx++;
            }
        }

        foreach ($cols as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);
        return $filepath;
    }

    /**
     * Queries the alumni list grouped by program and classifies each graduate's tracer status:
     * - Employed (E): accounts that responded to the survey and chose "Employed"
     * - Unemployed (UE): accounts that responded to the survey and did not choose "Employed" (chose another choice)
     * - Not Tracked (NT): accounts that did not respond/answer the survey
     *
     * @param array<string, mixed> $filters
     * @param int $surveyId
     * @param array<int, array<string, mixed>> $matchingResponses
     * @return array<int, array<string, mixed>>
     */
    private function getAlumniProgramGroups(array $filters = [], int $surveyId = 0, array $matchingResponses = []): array
    {
        $db = \Config\Database::connect();

        // Preload department abbreviations by department name
        $deptRows = $db->table('departments')
            ->select('Department_ID, Department_Name, Abbreviation')
            ->orderBy('Department_Name', 'ASC')
            ->get()
            ->getResultArray();

        $abbrByCourseName = [];
        foreach ($deptRows as $dRow) {
            $dName = strtolower(trim((string) ($dRow['Department_Name'] ?? '')));
            $dAbbr = trim((string) ($dRow['Abbreviation'] ?? ''));
            if ($dName !== '' && $dAbbr !== '') {
                $abbrByCourseName[$dName] = $dAbbr;
            }
        }

        // Resolve survey responses and classify each respondent as Employed or Unemployed
        $responseRows = [];
        if (! empty($matchingResponses)) {
            foreach ($matchingResponses as $mr) {
                $responseRows[] = [
                    'id' => (int) ($mr['id'] ?? 0),
                    'user_email' => (string) ($mr['email'] ?? $mr['user_email'] ?? ''),
                    'user_name' => (string) ($mr['name'] ?? $mr['user_name'] ?? ''),
                ];
            }
        } else {
            $respBuilder = $db->table('responses')
                ->select('id, survey_id, user_email, user_name, submitted_at')
                ->orderBy('submitted_at', 'DESC')
                ->orderBy('id', 'DESC');
            if ($surveyId > 0) {
                $respBuilder->where('survey_id', $surveyId);
            }
            $responseRows = $respBuilder->get()->getResultArray();
        }

        $responseIds = array_values(array_filter(array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $responseRows)));
        $answersByResp = [];
        if (! empty($responseIds)) {
            $answerRows = $db->table('answers a')
                ->select('a.response_id, a.question_id, a.choice_id, a.answer_text, a.answer_image_path, c.choice_text, q.question_text')
                ->join('choices c', 'c.id = a.choice_id', 'left')
                ->join('questions q', 'q.id = a.question_id', 'left')
                ->whereIn('a.response_id', $responseIds)
                ->get()
                ->getResultArray();

            foreach ($answerRows as $ans) {
                $rId = (int) ($ans['response_id'] ?? 0);
                if ($rId > 0) {
                    $answersByResp[$rId][] = $ans;
                }
            }
        }

        $respondentInfoByEmail = [];
        $respondentInfoByName = [];
        foreach ($responseRows as $resp) {
            $rId = (int) ($resp['id'] ?? 0);
            if ($rId <= 0) {
                continue;
            }
            $respAnswers = $answersByResp[$rId] ?? [];
            $choseEmployed = false;
            $foundEmploymentQuestion = false;
            $employmentName = '';
            $supportingDoc = '';

            foreach ($respAnswers as $ans) {
                $val = trim((string) ($ans['choice_text'] ?: $ans['answer_text'] ?: ''));
                $imgPath = trim((string) ($ans['answer_image_path'] ?? ''));
                $qText = strtolower(trim((string) ($ans['question_text'] ?? '')));

                if ($imgPath !== '' && $supportingDoc === '') {
                    $supportingDoc = basename($imgPath);
                }

                if ($val === '') {
                    continue;
                }

                if (str_contains($qText, 'employ') || str_contains($qText, 'status') || str_contains($qText, 'job') || str_contains($qText, 'work') || str_contains($qText, 'occupation')) {
                    if (stripos($val, 'employed') !== false || stripos($val, 'unemployed') !== false || str_contains($qText, 'status')) {
                        $foundEmploymentQuestion = true;
                        if (stripos($val, 'employed') !== false && stripos($val, 'unemployed') === false && stripos($val, 'not employed') === false) {
                            $choseEmployed = true;
                        }
                    }
                }

                if ($employmentName === '' && (str_contains($qText, 'company') || str_contains($qText, 'business') || str_contains($qText, 'employer') || str_contains($qText, 'organization') || str_contains($qText, 'workplace') || str_contains($qText, 'name of employment'))) {
                    $employmentName = $val;
                }
            }

            if (! $foundEmploymentQuestion) {
                foreach ($respAnswers as $ans) {
                    $val = trim((string) ($ans['choice_text'] ?: $ans['answer_text'] ?: ''));
                    if ($val !== '' && stripos($val, 'employed') !== false && stripos($val, 'unemployed') === false && stripos($val, 'not employed') === false) {
                        $choseEmployed = true;
                        break;
                    }
                }
            }

            $info = [
                'status' => $choseEmployed ? 'Employed' : 'Unemployed',
                'employment' => $choseEmployed ? $employmentName : '',
                'supportingDoc' => $supportingDoc,
            ];

            $emailKey = strtolower(trim((string) ($resp['user_email'] ?? '')));
            if ($emailKey !== '' && ! isset($respondentInfoByEmail[$emailKey])) {
                $respondentInfoByEmail[$emailKey] = $info;
            }

            $nameKey = strtolower(preg_replace('/\s+/', ' ', trim((string) ($resp['user_name'] ?? ''))) ?? '');
            if ($nameKey !== '' && ! isset($respondentInfoByName[$nameKey])) {
                $respondentInfoByName[$nameKey] = $info;
            }
        }

        // Fetch all alumni joined with colleges and departments
        $alumniRows = $db->table('alumni')
            ->select('alumni.id, alumni.alumni_id, alumni.FirstName, alumni.LastName, alumni.Email, alumni.GraduationYear, alumni.Status, alumni.College_ID, alumni.Department_ID, c.College_Name AS College, c.Abbreviation AS College_Abbr, d.Department_Name AS Course, d.Abbreviation AS Course_Abbr')
            ->join('colleges c', 'c.College_ID = alumni.College_ID', 'left')
            ->join('departments d', 'd.Department_ID = alumni.Department_ID', 'left')
            ->orderBy('d.Department_Name', 'ASC')
            ->orderBy('alumni.LastName', 'ASC')
            ->orderBy('alumni.FirstName', 'ASC')
            ->get()
            ->getResultArray();

        $filterYear = trim((string) ($filters['graduation_year'] ?? $filters['graduationYear'] ?? ''));
        $filterCollege = trim((string) ($filters['college'] ?? ''));
        $filterCourse = trim((string) ($filters['course'] ?? $filters['department'] ?? ''));
        $filterSearch = strtolower(trim((string) ($filters['search_query'] ?? $filters['searchQuery'] ?? '')));

        $groupsByProgram = [];
        foreach ($alumniRows as $al) {
            $firstName = trim((string) ($al['FirstName'] ?? ''));
            $lastName = trim((string) ($al['LastName'] ?? ''));
            $email = strtolower(trim((string) ($al['Email'] ?? '')));
            $alumniRecordId = trim((string) ($al['alumni_id'] ?? ''));
            $college = trim((string) ($al['College'] ?? ''));
            $collegeAbbr = trim((string) ($al['College_Abbr'] ?? ''));
            $course = trim((string) ($al['Course'] ?? ''));
            $courseAbbr = trim((string) ($al['Course_Abbr'] ?? ''));
            $gradYear = trim((string) ($al['GraduationYear'] ?? ''));

            if ($filterYear !== '' && strcasecmp($gradYear, $filterYear) !== 0) {
                continue;
            }
            if ($filterCollege !== '' && strcasecmp($college, $filterCollege) !== 0 && strcasecmp($collegeAbbr, $filterCollege) !== 0) {
                continue;
            }
            if ($filterCourse !== '' && strcasecmp($course, $filterCourse) !== 0 && strcasecmp($courseAbbr, $filterCourse) !== 0) {
                continue;
            }
            if ($filterSearch !== '') {
                $searchable = strtolower($firstName . ' ' . $lastName . ' ' . $email . ' ' . $alumniRecordId . ' ' . $college . ' ' . $course);
                if (! str_contains($searchable, $filterSearch)) {
                    continue;
                }
            }

            $programName = $course !== '' ? $course : 'Unassigned Program';
            if ($courseAbbr === '' && isset($abbrByCourseName[strtolower($programName)])) {
                $courseAbbr = $abbrByCourseName[strtolower($programName)];
            }
            $programAbbr = $this->deriveProgramAbbreviation($programName, $courseAbbr);

            if ($lastName !== '' && $firstName !== '') {
                $formattedName = $lastName . ', ' . $firstName;
            } else {
                $formattedName = trim($lastName . ' ' . $firstName) ?: ($email ?: 'Unnamed Graduate');
            }

            if (! isset($groupsByProgram[$programName])) {
                $groupsByProgram[$programName] = [
                    'programName' => $programName,
                    'programAbbr' => $programAbbr,
                    'graduates' => [],
                    'total' => 0,
                    'employedCount' => 0,
                    'unemployedCount' => 0,
                    'notTrackedCount' => 0,
                ];
            }

            $fullNameKey = strtolower(preg_replace('/\s+/', ' ', trim($firstName . ' ' . $lastName)) ?? '');
            $matchedInfo = null;
            if ($email !== '' && isset($respondentInfoByEmail[$email])) {
                $matchedInfo = $respondentInfoByEmail[$email];
            } elseif ($fullNameKey !== '' && isset($respondentInfoByName[$fullNameKey])) {
                $matchedInfo = $respondentInfoByName[$fullNameKey];
            }

            $gradStatus = $matchedInfo['status'] ?? 'Not Tracked';
            $groupsByProgram[$programName]['graduates'][] = [
                'name' => $formattedName,
                'status' => $gradStatus,
                'employment' => $matchedInfo['employment'] ?? '',
                'supportingDoc' => $matchedInfo['supportingDoc'] ?? '',
            ];
        }

        // Finalize E, UE, and NT counts for each program
        foreach ($groupsByProgram as &$grp) {
            $employedCount = 0;
            $unemployedCount = 0;
            $notTrackedCount = 0;

            foreach ($grp['graduates'] as $grad) {
                $st = (string) ($grad['status'] ?? 'Not Tracked');
                if ($st === 'Employed') {
                    $employedCount++;
                } elseif ($st === 'Unemployed') {
                    $unemployedCount++;
                } else {
                    $notTrackedCount++;
                }
            }

            $grp['total'] = count($grp['graduates']);
            $grp['employedCount'] = $employedCount;
            $grp['unemployedCount'] = $unemployedCount;
            $grp['notTrackedCount'] = $notTrackedCount;
        }
        unset($grp);

        return array_values($groupsByProgram);
    }

    private function deriveProgramAbbreviation(string $programName, string $dbAbbr = ''): string
    {
        $cleanAbbr = trim($dbAbbr);
        if ($cleanAbbr !== '') {
            return strtoupper($cleanAbbr);
        }

        $stopWords = ['in', 'of', 'and', 'the', 'for', 'on', 'with', 'to', 'a', 'an'];
        $words = preg_split('/[\s\-_\/]+/', trim($programName)) ?: [];
        $initials = '';
        foreach ($words as $word) {
            $cleanWord = preg_replace('/[^a-zA-Z]/', '', $word);
            if ($cleanWord === '' || in_array(strtolower($cleanWord), $stopWords, true)) {
                continue;
            }
            $initials .= strtoupper($cleanWord[0]);
        }

        return $initials !== '' ? $initials : strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $programName), 0, 6) ?: 'PROG');
    }

    private function buildProgramAlumniOutcomeTableHtml(array $programGroups): string
    {
        $institutionName = $this->getInstitutionHeaderName();
        $campusBannerText = str_ends_with(strtoupper($institutionName), 'CAMPUS')
            ? strtoupper($institutionName)
            : strtoupper($institutionName) . ' CAMPUS';

        $institutionEsc = htmlspecialchars($institutionName, ENT_QUOTES, 'UTF-8');
        $campusBannerEsc = htmlspecialchars($campusBannerText, ENT_QUOTES, 'UTF-8');

        $html = '<table class="tracer-outcome-table">';
        // Exact proportions matching 860px reference: 205px, 23px, 159px, 36px, 36px, 62px, 251px, 88px
        $html .= '<colgroup>
            <col style="width: 23.84%;">
            <col style="width: 2.67%;">
            <col style="width: 18.49%;">
            <col style="width: 4.19%;">
            <col style="width: 4.19%;">
            <col style="width: 7.21%;">
            <col style="width: 29.19%;">
            <col style="width: 10.23%;">
        </colgroup>';

        $html .= '<tbody>';
        $html .= '<tr class="tr-title">
            <th colspan="2" class="th-title">Program Name</th>
            <th class="th-title">Name of Graduate</th>
            <th colspan="3" class="th-title">Graduate Tracer Status<br><span class="th-sub">(Employed, Unemployed, Not Tracked)</span></th>
            <th class="th-title">Name of Employment / Business / Company</th>
            <th class="th-title">Supporting Documentation</th>
        </tr>';
        $html .= '<tr class="tr-banner">
            <td colspan="7" class="td-campus-banner">' . $campusBannerEsc . '</td>
            <td class="td-doc-cell"></td>
        </tr>';

        if (empty($programGroups)) {
            $html .= '<tr class="tr-data">
                <td class="td-prog" style="border-top: 1px solid #000000; border-bottom: 1px solid #000000;">—</td>
                <td class="td-seq">1</td>
                <td class="td-grad-name">No alumni records found</td>
                <td colspan="3" class="td-status">Not Tracked</td>
                <td class="td-emp"></td>
                <td class="td-doc-cell"></td>
            </tr>';
        } else {
            foreach ($programGroups as $group) {
                $programNameEsc = htmlspecialchars((string) ($group['programName'] ?? ''), ENT_QUOTES, 'UTF-8');
                $programAbbrEsc = htmlspecialchars((string) ($group['programAbbr'] ?? ''), ENT_QUOTES, 'UTF-8');
                $graduates = $group['graduates'] ?? [];
                $gradCount = count($graduates);

                foreach ($graduates as $idx => $grad) {
                    if ($gradCount === 1) {
                        $progBorderStyle = 'border-top: 1px solid #000000; border-bottom: 1px solid #000000;';
                    } elseif ($idx === 0) {
                        $progBorderStyle = 'border-top: 1px solid #000000; border-bottom: none;';
                    } elseif ($idx === $gradCount - 1) {
                        $progBorderStyle = 'border-top: none; border-bottom: 1px solid #000000;';
                    } else {
                        $progBorderStyle = 'border-top: none; border-bottom: none;';
                    }

                    $gradNameEsc = htmlspecialchars((string) ($grad['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $statusEsc = htmlspecialchars((string) ($grad['status'] ?? 'Not Tracked'), ENT_QUOTES, 'UTF-8');
                    $empEsc = htmlspecialchars((string) ($grad['employment'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $docEsc = htmlspecialchars((string) ($grad['supportingDoc'] ?? ''), ENT_QUOTES, 'UTF-8');

                    $html .= '<tr class="tr-data">';
                    $html .= '<td class="td-prog" style="' . $progBorderStyle . '">' . ($idx === 0 ? $programNameEsc : '') . '</td>';
                    $html .= '<td class="td-seq">' . ($idx + 1) . '</td>';
                    $html .= '<td class="td-grad-name">' . $gradNameEsc . '</td>';
                    $html .= '<td colspan="3" class="td-status">' . $statusEsc . '</td>';
                    $html .= '<td class="td-emp">' . $empEsc . '</td>';
                    $html .= '<td class="td-doc-cell">' . $docEsc . '</td>';
                    $html .= '</tr>';
                }

                $totalGraduates = (int) ($group['total'] ?? $gradCount);
                $employedCount = (int) ($group['employedCount'] ?? 0);
                $unemployedCount = (int) ($group['unemployedCount'] ?? 0);
                $notTrackedCount = (int) ($group['notTrackedCount'] ?? $totalGraduates);

                // Program Summary Row 1 (15px) & Row 2 (15px) = 30px total
                $html .= '<tr class="tr-sum">';
                $html .= '<td colspan="2" rowspan="2" class="td-yellow-merged">TOTAL NUMBER OF STUDENTS - ' . $programAbbrEsc . '<br>' . $institutionEsc . '</td>';
                $html .= '<td rowspan="2" class="td-yellow-merged">' . $totalGraduates . '</td>';
                $html .= '<td class="td-yellow-cell td-sum-e">E</td>';
                $html .= '<td class="td-yellow-cell td-sum-ue">UE</td>';
                $html .= '<td class="td-yellow-cell td-sum-nt">NT</td>';
                $html .= '<td rowspan="2" class="td-black-top" style="border-bottom: 1px solid #000000;">&nbsp;</td>';
                $html .= '<td class="td-doc-cell"></td>';
                $html .= '</tr>';

                // Program Summary Row 2 (15px)
                $html .= '<tr class="tr-sum">';
                $html .= '<td class="td-yellow-cell td-sum-e">' . $employedCount . '</td>';
                $html .= '<td class="td-yellow-cell td-sum-ue">' . $unemployedCount . '</td>';
                $html .= '<td class="td-yellow-cell td-sum-nt">' . $notTrackedCount . '</td>';
                $html .= '<td class="td-doc-cell"></td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';
        return $html;
    }

    private function addProgramAlumniOutcomeTableToWordSection(PhpWord $phpWord, $section, array $programGroups): void
    {
        $institutionName = $this->getInstitutionHeaderName();
        $campusBannerText = str_ends_with(strtoupper($institutionName), 'CAMPUS')
            ? strtoupper($institutionName)
            : strtoupper($institutionName) . ' CAMPUS';

        $tableStyleName = 'TracerOutcomeTable_' . uniqid();
        $phpWord->addTableStyle($tableStyleName, [
            'borderColor' => '000000',
            'borderSize' => 6,
            'cellMarginTop' => 20,
            'cellMarginBottom' => 20,
            'cellMarginLeft' => 40,
            'cellMarginRight' => 40,
        ]);

        $table = $section->addTable($tableStyleName);

        // Exact proportions matching 860px reference (total = 15700 twips):
        // Col A=205px (3742), Col B=23px (420), Col C=159px (2903),
        // Col D=36px (657), Col E=36px (657), Col F=62px (1132),
        // Col G=251px (4582), Col H=88px (1607)
        $wProg = 3742;
        $wSeq = 420;
        $wName = 2903;
        $wStatE = 657;
        $wStatUE = 657;
        $wStatNT = 1132;
        $wStatTotal = $wStatE + $wStatUE + $wStatNT; // 2446
        $wEmp = 4582;
        $wDoc = 1607;

        $noSpacePara = ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'spaceBefore' => 0];
        $leftPara = ['alignment' => Jc::LEFT, 'spaceAfter' => 0, 'spaceBefore' => 0];

        // Row 1: Column Headers (first page only, no tblHeader repeat) - height 30px (450 twips)
        $table->addRow(450, ['cantSplit' => true]);
        $table->addCell($wProg + $wSeq, ['gridSpan' => 2, 'valign' => 'center'])->addText($this->escapeWordXmlText('Program Name'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);
        $table->addCell($wName, ['valign' => 'center'])->addText($this->escapeWordXmlText('Name of Graduate'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);
        $statusHdrCell = $table->addCell($wStatTotal, ['gridSpan' => 3, 'valign' => 'center']);
        $statusHdrCell->addText($this->escapeWordXmlText('Graduate Tracer Status'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);
        $statusHdrCell->addText($this->escapeWordXmlText('(Employed, Unemployed, Not Tracked)'), ['name' => 'Tahoma', 'italic' => true, 'size' => 10], $noSpacePara);
        $table->addCell($wEmp, ['valign' => 'center'])->addText($this->escapeWordXmlText('Name of Employment / Business / Company'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);
        $table->addCell($wDoc, ['valign' => 'center'])->addText($this->escapeWordXmlText('Supporting Documentation'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);

        // Row 2: Campus Banner - height 15px (225 twips)
        $table->addRow(225, ['cantSplit' => true]);
        $table->addCell($wProg + $wSeq + $wName + $wStatTotal + $wEmp, ['gridSpan' => 7, 'bgColor' => 'DBE5F1', 'valign' => 'center'])
            ->addText($this->escapeWordXmlText($campusBannerText), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $leftPara);
        $table->addCell($wDoc, ['valign' => 'center'])->addText('', ['name' => 'Tahoma', 'size' => 10], $leftPara);

        if (empty($programGroups)) {
            $table->addRow(225);
            $table->addCell($wProg)->addText('—', ['name' => 'Tahoma', 'size' => 10], $leftPara);
            $table->addCell($wSeq)->addText('1', ['name' => 'Tahoma', 'size' => 10], $noSpacePara);
            $table->addCell($wName)->addText($this->escapeWordXmlText('No alumni records found'), ['name' => 'Tahoma', 'size' => 10], $leftPara);
            $table->addCell($wStatTotal, ['gridSpan' => 3])->addText($this->escapeWordXmlText('Not Tracked'), ['name' => 'Tahoma', 'size' => 10], $noSpacePara);
            $table->addCell($wEmp)->addText('', ['name' => 'Tahoma', 'size' => 10], $leftPara);
            $table->addCell($wDoc)->addText('', ['name' => 'Tahoma', 'size' => 10], $leftPara);
            return;
        }

        foreach ($programGroups as $group) {
            $programName = (string) ($group['programName'] ?? '');
            $programAbbr = (string) ($group['programAbbr'] ?? '');
            $graduates = $group['graduates'] ?? [];
            $gradCount = count($graduates);

            foreach ($graduates as $idx => $grad) {
                $table->addRow(225, ['cantSplit' => true]);
                $progCellStyle = $idx === 0 ? ['vMerge' => 'restart', 'valign' => 'top'] : ['vMerge' => 'continue'];
                $progCell = $table->addCell($wProg, $progCellStyle);
                if ($idx === 0) {
                    $progCell->addText($this->escapeWordXmlText($programName), ['name' => 'Tahoma', 'size' => 10], $leftPara);
                }

                $table->addCell($wSeq, ['valign' => 'center', 'cellMarginLeft' => 10, 'cellMarginRight' => 10])->addText((string) ($idx + 1), ['name' => 'Tahoma', 'size' => 10], $noSpacePara);
                $table->addCell($wName, ['valign' => 'center'])->addText($this->escapeWordXmlText((string) ($grad['name'] ?? '')), ['name' => 'Tahoma', 'size' => 10], $leftPara);
                $table->addCell($wStatTotal, ['gridSpan' => 3, 'valign' => 'center'])->addText($this->escapeWordXmlText((string) ($grad['status'] ?? 'Not Tracked')), ['name' => 'Tahoma', 'size' => 10], $noSpacePara);
                $table->addCell($wEmp, ['valign' => 'center'])->addText($this->escapeWordXmlText((string) ($grad['employment'] ?? '')), ['name' => 'Tahoma', 'size' => 10], $leftPara);
                $table->addCell($wDoc, ['valign' => 'center'])->addText($this->escapeWordXmlText((string) ($grad['supportingDoc'] ?? '')), ['name' => 'Tahoma', 'size' => 10], $leftPara);
            }

            $totalGraduates = (int) ($group['total'] ?? $gradCount);
            $employedCount = (int) ($group['employedCount'] ?? 0);
            $unemployedCount = (int) ($group['unemployedCount'] ?? 0);
            $notTrackedCount = (int) ($group['notTrackedCount'] ?? $totalGraduates);

            // Program Summary Row 1 - height 15px (225 twips)
            $table->addRow(225, ['cantSplit' => true]);
            $totalLabelCell = $table->addCell($wProg + $wSeq, ['gridSpan' => 2, 'vMerge' => 'restart', 'bgColor' => 'FFFF99', 'valign' => 'center']);
            $totalLabelCell->addText($this->escapeWordXmlText('TOTAL NUMBER OF STUDENTS - ' . $programAbbr), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);
            $totalLabelCell->addText($this->escapeWordXmlText($institutionName), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);

            $table->addCell($wName, ['vMerge' => 'restart', 'bgColor' => 'FFFF99', 'valign' => 'center'])
                ->addText((string) $totalGraduates, ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);
            $table->addCell($wStatE, ['bgColor' => 'FFFF99', 'valign' => 'center'])->addText('E', ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);
            $table->addCell($wStatUE, ['bgColor' => 'FFFF99', 'valign' => 'center'])->addText('UE', ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);
            $table->addCell($wStatNT, ['bgColor' => 'FFFF99', 'valign' => 'center'])->addText('NT', ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);
            $table->addCell($wEmp, ['vMerge' => 'restart', 'bgColor' => '000000', 'valign' => 'center'])->addText('', ['name' => 'Tahoma', 'size' => 10], $noSpacePara);
            $table->addCell($wDoc, ['valign' => 'center'])->addText('', ['name' => 'Tahoma', 'size' => 10], $noSpacePara);

            // Program Summary Row 2 - height 15px (225 twips)
            $table->addRow(225, ['cantSplit' => true]);
            $table->addCell($wProg + $wSeq, ['gridSpan' => 2, 'vMerge' => 'continue', 'bgColor' => 'FFFF99']);
            $table->addCell($wName, ['vMerge' => 'continue', 'bgColor' => 'FFFF99']);
            $table->addCell($wStatE, ['bgColor' => 'FFFF99', 'valign' => 'center'])->addText((string) $employedCount, ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);
            $table->addCell($wStatUE, ['bgColor' => 'FFFF99', 'valign' => 'center'])->addText((string) $unemployedCount, ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);
            $table->addCell($wStatNT, ['bgColor' => 'FFFF99', 'valign' => 'center'])->addText((string) $notTrackedCount, ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $noSpacePara);
            $table->addCell($wEmp, ['vMerge' => 'continue', 'bgColor' => '000000']);
            $table->addCell($wDoc, ['valign' => 'center'])->addText('', ['name' => 'Tahoma', 'size' => 10], $noSpacePara);
        }
    }

    private function writeProgramAlumniOutcomeSpreadsheet(string $filepath, array $programGroups): string
    {
        $institutionName = $this->getInstitutionHeaderName();
        $campusBannerText = str_ends_with(strtoupper($institutionName), 'CAMPUS')
            ? strtoupper($institutionName)
            : strtoupper($institutionName) . ' CAMPUS';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(10);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Graduate Tracer Report');

        // Exact pixel column widths from reference screenshots (Total = 860px)
        $sheet->getColumnDimension('A')->setWidth(205, 'px'); // Col 1a: Program Name & Top Header Labels
        $sheet->getColumnDimension('B')->setWidth(23, 'px');  // Col 1b: Sequence # (1, 2, 3...)
        $sheet->getColumnDimension('C')->setWidth(159, 'px'); // Col 2: Name of Graduate
        $sheet->getColumnDimension('D')->setWidth(36, 'px');  // Col 3a: Status E
        $sheet->getColumnDimension('E')->setWidth(36, 'px');  // Col 3b: Status UE
        $sheet->getColumnDimension('F')->setWidth(62, 'px');  // Col 3c: Status NT
        $sheet->getColumnDimension('G')->setWidth(251, 'px'); // Col 4: Name of Employment / Business / Company
        $sheet->getColumnDimension('H')->setWidth(88, 'px');  // Col 5: Supporting Documentation

        $thinBlackBorder = [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ];
        $thinGreyBorder = [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => 'D9D9D9'],
        ];

        // ============================================================
        // TOP INSTITUTIONAL OUTCOME INDICATOR HEADER BLOCK (Rows 1–7)
        // Label in Column A (205px), Value merged across B:H (655px)
        // ============================================================
        for ($r = 1; $r <= 7; $r++) {
            $sheet->mergeCells("B{$r}:H{$r}");
            $sheet->getStyle("A{$r}")->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
            $sheet->getStyle("B{$r}:H{$r}")->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)
                ->setWrapText(true);
            $sheet->getStyle("A{$r}:H{$r}")->getBorders()->getAllBorders()->applyFromArray($thinGreyBorder);
            $sheet->getStyle("H{$r}")->getBorders()->getRight()->applyFromArray($thinBlackBorder);
            $sheet->getStyle("A{$r}")->getBorders()->getLeft()->applyFromArray($thinBlackBorder);
            $sheet->getRowDimension($r)->setRowHeight($r === 4 ? 75 : 15, 'px');
        }

        // Row 1: NAME OF INSTITUTION (15px)
        $sheet->setCellValue('A1', 'NAME OF INSTITUTION:');
        $sheet->setCellValue('B1', $institutionName);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setItalic(true);
        $sheet->getStyle('B1:H1')->getFont()->setItalic(true);
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFE599');
        $sheet->getStyle('A1:H1')->getBorders()->getTop()->applyFromArray($thinBlackBorder);
        $sheet->getStyle('A1:H1')->getBorders()->getBottom()->applyFromArray($thinBlackBorder);

        // Row 2: PROGRAM (15px)
        $sheet->setCellValue('A2', 'PROGRAM:');
        $sheet->setCellValue('B2', 'HIGHER EDUCATION PROGRAM');
        $sheet->getStyle('A2:H2')->getFont()->setBold(true);

        // Row 3: INDICATOR/S (15px)
        $sheet->setCellValue('A3', 'INDICATOR/S:');
        $sheet->getStyle('A3')->getFont()->setBold(true);
        $indRich = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
        $indBold = $indRich->createTextRun('Outcome Indicator 2: ');
        $indBold->getFont()->setName('Tahoma')->setSize(10)->setBold(true);
        $indNorm = $indRich->createTextRun('Percentage of graduates (2 years prior) that are employed');
        $indNorm->getFont()->setName('Tahoma')->setSize(10);
        $sheet->setCellValue('B3', $indRich);

        // Row 4: INSTRUCTIONS (75px)
        $sheet->setCellValue('A4', 'INSTRUCTIONS:');
        $sheet->getStyle('A4')->getFont()->setBold(true);
        $instRich = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
        $addInstText = static function (\PhpOffice\PhpSpreadsheet\RichText\RichText $rt, string $text, bool $bold = false): void {
            $run = $rt->createTextRun($text);
            $run->getFont()->setName('Tahoma')->setSize(10)->setBold($bold);
        };
        $addInstText($instRich, 'In ');
        $addInstText($instRich, 'Column 1', true);
        $addInstText($instRich, ", in the campus header, input the name and address of each campus. List down the degree program for each graduate.\nIn ");
        $addInstText($instRich, 'Column 2', true);
        $addInstText($instRich, ' and ');
        $addInstText($instRich, 'Column 3', true);
        $addInstText($instRich, ", list down all the 1st semester FY 2023-2024 graduates per degree program and then provide the status for each graduate.\nIn ");
        $addInstText($instRich, 'Column 4', true);
        $addInstText($instRich, ', input the employment, business, or company of the graduate. For this purpose, Employment refers to any mode or form of employment regardless of status, including self-employment.');
        $sheet->setCellValue('B4', $instRich);

        // Row 5: REFERENCES (15px)
        $sheet->setCellValue('A5', 'REFERENCES:');
        $sheet->getStyle('A5')->getFont()->setBold(true);
        $sheet->setCellValue('B5', 'Summary Sheets');

        // Row 6: MEANS OF VERIFICATION (15px)
        $sheet->setCellValue('A6', 'MEANS OF VERIFICATION:');
        $sheet->getStyle('A6')->getFont()->setBold(true);
        $sheet->setCellValue('B6', '1. Graduate tracer study');

        // Row 7: LINK TO GOOGLE DRIVE FOLDER (15px)
        $sheet->setCellValue('A7', 'LINK TO GOOGLE DRIVE FOLDER:');
        $sheet->setCellValue('B7', '(Please input your Google Drive Folder link here.)');
        $sheet->getStyle('A7')->getFont()->setBold(true)->setItalic(true);
        $sheet->getStyle('B7:H7')->getFont()->setItalic(true);
        $sheet->getStyle('A7:H7')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFE599');
        $sheet->getStyle('A7:H7')->getBorders()->getTop()->applyFromArray($thinBlackBorder);
        $sheet->getStyle('A7:H7')->getBorders()->getBottom()->applyFromArray($thinBlackBorder);

        // ============================================================
        // 2 BLANK GAP ROWS BETWEEN HEADER AND TABLE (Rows 8–9, 12px each)
        // ============================================================
        $sheet->getRowDimension(8)->setRowHeight(12, 'px');
        $sheet->getRowDimension(9)->setRowHeight(12, 'px');

        // ============================================================
        // GRADUATE TRACER TABLE HEADERS (Rows 10–11)
        // ============================================================
        // Row 10: Column Titles - height 30px
        $sheet->mergeCells('A10:B10');
        $sheet->setCellValue('A10', 'Program Name');
        $sheet->setCellValue('C10', 'Name of Graduate');
        $sheet->mergeCells('D10:F10');

        $statusHdrRich = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
        $stBold = $statusHdrRich->createTextRun("Graduate Tracer Status\n");
        $stBold->getFont()->setName('Tahoma')->setSize(10)->setBold(true);
        $stItal = $statusHdrRich->createTextRun('(Employed, Unemployed, Not Tracked)');
        $stItal->getFont()->setName('Tahoma')->setSize(10)->setItalic(true);
        $sheet->setCellValue('D10', $statusHdrRich);

        $sheet->setCellValue('G10', 'Name of Employment / Business / Company');
        $sheet->setCellValue('H10', 'Supporting Documentation');

        $sheet->getStyle('A10:C10')->getFont()->setBold(true);
        $sheet->getStyle('G10:H10')->getFont()->setBold(true);
        $sheet->getStyle('A10:H10')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle('A10:H10')->getBorders()->getAllBorders()->applyFromArray($thinBlackBorder);
        $sheet->getRowDimension(10)->setRowHeight(30, 'px');

        // Row 11: Campus Banner - height 15px
        $sheet->mergeCells('A11:G11');
        $sheet->setCellValue('A11', $campusBannerText);
        $sheet->getStyle('A11:G11')->getFont()->setBold(true);
        $sheet->getStyle('A11:G11')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DBE5F1');
        $sheet->getStyle('A11:G11')->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A11:G11')->getBorders()->getAllBorders()->applyFromArray($thinBlackBorder);
        $sheet->getStyle('H11')->getBorders()->getRight()->applyFromArray($thinBlackBorder);
        $sheet->getRowDimension(11)->setRowHeight(15, 'px');

        // ============================================================
        // PER-PROGRAM GRADUATE ROWS & SUMMARY BLOCKS (Row 12+)
        // ============================================================
        $currentRow = 12;

        if (empty($programGroups)) {
            $sheet->setCellValue("A{$currentRow}", '—');
            $sheet->setCellValue("B{$currentRow}", 1);
            $sheet->setCellValue("C{$currentRow}", 'No alumni records found');
            $sheet->mergeCells("D{$currentRow}:F{$currentRow}");
            $sheet->setCellValue("D{$currentRow}", 'Not Tracked');
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$currentRow}:G{$currentRow}")->getBorders()->getAllBorders()->applyFromArray($thinBlackBorder);
            $sheet->getStyle("H{$currentRow}")->getBorders()->getRight()->applyFromArray($thinBlackBorder);
            $sheet->getRowDimension($currentRow)->setRowHeight(15, 'px');
        } else {
            foreach ($programGroups as $group) {
                $programName = (string) ($group['programName'] ?? '');
                $programAbbr = (string) ($group['programAbbr'] ?? '');
                $graduates = $group['graduates'] ?? [];
                $gradCount = count($graduates);

                $startRow = $currentRow;
                foreach ($graduates as $idx => $grad) {
                    if ($idx === 0) {
                        $sheet->setCellValue("A{$currentRow}", $programName);
                    }
                    $sheet->setCellValue("B{$currentRow}", $idx + 1);
                    $sheet->setCellValue("C{$currentRow}", (string) ($grad['name'] ?? ''));
                    $sheet->mergeCells("D{$currentRow}:F{$currentRow}");
                    $sheet->setCellValue("D{$currentRow}", (string) ($grad['status'] ?? 'Not Tracked'));
                    $sheet->setCellValue("G{$currentRow}", (string) ($grad['employment'] ?? ''));
                    $sheet->setCellValue("H{$currentRow}", (string) ($grad['supportingDoc'] ?? ''));

                    $sheet->getStyle("B{$currentRow}")->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    $sheet->getStyle("C{$currentRow}")->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    $sheet->getStyle("D{$currentRow}:F{$currentRow}")->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                    $sheet->getStyle("G{$currentRow}:H{$currentRow}")->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

                    $sheet->getStyle("B{$currentRow}:G{$currentRow}")->getBorders()->getAllBorders()->applyFromArray($thinBlackBorder);
                    $sheet->getStyle("H{$currentRow}")->getBorders()->getAllBorders()->applyFromArray($thinGreyBorder);
                    $sheet->getStyle("H{$currentRow}")->getBorders()->getRight()->applyFromArray($thinBlackBorder);
                    $sheet->getRowDimension($currentRow)->setRowHeight(15, 'px');

                    $currentRow++;
                }
                $endRow = $currentRow - 1;

                if ($endRow >= $startRow) {
                    if ($endRow > $startRow) {
                        $sheet->mergeCells("A{$startRow}:A{$endRow}");
                    }
                    $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                        ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP)
                        ->setWrapText(true);
                    $sheet->getStyle("A{$startRow}:A{$endRow}")->getBorders()->getOutline()->applyFromArray($thinBlackBorder);
                }

                $totalGraduates = (int) ($group['total'] ?? $gradCount);
                $employedCount = (int) ($group['employedCount'] ?? 0);
                $unemployedCount = (int) ($group['unemployedCount'] ?? 0);
                $notTrackedCount = (int) ($group['notTrackedCount'] ?? $totalGraduates);

                // 2-Row Program Summary Block (15px + 15px = 30px)
                $sumRow1 = $currentRow;
                $sumRow2 = $currentRow + 1;

                // Col 1 (A:B merged across 2 rows): TOTAL NUMBER OF STUDENTS - {ABBR} \n {INSTITUTION}
                $sheet->mergeCells("A{$sumRow1}:B{$sumRow2}");
                $sheet->setCellValue("A{$sumRow1}", "TOTAL NUMBER OF STUDENTS - {$programAbbr}\n{$institutionName}");

                // Col 2 (C merged across 2 rows): Total count
                $sheet->mergeCells("C{$sumRow1}:C{$sumRow2}");
                $sheet->setCellValue("C{$sumRow1}", $totalGraduates);

                // Col 3 (D, E, F): Row 1 = E | UE | NT, Row 2 = counts
                $sheet->setCellValue("D{$sumRow1}", 'E');
                $sheet->setCellValue("E{$sumRow1}", 'UE');
                $sheet->setCellValue("F{$sumRow1}", 'NT');
                $sheet->setCellValue("D{$sumRow2}", $employedCount);
                $sheet->setCellValue("E{$sumRow2}", $unemployedCount);
                $sheet->setCellValue("F{$sumRow2}", $notTrackedCount);

                // Col 4 (G merged across 2 rows): Solid black fill
                $sheet->mergeCells("G{$sumRow1}:G{$sumRow2}");
                $sheet->setCellValue("G{$sumRow1}", '');

                // Style Yellow Summary Cells (A..F)
                $sheet->getStyle("A{$sumRow1}:F{$sumRow2}")->getFont()->setBold(true);
                $sheet->getStyle("A{$sumRow1}:F{$sumRow2}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFFF99');
                $sheet->getStyle("A{$sumRow1}:F{$sumRow2}")->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle("A{$sumRow1}:F{$sumRow2}")->getBorders()->getAllBorders()->applyFromArray($thinBlackBorder);

                // Style Solid Black Cell (G)
                $sheet->getStyle("G{$sumRow1}:G{$sumRow2}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('000000');
                $sheet->getStyle("G{$sumRow1}:G{$sumRow2}")->getBorders()->getAllBorders()->applyFromArray($thinBlackBorder);

                // Style Supporting Doc Summary Cells (H - unmerged 2 rows with light grey borders and black right edge)
                $sheet->getStyle("H{$sumRow1}:H{$sumRow2}")->getBorders()->getAllBorders()->applyFromArray($thinGreyBorder);
                $sheet->getStyle("H{$sumRow1}:H{$sumRow2}")->getBorders()->getRight()->applyFromArray($thinBlackBorder);

                $sheet->getRowDimension($sumRow1)->setRowHeight(15, 'px');
                $sheet->getRowDimension($sumRow2)->setRowHeight(15, 'px');

                $currentRow += 2;
            }
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);

        return $filepath;
    }

    private function getInstitutionHeaderName(): string
    {
        $campusModel = new \App\Models\CampusModel();
        $campusRow = null;

        $campusIdOrName = $this->getHeadAdminCampusId();
        if ($campusIdOrName !== null && $campusIdOrName !== '') {
            if (is_numeric($campusIdOrName)) {
                $campusRow = $campusModel->where('Campus_ID', (int) $campusIdOrName)->first();
            }
            if (! is_array($campusRow)) {
                $campusRow = $campusModel->where('Campus', (string) $campusIdOrName)->first();
            }
        }

        if (! is_array($campusRow)) {
            $headAdminEmail = trim((string) (session('auth_user.email') ?? session('auth_user.Email') ?? ''));
            if ($headAdminEmail !== '') {
                $campusRow = $campusModel->where('LOWER("Alumni_Head_Email")', strtolower($headAdminEmail))->first();
            }
        }

        if (! is_array($campusRow)) {
            $campusRow = $campusModel->orderBy('Campus_ID', 'ASC')->first();
        }

        if (is_array($campusRow)) {
            $abbreviation = trim((string) ($campusRow['Abbreviation'] ?? ''));
            $campusName = trim((string) ($campusRow['Campus'] ?? ''));
            $combined = trim($abbreviation . ' ' . $campusName);
            if ($combined !== '') {
                return strtoupper($combined);
            }
        }

        return 'USTP CLAVERIA';
    }

    private function buildInstitutionalOutcomeHeaderHtml(): string
    {
        $institutionName = htmlspecialchars($this->getInstitutionHeaderName(), ENT_QUOTES, 'UTF-8');

        return '
        <table class="outcome-header-table">
            <tr class="row-highlight">
                <td class="col-label"><em>NAME OF INSTITUTION:</em></td>
                <td class="col-value"><em>' . $institutionName . '</em></td>
            </tr>
            <tr>
                <td class="col-label">PROGRAM:</td>
                <td class="col-value"><strong>HIGHER EDUCATION PROGRAM</strong></td>
            </tr>
            <tr>
                <td class="col-label">INDICATOR/S:</td>
                <td class="col-value"><strong>Outcome Indicator 2:</strong> Percentage of graduates (2 years prior) that are employed</td>
            </tr>
            <tr class="row-instructions">
                <td class="col-label">INSTRUCTIONS:</td>
                <td class="col-value">
                    In <strong>Column 1</strong>, in the campus header, input the name and address of each campus. List down the degree program for each graduate.<br>
                    In <strong>Column 2</strong> and <strong>Column 3</strong>, list down all the 1st semester FY 2023-2024 graduates per degree program and then provide the status for each graduate.<br>
                    In <strong>Column 4</strong>, input the employment, business, or company of the graduate. For this purpose, Employment refers to any mode or form of employment regardless of status, including self-employment.
                </td>
            </tr>
            <tr>
                <td class="col-label">REFERENCES:</td>
                <td class="col-value">Summary Sheets</td>
            </tr>
            <tr>
                <td class="col-label">MEANS OF VERIFICATION:</td>
                <td class="col-value">1. Graduate tracer study</td>
            </tr>
            <tr class="row-highlight">
                <td class="col-label"><em>LINK TO GOOGLE DRIVE FOLDER:</em></td>
                <td class="col-value"><em>(Please input your Google Drive Folder link here.)</em></td>
            </tr>
        </table>';
    }

    private function addInstitutionalOutcomeHeaderToWordSection(PhpWord $phpWord, $section): void
    {
        $institutionName = $this->getInstitutionHeaderName();
        $outcomeTableStyleName = 'OutcomeHeaderTable_' . uniqid();
        $phpWord->addTableStyle($outcomeTableStyleName, [
            'borderColor' => '000000',
            'borderSize' => 6,
            'cellMarginTop' => 20,
            'cellMarginBottom' => 20,
            'cellMarginLeft' => 40,
            'cellMarginRight' => 40,
        ]);

        $table = $section->addTable($outcomeTableStyleName);
        // Matches Column A (205px = 3742 twips) and Columns B:H (655px = 11958 twips)
        $labelWidth = 3742;
        $valueWidth = 11958;
        $rightPara = ['alignment' => Jc::RIGHT, 'spaceAfter' => 0, 'spaceBefore' => 0];
        $leftPara = ['alignment' => Jc::LEFT, 'spaceAfter' => 0, 'spaceBefore' => 0];

        // Row 1: NAME OF INSTITUTION (15px = 225 twips, yellow highlight)
        $table->addRow(225);
        $table->addCell($labelWidth, ['bgColor' => 'FFE599'])->addText($this->escapeWordXmlText('NAME OF INSTITUTION:'), ['name' => 'Tahoma', 'bold' => true, 'italic' => true, 'size' => 10], $rightPara);
        $table->addCell($valueWidth, ['bgColor' => 'FFE599'])->addText($this->escapeWordXmlText($institutionName), ['name' => 'Tahoma', 'italic' => true, 'size' => 10], $leftPara);

        // Row 2: PROGRAM (15px = 225 twips)
        $table->addRow(225);
        $table->addCell($labelWidth)->addText($this->escapeWordXmlText('PROGRAM:'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $rightPara);
        $table->addCell($valueWidth)->addText($this->escapeWordXmlText('HIGHER EDUCATION PROGRAM'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $leftPara);

        // Row 3: INDICATOR/S (15px = 225 twips)
        $table->addRow(225);
        $table->addCell($labelWidth)->addText($this->escapeWordXmlText('INDICATOR/S:'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $rightPara);
        $indCell = $table->addCell($valueWidth);
        $indRun = $indCell->addTextRun($leftPara);
        $indRun->addText($this->escapeWordXmlText('Outcome Indicator 2: '), ['name' => 'Tahoma', 'bold' => true, 'size' => 10]);
        $indRun->addText($this->escapeWordXmlText('Percentage of graduates (2 years prior) that are employed'), ['name' => 'Tahoma', 'size' => 10]);

        // Row 4: INSTRUCTIONS (75px = 1125 twips)
        $table->addRow(1125);
        $table->addCell($labelWidth)->addText($this->escapeWordXmlText('INSTRUCTIONS:'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $rightPara);
        $instCell = $table->addCell($valueWidth);

        $line1 = $instCell->addTextRun($leftPara);
        $line1->addText($this->escapeWordXmlText('In '), ['name' => 'Tahoma', 'size' => 10]);
        $line1->addText($this->escapeWordXmlText('Column 1'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10]);
        $line1->addText($this->escapeWordXmlText(', in the campus header, input the name and address of each campus. List down the degree program for each graduate.'), ['name' => 'Tahoma', 'size' => 10]);

        $line2 = $instCell->addTextRun($leftPara);
        $line2->addText($this->escapeWordXmlText('In '), ['name' => 'Tahoma', 'size' => 10]);
        $line2->addText($this->escapeWordXmlText('Column 2'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10]);
        $line2->addText($this->escapeWordXmlText(' and '), ['name' => 'Tahoma', 'size' => 10]);
        $line2->addText($this->escapeWordXmlText('Column 3'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10]);
        $line2->addText($this->escapeWordXmlText(', list down all the 1st semester FY 2023-2024 graduates per degree program and then provide the status for each graduate.'), ['name' => 'Tahoma', 'size' => 10]);

        $line3 = $instCell->addTextRun($leftPara);
        $line3->addText($this->escapeWordXmlText('In '), ['name' => 'Tahoma', 'size' => 10]);
        $line3->addText($this->escapeWordXmlText('Column 4'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10]);
        $line3->addText($this->escapeWordXmlText(', input the employment, business, or company of the graduate. For this purpose, Employment refers to any mode or form of employment regardless of status, including self-employment.'), ['name' => 'Tahoma', 'size' => 10]);

        // Row 5: REFERENCES (15px = 225 twips)
        $table->addRow(225);
        $table->addCell($labelWidth)->addText($this->escapeWordXmlText('REFERENCES:'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $rightPara);
        $table->addCell($valueWidth)->addText($this->escapeWordXmlText('Summary Sheets'), ['name' => 'Tahoma', 'size' => 10], $leftPara);

        // Row 6: MEANS OF VERIFICATION (15px = 225 twips)
        $table->addRow(225);
        $table->addCell($labelWidth)->addText($this->escapeWordXmlText('MEANS OF VERIFICATION:'), ['name' => 'Tahoma', 'bold' => true, 'size' => 10], $rightPara);
        $table->addCell($valueWidth)->addText($this->escapeWordXmlText('1. Graduate tracer study'), ['name' => 'Tahoma', 'size' => 10], $leftPara);

        // Row 7: LINK TO GOOGLE DRIVE FOLDER (15px = 225 twips, yellow highlight)
        $table->addRow(225);
        $table->addCell($labelWidth, ['bgColor' => 'FFE599'])->addText($this->escapeWordXmlText('LINK TO GOOGLE DRIVE FOLDER:'), ['name' => 'Tahoma', 'bold' => true, 'italic' => true, 'size' => 10], $rightPara);
        $table->addCell($valueWidth, ['bgColor' => 'FFE599'])->addText($this->escapeWordXmlText('(Please input your Google Drive Folder link here.)'), ['name' => 'Tahoma', 'italic' => true, 'size' => 10], $leftPara);
    }

    private function buildFilterSummaryString(array $filters): string
    {
        $parts = [];
        if (! empty($filters['graduation_year'] ?? $filters['graduationYear'] ?? null)) {
            $parts[] = 'Graduation Year: ' . ($filters['graduation_year'] ?? $filters['graduationYear']);
        }
        if (! empty($filters['college'] ?? null)) {
            $parts[] = 'College: ' . $filters['college'];
        }
        if (! empty($filters['course'] ?? $filters['department'] ?? null)) {
            $parts[] = 'Program: ' . ($filters['course'] ?? $filters['department']);
        }
        if (! empty($filters['review_status'] ?? $filters['reviewStatus'] ?? null) && strtolower((string) ($filters['review_status'] ?? $filters['reviewStatus'])) !== 'all') {
            $parts[] = 'Review Status: ' . ucfirst($filters['review_status'] ?? $filters['reviewStatus']);
        }
        if (! empty($filters['date_from'] ?? $filters['dateFrom'] ?? null)) {
            $parts[] = 'From: ' . ($filters['date_from'] ?? $filters['dateFrom']);
        }
        if (! empty($filters['date_to'] ?? $filters['dateTo'] ?? null)) {
            $parts[] = 'To: ' . ($filters['date_to'] ?? $filters['dateTo']);
        }
        if (! empty($filters['search_query'] ?? $filters['searchQuery'] ?? null)) {
            $parts[] = 'Search: "' . ($filters['search_query'] ?? $filters['searchQuery']) . '"';
        }
        if (! empty($filters['answer_filters'] ?? $filters['answerFilters'] ?? null)) {
            $afCount = count($filters['answer_filters'] ?? $filters['answerFilters']);
            $parts[] = $afCount . ' Survey Answer Filter' . ($afCount === 1 ? '' : 's');
        }

        return implode('; ', $parts);
    }

    /**
     * Legacy General Alumni Report Generator (Fallback)
     */
    private function generateLegacyAlumniReport(array $jsonData): ResponseInterface
    {
        $format = strtolower((string) ($jsonData['format'] ?? 'excel'));
        $reportType = strtolower((string) ($jsonData['reportType'] ?? 'comprehensive'));
        $includeSummary = (bool) ($jsonData['includeSummary'] ?? true);
        $includeCharts = (bool) ($jsonData['includeCharts'] ?? true);
        $includeDetails = (bool) ($jsonData['includeDetails'] ?? true);
        $includeContactInfo = (bool) ($jsonData['includeContactInfo'] ?? true);

        if (! in_array($reportType, ['comprehensive', 'master_list', 'status', 'year', 'college', 'course'], true)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Invalid report type specified.',
            ]);
        }

        if (! $includeSummary && ! $includeCharts && ! $includeDetails) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Select at least one report content option.',
            ]);
        }

        // Load alumni data
        $alumniModel = new \App\Models\AlumniModel();
        $alumniData = $alumniModel->getSortedList('last_name_asc');

        $reportData = $this->prepareReportData($alumniData, [
            'reportType' => $reportType,
            'includeSummary' => $includeSummary,
            'includeCharts' => $includeCharts,
            'includeDetails' => $includeDetails,
            'includeContactInfo' => $includeContactInfo,
        ]);

        if ($includeCharts) {
            $chartUrls = $this->generateCharts($reportData['chartKeys']);
            $chartBase64 = [];
            foreach ($chartUrls as $key => $url) {
                $chartBase64[$key] = $this->getChartBase64($url);
            }
            $reportData['charts'] = $chartBase64;
        }
        
        $filename = 'alumni_report_' . date('Y-m-d');
        
        switch ($format) {
            case 'excel':
            case 'csv':
            case 'xlsx':
                $file = $this->generateExcelReport($reportData, $filename);
                $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                $extension = 'xlsx';
                break;
            case 'pdf':
                $file = $this->generatePdfReport($reportData, $filename);
                $mimeType = 'application/pdf';
                $extension = 'pdf';
                break;
            case 'word':
                $file = $this->generateWordReport($reportData, $filename);
                $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                $extension = 'docx';
                break;
            default:
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Invalid format specified.',
                ]);
        }

        return $this->response
            ->download($file, null)
            ->setFileName($filename . '.' . $extension)
            ->setHeader('Content-Type', $mimeType);
    }

    private function prepareReportData($alumniData, array $options): array
    {
        $reportType = $options['reportType'] ?? 'comprehensive';
        $normalizedRecords = array_map(function (array $alumni): array {
            $firstName = trim((string) ($alumni['FirstName'] ?? ''));
            $lastName = trim((string) ($alumni['LastName'] ?? ''));
            return [
                'name' => trim($firstName . ' ' . $lastName),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => (string) ($alumni['Email'] ?? ''),
                'year' => (string) ($alumni['GraduationYear'] ?? 'Unknown'),
                'status' => (string) ($alumni['Status'] ?? 'Unknown'),
                'college' => (string) ($alumni['College'] ?? 'Unknown'),
                'course' => (string) ($alumni['Course'] ?? 'Unknown'),
            ];
        }, $alumniData);

        $sectionLabels = $this->getReportSectionLabels();
        $reportTitles = $this->getReportTypeLabels();
        $sectionOrder = match ($reportType) {
            'status' => ['status'],
            'year' => ['year'],
            'college' => ['college'],
            'course' => ['course'],
            'master_list' => [],
            default => ['status', 'year', 'college', 'course'],
        };

        $groupSections = [
            'status' => $this->groupRecords($normalizedRecords, 'status', 'Status'),
            'year' => $this->groupRecords($normalizedRecords, 'year', 'Graduation Year', true),
            'college' => $this->groupRecords($normalizedRecords, 'college', 'College'),
            'course' => $this->groupRecords($normalizedRecords, 'course', 'Course'),
        ];

        $summary = [];
        foreach ($groupSections as $key => $section) {
            $summary[$key] = array_map(
                static fn (array $group): array => [
                    'label' => $group['label'],
                    'count' => $group['count'],
                ],
                $section['groups']
            );
        }

        return [
            'reportType' => $reportType,
            'reportTitle' => $reportTitles[$reportType] ?? 'Alumni Report',
            'generatedDate' => date('Y-m-d H:i:s'),
            'total' => count($normalizedRecords),
            'records' => $normalizedRecords,
            'sections' => array_intersect_key($groupSections, array_flip($sectionOrder)),
            'sectionOrder' => $sectionOrder,
            'sectionLabels' => $sectionLabels,
            'summary' => $summary,
            'chartKeys' => $sectionOrder === [] ? ['status', 'year', 'college', 'course'] : $sectionOrder,
            'options' => [
                'includeSummary' => (bool) ($options['includeSummary'] ?? true),
                'includeCharts' => (bool) ($options['includeCharts'] ?? true),
                'includeDetails' => (bool) ($options['includeDetails'] ?? true),
                'includeContactInfo' => (bool) ($options['includeContactInfo'] ?? true),
            ],
        ];
    }

    private function generateExcelReport($reportData, $filename): string
    {
        $filepath = WRITEPATH . 'uploads/' . $filename . '.xlsx';
        if (! is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0777, true);
        }

        $programGroups = $this->getAlumniProgramGroups([]);
        return $this->writeProgramAlumniOutcomeSpreadsheet($filepath, $programGroups);
    }

    private function generatePdfReport($reportData, $filename): string
    {
        $filepath = WRITEPATH . 'uploads/' . $filename . '.pdf';
        $html = $this->buildHtmlReport($reportData, 'pdf');

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        file_put_contents($filepath, $dompdf->output());
        return $filepath;
    }

    private function generateWordReport($reportData, $filename): string
    {
        $filepath = WRITEPATH . 'uploads/' . $filename . '.docx';

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Tahoma');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'pageSizeW' => 16838,
            'pageSizeH' => 11906,
            'marginTop' => 567,
            'marginBottom' => 567,
            'marginLeft' => 567,
            'marginRight' => 567,
        ]);

        $this->addInstitutionalOutcomeHeaderToWordSection($phpWord, $section);
        $section->addTextBreak(1);

        $programGroups = $this->getAlumniProgramGroups([]);
        $this->addProgramAlumniOutcomeTableToWordSection($phpWord, $section, $programGroups);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);
        return $filepath;
    }

    private function buildHtmlReport(array $reportData, string $variant): string
    {
        $isWord = $variant === 'word';
        $html = '<!DOCTYPE html>';
        $html .= $isWord
            ? '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">'
            : '<html>';
        $html .= '<head><meta charset="utf-8"><title>' . $this->escapeHtml($reportData['reportTitle']) . '</title><style>';
        $html .= '@page { margin: 8mm 6mm; }';
        $html .= 'body { font-family: Tahoma, Arial, Helvetica, sans-serif; font-size: 10pt; color: #000000; line-height: 1.15; }';
        $html .= '.outcome-header-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; border: 1px solid #000000; font-family: Tahoma, Arial, Helvetica, sans-serif; font-size: 10pt; color: #000000; table-layout: fixed; }';
        $html .= '.outcome-header-table tr { height: 15px; }';
        $html .= '.outcome-header-table tr.row-instructions { height: 75px; }';
        $html .= '.outcome-header-table td { padding: 1px 4px; border: 1px solid #d9d9d9; vertical-align: top; line-height: 1.15; }';
        $html .= '.outcome-header-table tr.row-highlight td { background-color: #ffe599; border-top: 1px solid #000000; border-bottom: 1px solid #000000; }';
        $html .= '.outcome-header-table td.col-label { width: 23.84%; text-align: right; font-weight: bold; white-space: nowrap; border-left: 1px solid #000000; }';
        $html .= '.outcome-header-table td.col-value { width: 76.16%; text-align: left; border-right: 1px solid #000000; }';
        $html .= '.tracer-outcome-table { width: 100%; border-collapse: collapse; font-family: Tahoma, Arial, Helvetica, sans-serif; font-size: 10pt; color: #000000; }';
        $html .= '.tracer-outcome-table th, .tracer-outcome-table td { border: 1px solid #000000; padding: 1px 4px; vertical-align: middle; word-wrap: break-word; line-height: 1.15; }';
        $html .= '.tracer-outcome-table tr.tr-num { height: 15px; }';
        $html .= '.tracer-outcome-table tr.tr-title { height: 30px; }';
        $html .= '.tracer-outcome-table tr.tr-banner { height: 15px; }';
        $html .= '.tracer-outcome-table tr.tr-data { height: 15px; }';
        $html .= '.tracer-outcome-table tr.tr-sum { height: 15px; }';
        $html .= '.tracer-outcome-table th.th-num { text-align: center; font-weight: normal; background-color: #ffffff; height: 15px; padding: 1px 2px; }';
        $html .= '.tracer-outcome-table th.th-title { text-align: center; font-weight: bold; background-color: #ffffff; height: 30px; padding: 2px 4px; }';
        $html .= '.tracer-outcome-table .th-sub { font-weight: normal; font-style: italic; font-size: 10pt; }';
        $html .= '.tracer-outcome-table td.td-campus-banner { background-color: #dbe5f1; font-weight: bold; text-align: left; height: 15px; padding: 1px 4px; text-transform: uppercase; }';
        $html .= '.tracer-outcome-table td.td-prog { width: 23.84%; vertical-align: top; text-align: left; padding: 1px 4px; border-left: 1px solid #000000; border-right: 1px solid #000000; }';
        $html .= '.tracer-outcome-table td.td-seq { width: 2.67%; text-align: center; padding: 1px 1px; white-space: nowrap; }';
        $html .= '.tracer-outcome-table td.td-grad-name { width: 18.49%; text-align: left; padding: 1px 4px; }';
        $html .= '.tracer-outcome-table td.td-status { width: 15.58%; text-align: center; padding: 1px 2px; }';
        $html .= '.tracer-outcome-table td.td-emp { width: 29.19%; text-align: left; padding: 1px 4px; }';
        $html .= '.tracer-outcome-table td.td-doc-cell { width: 10.23%; text-align: left; border-color: #e5e7eb; border-right: 1px solid #000000; padding: 1px 4px; }';
        $html .= '.tracer-outcome-table td.td-yellow-top { background-color: #ffff99; font-weight: bold; text-align: center; border-bottom: none; vertical-align: bottom; padding: 1px 2px 0 2px; height: 15px; }';
        $html .= '.tracer-outcome-table td.td-yellow-bottom { background-color: #ffff99; font-weight: bold; text-align: center; border-top: none; vertical-align: top; padding: 0 2px 1px 2px; height: 15px; }';
        $html .= '.tracer-outcome-table td.td-yellow-merged { background-color: #ffff99; font-weight: bold; text-align: center; vertical-align: middle; height: 30px; padding: 1px 2px; }';
        $html .= '.tracer-outcome-table td.td-yellow-cell { background-color: #ffff99; font-weight: bold; text-align: center; height: 15px; padding: 1px 2px; }';
        $html .= '.tracer-outcome-table td.td-sum-e { width: 4.19%; }';
        $html .= '.tracer-outcome-table td.td-sum-ue { width: 4.19%; }';
        $html .= '.tracer-outcome-table td.td-sum-nt { width: 7.21%; }';
        $html .= '.tracer-outcome-table td.td-black-top { width: 29.19%; background-color: #000000; border-bottom: none; height: 15px; }';
        $html .= '.tracer-outcome-table td.td-black-bottom { width: 29.19%; background-color: #000000; border-top: none; height: 15px; }';
        $html .= '</style></head><body>';
        $html .= $this->buildInstitutionalOutcomeHeaderHtml();
        $programGroups = $this->getAlumniProgramGroups([]);
        $html .= $this->buildProgramAlumniOutcomeTableHtml($programGroups);
        $html .= '</body></html>';
        return $html;
    }

    private function buildHtmlRecordsTable(array $records, bool $includeContactInfo): string
    {
        $html = '<table><thead><tr>';
        $html .= '<th>Name</th>';
        if ($includeContactInfo) {
            $html .= '<th>Email</th>';
        }
        $html .= '<th>Graduation Year</th><th>Status</th><th>College</th><th>Course</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($records as $record) {
            $html .= '<tr>';
            $html .= '<td>' . $this->escapeHtml($record['name']) . '</td>';
            if ($includeContactInfo) {
                $html .= '<td>' . $this->escapeHtml($record['email']) . '</td>';
            }
            $html .= '<td>' . $this->escapeHtml($record['year']) . '</td>';
            $html .= '<td>' . $this->escapeHtml($record['status']) . '</td>';
            $html .= '<td>' . $this->escapeHtml($record['college']) . '</td>';
            $html .= '<td>' . $this->escapeHtml($record['course']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    private function buildCsvRecordTable(array $records, bool $includeContactInfo): array
    {
        $rows = [];
        $header = ['Name'];
        if ($includeContactInfo) {
            $header[] = 'Email';
        }
        $header = array_merge($header, ['Graduation Year', 'Status', 'College', 'Course']);
        $rows[] = $header;

        foreach ($records as $record) {
            $row = [$record['name']];
            if ($includeContactInfo) {
                $row[] = $record['email'];
            }
            $row[] = $record['year'];
            $row[] = $record['status'];
            $row[] = $record['college'];
            $row[] = $record['course'];
            $rows[] = $row;
        }

        $rows[] = [];
        return $rows;
    }

    private function csvRow(array $columns): string
    {
        return implode(',', array_map(function ($value): string {
            $string = (string) $value;
            $string = str_replace('"', '""', $string);
            return '"' . $string . '"';
        }, $columns));
    }

    private function groupRecords(array $records, string $field, string $title, bool $sortDesc = false): array
    {
        $groups = [];
        foreach ($records as $record) {
            $label = trim((string) ($record[$field] ?? ''));
            if ($label === '') {
                $label = 'Unknown';
            }

            if (! isset($groups[$label])) {
                $groups[$label] = [
                    'label' => $label,
                    'count' => 0,
                    'records' => [],
                ];
            }

            $groups[$label]['count']++;
            $groups[$label]['records'][] = $record;
        }

        if ($sortDesc) {
            uksort($groups, static fn ($a, $b): int => strnatcasecmp((string) $b, (string) $a));
        } else {
            uasort($groups, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcasecmp($a['label'], $b['label']));
        }

        return [
            'title' => $title,
            'groups' => array_values($groups),
        ];
    }

    private function getReportSectionLabels(): array
    {
        return [
            'status' => 'Status',
            'year' => 'Graduation Year',
            'college' => 'College',
            'course' => 'Course',
        ];
    }

    private function getReportTypeLabels(): array
    {
        return [
            'comprehensive' => 'Comprehensive Alumni Report',
            'master_list' => 'Alumni Master List',
            'status' => 'Alumni Status Report',
            'year' => 'Graduation Year Report',
            'college' => 'College Distribution Report',
            'course' => 'Course Distribution Report',
        ];
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function addWordRecordsTable($section, array $records, bool $includeContactInfo): void
    {
        $table = $section->addTable([
            'borderSize' => 6,
            'borderColor' => 'D9E2EC',
            'cellMargin' => 80,
        ]);

        $headerStyle = ['bgColor' => 'F4F7FA'];
        $cellStyle = ['valign' => 'center'];
        $headerFont = ['bold' => true, 'color' => '1E4A76'];

        $headers = ['Name'];
        if ($includeContactInfo) {
            $headers[] = 'Email';
        }
        $headers = array_merge($headers, ['Graduation Year', 'Status', 'College', 'Course']);

        $table->addRow();
        foreach ($headers as $header) {
            $table->addCell(2200, $headerStyle)->addText($header, $headerFont);
        }

        foreach ($records as $record) {
            $table->addRow();
            $table->addCell(2200, $cellStyle)->addText((string) $record['name']);
            if ($includeContactInfo) {
                $table->addCell(3200, $cellStyle)->addText((string) $record['email']);
            }
            $table->addCell(1600, $cellStyle)->addText((string) $record['year']);
            $table->addCell(1600, $cellStyle)->addText((string) $record['status']);
            $table->addCell(2200, $cellStyle)->addText((string) $record['college']);
            $table->addCell(2200, $cellStyle)->addText((string) $record['course']);
        }

        $section->addTextBreak(1);
    }

    private function writeBase64ImageToTempFile(string $dataUri, string $prefix): ?string
    {
        if (! preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.*)$/', $dataUri, $matches)) {
            return null;
        }

        $imageData = base64_decode($matches[2], true);
        if ($imageData === false) {
            return null;
        }

        $extension = match (strtolower($matches[1])) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            default => 'png',
        };

        $tempPath = WRITEPATH . 'uploads/' . $prefix . '_' . uniqid('', true) . '.' . $extension;
        file_put_contents($tempPath, $imageData);

        return $tempPath;
    }

    private function isStaffAuthorized(): bool
    {
        if (! session()->has('auth_user')) {
            return false;
        }

        $role = strtolower((string) (session('auth_user.role') ?? ''));
        return in_array($role, ['head_admin', 'admin'], true);
    }

    private function isCoordinatorAuthorized(): bool
    {
        if (! session()->has('auth_user')) {
            return false;
        }

        $role = strtolower((string) (session('auth_user.role') ?? ''));
        return in_array($role, ['coordinator', 'head_admin', 'admin'], true);
    }

    public function settings(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/Settings.html');
    }

    public function surveys(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/Surveys.html');
    }

    public function surveyCreate(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/SurveyCreate.html');
    }

    public function surveyResponseDetails(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/SurveyResponseDetails.html');
    }

    public function surveyAllResponsesDetails(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/SurveyAllResponsesDetails.html');
    }

    public function surveyResponses(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/SurveyResponses.html');
    }

    public function coordinators(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/Coordinators.html');
    }

    private function getHeadAdminCampusId(): ?string
    {
        $userAccountModel = new \App\Models\UserAccountModel();
        $headAdminEmail = (string) (session('auth_user.email') ?? session('auth_user.Email') ?? '');
        if ($headAdminEmail !== '') {
            $headAdminAccount = $userAccountModel->findByEmail($headAdminEmail);
            if ($headAdminAccount !== null && isset($headAdminAccount['Campus']) && $headAdminAccount['Campus'] !== '' && $headAdminAccount['Campus'] !== null) {
                return (string) $headAdminAccount['Campus'];
            }
        }

        $sessionCampus = session('auth_user.campus') ?? session('auth_user.Campus') ?? null;
        if ($sessionCampus !== null && (string) $sessionCampus !== '') {
            return (string) $sessionCampus;
        }

        return null;
    }

    private function getHeadAdminCampusQueryValues(): array
    {
        $campusId = $this->getHeadAdminCampusId();
        if ($campusId === null || $campusId === '') {
            return [];
        }

        $campusModel = new \App\Models\CampusModel();
        $values = [];

        if (is_numeric($campusId)) {
            $values[] = (int) $campusId;
        } else {
            $campusRow = $campusModel->where('Campus', (string) $campusId)->first();
            if ($campusRow && !empty($campusRow['Campus_ID'])) {
                $values[] = (int) $campusRow['Campus_ID'];
            }
        }

        return array_values(array_unique(array_filter($values, fn ($v) => $v !== null && $v !== '')));
    }

    public function getCoordinators(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $campusQueryValues = $this->getHeadAdminCampusQueryValues();
        if (empty($campusQueryValues)) {
            return $this->response->setJSON([
                'success' => true,
                'coordinators' => [],
            ]);
        }

        $campusModel = new \App\Models\CampusModel();
        $userAccountModel = new \App\Models\UserAccountModel();

        // Fetch all campuses to map Campus_ID to actual Campus name from database
        $allCampuses = $campusModel->findAll();
        $campusMap = [];
        foreach ($allCampuses as $c) {
            if (isset($c['Campus_ID']) && isset($c['Campus'])) {
                $campusMap[(string)$c['Campus_ID']] = $c['Campus'];
                $campusMap[(string)$c['Campus']] = $c['Campus'];
            }
        }

        // Search ONLY coordinators matching the campus ID of the head admin's account
        $coordinators = $userAccountModel
            ->where('Role', 'coordinator')
            ->whereIn('Campus', $campusQueryValues)
            ->orderBy('First_Name', 'ASC')
            ->findAll();

        return $this->response->setJSON([
            'success' => true,
            'coordinators' => array_map(fn (array $coordinator): array => $this->formatCoordinatorForResponse($coordinator, $campusMap), $coordinators),
        ]);
    }

    private function formatCoordinatorForResponse(array $coordinator, array $campusMap = []): array
    {
        $rawCampus = (string) ($coordinator['Campus'] ?? '');
        $campusName = $campusMap[$rawCampus] ?? $rawCampus;

        return [
            'id' => (string) ($coordinator['Email'] ?? ''),
            'firstName' => (string) ($coordinator['First_Name'] ?? ''),
            'lastName' => (string) ($coordinator['Last_Name'] ?? ''),
            'email' => (string) ($coordinator['Email'] ?? ''),
            'campus' => $campusName,
            'department' => (string) ($coordinator['Department'] ?? ''),
            'college' => (string) ($coordinator['College'] ?? ''),
            'status' => 'Active',
            'createdAt' => (string) ($coordinator['created_at'] ?? ''),
        ];
    }

    public function createCoordinator(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $input = $this->request->getJSON(true);
        
        // Validate required fields
        if (empty($input['firstName']) || empty($input['lastName']) || empty($input['email']) || empty($input['password'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Missing required fields.',
            ]);
        }

        $userAccountModel = new \App\Models\UserAccountModel();
        
        // Check if email already exists
        $existingUser = $userAccountModel->findByEmail($input['email']);
        if ($existingUser !== null) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Email already exists.',
            ]);
        }

        // Get head admin's campus from account or session
        $headAdminCampus = $this->getHeadAdminCampusId() ?? session('auth_user.campus') ?? '';

        // Prepare coordinator data
        $coordinatorData = [
            'Email' => $input['email'],
            'First_Name' => $input['firstName'],
            'Last_Name' => $input['lastName'],
            'Password' => (string) $input['password'],
            'Role' => 'coordinator',
            'Campus' => $headAdminCampus,
            'Department' => $input['department'] ?? null,
            'College' => $input['college'] ?? null,
        ];

        try {
            // Insert the new coordinator
            $userAccountModel->insert($coordinatorData);

            // Sync new coordinator account to Supabase Auth with app_metadata role=coordinator
            (new \App\Services\SupabaseAuthService())->syncLocalUserToSupabase($coordinatorData, (string) $input['password']);
            
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Coordinator created successfully.',
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error creating coordinator: ' . $e->getMessage(),
            ]);
        }
    }

    public function getAssignableAccounts(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $campusQueryValues = $this->getHeadAdminCampusQueryValues();
        if (empty($campusQueryValues)) {
            return $this->response->setJSON([
                'success' => true,
                'accounts' => [],
            ]);
        }

        $userAccountModel = new \App\Models\UserAccountModel();

        // Only show coordinator accounts that have the same campus id as the head admin.
        // Do not show alumni, head admin, or developer accounts.
        $accounts = $userAccountModel
            ->whereIn('Role', ['coordinator', 'Coordinator'])
            ->whereIn('Campus', $campusQueryValues)
            ->orderBy('First_Name', 'ASC')
            ->findAll();

        $formattedAccounts = array_map(function (array $account): array {
            $firstName = trim((string) ($account['First_Name'] ?? ''));
            $lastName = trim((string) ($account['Last_Name'] ?? ''));
            $fullName = trim($firstName . ' ' . $lastName);
            if ($fullName === '') {
                $fullName = (string) ($account['Email'] ?? '');
            }

            return [
                'email' => (string) ($account['Email'] ?? ''),
                'firstName' => $firstName,
                'lastName' => $lastName,
                'fullName' => $fullName,
                'role' => (string) ($account['Role'] ?? 'coordinator'),
                'college' => (string) ($account['College'] ?? ''),
                'department' => (string) ($account['Department'] ?? ''),
                'campus' => (string) ($account['Campus'] ?? ''),
            ];
        }, $accounts);

        return $this->response->setJSON([
            'success' => true,
            'accounts' => $formattedAccounts,
        ]);
    }

    public function assignCoordinator(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $input = $this->request->getJSON(true);
        if (! is_array($input)) {
            $input = $this->request->getPost();
        }

        $email = trim((string) ($input['email'] ?? ''));
        $college = trim((string) ($input['college'] ?? ''));
        $department = trim((string) ($input['department'] ?? ''));

        if ($email === '') {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Email is required.',
            ]);
        }

        if ($college === '') {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Please select a college.',
            ]);
        }

        if ($department === '') {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Please select a department.',
            ]);
        }

        $userAccountModel = new \App\Models\UserAccountModel();
        $user = $userAccountModel->findByEmail($email);

        if ($user === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Account not found for email: ' . $email,
            ]);
        }

        $headAdminCampus = $this->getHeadAdminCampusId() ?? session('auth_user.campus') ?? session('auth_user.Campus') ?? '';

        $updateData = [
            'Role' => 'coordinator',
            'College' => $college,
            'Department' => $department,
        ];

        if (empty($user['Campus']) && !empty($headAdminCampus)) {
            $updateData['Campus'] = $headAdminCampus;
        }

        try {
            $userAccountModel->update($email, $updateData);

            $updatedUser = array_merge($user, $updateData);
            (new \App\Services\SupabaseAuthService())->syncLocalUserToSupabase($updatedUser);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Coordinator assigned successfully.',
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error assigning coordinator: ' . $e->getMessage(),
            ]);
        }
    }

    public function colleges(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/Colleges.html');
    }

    public function getCollegesData(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $collegeModel = new \App\Models\CollegeModel();
        $campusModel = new \App\Models\CampusModel();
        
        // Get head admin's campus ID from account or session
        $campusId = $this->getHeadAdminCampusId() ?? session('auth_user.campus') ?? session('auth_user.Campus') ?? '';
        
        if (empty($campusId)) {
            return $this->response->setJSON([
                'success' => true,
                'colleges' => [],
            ]);
        }
        
        $campus = $campusModel->where('Campus_ID', (int) $campusId)->first();
        $headAdminCampus = $campus['Campus'] ?? '';
        
        // Get colleges for this campus
        $colleges = $collegeModel->where('Campus_ID', (int) $campusId)->findAll();
        if ($colleges === []) {
            return $this->response->setJSON([
                'success' => true,
                'colleges' => [],
            ]);
        }

        $collegeIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) ($row['College_ID'] ?? 0),
            $colleges
        )));

        $db = \Config\Database::connect();

        // 1. Batch-load staff coordinators for all colleges on this campus
        $staffCoordinatorsByCollegeId = [];
        if ($collegeIds !== []) {
            $staffRows = $db->table('staffs')
                ->where('Campus_ID', (int) $campusId)
                ->whereIn('College_ID', $collegeIds)
                ->where('Role', 'Coordinator')
                ->get()
                ->getResultArray();

            foreach ($staffRows as $staffRow) {
                $cid = (int) ($staffRow['College_ID'] ?? 0);
                $staffCoordinatorsByCollegeId[$cid][] = $staffRow;
            }
        }

        // 2. Batch-load coordinator user_accounts for this campus once
        $allAccountCoordinators = $this->getCampusCoordinatorAccounts((string) $campusId, (string) $headAdminCampus);
        $accountCoordinatorsByCollege = [];
        foreach ($allAccountCoordinators as $coordinator) {
            $normCollege = $this->normalizeCollegeName((string) ($coordinator['College'] ?? ''));
            if ($normCollege !== '') {
                $accountCoordinatorsByCollege[$normCollege][] = $coordinator;
            }
        }

        // 3. Batch-load department counts per college
        $deptCountsByCollegeId = [];
        if ($collegeIds !== []) {
            $deptCountRows = $db->table('departments')
                ->select('College_ID, COUNT(*) AS dept_count')
                ->where('Campus_ID', (int) $campusId)
                ->whereIn('College_ID', $collegeIds)
                ->groupBy('College_ID')
                ->get()
                ->getResultArray();

            foreach ($deptCountRows as $countRow) {
                $deptCountsByCollegeId[(int) ($countRow['College_ID'] ?? 0)] = (int) ($countRow['dept_count'] ?? 0);
            }
        }

        // 4. Batch-load alumni counts per college
        $alumniCountsByCollegeId = [];
        $activeCountsByCollegeId = [];
        if ($collegeIds !== []) {
            try {
                $alumniCountRows = $db->table('alumni')
                    ->select('"College_ID", COUNT(*) AS alumni_count')
                    ->select("SUM(CASE WHEN LOWER(\"Status\"::text) = 'active' THEN 1 ELSE 0 END) AS active_count", false)
                    ->whereIn('College_ID', $collegeIds)
                    ->groupBy('College_ID')
                    ->get()
                    ->getResultArray();

                foreach ($alumniCountRows as $acRow) {
                    $cid = (int) ($acRow['College_ID'] ?? 0);
                    $alumniCountsByCollegeId[$cid] = (int) ($acRow['alumni_count'] ?? 0);
                    $activeCountsByCollegeId[$cid] = (int) ($acRow['active_count'] ?? 0);
                }
            } catch (\Throwable $e) {
                log_message('error', 'Error batch-counting alumni per college: ' . $e->getMessage());
            }
        }

        $collegeData = [];
        foreach ($colleges as $collegeRow) {
            $collegeName = (string) $collegeRow['College_Name'];
            $collegeId = (int) $collegeRow['College_ID'];
            $normCollege = $this->normalizeCollegeName($collegeName);

            $staffCoordinators = $staffCoordinatorsByCollegeId[$collegeId] ?? [];
            $accountCoordinators = $accountCoordinatorsByCollege[$normCollege] ?? [];
            $collegeCoordinators = $this->mergeCoordinatorRows($staffCoordinators, $accountCoordinators);

            $totalAlumni = $alumniCountsByCollegeId[$collegeId] ?? 0;
            $activeAlumni = $activeCountsByCollegeId[$collegeId] ?? 0;
            $totalDepartments = $deptCountsByCollegeId[$collegeId] ?? 0;

            $collegeData[] = [
                'college_id' => $collegeRow['College_ID'],
                'college' => $collegeName,
                'abbreviation' => (string) ($collegeRow['Abbreviation'] ?? ''),
                'totalCourses' => $totalDepartments,
                'totalDepartments' => $totalDepartments,
                'totalAlumni' => $totalAlumni,
                'activeAlumni' => $activeAlumni,
                'coordinators' => $collegeCoordinators,
            ];
        }

        return $this->response->setJSON([
            'success' => true,
            'colleges' => $collegeData,
        ]);
    }

    /**
     * Load all coordinator user_accounts for a campus in a single query.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getCampusCoordinatorAccounts(string $campusId, string $campusName): array
    {
        $campusValues = [];
        if (is_numeric($campusId)) {
            $campusValues[] = (int) $campusId;
        } elseif ($campusName !== '') {
            $campusModel = new \App\Models\CampusModel();
            $campusRow = $campusModel->where('Campus', $campusName)->first();
            if ($campusRow && !empty($campusRow['Campus_ID'])) {
                $campusValues[] = (int) $campusRow['Campus_ID'];
            }
        }

        if ($campusValues === []) {
            return [];
        }

        $userAccountModel = new \App\Models\UserAccountModel();

        return $userAccountModel
            ->where('Role', 'coordinator')
            ->whereIn('Campus', $campusValues)
            ->findAll();
    }

    private function getCoordinatorAccountsForCollege(string $collegeName, string $campusId, string $campusName): array
    {
        $coordinators = $this->getCampusCoordinatorAccounts($campusId, $campusName);
        $normalizedCollegeName = $this->normalizeCollegeName($collegeName);

        return array_values(array_filter($coordinators, function (array $coordinator) use ($normalizedCollegeName): bool {
            return $this->normalizeCollegeName((string) ($coordinator['College'] ?? '')) === $normalizedCollegeName;
        }));
    }

    private function mergeCoordinatorRows(array $staffCoordinators, array $accountCoordinators): array
    {
        $merged = [];
        $seenEmails = [];

        foreach (array_merge($staffCoordinators, $accountCoordinators) as $coordinator) {
            $email = trim((string) ($coordinator['Email'] ?? ''));
            $emailKey = strtolower($email);

            if ($emailKey !== '' && isset($seenEmails[$emailKey])) {
                continue;
            }

            if ($emailKey !== '') {
                $seenEmails[$emailKey] = true;
            }

            $name = trim((string) ($coordinator['First_Name'] ?? '') . ' ' . (string) ($coordinator['Last_Name'] ?? ''));

            $merged[] = [
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
            ];
        }

        return $merged;
    }

    private function getCoordinatorAccountsForDepartment(string $departmentName, string $collegeName, string $campusId, string $campusName): array
    {
        $coordinators = $this->getCampusCoordinatorAccounts($campusId, $campusName);
        $normalizedDepartmentName = $this->normalizeCollegeName($departmentName);
        $normalizedCollegeName = $this->normalizeCollegeName($collegeName);

        return array_values(array_filter($coordinators, function (array $coordinator) use ($normalizedDepartmentName, $normalizedCollegeName): bool {
            $departmentMatches = $this->normalizeCollegeName((string) ($coordinator['Department'] ?? '')) === $normalizedDepartmentName;
            $collegeMatches = $this->normalizeCollegeName((string) ($coordinator['College'] ?? '')) === $normalizedCollegeName;

            return $departmentMatches && $collegeMatches;
        }));
    }

    private function normalizeCollegeName(string $collegeName): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim($collegeName))) ?? '';
    }

    public function departments(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/Departments.html');
    }

    public function getDepartmentsData(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $departmentModel = new \App\Models\DepartmentModel();
        $campusModel = new \App\Models\CampusModel();
        $collegeModel = new \App\Models\CollegeModel();
        
        // Get head admin's campus ID from account or session
        $campusId = $this->getHeadAdminCampusId() ?? session('auth_user.campus') ?? session('auth_user.Campus') ?? '';
        
        if (empty($campusId)) {
            return $this->response->setJSON([
                'success' => true,
                'departments' => [],
            ]);
        }
        
        $campus = $campusModel->where('Campus_ID', (int) $campusId)->first();
        $headAdminCampus = $campus['Campus'] ?? '';
        
        // Get departments for this campus
        $departments = $departmentModel->where('Campus_ID', (int) $campusId)->findAll();
        if ($departments === []) {
            return $this->response->setJSON([
                'success' => true,
                'departments' => [],
            ]);
        }

        $collegeIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): int => (int) ($row['College_ID'] ?? 0),
            $departments
        ))));
        $departmentIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): int => (int) ($row['Department_ID'] ?? 0),
            $departments
        ))));

        // 1. Batch-load colleges by ID
        $collegeNameById = [];
        if ($collegeIds !== []) {
            $collegeRows = $collegeModel->whereIn('College_ID', $collegeIds)->findAll();
            foreach ($collegeRows as $collegeRow) {
                $collegeNameById[(int) $collegeRow['College_ID']] = (string) ($collegeRow['College_Name'] ?? 'Unknown College');
            }
        }

        // 2. Batch-load staff coordinators for all departments on this campus
        $db = \Config\Database::connect();
        $staffCoordinatorsByDeptId = [];
        if ($departmentIds !== []) {
            $staffRows = $db->table('staffs')
                ->where('Campus_ID', (int) $campusId)
                ->whereIn('Department_ID', $departmentIds)
                ->where('Role', 'Coordinator')
                ->get()
                ->getResultArray();

            foreach ($staffRows as $staffRow) {
                $did = (int) ($staffRow['Department_ID'] ?? 0);
                $staffCoordinatorsByDeptId[$did][] = $staffRow;
            }
        }

        // 3. Batch-load coordinator user_accounts for this campus once
        $allAccountCoordinators = $this->getCampusCoordinatorAccounts((string) $campusId, (string) $headAdminCampus);
        $accountCoordinatorsByCollegeAndDept = [];
        foreach ($allAccountCoordinators as $coordinator) {
            $normCollege = $this->normalizeCollegeName((string) ($coordinator['College'] ?? ''));
            $normDept = $this->normalizeCollegeName((string) ($coordinator['Department'] ?? ''));
            if ($normCollege !== '' && $normDept !== '') {
                $accountCoordinatorsByCollegeAndDept[$normCollege . '|' . $normDept][] = $coordinator;
            }
        }

        $departmentData = [];
        foreach ($departments as $deptRow) {
            $departmentName = (string) $deptRow['Department_Name'];
            $collegeId = (int) $deptRow['College_ID'];
            $departmentId = (int) $deptRow['Department_ID'];
            $collegeName = $collegeNameById[$collegeId] ?? 'Unknown College';

            $normKey = $this->normalizeCollegeName($collegeName) . '|' . $this->normalizeCollegeName($departmentName);
            $staffCoordinators = $staffCoordinatorsByDeptId[$departmentId] ?? [];
            $accountCoordinators = $accountCoordinatorsByCollegeAndDept[$normKey] ?? [];
            $deptCoordinators = $this->mergeCoordinatorRows($staffCoordinators, $accountCoordinators);

            $totalUsers = 0;

            $departmentData[] = [
                'department_id' => $deptRow['Department_ID'],
                'department' => $departmentName,
                'college' => $collegeName,
                'totalCoordinators' => count($deptCoordinators),
                'totalUsers' => $totalUsers,
                'coordinators' => $deptCoordinators,
                'members' => array_map(fn (array $coordinator): array => [
                    'name' => $coordinator['name'],
                    'email' => $coordinator['email'],
                    'role' => 'coordinator',
                    'college' => $collegeName,
                    'campus' => $headAdminCampus !== '' ? $headAdminCampus : (string) $campusId,
                ], $deptCoordinators),
            ];
        }

        return $this->response->setJSON([
            'success' => true,
            'departments' => $departmentData,
        ]);
    }

    public function createCollege(): ResponseInterface
    {
        log_message('error', 'createCollege called');
        
        if (! $this->isStaffAuthorized()) {
            log_message('error', 'createCollege: Unauthorized');
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $collegeModel = new \App\Models\CollegeModel();
        $campusModel = new \App\Models\CampusModel();

        $collegeName = $this->request->getPost('college_name');
        $abbreviation = $this->request->getPost('abbreviation');

        log_message('error', 'createCollege: college_name=' . $collegeName . ', abbreviation=' . $abbreviation);

        if (empty($collegeName)) {
            log_message('error', 'createCollege: College name is empty');
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'College name is required.',
            ]);
        }

        try {
            $authUser = session('auth_user');
            log_message('error', 'createCollege: auth_user session data: ' . json_encode($authUser));
            
            // Campus is stored as Campus_ID (int) in the session
            $campusId = session('auth_user.campus') 
                ?? session('auth_user.Campus') 
                ?? '';
            
            log_message('error', 'createCollege: campusId=' . $campusId);
            
            if (empty($campusId)) {
                log_message('error', 'createCollege: Campus is empty in session');
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Campus information not found in session. Please log in again.',
                ]);
            }
            
            // Verify the campus exists in the campuses table
            $campus = $campusModel->where('Campus_ID', $campusId)->first();
            
            if (!$campus) {
                // Try getting all campuses to see what's available
                $allCampuses = $campusModel->findAll();
                log_message('error', 'createCollege: All campuses: ' . json_encode($allCampuses));
                log_message('error', 'createCollege: Campus not found for ID: ' . $campusId);
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Campus not found in database. Campus ID: ' . $campusId,
                ]);
            }

            $collegeData = [
                'Campus_ID' => $campusId,
                'College_Name' => $collegeName,
                'Abbreviation' => $abbreviation ?? '',
            ];

            log_message('error', 'createCollege: Inserting college data: ' . json_encode($collegeData));
            
            $collegeId = $collegeModel->insert($collegeData);

            if (!$collegeId) {
                log_message('error', 'createCollege: Failed to insert college');
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => 'Failed to create college.',
                ]);
            }

            log_message('error', 'createCollege: College created with ID: ' . $collegeId);
            
            return $this->response->setJSON([
                'success' => true,
                'message' => 'College created successfully.',
                'college_id' => $collegeId,
            ]);
        } catch (\Exception $e) {
            log_message('error', 'createCollege Exception: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    public function createDepartment(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $departmentModel = new \App\Models\DepartmentModel();
        $collegeModel = new \App\Models\CollegeModel();

        $departmentName = $this->request->getPost('department_name');
        $abbreviation = $this->request->getPost('abbreviation');
        $collegeId = $this->request->getPost('college_id');
        $coordinatorName = $this->request->getPost('coordinator_name');

        if (empty($departmentName)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Department name is required.',
            ]);
        }

        if (empty($collegeId)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'College is required.',
            ]);
        }

        try {
            // Campus is stored as Campus_ID (int) in the session
            $campusId = session('auth_user.campus') 
                ?? session('auth_user.Campus') 
                ?? '';
            
            if (empty($campusId)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Campus information not found in session. Please log in again.',
                ]);
            }
            
            // Verify the college exists and belongs to this campus
            $college = $collegeModel->where('College_ID', $collegeId)
                ->where('Campus_ID', $campusId)
                ->first();

            if (!$college) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'College not found or does not belong to your campus.',
                ]);
            }

            $departmentData = [
                'Campus_ID' => $campusId,
                'College_ID' => $collegeId,
                'Department_Name' => $departmentName,
                'Abbreviation' => $abbreviation ?? '',
                'Coordinator_Name' => $coordinatorName ?? '',
            ];

            $departmentId = $departmentModel->insert($departmentData);

            if (!$departmentId) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => 'Failed to create department.',
                ]);
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Department created successfully.',
                'department_id' => $departmentId,
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    public function payments(): ResponseInterface
    {
        return $this->renderProtectedView('HTML/Staff/Payments.html');
    }

    public function getPaymentsData(): ResponseInterface
    {
        if (! $this->isStaffAuthorized() && ! $this->isCoordinatorAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        try {
            $paymentModel = new \App\Models\PaymentModel();
            $collegeModel = new \App\Models\CollegeModel();
            $alumniModel = new \App\Models\AlumniModel();
            $purposeModel = new \App\Models\PaymentPurposeModel();

            $userRole = strtolower(trim((string) (session('auth_user.role') ?? '')));
            $isCoordinator = ($userRole === 'coordinator');
            $campusId = $this->getHeadAdminCampusId();
            $db = \Config\Database::connect();

            $coordDeptName = '';
            $coordDeptId = 0;
            $coordCollegeId = 0;
            $coordUserId = (int) (session('auth_user.id') ?? session('auth_user.ID') ?? 0);

            if ($isCoordinator) {
                $coordEmail = strtolower(trim((string) (session('auth_user.email') ?? '')));
                $accModel = new \App\Models\UserAccountModel();
                $coordAcc = $coordEmail !== '' ? $accModel->findByEmail($coordEmail) : null;
                $coordDeptName = trim((string) ($coordAcc['Department'] ?? session('auth_user.department') ?? ''));
                if (! empty($coordAcc['id'])) {
                    $coordUserId = (int) $coordAcc['id'];
                }
                if ($coordDeptName !== '') {
                    $deptRow = $db->table('departments')
                        ->where('LOWER("Department_Name")', strtolower($coordDeptName))
                        ->get()
                        ->getRowArray();
                    if (is_array($deptRow)) {
                        $coordDeptId = (int) ($deptRow['Department_ID'] ?? 0);
                        $coordCollegeId = (int) ($deptRow['College_ID'] ?? 0);
                        $coordDeptName = trim((string) ($deptRow['Department_Name'] ?? $coordDeptName));
                    }
                }

                // A coordinator without an assigned department must not receive campus-wide payment or alumni records
                if ($coordDeptName === '' && $coordDeptId <= 0) {
                    return $this->response->setJSON([
                        'success'    => true,
                        'payments'   => [],
                        'purposes'   => $purposeModel->getActivePurposes(),
                        'statistics' => [
                            'total_revenue' => 0,
                            'pending_revenue' => 0,
                            'total_transactions' => 0,
                            'verified_count' => 0,
                            'pending_count' => 0,
                            'rejected_count' => 0,
                            'alumni_id_count' => 0,
                        ],
                        'colleges'   => [],
                        'campuses'   => [],
                        'alumni'     => [],
                    ]);
                }
            }

            $filters = [
                'status' => $this->request->getGet('status') ?? 'all',
                'payment_purpose_id' => $this->request->getGet('payment_purpose_id') ?? 'all',
                'payment_type' => $this->request->getGet('payment_type') ?? 'all',
                'payment_method' => $this->request->getGet('payment_method') ?? 'all',
                'college_id' => $isCoordinator ? 'all' : ($this->request->getGet('college_id') ?? 'all'),
                'search' => $this->request->getGet('search') ?? '',
                'date_from' => $this->request->getGet('date_from') ?? '',
                'date_to' => $this->request->getGet('date_to') ?? '',
            ];

            if ($campusId !== null && is_numeric($campusId)) {
                $filters['campus_id'] = (int) $campusId;
            }

            $departmentScope = [];
            if ($isCoordinator) {
                $departmentScope = [
                    'department_id'       => $coordDeptId,
                    'department_name'     => $coordDeptName,
                    'coordinator_user_id' => $coordUserId,
                ];
                $filters['department_id'] = $coordDeptId;
                $filters['department_name'] = $coordDeptName;
                $filters['coordinator_user_id'] = $coordUserId;
            }

            $payments = $paymentModel->getFilteredPayments($filters);
            $statistics = $paymentModel->getPaymentStatistics($campusId !== null && is_numeric($campusId) ? (int) $campusId : null, $departmentScope);
            $purposes = $purposeModel->getActivePurposes();

            // Colleges list for filter
            $collegeBuilder = $collegeModel;
            if ($campusId !== null && is_numeric($campusId)) {
                $collegeBuilder = $collegeBuilder->where('Campus_ID', (int) $campusId);
            }
            if ($isCoordinator && $coordCollegeId > 0) {
                $collegeBuilder = $collegeBuilder->where('College_ID', $coordCollegeId);
            }
            $colleges = $collegeBuilder->orderBy('College_Name', 'ASC')->findAll();

            // Campuses list for filter
            $campusModel = new \App\Models\CampusModel();
            $campuses = $campusModel->orderBy('Campus', 'ASC')->findAll();

            // Alumni list strictly from user_accounts (Role = 'alumni') for modal selection
            $alumniQuery = $db->table('user_accounts ua')
                ->select('a.id AS id, ua.id AS user_account_id, COALESCE(NULLIF(ua.account_id, \'\'), a.alumni_id) AS alumni_id, ua."First_Name" AS "FirstName", ua."Last_Name" AS "LastName", ua."Email", COALESCE(a."College_ID", c."College_ID") AS "College_ID", ua."College", ua."Department"')
                ->join('alumni a', 'LOWER(a."Email") = LOWER(ua."Email")', 'left')
                ->join('colleges c', 'LOWER(c."College_Name") = LOWER(ua."College")', 'left')
                ->where('LOWER(ua."Role"::text)', 'alumni');

            if ($isCoordinator) {
                $alumniQuery->groupStart();
                if ($coordDeptId > 0) {
                    $alumniQuery->where('a."Department_ID"', $coordDeptId);
                }
                if ($coordDeptName !== '') {
                    $alumniQuery->orWhere('LOWER(ua."Department")', strtolower($coordDeptName));
                }
                $alumniQuery->groupEnd();
            }

            $recentAlumni = $alumniQuery
                ->orderBy('ua."Last_Name"', 'ASC')
                ->orderBy('ua."First_Name"', 'ASC')
                ->get()
                ->getResultArray();

            return $this->response->setJSON([
                'success' => true,
                'payments' => $payments,
                'purposes' => $purposes,
                'statistics' => $statistics,
                'colleges' => $colleges,
                'campuses' => $campuses,
                'alumni' => $recentAlumni,
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error fetching payments: ' . $e->getMessage(),
            ]);
        }
    }

    public function createPayment(): ResponseInterface
    {
        if (! $this->isStaffAuthorized() && ! $this->isCoordinatorAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $input = $this->request->getJSON(true) ?? $this->request->getPost();

        $payerName = trim((string) ($input['payer_name'] ?? ''));
        $amount = (float) ($input['amount'] ?? 0);
        $paymentMethod = (string) ($input['payment_method'] ?? 'cash');

        if ($payerName === '' || $amount <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Payer name and a valid amount greater than 0 are required.',
            ]);
        }

        // Validate payment purpose on the server: reject an ID that does not exist or is inactive
        $purposeModel = new \App\Models\PaymentPurposeModel();
        $purposeId = (int) ($input['payment_purpose_id'] ?? 0);
        $purpose = null;

        if ($purposeId > 0) {
            $purpose = $purposeModel->findActiveById($purposeId);
            if (! $purpose) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'The selected payment purpose does not exist or is inactive.',
                ]);
            }
        } elseif (!empty($input['payment_type'])) {
            // Fallback for requests supplying type/code
            $code = trim((string) $input['payment_type']);
            if (is_numeric($code)) {
                $purpose = $purposeModel->findActiveById((int) $code);
            } else {
                $purpose = $purposeModel->findActiveByCode($code);
            }

            if (! $purpose) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'The selected payment purpose does not exist or is inactive.',
                ]);
            }
        } else {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'A valid payment purpose is required.',
            ]);
        }

        // Dual-column saving: save both payment_purpose_id and its matching code in payment_type
        $paymentPurposeId = (int) $purpose['id'];
        $paymentType = (string) $purpose['code'];

        try {
            $paymentModel = new \App\Models\PaymentModel();
            $campusId = $this->getHeadAdminCampusId();

            $ref = trim((string) ($input['transaction_ref'] ?? ''));
            if ($ref === '') {
                $ref = $paymentModel->generateTransactionRef();
            }

            $currentUserId = session('auth_user.id') ?? session('auth_user.ID') ?? 1;
            $userRole = strtolower((string) (session('auth_user.role') ?? 'head_admin'));
            $isCoordinator = ($userRole === 'coordinator');

            $status = $isCoordinator ? 'pending' : (string) ($input['status'] ?? 'verified');
            $submittedByRole = $isCoordinator ? 'coordinator' : 'head_admin';
            $coordinatorId = $isCoordinator ? (int) $currentUserId : null;

            $forcedCollegeId = !empty($input['college_id']) ? (int) $input['college_id'] : null;
            if ($isCoordinator) {
                $db = \Config\Database::connect();
                $coordEmail = strtolower(trim((string) (session('auth_user.email') ?? '')));
                $accModel = new \App\Models\UserAccountModel();
                $coordAcc = $coordEmail !== '' ? $accModel->findByEmail($coordEmail) : null;
                $coordDeptName = trim((string) ($coordAcc['Department'] ?? session('auth_user.department') ?? ''));
                $coordDeptId = 0;

                if ($coordDeptName !== '') {
                    $deptRow = $db->table('departments')
                        ->where('LOWER("Department_Name")', strtolower($coordDeptName))
                        ->get()
                        ->getRowArray();
                    if (is_array($deptRow)) {
                        $coordDeptId = (int) ($deptRow['Department_ID'] ?? 0);
                        $forcedCollegeId = (int) ($deptRow['College_ID'] ?? 0) ?: $forcedCollegeId;
                    }
                }

                if ($coordDeptName === '' && $coordDeptId <= 0) {
                    return $this->response->setStatusCode(403)->setJSON([
                        'success' => false,
                        'message' => 'Your coordinator account is not assigned to a department.',
                    ]);
                }

                if (! empty($input['alumni_id'])) {
                    $targetAlum = $db->table('alumni a')
                        ->select('a.id, a."Department_ID", d."Department_Name"')
                        ->join('departments d', 'd."Department_ID" = a."Department_ID"', 'left')
                        ->where('a.id', (int) $input['alumni_id'])
                        ->get()
                        ->getRowArray();
                    $targetDeptId = (int) ($targetAlum['Department_ID'] ?? 0);
                    $targetDeptName = trim((string) ($targetAlum['Department_Name'] ?? ''));
                    $sameDept = ($coordDeptId > 0 && $targetDeptId === $coordDeptId)
                        || ($coordDeptName !== '' && strcasecmp($targetDeptName, $coordDeptName) === 0);
                    if (! $targetAlum || ! $sameDept) {
                        return $this->response->setStatusCode(403)->setJSON([
                            'success' => false,
                            'message' => 'You can only record payments for alumni in your assigned department.',
                        ]);
                    }
                }
            }

            $data = [
                'transaction_ref'    => $ref,
                'alumni_id'          => !empty($input['alumni_id']) ? (int) $input['alumni_id'] : null,
                'student_number'     => !empty($input['student_number']) ? trim((string) $input['student_number']) : null,
                'payer_name'         => $payerName,
                'payer_email'        => !empty($input['payer_email']) ? trim((string) $input['payer_email']) : null,
                'payer_contact'      => !empty($input['payer_contact']) ? trim((string) $input['payer_contact']) : null,
                'campus_id'          => !empty($input['campus_id']) ? (int) $input['campus_id'] : ($campusId !== null && is_numeric($campusId) ? (int) $campusId : 1),
                'college_id'         => $forcedCollegeId,
                'payment_purpose_id' => $paymentPurposeId,
                'payment_type'       => $paymentType,
                'description'        => !empty($input['description']) ? trim((string) $input['description']) : null,
                'amount'             => $amount,
                'payment_method'     => $paymentMethod,
                'channel_reference'  => !empty($input['channel_reference']) ? trim((string) $input['channel_reference']) : null,
                'status'             => $status,
                'proof_file'         => !empty($input['proof_file']) ? trim((string) $input['proof_file']) : null,
                'submitted_by_role'  => $submittedByRole,
                'coordinator_id'     => $coordinatorId,
                'notes'              => !empty($input['notes']) ? trim((string) $input['notes']) : ($isCoordinator ? 'Recorded by College Coordinator.' : 'Recorded directly by Head Admin cashier.'),
                'verified_by'        => ($status === 'verified' && ! $isCoordinator) ? (int) $currentUserId : null,
                'verified_at'        => ($status === 'verified' && ! $isCoordinator) ? date('Y-m-d H:i:s') : null,
            ];

            $insertId = $paymentModel->insert($data);

            if (! $insertId) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => 'Failed to record payment in database.',
                ]);
            }

            // Log activity
            try {
                $activityModel = new \App\Models\Activity_Model();
                $activityModel->logActivity(
                    session('auth_user') ?? ['name' => 'Head Admin'],
                    'record_payment',
                    'payments',
                    [
                        'transaction_ref'    => $ref,
                        'payer_name'         => $payerName,
                        'amount'             => $amount,
                        'payment_purpose_id' => $paymentPurposeId,
                        'payment_type'       => $paymentType,
                        'status'             => $status,
                    ]
                );
            } catch (\Exception $e) {
                // Ignore activity log failure
            }

            $createdPayment = $paymentModel->find($insertId);

            // If payment purpose is alumni_id and status is verified, automatically generate Alumni ID
            $generatedAlumniId = $this->handleAlumniIdGenerationForVerifiedPayment($createdPayment);
            if ($generatedAlumniId !== null) {
                $createdPayment = $paymentModel->find($insertId);
            }

            $successMsg = $generatedAlumniId !== null
                ? "Payment recorded and verified. Generated Alumni ID: {$generatedAlumniId}."
                : 'Payment recorded successfully.';

            return $this->response->setJSON([
                'success' => true,
                'message' => $successMsg,
                'payment' => $createdPayment,
                'generated_alumni_id' => $generatedAlumniId,
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error recording payment: ' . $e->getMessage(),
            ]);
        }
    }

    public function updatePayment(): ResponseInterface
    {
        if (! $this->isStaffAuthorized() && ! $this->isCoordinatorAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $input = $this->request->getJSON(true) ?? $this->request->getPost();
        $paymentId = (int) ($input['payment_id'] ?? $input['id'] ?? 0);

        if ($paymentId <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Invalid payment ID.',
            ]);
        }

        try {
            $paymentModel = new \App\Models\PaymentModel();
            $payment = $paymentModel->find($paymentId);

            if (!$payment) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'message' => 'Payment record not found.',
                ]);
            }

            $updateData = [];

            // If updating payment purpose, validate server-side and update both columns
            if (isset($input['payment_purpose_id']) || isset($input['payment_type'])) {
                $purposeModel = new \App\Models\PaymentPurposeModel();
                $purposeId = (int) ($input['payment_purpose_id'] ?? 0);
                $purpose = null;

                if ($purposeId > 0) {
                    $purpose = $purposeModel->findActiveById($purposeId);
                } elseif (!empty($input['payment_type'])) {
                    $code = trim((string) $input['payment_type']);
                    $purpose = is_numeric($code)
                        ? $purposeModel->findActiveById((int) $code)
                        : $purposeModel->findActiveByCode($code);
                }

                if (! $purpose) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'success' => false,
                        'message' => 'The selected payment purpose does not exist or is inactive.',
                    ]);
                }

                $updateData['payment_purpose_id'] = (int) $purpose['id'];
                $updateData['payment_type'] = (string) $purpose['code'];
            }

            if (isset($input['payer_name']) && trim((string) $input['payer_name']) !== '') {
                $updateData['payer_name'] = trim((string) $input['payer_name']);
            }
            if (isset($input['amount']) && (float) $input['amount'] > 0) {
                $updateData['amount'] = (float) $input['amount'];
            }
            if (isset($input['payment_method'])) {
                $updateData['payment_method'] = trim((string) $input['payment_method']);
            }
            if (isset($input['student_number'])) {
                $updateData['student_number'] = trim((string) $input['student_number']);
            }
            if (isset($input['payer_email'])) {
                $updateData['payer_email'] = trim((string) $input['payer_email']);
            }
            if (isset($input['payer_contact'])) {
                $updateData['payer_contact'] = trim((string) $input['payer_contact']);
            }
            if (isset($input['college_id'])) {
                $updateData['college_id'] = !empty($input['college_id']) ? (int) $input['college_id'] : null;
            }
            if (isset($input['campus_id'])) {
                $updateData['campus_id'] = !empty($input['campus_id']) ? (int) $input['campus_id'] : null;
            }
            if (isset($input['description'])) {
                $updateData['description'] = trim((string) $input['description']);
            }
            if (isset($input['channel_reference'])) {
                $updateData['channel_reference'] = trim((string) $input['channel_reference']);
            }
            if (isset($input['notes'])) {
                $updateData['notes'] = trim((string) $input['notes']);
            }
            if (isset($input['status'])) {
                $newStatus = strtolower(trim((string) $input['status']));
                if (in_array($newStatus, ['verified', 'rejected', 'pending', 'refunded'], true)) {
                    $updateData['status'] = $newStatus;
                    if ($newStatus === 'verified' && empty($payment['verified_at'])) {
                        $currentUserId = session('auth_user.id') ?? session('auth_user.ID') ?? 1;
                        $updateData['verified_by'] = (int) $currentUserId;
                        $updateData['verified_at'] = date('Y-m-d H:i:s');
                    }
                }
            }

            if (!empty($updateData)) {
                $paymentModel->update($paymentId, $updateData);
            }

            $updatedPayment = $paymentModel->find($paymentId);
            $generatedAlumniId = null;

            if (($updatedPayment['status'] ?? '') === 'verified') {
                $generatedAlumniId = $this->handleAlumniIdGenerationForVerifiedPayment($updatedPayment);
                if ($generatedAlumniId !== null) {
                    $updatedPayment = $paymentModel->find($paymentId);
                }
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Payment updated successfully.',
                'payment' => $updatedPayment,
                'generated_alumni_id' => $generatedAlumniId,
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error updating payment: ' . $e->getMessage(),
            ]);
        }
    }

    public function updatePaymentStatus(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
        }

        $input = $this->request->getJSON(true) ?? $this->request->getPost();
        $paymentId = (int) ($input['payment_id'] ?? 0);
        $newStatus = strtolower(trim((string) ($input['status'] ?? '')));
        $notes = trim((string) ($input['notes'] ?? ''));

        if ($paymentId <= 0 || !in_array($newStatus, ['verified', 'rejected', 'pending', 'refunded'], true)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Invalid payment ID or status.',
            ]);
        }

        try {
            $paymentModel = new \App\Models\PaymentModel();
            $payment = $paymentModel->find($paymentId);

            if (!$payment) {
                return $this->response->setStatusCode(404)->setJSON([
                    'success' => false,
                    'message' => 'Payment record not found.',
                ]);
            }

            $currentUserId = session('auth_user.id') ?? session('auth_user.ID') ?? 1;

            $updateData = [
                'status' => $newStatus,
                'verified_by' => (int) $currentUserId,
                'verified_at' => date('Y-m-d H:i:s'),
            ];

            // If updating payment purpose alongside status update
            if (isset($input['payment_purpose_id']) || isset($input['payment_type'])) {
                $purposeModel = new \App\Models\PaymentPurposeModel();
                $purposeId = (int) ($input['payment_purpose_id'] ?? 0);
                $purpose = null;

                if ($purposeId > 0) {
                    $purpose = $purposeModel->findActiveById($purposeId);
                } elseif (!empty($input['payment_type'])) {
                    $code = trim((string) $input['payment_type']);
                    $purpose = is_numeric($code)
                        ? $purposeModel->findActiveById((int) $code)
                        : $purposeModel->findActiveByCode($code);
                }

                if (! $purpose) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'success' => false,
                        'message' => 'The selected payment purpose does not exist or is inactive.',
                    ]);
                }

                $updateData['payment_purpose_id'] = (int) $purpose['id'];
                $updateData['payment_type'] = (string) $purpose['code'];
            }

            if ($notes !== '') {
                $existingNotes = (string) ($payment['notes'] ?? '');
                $updateData['notes'] = $existingNotes ? ($existingNotes . "\n" . "[Update]: " . $notes) : $notes;
            }

            $paymentModel->update($paymentId, $updateData);

            // Log activity
            try {
                $activityModel = new \App\Models\Activity_Model();
                $activityModel->logActivity(
                    session('auth_user') ?? ['name' => 'Head Admin'],
                    'update_payment_status',
                    'payments',
                    [
                        'payment_id' => $paymentId,
                        'transaction_ref' => $payment['transaction_ref'],
                        'old_status' => $payment['status'],
                        'new_status' => $newStatus,
                        'notes' => $notes,
                    ]
                );
            } catch (\Exception $e) {
                // Ignore activity log failure
            }

            $updatedPayment = $paymentModel->find($paymentId);

            // If payment purpose is alumni_id and status is verified, automatically generate Alumni ID
            $generatedAlumniId = $this->handleAlumniIdGenerationForVerifiedPayment($updatedPayment);
            if ($generatedAlumniId !== null) {
                $updatedPayment = $paymentModel->find($paymentId);
            }

            $message = 'Payment status updated to ' . ucfirst($newStatus) . '.';
            if ($generatedAlumniId !== null) {
                $message .= " Automatically generated Alumni ID: {$generatedAlumniId}.";
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => $message,
                'payment' => $updatedPayment,
                'generated_alumni_id' => $generatedAlumniId,
            ]);
        } catch (\Exception $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error updating payment status: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Check payment purpose and status:
     * Requires a linked purpose with code 'alumni_id' and payment status of 'verified'.
     * Does NOT generate an ID merely because a payment record was created.
     * Preserves existing controls that prevent duplicate ID generation.
     */
    private function handleAlumniIdGenerationForVerifiedPayment(array $payment): ?string
    {
        $status = strtolower(trim((string) ($payment['status'] ?? '')));

        // 1. Eligibility Rule: strictly require verified payment status
        if ($status !== 'verified') {
            return null;
        }

        // 2. Eligibility Rule: strictly require linked purpose with code 'alumni_id'
        $purposeCode = '';
        $purposeId = (int) ($payment['payment_purpose_id'] ?? 0);
        $purposeModel = new \App\Models\PaymentPurposeModel();

        if ($purposeId > 0) {
            $purpose = $purposeModel->find($purposeId);
            if ($purpose) {
                $purposeCode = strtolower(trim((string) $purpose['code']));
            }
        } elseif (!empty($payment['id'])) {
            $paymentModel = new \App\Models\PaymentModel();
            $dbPayment = $paymentModel->find((int) $payment['id']);
            if (!empty($dbPayment['payment_purpose_id'])) {
                $purpose = $purposeModel->find((int) $dbPayment['payment_purpose_id']);
                if ($purpose) {
                    $purposeCode = strtolower(trim((string) $purpose['code']));
                }
            }
        }

        // If purpose was not found via payment_purpose_id, check payment_type fallback
        if ($purposeCode === '' && !empty($payment['payment_type'])) {
            $purposeCode = strtolower(trim((string) $payment['payment_type']));
        }

        if ($purposeCode !== 'alumni_id') {
            return null;
        }

        $alumniModel = new \App\Models\AlumniModel();
        $alumni = null;

        // Try matching by alumni_id foreign key (points to alumni.id)
        if (!empty($payment['alumni_id']) && is_numeric($payment['alumni_id'])) {
            $alumni = $alumniModel->find((int) $payment['alumni_id']);
        }

        // Fallback: match by payer email
        if (!$alumni && !empty($payment['payer_email'])) {
            $alumni = $alumniModel->where('LOWER("Email")', strtolower(trim((string) $payment['payer_email'])))->first();
        }

        // Fallback: match by student number / alumni_id string
        if (!$alumni && !empty($payment['student_number'])) {
            $alumni = $alumniModel->where('alumni_id', trim((string) $payment['student_number']))->first();
        }

        // Fallback: match by payer name
        if (!$alumni && !empty($payment['payer_name'])) {
            $payerName = strtolower(trim((string) $payment['payer_name']));
            $alumni = $alumniModel->where("LOWER(CONCAT(\"FirstName\", ' ', \"LastName\"))", $payerName)->first();
            if (!$alumni) {
                $alumni = $alumniModel->where("LOWER(CONCAT(TRIM(\"FirstName\"), ' ', TRIM(\"LastName\")))", $payerName)->first();
            }
        }

        if (!$alumni) {
            // Fallback: check user_accounts (Role = 'alumni') if no row exists in alumni table yet
            $db = \Config\Database::connect();
            $uaBuilder = $db->table('user_accounts')->where('LOWER("Role"::text)', 'alumni');
            if (!empty($payment['payer_email'])) {
                $uaBuilder->where('LOWER("Email")', strtolower(trim((string) $payment['payer_email'])));
            } elseif (!empty($payment['payer_name'])) {
                $payerName = strtolower(trim((string) $payment['payer_name']));
                $uaBuilder->where("LOWER(CONCAT(TRIM(\"First_Name\"), ' ', TRIM(\"Last_Name\")))", $payerName);
            } else {
                return null;
            }
            $uaRow = $uaBuilder->get()->getRowArray();
            if (!$uaRow) {
                return null;
            }

            $currentAccId = trim((string) ($uaRow['account_id'] ?? ''));
            $generatedAlumniId = $currentAccId !== '' ? $currentAccId : $alumniModel->generateUniqueAlumniId();

            if ($currentAccId === '') {
                $db->table('user_accounts')
                    ->where('id', $uaRow['id'])
                    ->update(['account_id' => $generatedAlumniId]);
            }

            if (!empty($payment['id'])) {
                $paymentModel = new \App\Models\PaymentModel();
                $paymentUpdates = ['student_number' => $generatedAlumniId];
                $notes = (string) ($payment['notes'] ?? '');
                if (!str_contains($notes, "Alumni ID: {$generatedAlumniId}")) {
                    $paymentUpdates['notes'] = ($notes !== '' ? ($notes . "\n") : '') . "[System]: Verified Alumni ID Fee payment. Automatically generated and assigned Alumni ID: {$generatedAlumniId}.";
                }
                $paymentModel->update((int) $payment['id'], $paymentUpdates);
            }

            return $generatedAlumniId;
        }

        $alumniDbId = (int) $alumni['id'];

        // assignAlumniId preserves existing controls preventing duplicate ID generation
        $generatedAlumniId = $alumniModel->assignAlumniId($alumniDbId);

        if ($generatedAlumniId !== null && !empty($payment['id'])) {
            $paymentModel = new \App\Models\PaymentModel();
            $paymentId = (int) $payment['id'];

            // Update payment record to link directly to this alumni and store the generated ID
            $paymentUpdates = [
                'alumni_id' => $alumniDbId,
                'student_number' => $generatedAlumniId,
            ];

            $notes = (string) ($payment['notes'] ?? '');
            if (!str_contains($notes, "Alumni ID: {$generatedAlumniId}")) {
                $paymentUpdates['notes'] = ($notes !== '' ? ($notes . "\n") : '') . "[System]: Verified Alumni ID Fee payment. Automatically generated and assigned Alumni ID: {$generatedAlumniId}.";
            }

            $paymentModel->update($paymentId, $paymentUpdates);

            // Log activity
            try {
                $activityModel = new \App\Models\Activity_Model();
                $alumniName = trim(($alumni['FirstName'] ?? '') . ' ' . ($alumni['LastName'] ?? ''));
                $activityModel->logActivity(
                    session('auth_user') ?? ['name' => 'Head Admin', 'role' => 'head_admin'],
                    'generate_alumni_id',
                    'alumni',
                    [
                        'action' => 'generate_alumni_id',
                        'category' => 'alumni',
                        'description' => "Automatically generated Alumni ID {$generatedAlumniId} for {$alumniName} upon verified payment.",
                        'alumni_id' => $alumniDbId,
                        'generated_id_number' => $generatedAlumniId,
                        'transaction_ref' => $payment['transaction_ref'] ?? '',
                    ]
                );
            } catch (\Exception $e) {
                // Silently continue
            }
        }

        return $generatedAlumniId;
    }

    public function exportPayments(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setBody('Unauthorized.');
        }

        $paymentModel = new \App\Models\PaymentModel();
        $campusId = $this->getHeadAdminCampusId();

        $filters = [
            'status' => $this->request->getGet('status') ?? 'all',
            'payment_purpose_id' => $this->request->getGet('payment_purpose_id') ?? 'all',
            'payment_type' => $this->request->getGet('payment_type') ?? 'all',
            'payment_method' => $this->request->getGet('payment_method') ?? 'all',
            'college_id' => $this->request->getGet('college_id') ?? 'all',
            'search' => $this->request->getGet('search') ?? '',
            'date_from' => $this->request->getGet('date_from') ?? '',
            'date_to' => $this->request->getGet('date_to') ?? '',
        ];

        if ($campusId !== null && is_numeric($campusId)) {
            $filters['campus_id'] = (int) $campusId;
        }

        $payments = $paymentModel->getFilteredPayments($filters);

        $filename = 'payments_report_' . date('Ymd_His') . '.csv';
        $output = fopen('php://temp', 'r+');

        // Report Header
        fputcsv($output, ['UNIVERSITY OF SCIENCE AND TECHNOLOGY OF SOUTHERN PHILIPPINES']);
        fputcsv($output, ['Payment Transactions Report']);
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateFrom !== '' && $dateTo !== '') {
            fputcsv($output, ["Reporting Period: {$dateFrom} to {$dateTo}"]);
        }
        fputcsv($output, ['Generated on: ' . date('F d, Y h:i:s A')]);
        fputcsv($output, []);

        // Group payments by College
        $paymentsByCollege = [];
        foreach ($payments as $p) {
            $collegeName = trim((string) ($p['college_name'] ?? ($p['college_abbr'] ?? '')));
            if ($collegeName === '') {
                $collegeName = 'Unassigned / General';
            }
            $paymentsByCollege[$collegeName][] = $p;
        }
        ksort($paymentsByCollege);

        foreach ($paymentsByCollege as $collegeName => $collegePayments) {
            fputcsv($output, [strtoupper($collegeName)]);
            fputcsv($output, [
                'Alumni ID',
                'Full Name',
                'Payment Purpose',
                'Payment Method',
                'Amount',
            ]);

            foreach ($collegePayments as $p) {
                $purposeDisplay = !empty($p['payment_purpose_name'])
                    ? $p['payment_purpose_name']
                    : ucwords(str_replace('_', ' ', (string) ($p['payment_type'] ?? '')));

                $alumniIdDisplay = trim((string) ($p['resolved_student_number'] ?? ($p['student_number'] ?? ($p['alumni_id'] ?? ''))));

                fputcsv($output, [
                    $alumniIdDisplay !== '' ? $alumniIdDisplay : 'N/A',
                    $p['payer_name'] ?? 'N/A',
                    $purposeDisplay,
                    strtoupper(str_replace('_', ' ', (string) ($p['payment_method'] ?? ''))),
                    number_format((float) ($p['amount'] ?? 0), 2, '.', ''),
                ]);
            }

            fputcsv($output, []);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csvContent);
    }

    /**
     * Check if payment records exist for the selected filters before exporting to Excel.
     * Prevents misleading empty reports and keeps the modal open.
     */
    public function checkPaymentExcelCount(): ResponseInterface
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Unauthorized access.',
            ]);
        }

        $filters = $this->extractAndSanitizePaymentFilters();

        if (empty($filters['date_from']) || empty($filters['date_to'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'count' => 0,
                'message' => 'Please select both "From Date" and "To Date" before exporting.',
            ]);
        }

        $paymentModel = new \App\Models\PaymentModel();
        $payments = $paymentModel->getPaymentsForExcelReport($filters);
        $count = count($payments);

        if ($count === 0) {
            return $this->response->setJSON([
                'success' => false,
                'count' => 0,
                'message' => 'No payment records were found for the selected date range and filters.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'count' => $count,
            'message' => "Found {$count} matching payment record(s).",
        ]);
    }

    /**
     * Generate and stream formatted Excel payment report using PhpSpreadsheet.
     */
    public function exportPaymentExcel()
    {
        if (! $this->isStaffAuthorized()) {
            return $this->response->setStatusCode(401)->setBody('Unauthorized access.');
        }

        $filters = $this->extractAndSanitizePaymentFilters();

        if (empty($filters['date_from']) || empty($filters['date_to'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Please select both "From Date" and "To Date" before exporting.',
            ]);
        }

        $paymentModel = new \App\Models\PaymentModel();
        $payments = $paymentModel->getPaymentsForExcelReport($filters);

        // Empty result handling
        if (empty($payments)) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'No payment records were found for the selected filters.',
            ]);
        }

        // Generate Excel file via PaymentReportService
        $service = new \App\Services\PaymentReportService();
        $spreadsheet = $service->generate($payments, $filters);
        $filename = $service->generateFilename($filters);

        // Log export activity in activities table
        try {
            $activityModel = new \App\Models\Activity_Model();
            $authUser = session('auth_user') ?? [
                'name' => 'Head Admin',
                'role' => 'head_admin',
                'email' => session('auth_user.email') ?? session('auth_user.Email') ?? '',
            ];
            $userIp = $this->request->getIPAddress();
            $userAgent = (string) $this->request->getUserAgent();

            $activityDetails = [
                'name' => 'Head Admin',
                'action' => 'Generated payment report',
                'category' => 'Reports',
                'description' => 'Head Admin generated an Excel payment transactions report.',
                'filename' => $filename,
                'exported_records_count' => count($payments),
                'filters' => [
                    'date_from' => $filters['date_from'] ?? 'all',
                    'date_to' => $filters['date_to'] ?? 'all',
                    'campus_id' => $filters['campus_id'] ?? 'all',
                    'college_id' => $filters['college_id'] ?? 'all',
                    'payment_type' => $filters['payment_type'] ?? 'all',
                    'payment_method' => $filters['payment_method'] ?? 'all',
                    'status' => $filters['status'] ?? 'all',
                    'submitted_by_role' => $filters['submitted_by_role'] ?? 'all',
                ],
            ];

            $activityModel->logActivity(
                $authUser,
                'Generated payment report',
                'Reports',
                $activityDetails,
                'staff',
                $userIp,
                $userAgent
            );
        } catch (\Exception $e) {
            // Silently suppress activity log exceptions so report download proceeds
            log_message('error', 'Failed to log payment report export activity: ' . $e->getMessage());
        }

        // Clean output buffers to prevent corrupting the binary XLSX file
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Send XLSX headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Extract and sanitize payment report filter parameters from GET or POST requests.
     */
    private function extractAndSanitizePaymentFilters(): array
    {
        $input = array_merge($this->request->getGet(), $this->request->getPost(), $this->request->getJSON(true) ?? []);

        $dateFrom = trim((string) ($input['date_from'] ?? ''));
        $dateTo = trim((string) ($input['date_to'] ?? ''));
        $collegeId = trim((string) ($input['college_id'] ?? 'all'));
        $paymentPurposeId = trim((string) ($input['payment_purpose_id'] ?? 'all'));
        $paymentType = trim((string) ($input['payment_type'] ?? 'all'));
        $paymentMethod = trim((string) ($input['payment_method'] ?? 'all'));
        $status = trim((string) ($input['status'] ?? 'all'));

        // Automatically use the campus of the logged-in account
        $scopedCampusId = $this->getHeadAdminCampusId();

        $filters = [];

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $filters['date_from'] = $dateFrom;
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $filters['date_to'] = $dateTo;
        }

        if ($scopedCampusId !== null && is_numeric($scopedCampusId) && (int) $scopedCampusId > 0) {
            $filters['campus_id'] = (int) $scopedCampusId;
        }

        if ($collegeId !== 'all' && is_numeric($collegeId) && (int) $collegeId > 0) {
            $filters['college_id'] = (int) $collegeId;
        }

        if ($paymentPurposeId !== 'all' && is_numeric($paymentPurposeId) && (int) $paymentPurposeId > 0) {
            $filters['payment_purpose_id'] = (int) $paymentPurposeId;
        }

        $allowedTypes = ['alumni_id', 'event_fee', 'membership', 'graduation_fee', 'donation', 'document_cert', 'other'];
        if ($paymentType !== 'all') {
            if (is_numeric($paymentType) && (int) $paymentType > 0) {
                $filters['payment_purpose_id'] = (int) $paymentType;
            } elseif (in_array($paymentType, $allowedTypes, true)) {
                $filters['payment_type'] = $paymentType;
            }
        }

        $allowedMethods = ['cash', 'gcash', 'maya', 'bank_transfer', 'online_portal', 'over_the_counter'];
        if ($paymentMethod !== 'all' && in_array($paymentMethod, $allowedMethods, true)) {
            $filters['payment_method'] = $paymentMethod;
        }

        $allowedStatuses = ['pending', 'verified', 'rejected', 'refunded'];
        if ($status !== 'all' && in_array($status, $allowedStatuses, true)) {
            $filters['status'] = $status;
        }

        return $filters;

    }

    public function commonCss(): ResponseInterface
    {
        $css = file_get_contents(APPPATH . 'Views/HTML/Staff/StaffCommon.css');

        return $this->response
            ->setContentType('text/css')
            ->setBody($css === false ? '' : $css);
    }

    public function commonJs(): ResponseInterface
    {
        $js = file_get_contents(APPPATH . 'Views/HTML/Staff/StaffCommon.js');

        return $this->response
            ->setContentType('application/javascript')
            ->setBody($js === false ? '' : $js);
    }

    private function renderProtectedView(string $view): ResponseInterface
    {
        if (! session()->has('auth_user')) {
            return redirect()->to(base_url('/'));
        }

        $role = strtolower((string) (session('auth_user.role') ?? ''));
        if ($role !== 'head_admin') {
            return redirect()->to(base_url('alumni/Main.html'));
        }

        return $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setBody(view($view));
    }
}
