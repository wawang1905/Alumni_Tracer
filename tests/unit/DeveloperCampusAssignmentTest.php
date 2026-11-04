<?php

$controllerPath = __DIR__ . '/../../app/Controllers/DeveloperController.php';
$controllerSource = file_get_contents($controllerPath);

if ($controllerSource === false || strpos($controllerSource, "syncCampusAssignment") === false || strpos($controllerSource, "'Campus' => $campusId") === false) {
    fwrite(STDERR, "Campus assignment update is missing from the developer controller.\n");
    exit(1);
}

$modelPath = __DIR__ . '/../../app/Models/UserAccountModel.php';
$modelSource = file_get_contents($modelPath);

if ($modelSource === false || strpos($modelSource, "'Campus',") === false) {
    fwrite(STDERR, "The user account model is missing the Campus field.\n");
    exit(1);
}

echo "Developer campus assignment tests passed.\n";
