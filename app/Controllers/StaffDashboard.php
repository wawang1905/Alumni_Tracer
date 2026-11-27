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

            $rawBatchYear = trim((string) ($this->request ? $this->request->getGet('batchYear') : ''));
            $batchYear = ($rawBatchYear !== '' && strtolower($rawBatchYear) !== 'all' && preg_match('/^\d{4}$/', $rawBatchYear) === 1)
                ? $rawBatchYear
                : null;

            $db = \Config\Database::connect();
            if ($scope !== null || $batchYear !== null) {
                $totalBuilder = $db->table('alumni')
                    ->join('colleges c', 'c."College_ID" = alumni."College_ID"', 'left')
                    ->join('departments d', 'd."Department_ID" = alumni."Department_ID"', 'left');
                if ($scope !== null) {
                    $scopeService->applyAlumniScope($totalBuilder, $scope, 'alumni', 'c', 'd');
                }
                if ($batchYear !== null) {
                    $totalBuilder->where('TRIM(alumni."GraduationYear"::text)', $batchYear);
                }
                $totalAlumni = $totalBuilder->countAllResults();

                $activeBuilder = $db->table('alumni')
                    ->join('colleges c', 'c."College_ID" = alumni."College_ID"', 'left')
                    ->join('departments d', 'd."Department_ID" = alumni."Department_ID"', 'left')
                    ->where('LOWER(alumni."Status"::text)', 'active');
                if ($scope !== null) {
                    $scopeService->applyAlumniScope($activeBuilder, $scope, 'alumni', 'c', 'd');
                }
                if ($batchYear !== null) {
                    $activeBuilder->where('TRIM(alumni."GraduationYear"::text)', $batchYear);
                }
                $activeAlumni = $activeBuilder->countAllResults();
            } else {
                $totalAlumni = $this->alumniModel->getTotalAlumni();
                $activeAlumni = $this->alumniModel->getActiveAlumni();
            }

            $tracedGraduates = $this->calculateTracedGraduates($scope, $totalAlumni, $batchYear);
            $employmentRate = $this->calculateEmploymentRate($scope, $totalAlumni, $batchYear);
            $curriculumAlignment = $this->calculateCurriculumAlignment($scope, $batchYear);
            $timeToFirstEmployment = $this->calculateTimeToFirstEmployment($scope, $batchYear);

            $data = [
                'totalAlumni'           => $totalAlumni,
                'activeAlumni'          => $activeAlumni,
                'upcomingEvents'        => $this->eventModel->getUpcomingEventsCount(),
                'tracedGraduates'       => $tracedGraduates,
                'employmentRate'        => $employmentRate,
                'curriculumAlignment'   => $curriculumAlignment,
                'timeToFirstEmployment' => $timeToFirstEmployment,
            ];

            return $this->response->setJSON($data);

        } catch (\Throwable $e) {
            log_message('error', 'StaffDashboard::getDashboardData failed: {msg}', ['msg' => $e->getMessage()]);
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Unable to load dashboard data.'
            ]);
        }
    }

    /**
     * Normalize a raw curriculum alignment answer value without modifying stored data.
     * Returns 'related', 'moderately_related', 'unrelated', or null if unanswered/invalid.
     */
    public static function normalizeCurriculumAlignmentAnswer(?string $rawValue): ?string
    {
        if ($rawValue === null) {
            return null;
        }

        $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', $rawValue)));
        if ($normalized === '') {
            return null;
        }

        if ($normalized === 'related') {
            return 'related';
        }
        if ($normalized === 'moderately related') {
            return 'moderately_related';
        }
        if ($normalized === 'unrelated') {
            return 'unrelated';
        }

        return null;
    }

    /**
     * Compute Curriculum Alignment percentage and response distribution from valid counts.
     *
     * Scoring:
     * - Related = 3
     * - Moderately Related = 2
     * - Unrelated = 1
     *
     * Formula: (Total Actual Score / (Total Valid Responses * 3)) * 100
     *
     * @return array<string, mixed>
     */
    public static function computeCurriculumAlignmentMetrics(
        int $relatedCount,
        int $moderatelyRelatedCount,
        int $unrelatedCount
    ): array {
        $related = max(0, $relatedCount);
        $moderatelyRelated = max(0, $moderatelyRelatedCount);
        $unrelated = max(0, $unrelatedCount);

        $totalValid = $related + $moderatelyRelated + $unrelated;
        $totalActualScore = ($related * 3) + ($moderatelyRelated * 2) + ($unrelated * 1);
        $totalPossibleScore = $totalValid * 3;

        $alignmentPercentage = $totalValid > 0
            ? round(($totalActualScore / $totalPossibleScore) * 100, 2)
            : 0.0;

        $relatedPct = $totalValid > 0 ? round(($related / $totalValid) * 100, 2) : 0.0;
        $moderatelyRelatedPct = $totalValid > 0 ? round(($moderatelyRelated / $totalValid) * 100, 2) : 0.0;
        $unrelatedPct = $totalValid > 0 ? round(($unrelated / $totalValid) * 100, 2) : 0.0;

        return [
            'hasData'             => $totalValid > 0,
            'alignmentPercentage' => $alignmentPercentage,
            'totalValidResponses' => $totalValid,
            'totalActualScore'    => $totalActualScore,
            'totalPossibleScore'  => $totalPossibleScore,
            'distribution'        => [
                'related' => [
                    'label'      => 'Related',
                    'score'      => 3,
                    'count'      => $related,
                    'percentage' => $relatedPct,
                ],
                'moderatelyRelated' => [
                    'label'      => 'Moderately Related',
                    'score'      => 2,
                    'count'      => $moderatelyRelated,
                    'percentage' => $moderatelyRelatedPct,
                ],
                'unrelated' => [
                    'label'      => 'Unrelated',
                    'score'      => 1,
                    'count'      => $unrelated,
                    'percentage' => $unrelatedPct,
                ],
            ],
        ];
    }

    /**
     * Calculate Curriculum Alignment metrics from actual Supabase survey responses,
     * enforcing Head Admin campus / Coordinator college scope on the backend.
     *
     * @param array<string, mixed>|null $scope
     * @return array<string, mixed>
     */
    public function calculateCurriculumAlignment(?array $scope, ?string $batchYear = null): array
    {
        $db = \Config\Database::connect();
        $scopeService = new \App\Services\AssignmentScopeService();

        $builder = $db->table('answers a')
            ->select("COALESCE(NULLIF(TRIM(ch.choice_text), ''), NULLIF(TRIM(a.answer_text), '')) AS raw_answer", false)
            ->join('questions q', 'q.id = a.question_id', 'inner')
            ->join('responses r', 'r.id = a.response_id', 'inner')
            ->join('choices ch', 'ch.id = a.choice_id', 'left')
            ->join(
                'alumni al',
                '(NULLIF(TRIM(r.user_email), \'\') IS NOT NULL AND LOWER(TRIM(al."Email")) = LOWER(TRIM(r.user_email)))'
                . ' OR (NULLIF(TRIM(r.user_email), \'\') IS NULL AND NULLIF(TRIM(r.user_id), \'\') IS NOT NULL AND TRIM(al.alumni_id::text) = TRIM(r.user_id))',
                $scope !== null ? 'inner' : 'left'
            )
            ->join('colleges col', 'col."College_ID" = al."College_ID"', $scope !== null ? 'inner' : 'left')
            ->join('departments d', 'd."Department_ID" = al."Department_ID"', 'left')
            ->groupStart()
                ->where("LOWER(REGEXP_REPLACE(TRIM(q.question_text), '\\s+', ' ', 'g')) IN ('is your job related to your degree?', 'is your job related to your degree')", null, false)
                ->orWhere("LOWER(TRIM(COALESCE(q.analytics_key, ''))) IN ('curriculum_alignment', 'job_related_to_degree')", null, false)
            ->groupEnd();

        if ($scope !== null) {
            $scopeService->applyAlumniScope($builder, $scope, 'al', 'col', 'd');
        }

        if ($batchYear !== null && $batchYear !== '') {
            $builder->where('TRIM(al."GraduationYear"::text)', $batchYear);
        }

        $rows = $builder->get()->getResultArray();

        $relatedCount = 0;
        $moderatelyRelatedCount = 0;
        $unrelatedCount = 0;

        foreach ($rows as $row) {
            $bucket = self::normalizeCurriculumAlignmentAnswer(
                isset($row['raw_answer']) ? (string) $row['raw_answer'] : null
            );

            if ($bucket === 'related') {
                $relatedCount++;
            } elseif ($bucket === 'moderately_related') {
                $moderatelyRelatedCount++;
            } elseif ($bucket === 'unrelated') {
                $unrelatedCount++;
            }
        }

        return self::computeCurriculumAlignmentMetrics(
            $relatedCount,
            $moderatelyRelatedCount,
            $unrelatedCount
        );
    }

    /**
     * Count distinct alumni in scope who have submitted at least one tracer survey response.
     *
     * @param array<string, mixed>|null $scope
     * @return array<string, mixed>
     */
    public function calculateTracedGraduates(?array $scope, int $totalAlumni, ?string $batchYear = null): array
    {
        $db = \Config\Database::connect();
        $scopeService = new \App\Services\AssignmentScopeService();

        $builder = $db->table('responses r')
            ->select("COUNT(DISTINCT COALESCE(NULLIF(LOWER(TRIM(al.\"Email\")), ''), NULLIF(TRIM(al.alumni_id::text), ''))) AS traced_count", false)
            ->join(
                'alumni al',
                '(NULLIF(TRIM(r.user_email), \'\') IS NOT NULL AND LOWER(TRIM(al."Email")) = LOWER(TRIM(r.user_email)))'
                . ' OR (NULLIF(TRIM(r.user_email), \'\') IS NULL AND NULLIF(TRIM(r.user_id), \'\') IS NOT NULL AND TRIM(al.alumni_id::text) = TRIM(r.user_id))',
                'inner'
            )
            ->join('colleges col', 'col."College_ID" = al."College_ID"', $scope !== null ? 'inner' : 'left')
            ->join('departments d', 'd."Department_ID" = al."Department_ID"', 'left');

        if ($scope !== null) {
            $scopeService->applyAlumniScope($builder, $scope, 'al', 'col', 'd');
        }

        if ($batchYear !== null && $batchYear !== '') {
            $builder->where('TRIM(al."GraduationYear"::text)', $batchYear);
        }

        $row = $builder->get()->getRowArray();
        $tracedCount = max(0, (int) ($row['traced_count'] ?? 0));
        $safeTotal = max(0, $totalAlumni);
        $responseRate = $safeTotal > 0 ? round(($tracedCount / $safeTotal) * 100, 2) : 0.0;

        return [
            'tracedCount'  => $tracedCount,
            'totalAlumni'  => $safeTotal,
            'responseRate' => $responseRate,
        ];
    }

    /**
     * Calculate Employment Rate as (distinct alumni whose latest status is Employed / total alumni) * 100.
     *
     * @param array<string, mixed>|null $scope
     * @return array<string, mixed>
     */
    public function calculateEmploymentRate(?array $scope, int $totalAlumni, ?string $batchYear = null): array
    {
        $db = \Config\Database::connect();
        $scopeService = new \App\Services\AssignmentScopeService();

        $builder = $db->table('answers a')
            ->select(
                "COALESCE(NULLIF(LOWER(TRIM(al.\"Email\")), ''), NULLIF(TRIM(al.alumni_id::text), '')) AS alumni_key, "
                . "COALESCE(NULLIF(TRIM(ch.choice_text), ''), NULLIF(TRIM(a.answer_text), '')) AS raw_answer",
                false
            )
            ->join('questions q', 'q.id = a.question_id', 'inner')
            ->join('responses r', 'r.id = a.response_id', 'inner')
            ->join('choices ch', 'ch.id = a.choice_id', 'left')
            ->join(
                'alumni al',
                '(NULLIF(TRIM(r.user_email), \'\') IS NOT NULL AND LOWER(TRIM(al."Email")) = LOWER(TRIM(r.user_email)))'
                . ' OR (NULLIF(TRIM(r.user_email), \'\') IS NULL AND NULLIF(TRIM(r.user_id), \'\') IS NOT NULL AND TRIM(al.alumni_id::text) = TRIM(r.user_id))',
                'inner'
            )
            ->join('colleges col', 'col."College_ID" = al."College_ID"', $scope !== null ? 'inner' : 'left')
            ->join('departments d', 'd."Department_ID" = al."Department_ID"', 'left')
            ->groupStart()
                ->where("LOWER(REGEXP_REPLACE(TRIM(q.question_text), '\\s+', ' ', 'g')) IN ('what is your employment status?', 'what is your employment status')", null, false)
                ->orWhere("LOWER(TRIM(COALESCE(q.analytics_key, ''))) = 'employment_status'", null, false)
            ->groupEnd()
            ->orderBy('r.submitted_at', 'DESC')
            ->orderBy('r.id', 'DESC');

        if ($scope !== null) {
            $scopeService->applyAlumniScope($builder, $scope, 'al', 'col', 'd');
        }

        if ($batchYear !== null && $batchYear !== '') {
            $builder->where('TRIM(al."GraduationYear"::text)', $batchYear);
        }

        $rows = $builder->get()->getResultArray();

        $latestStatusByAlumni = [];
        foreach ($rows as $row) {
            $alumniKey = trim((string) ($row['alumni_key'] ?? ''));
            if ($alumniKey === '' || array_key_exists($alumniKey, $latestStatusByAlumni)) {
                continue;
            }

            $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', (string) ($row['raw_answer'] ?? ''))));
            if ($normalized === 'employed' || $normalized === 'unemployed') {
                $latestStatusByAlumni[$alumniKey] = $normalized;
            }
        }

        $employedCount = 0;
        foreach ($latestStatusByAlumni as $status) {
            if ($status === 'employed') {
                $employedCount++;
            }
        }

        $safeTotal = max(0, $totalAlumni);
        $employmentRate = $safeTotal > 0 ? round(($employedCount / $safeTotal) * 100, 2) : 0.0;

        return [
            'employedCount'  => $employedCount,
            'totalAlumni'    => $safeTotal,
            'employmentRate' => $employmentRate,
        ];
    }

    /**
     * Normalize a raw Time to First Employment survey answer into a canonical bucket key
     * without modifying stored database values.
     */
    public static function normalizeTimeToFirstEmploymentAnswer(?string $rawValue): ?string
    {
        if ($rawValue === null) {
            return null;
        }

        $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', $rawValue)));
        if ($normalized === '') {
            return null;
        }

        // Normalize en-dash / em-dash to hyphen for consistent matching
        $normalized = str_replace(["\u{2013}", "\u{2014}"], '-', $normalized);
        $normalized = (string) preg_replace('/\s*-\s*/', '-', $normalized);

        if (in_array($normalized, ['less than a month', 'less than 1 month', '< 1 month', 'under 1 month'], true)) {
            return 'less_than_a_month';
        }
        if (in_array($normalized, ['1-3 months', '1 to 3 months'], true)) {
            return 'one_to_three_months';
        }
        if (in_array($normalized, ['3-6 months', '3 to 6 months'], true)) {
            return 'three_to_six_months';
        }
        if (in_array($normalized, ['6-12 months', '6 to 12 months'], true)) {
            return 'six_to_twelve_months';
        }
        if (in_array($normalized, ['more than a year', 'more than 1 year', 'over a year', 'over 1 year'], true)) {
            return 'more_than_a_year';
        }

        return null;
    }

    /**
     * Compute estimated average Time to First Employment (in months) and category distribution.
     *
     * Assigned values:
     * - Less than a month: 0.5 months
     * - 1-3 months: 2 months
     * - 3-6 months: 4.5 months
     * - 6-12 months: 9 months
     * - More than a year: 15 months
     *
     * @param array<string, int> $counts
     * @return array<string, mixed>
     */
    public static function computeTimeToFirstEmploymentMetrics(array $counts): array
    {
        $definitions = [
            'less_than_a_month'    => ['label' => 'Less than a month', 'months' => 0.5],
            'one_to_three_months'  => ['label' => '1–3 months',        'months' => 2.0],
            'three_to_six_months'  => ['label' => '3–6 months',        'months' => 4.5],
            'six_to_twelve_months' => ['label' => '6–12 months',       'months' => 9.0],
            'more_than_a_year'     => ['label' => 'More than a year',  'months' => 15.0],
        ];

        $totalValid = 0;
        $weightedTotal = 0.0;
        $normalizedCounts = [];

        foreach ($definitions as $key => $meta) {
            $count = max(0, (int) ($counts[$key] ?? 0));
            $normalizedCounts[$key] = $count;
            $totalValid += $count;
            $weightedTotal += $count * $meta['months'];
        }

        $averageMonths = $totalValid > 0 ? round($weightedTotal / $totalValid, 2) : null;
        $withinThreeMonthsCount = $normalizedCounts['less_than_a_month'] + $normalizedCounts['one_to_three_months'];
        $withinThreeMonthsPct = $totalValid > 0 ? round(($withinThreeMonthsCount / $totalValid) * 100, 2) : 0.0;

        $distribution = [];
        foreach ($definitions as $key => $meta) {
            $count = $normalizedCounts[$key];
            $distribution[$key] = [
                'label'          => $meta['label'],
                'assignedMonths' => $meta['months'],
                'count'          => $count,
                'percentage'     => $totalValid > 0 ? round(($count / $totalValid) * 100, 2) : 0.0,
            ];
        }

        return [
            'hasData'                     => $totalValid > 0,
            'averageMonths'               => $averageMonths,
            'weightedTotalMonths'         => round($weightedTotal, 2),
            'totalValidResponses'         => $totalValid,
            'withinThreeMonthsCount'      => $withinThreeMonthsCount,
            'withinThreeMonthsPercentage' => $withinThreeMonthsPct,
            'distribution'                => $distribution,
        ];
    }

    /**
     * Calculate Time to First Employment metrics from actual Supabase survey responses,
     * deduplicated per alumni (latest valid response) and scoped to Head Admin campus / Coordinator college.
     *
     * @param array<string, mixed>|null $scope
     * @return array<string, mixed>
     */
    public function calculateTimeToFirstEmployment(?array $scope, ?string $batchYear = null): array
    {
        $db = \Config\Database::connect();
        $scopeService = new \App\Services\AssignmentScopeService();

        $builder = $db->table('answers a')
            ->select(
                "COALESCE(NULLIF(LOWER(TRIM(al.\"Email\")), ''), NULLIF(TRIM(al.alumni_id::text), ''), NULLIF(LOWER(TRIM(r.user_email)), ''), CONCAT('resp:', r.id::text)) AS respondent_key, "
                . "COALESCE(NULLIF(TRIM(ch.choice_text), ''), NULLIF(TRIM(a.answer_text), '')) AS raw_answer",
                false
            )
            ->join('questions q', 'q.id = a.question_id', 'inner')
            ->join('responses r', 'r.id = a.response_id', 'inner')
            ->join('choices ch', 'ch.id = a.choice_id', 'left')
            ->join(
                'alumni al',
                '(NULLIF(TRIM(r.user_email), \'\') IS NOT NULL AND LOWER(TRIM(al."Email")) = LOWER(TRIM(r.user_email)))'
                . ' OR (NULLIF(TRIM(r.user_email), \'\') IS NULL AND NULLIF(TRIM(r.user_id), \'\') IS NOT NULL AND TRIM(al.alumni_id::text) = TRIM(r.user_id))',
                $scope !== null ? 'inner' : 'left'
            )
            ->join('colleges col', 'col."College_ID" = al."College_ID"', $scope !== null ? 'inner' : 'left')
            ->join('departments d', 'd."Department_ID" = al."Department_ID"', 'left')
            ->groupStart()
                ->where("LOWER(REGEXP_REPLACE(TRIM(q.question_text), '\\s+', ' ', 'g')) IN ('how long did it take you to find your first job after graduation?', 'how long did it take you to find your first job after graduation')", null, false)
                ->orWhere("LOWER(TRIM(COALESCE(q.analytics_key, ''))) IN ('time_to_first_job', 'time_to_first_employment')", null, false)
            ->groupEnd()
            ->orderBy('r.submitted_at', 'DESC')
            ->orderBy('r.id', 'DESC');

        if ($scope !== null) {
            $scopeService->applyAlumniScope($builder, $scope, 'al', 'col', 'd');
        }

        if ($batchYear !== null && $batchYear !== '') {
            $builder->where('TRIM(al."GraduationYear"::text)', $batchYear);
        }

        $rows = $builder->get()->getResultArray();

        $counts = [
            'less_than_a_month'    => 0,
            'one_to_three_months'  => 0,
            'three_to_six_months'  => 0,
            'six_to_twelve_months' => 0,
            'more_than_a_year'     => 0,
        ];

        $seenRespondents = [];
        foreach ($rows as $row) {
            $respondentKey = trim((string) ($row['respondent_key'] ?? ''));
            if ($respondentKey === '' || isset($seenRespondents[$respondentKey])) {
                continue;
            }

            $bucket = self::normalizeTimeToFirstEmploymentAnswer(
                isset($row['raw_answer']) ? (string) $row['raw_answer'] : null
            );

            if ($bucket === null) {
                continue;
            }

            $seenRespondents[$respondentKey] = true;
            $counts[$bucket]++;
        }

        return self::computeTimeToFirstEmploymentMetrics($counts);
    }
}