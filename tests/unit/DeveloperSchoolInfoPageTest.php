<?php

$path = __DIR__ . '/../../app/Views/HTML/Developer/SchoolInfo.html';
if (!is_file($path)) {
    fwrite(STDERR, "School info page template was not found.\n");
    exit(1);
}

$html = file_get_contents($path);
if ($html === false) {
    fwrite(STDERR, "Unable to read the school info page template.\n");
    exit(1);
}

$failures = [];
if (str_contains($html, 'id="schoolPanel"')) {
    $failures[] = 'The legacy school panel markup still exists.';
}
if (str_contains($html, 'id="campusModal"')) {
    $failures[] = 'The legacy campus modal markup still exists.';
}
if (!str_contains($html, 'School information management has been removed')) {
    $failures[] = 'The page does not show the removed-state message.';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . "\n");
    }
    exit(1);
}

echo "Developer school info page tests passed.\n";
