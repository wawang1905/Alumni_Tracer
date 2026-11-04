<?php

require __DIR__ . '/../../app/Helpers/role_helper.php';

$cases = [
    ['alumni', 'Alumni'],
    ['head_admin', 'Head Admin'],
    ['staff', 'Staff'],
    ['developer', 'Developer'],
    ['system_admin', 'Developer'],
];

foreach ($cases as [$role, $expected]) {
    $actual = format_user_role_label($role);
    if ($actual !== $expected) {
        fwrite(STDERR, "Expected {$expected} for {$role}, got {$actual}\n");
        exit(1);
    }
}

$options = developer_user_role_options();
if (!in_array('alumni', array_column($options, 'value'), true)) {
    fwrite(STDERR, "The role dropdown is missing the alumni option.\n");
    exit(1);
}

echo "Developer role mapping tests passed.\n";
