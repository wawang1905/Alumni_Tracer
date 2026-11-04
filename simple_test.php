<?php
// Simple test to isolate the issue
echo "Testing basic PHP functionality...\n";

// Test if we can include the view file
if (file_exists('app/Views/HTML/Alumni/Profile.html')) {
    echo "✓ Profile.html file exists\n";
} else {
    echo "✗ Profile.html file NOT found\n";
}

// Test if we can create the controller
try {
    require_once 'app/Controllers/AlumniController.php';
    echo "✓ AlumniController loaded\n";
    
    $controller = new App\Controllers\AlumniController();
    echo "✓ AlumniController instantiated\n";
    
    // Test the profilePage method directly
    echo "Testing profilePage method...\n";
    
    // Mock session for testing
    $_SESSION['auth_user'] = [
        'email' => 'test@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
        'role' => 'alumni'
    ];
    
    $result = $controller->profilePage();
    echo "Result: " . get_class($result) . "\n";
    echo "Status Code: " . $result->getStatusCode() . "\n";
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>
