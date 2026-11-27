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

            $rawBatchYear = trim((string) ($this->request ? $this->request->getGet('batchYear') : ''));
            $batchYear = ($rawBatchYear !== '' && strtolower($rawBatchYear) !== 'all' && preg_match('/^\d{4}$/', $rawBatchYear) === 1)
                ? $rawBatchYear
                : null;

            $curriculumAlignment = $this->calculateCurriculumAlignment($scope, $batchYear);

            $data = [
                'totalAlumni'         => $totalAlumni,
                'activeAlumni'        => $activeAlumni,
                'upcomingEvents'      => $this->eventModel->getUpcomingEventsCount(),
                'curriculumAlignment' => $curriculumAlignment,
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
}