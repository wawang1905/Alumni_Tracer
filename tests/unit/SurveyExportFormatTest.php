<?php

// Test script for survey export format validation

$testCategories = [
    [
        'id' => 1,
        'name' => 'General Information',
        'questions' => [
            [
                'id' => 101,
                'text' => 'What is your gender?',
                'type' => 'multiple_choice',
                'choiceStats' => [
                    ['choice' => 'Male', 'count' => 20],
                    ['choice' => 'Female', 'count' => 25],
                ],
            ],
            [
                'id' => 102,
                'text' => 'Civil Status',
                'type' => 'multiple_choice',
                'choiceStats' => [
                    ['choice' => 'Single', 'count' => 35],
                    ['choice' => 'Married', 'count' => 10],
                ],
            ],
        ],
    ],
    [
        'id' => 2,
        'name' => 'Employment Information',
        'questions' => [
            [
                'id' => 201,
                'text' => 'Are you currently employed?',
                'type' => 'multiple_choice',
                'choiceStats' => [
                    ['choice' => 'Yes', 'count' => 40],
                    ['choice' => 'No', 'count' => 5],
                ],
            ],
        ],
    ],
];

// Build CSV rows following the exact requested format:
// Category
//    Question
//      Choices      number of responses      Percentage
//      Choices      number of responses      Percentage
//      TOTAL        total responses          total percentage

$rows = [];
foreach ($testCategories as $category) {
    $rows[] = [$category['name'], '', ''];
    foreach ($category['questions'] as $q) {
        $rows[] = ['   ' . $q['text'], '', ''];
        $rows[] = ['     Choices', 'number of responses', 'Percentage'];
        $choiceStats = $q['choiceStats'];
        $totalCount = array_sum(array_column($choiceStats, 'count'));
        foreach ($choiceStats as $cs) {
            $pct = $totalCount > 0 ? round(($cs['count'] / $totalCount) * 100, 1) : 0.0;
            $rows[] = ['     ' . $cs['choice'], (string) $cs['count'], number_format($pct, 1) . '%'];
        }
        $rows[] = ['     TOTAL', (string) $totalCount, $totalCount > 0 ? '100.0%' : '0.0%'];
        $rows[] = [];
    }
    $rows[] = [];
}

// Assertions
if ($rows[0][0] !== 'General Information') {
    fwrite(STDERR, "Assertion failed: Category row mismatch\n");
    exit(1);
}

if ($rows[1][0] !== '   What is your gender?') {
    fwrite(STDERR, "Assertion failed: Question row indentation mismatch\n");
    exit(1);
}

if ($rows[2][0] !== '     Choices' || $rows[2][1] !== 'number of responses' || $rows[2][2] !== 'Percentage') {
    fwrite(STDERR, "Assertion failed: Choices header mismatch\n");
    exit(1);
}

if ($rows[3][0] !== '     Male' || $rows[3][1] !== '20' || $rows[3][2] !== '44.4%') {
    fwrite(STDERR, "Assertion failed: First choice row mismatch\n");
    exit(1);
}

if ($rows[4][0] !== '     Female' || $rows[4][1] !== '25' || $rows[4][2] !== '55.6%') {
    fwrite(STDERR, "Assertion failed: Second choice row mismatch\n");
    exit(1);
}

if ($rows[5][0] !== '     TOTAL' || $rows[5][1] !== '45' || $rows[5][2] !== '100.0%') {
    fwrite(STDERR, "Assertion failed: TOTAL row mismatch\n");
    exit(1);
}

echo "Survey export format tests passed.\n";
