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
            $programGroups = $this->getAlumniProgramGroups($filters);
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
        $programGroups = $this->getAlumniProgramGroups($filters);

        // Compile Choice & Response Statistics (Category -> Question -> Choices & number of responses)
        $selectedCategories = $this->buildCategoryQuestionChoiceStatistics(
            $db,
            $selectedCategories,
            $selectedQuestions,
            $matchingResponses
        );

        $format = strtolower(trim((string) ($payload['format'] ?? 'pdf')));
        $cleanTitle = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim((string) ($survey['title'] ?? 'survey')));
        $filename = 'Survey_Report_' . $cleanTitle . '_' . date('Y-m-d_His');

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

        // 2. Fetch all alumni indexed by lowercase email
        $alumniRows = $db->table('alumni')->get()->getResultArray();
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

            $college = is_array($alumni) ? trim((string) ($alumni['College'] ?? '')) : '';
            $course = is_array($alumni) ? trim((string) ($alumni['Course'] ?? '')) : '';
            $gradYear = is_array($alumni) ? trim((string) ($alumni['GraduationYear'] ?? '')) : '';
            $alumniRecordId = is_array($alumni) ? trim((string) ($alumni['alumni_id'] ?? '')) : '';
            $reviewStatus = strtolower(trim((string) ($resp['review_status'] ?? 'pending')));
            $submittedAt = (string) ($resp['submitted_at'] ?? '');

            // System filter checks
            if ($filterYear !== '' && strcasecmp($gradYear, $filterYear) !== 0) {
                continue;
            }
            if ($filterCollege !== '' && strcasecmp($college, $filterCollege) !== 0) {
                continue;
            }
            if ($filterCourse !== '' && strcasecmp($course, $filterCourse) !== 0) {
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
                'college' => $college ?: '—',
                'course' => $course ?: '—',
                'graduationYear' => $gradYear ?: '—',
                'reviewStatus' => $reviewStatus ?: 'pending',
                'submittedAt' => $submittedAt,
                'answers' => $respAnswers,
            ];
        }

        return $matching;
    }

    /**
     * Builds Choice and Response Statistics for Category-Question Hierarchy
     * Format:
     * Category
     *    Question
     *      Choices      number of responses
     */
    private function buildCategoryQuestionChoiceStatistics(\CodeIgniter\Database\BaseConnection $db, array $selectedCategories, array $selectedQuestions, array $matchingResponses): array
    {
        $selectedQIds = array_values(array_filter(array_map('intval', array_column($selectedQuestions, 'id'))));
        if (empty($selectedQIds)) {
            return $selectedCategories;
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

        $answersByQId = [];
        foreach ($rawAnswers as $row) {
            $qId = (int) $row['question_id'];
            $answersByQId[$qId][] = [
                'choice_id' => $row['choice_id'] !== null ? (int) $row['choice_id'] : null,
                'choice_text' => trim((string) ($row['choice_text'] ?? '')),
                'answer_text' => trim((string) ($row['answer_text'] ?? '')),
                'value' => trim((string) ($row['choice_text'] ?? $row['answer_text'] ?? '')),
            ];
        }

        // 3. For each category and each question, compile choice statistics
        foreach ($selectedCategories as &$category) {
            foreach ($category['questions'] as &$question) {
                $qId = (int) $question['id'];
                $qType = strtolower(trim((string) ($question['type'] ?? 'text')));
                $qAnswers = $answersByQId[$qId] ?? [];
                $choicesList = [];

                if ($qType === 'multiple_choice') {
                    $definedChoices = $choicesByQuestion[$qId] ?? [];
                    $choiceCounts = [];
                    $choiceIdToText = [];

                    foreach ($definedChoices as $dc) {
                        $cText = $dc['text'];
                        $choiceCounts[$cText] = 0;
                        $choiceIdToText[$dc['id']] = $cText;
                    }

                    foreach ($qAnswers as $ans) {
                        if ($ans['choice_id'] !== null && isset($choiceIdToText[$ans['choice_id']])) {
                            $targetText = $choiceIdToText[$ans['choice_id']];
                            $choiceCounts[$targetText] = ($choiceCounts[$targetText] ?? 0) + 1;
                        } elseif ($ans['choice_text'] !== '' && isset($choiceCounts[$ans['choice_text']])) {
                            $choiceCounts[$ans['choice_text']]++;
                        } elseif ($ans['value'] !== '') {
                            $choiceCounts[$ans['value']] = ($choiceCounts[$ans['value']] ?? 0) + 1;
                        }
                    }

                    if (empty($choiceCounts)) {
                        $choiceCounts['No choices configured'] = 0;
                    }

                    foreach ($choiceCounts as $cText => $count) {
                        $choicesList[] = [
                            'choice' => $cText,
                            'count' => $count,
                        ];
                    }
                } elseif ($qType === 'rating') {
                    $definedChoices = $choicesByQuestion[$qId] ?? [];
                    if (! empty($definedChoices)) {
                        $choiceCounts = [];
                        foreach ($definedChoices as $dc) {
                            $choiceCounts[$dc['text']] = 0;
                        }
                        foreach ($qAnswers as $ans) {
                            $val = $ans['choice_text'] !== '' ? $ans['choice_text'] : $ans['value'];
                            if (isset($choiceCounts[$val])) {
                                $choiceCounts[$val]++;
                            } elseif ($val !== '') {
                                $choiceCounts[$val] = ($choiceCounts[$val] ?? 0) + 1;
                            }
                        }
                        foreach ($choiceCounts as $cText => $count) {
                            $choicesList[] = [
                                'choice' => $cText,
                                'count' => $count,
                            ];
                        }
                    } else {
                        $min = ! empty($question['rating_min']) ? (int) $question['rating_min'] : 1;
                        $max = ! empty($question['rating_max']) ? (int) $question['rating_max'] : 5;
                        $minLabel = trim((string) ($question['rating_min_label'] ?? ''));
                        $maxLabel = trim((string) ($question['rating_max_label'] ?? ''));

                        $scaleCounts = [];
                        $scoreToLabel = [];
                        for ($s = $min; $s <= $max; $s++) {
                            $label = (string) $s;
                            if ($s === $min && $minLabel !== '') {
                                $label .= ' (' . $minLabel . ')';
                            } elseif ($s === $max && $maxLabel !== '') {
                                $label .= ' (' . $maxLabel . ')';
                            }
                            $scaleCounts[$label] = 0;
                            $scoreToLabel[$s] = $label;
                        }

                        foreach ($qAnswers as $ans) {
                            $numVal = is_numeric($ans['value']) ? (int) $ans['value'] : null;
                            if ($numVal !== null && isset($scoreToLabel[$numVal])) {
                                $lbl = $scoreToLabel[$numVal];
                                $scaleCounts[$lbl] = ($scaleCounts[$lbl] ?? 0) + 1;
                            } elseif ($ans['value'] !== '') {
                                $scaleCounts[$ans['value']] = ($scaleCounts[$ans['value']] ?? 0) + 1;
                            }
                        }

                        foreach ($scaleCounts as $label => $cnt) {
                            $choicesList[] = [
                                'choice' => $label,
                                'count' => $cnt,
                            ];
                        }
                    }
                } else {
                    // Open-ended / text questions
                    if (empty($qAnswers)) {
                        $choicesList[] = [
                            'choice' => 'No responses submitted',
                            'count' => 0,
                        ];
                    } else {
                        $textCounts = [];
                        foreach ($qAnswers as $ans) {
                            $val = trim((string) $ans['value']);
                            if ($val !== '' && $val !== '—') {
                                $textCounts[$val] = ($textCounts[$val] ?? 0) + 1;
                            }
                        }

                        if (empty($textCounts)) {
                            $choicesList[] = [
                                'choice' => 'No responses submitted',
                                'count' => 0,
                            ];
                        } else {
                            arsort($textCounts);
                            foreach ($textCounts as $tText => $cnt) {
                                $choicesList[] = [
                                    'choice' => $tText,
                                    'count' => $cnt,
                                ];
                            }
                        }
                    }
                }

                $qTotalResponses = array_sum(array_column($choicesList, 'count'));
                foreach ($choicesList as &$cItem) {
                    $cCount = (int) ($cItem['count'] ?? 0);
                    $cItem['percentage'] = $qTotalResponses > 0 ? round(($cCount / $qTotalResponses) * 100, 1) : 0.0;
                    $cItem['percentage_formatted'] = number_format($cItem['percentage'], 1) . '%';
                }
                unset($cItem);

                $question['choiceStats'] = $choicesList;
                $question['totalResponses'] = $qTotalResponses;
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
            $programGroups = $this->getAlumniProgramGroups($filters);
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
            $programGroups = $this->getAlumniProgramGroups($filters);
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
            $programGroups = $this->getAlumniProgramGroups($filters);
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
     * Queries the alumni list grouped by program, marking all graduates as Not Tracked (NT) for now.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function getAlumniProgramGroups(array $filters = []): array
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

        // Fetch all alumni joined with colleges and departments
        $alumniRows = $db->table('alumni')
            ->select('alumni.id, alumni.alumni_id, alumni.FirstName, alumni.LastName, alumni.Email, alumni.GraduationYear, alumni.Status, alumni.College_ID, alumni.Department_ID, c.College_Name AS College, d.Department_Name AS Course, d.Abbreviation AS Course_Abbr')
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
            $course = trim((string) ($al['Course'] ?? ''));
            $courseAbbr = trim((string) ($al['Course_Abbr'] ?? ''));
            $gradYear = trim((string) ($al['GraduationYear'] ?? ''));

            if ($filterYear !== '' && strcasecmp($gradYear, $filterYear) !== 0) {
                continue;
            }
            if ($filterCollege !== '' && strcasecmp($college, $filterCollege) !== 0) {
                continue;
            }
            if ($filterCourse !== '' && strcasecmp($course, $filterCourse) !== 0) {
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

            $groupsByProgram[$programName]['graduates'][] = [
                'name' => $formattedName,
                'status' => 'Not Tracked',
                'employment' => '',
                'supportingDoc' => '',
            ];
        }

        // Finalize counts for each program (all marked as Not Tracked for now)
        foreach ($groupsByProgram as &$grp) {
            $total = count($grp['graduates']);
            $grp['total'] = $total;
            $grp['employedCount'] = 0;
            $grp['unemployedCount'] = 0;
            $grp['notTrackedCount'] = $total;
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
        $values = [$campusId];

        if (is_numeric($campusId)) {
            $values[] = (int) $campusId;
            $values[] = (string) $campusId;
            $campusRow = $campusModel->where('Campus_ID', (int) $campusId)->first();
            if ($campusRow && !empty($campusRow['Campus'])) {
                $values[] = $campusRow['Campus'];
            }
        } else {
            $campusRow = $campusModel->where('Campus', (string) $campusId)->first();
            if ($campusRow && !empty($campusRow['Campus_ID'])) {
                $values[] = $campusRow['Campus_ID'];
                $values[] = (string) $campusRow['Campus_ID'];
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
        $departmentModel = new \App\Models\DepartmentModel();
        
        // Get head admin's campus ID from account or session
        $campusId = $this->getHeadAdminCampusId() ?? session('auth_user.campus') ?? session('auth_user.Campus') ?? '';
        
        if (empty($campusId)) {
            return $this->response->setJSON([
                'success' => true,
                'colleges' => [],
            ]);
        }
        
        $campus = $campusModel->where('Campus_ID', $campusId)->first();
        $headAdminCampus = $campus['Campus'] ?? '';
        
        // Get colleges for this campus
        $colleges = $collegeModel->where('Campus_ID', $campusId)->findAll();

        $collegeData = [];
        foreach ($colleges as $collegeRow) {
            $collegeName = $collegeRow['College_Name'];
            $collegeId = $collegeRow['College_ID'];
            
            $db = \Config\Database::connect();
            $staffCoordinators = $db->table('staffs')
                ->where('Campus_ID', $campusId)
                ->where('College_ID', $collegeId)
                ->where('Role', 'Coordinator')
                ->get()
                ->getResultArray();

            $accountCoordinators = $this->getCoordinatorAccountsForCollege($collegeName, (string) $campusId, (string) $headAdminCampus);
            $collegeCoordinators = $this->mergeCoordinatorRows($staffCoordinators, $accountCoordinators);

            log_message('error', 'getCollegesData: College=' . $collegeName . ', College_ID=' . $collegeId . ', Campus_ID=' . $campusId . ', Found coordinators: ' . count($collegeCoordinators));

            // Get total alumni for this college
            $totalAlumni = 0; // Will need to update when alumni table structure is known

            // Get active alumni for this college
            $activeAlumni = 0; // Will need to update when alumni table structure is known

            $totalDepartments = $departmentModel
                ->where('Campus_ID', $campusId)
                ->where('College_ID', $collegeId)
                ->countAllResults();

            $collegeData[] = [
                'college_id' => $collegeRow['College_ID'],
                'college' => $collegeName,
                'totalCourses' => $totalDepartments,
                'totalDepartments' => $totalDepartments,
                'totalAlumni' => $totalAlumni,
                'activeAlumni' => $activeAlumni,
                'coordinators' => $collegeCoordinators
            ];
        }

        return $this->response->setJSON([
            'success' => true,
            'colleges' => $collegeData,
        ]);
    }

    private function getCoordinatorAccountsForCollege(string $collegeName, string $campusId, string $campusName): array
    {
        $campusValues = array_values(array_unique(array_filter([$campusId, $campusName], fn (string $value): bool => trim($value) !== '')));

        if ($campusValues === []) {
            return [];
        }

        $userAccountModel = new \App\Models\UserAccountModel();
        $coordinators = $userAccountModel
            ->where('Role', 'coordinator')
            ->whereIn('Campus', $campusValues)
            ->findAll();

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
        $campusValues = array_values(array_unique(array_filter([$campusId, $campusName], fn (string $value): bool => trim($value) !== '')));

        if ($campusValues === []) {
            return [];
        }

        $userAccountModel = new \App\Models\UserAccountModel();
        $coordinators = $userAccountModel
            ->where('Role', 'coordinator')
            ->whereIn('Campus', $campusValues)
            ->findAll();

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
        
        $campus = $campusModel->where('Campus_ID', $campusId)->first();
        $headAdminCampus = $campus['Campus'] ?? '';
        
        // Get departments for this campus
        $departments = $departmentModel->where('Campus_ID', $campusId)->findAll();

        $departmentData = [];
        foreach ($departments as $deptRow) {
            $departmentName = $deptRow['Department_Name'];
            $collegeId = $deptRow['College_ID'];
            $departmentId = $deptRow['Department_ID'];
            
            // Get college name
            $college = $collegeModel->where('College_ID', $collegeId)->first();
            $collegeName = $college['College_Name'] ?? 'Unknown College';
            
            $db = \Config\Database::connect();
            $staffCoordinators = $db->table('staffs')
                ->where('Campus_ID', $campusId)
                ->where('Department_ID', $departmentId)
                ->where('Role', 'Coordinator')
                ->get()
                ->getResultArray();

            $accountCoordinators = $this->getCoordinatorAccountsForDepartment($departmentName, $collegeName, (string) $campusId, (string) $headAdminCampus);
            $deptCoordinators = $this->mergeCoordinatorRows($staffCoordinators, $accountCoordinators);

            log_message('error', 'getDepartmentsData: Department=' . $departmentName . ', Department_ID=' . $departmentId . ', Campus_ID=' . $campusId . ', Found coordinators: ' . count($deptCoordinators));

            // Get total users for this department
            $totalUsers = 0; // Will need to update when alumni table structure is known

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
            $statistics = $paymentModel->getPaymentStatistics($campusId !== null && is_numeric($campusId) ? (int) $campusId : null);
            $purposes = $purposeModel->getActivePurposes();

            // Colleges list for filter
            $collegeBuilder = $collegeModel;
            if ($campusId !== null && is_numeric($campusId)) {
                $collegeBuilder = $collegeBuilder->where('Campus_ID', (int) $campusId);
            }
            $colleges = $collegeBuilder->orderBy('College_Name', 'ASC')->findAll();

            // Campuses list for filter
            $campusModel = new \App\Models\CampusModel();
            $campuses = $campusModel->orderBy('Campus', 'ASC')->findAll();

            // Recent alumni list for autocomplete in modal
            $recentAlumni = $alumniModel->select('id, alumni_id, FirstName, LastName, Email, College_ID')
                ->orderBy('LastName', 'ASC')
                ->limit(100)
                ->findAll();

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

            $data = [
                'transaction_ref'    => $ref,
                'alumni_id'          => !empty($input['alumni_id']) ? (int) $input['alumni_id'] : null,
                'student_number'     => !empty($input['student_number']) ? trim((string) $input['student_number']) : null,
                'payer_name'         => $payerName,
                'payer_email'        => !empty($input['payer_email']) ? trim((string) $input['payer_email']) : null,
                'payer_contact'      => !empty($input['payer_contact']) ? trim((string) $input['payer_contact']) : null,
                'campus_id'          => !empty($input['campus_id']) ? (int) $input['campus_id'] : ($campusId !== null && is_numeric($campusId) ? (int) $campusId : 1),
                'college_id'         => !empty($input['college_id']) ? (int) $input['college_id'] : null,
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
            return null;
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

        // CSV Headers
        fputcsv($output, [
            'Transaction Ref',
            'Payer Name',
            'Student Number',
            'Email',
            'Contact',
            'College',
            'Purpose / Type',
            'Description',
            'Amount (PHP)',
            'Payment Method',
            'Channel Reference',
            'Status',
            'Date Created',
            'Verified At',
            'Notes'
        ]);

        foreach ($payments as $p) {
            $purposeDisplay = !empty($p['payment_purpose_name'])
                ? $p['payment_purpose_name']
                : ucwords(str_replace('_', ' ', (string) ($p['payment_type'] ?? '')));

            fputcsv($output, [
                $p['transaction_ref'] ?? '',
                $p['payer_name'] ?? '',
                $p['student_number'] ?? '',
                $p['payer_email'] ?? '',
                $p['payer_contact'] ?? '',
                $p['college_name'] ?? ($p['college_abbr'] ?? ''),
                $purposeDisplay,
                $p['description'] ?? '',
                number_format((float) ($p['amount'] ?? 0), 2, '.', ''),
                strtoupper(str_replace('_', ' ', (string) ($p['payment_method'] ?? ''))),
                $p['channel_reference'] ?? '',
                ucfirst((string) ($p['status'] ?? '')),
                $p['created_at'] ?? '',
                $p['verified_at'] ?? '',
                $p['notes'] ?? ''
            ]);
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

        $paymentModel = new \App\Models\PaymentModel();
        $payments = $paymentModel->getPaymentsForExcelReport($filters);
        $count = count($payments);

        if ($count === 0) {
            return $this->response->setJSON([
                'success' => false,
                'count' => 0,
                'message' => 'No payment records were found for the selected filters.',
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
        $campusId = trim((string) ($input['campus_id'] ?? 'all'));
        $collegeId = trim((string) ($input['college_id'] ?? 'all'));
        $paymentPurposeId = trim((string) ($input['payment_purpose_id'] ?? 'all'));
        $paymentType = trim((string) ($input['payment_type'] ?? 'all'));
        $paymentMethod = trim((string) ($input['payment_method'] ?? 'all'));
        $status = trim((string) ($input['status'] ?? 'all'));
        $role = trim((string) ($input['submitted_by_role'] ?? 'all'));

        // Enforce Head Admin campus restriction
        $scopedCampusId = $this->getHeadAdminCampusId();
        if ($scopedCampusId !== null && is_numeric($scopedCampusId)) {
            $campusId = (string) $scopedCampusId;
        }

        $filters = [];

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $filters['date_from'] = $dateFrom;
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $filters['date_to'] = $dateTo;
        }

        if ($campusId !== 'all' && is_numeric($campusId) && (int) $campusId > 0) {
            $filters['campus_id'] = (int) $campusId;
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

        $allowedRoles = ['head_admin', 'coordinator', 'alumni'];
        if ($role !== 'all' && in_array($role, $allowedRoles, true)) {
            $filters['submitted_by_role'] = $role;
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
