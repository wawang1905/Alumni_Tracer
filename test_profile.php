<?php
// Simple test script to check profile endpoint
require_once 'app/Config/Routes.php';
require_once 'app/Controllers/AlumniController.php';
require_once 'app/Models/UserAccountModel.php';
require_once 'app/Models/AlumniModel.php';

// Mock session data
$_SESSION['auth_user'] = [
    'email' => 'test@example.com',
    'first_name' => 'Test',
    'last_name' => 'User',
    'role' => 'alumni'
];

echo "Testing profile endpoint...\n";

try {
    $controller = new App\Controllers\AlumniController();
    
    // Test profile method
    $response = $controller->profile();
    
    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "Response body: " . $response->getBody() . "\n";
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
