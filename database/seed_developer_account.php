<?php

/**
 * One-time script to create or update the developer login account.
 * Run: php database/seed_developer_account.php
 */

require __DIR__ . '/../vendor/autoload.php';

$pathsConfig = new Config\Paths();
require $pathsConfig->systemDirectory . '/Boot.php';

CodeIgniter\Boot::bootSpark($pathsConfig);

$db = Config\Database::connect();
$email = 'developer@ustp.edu.ph';
$password = 'Developer@123';

$exists = $db->table('user_accounts')->where('Email', $email)->countAllResults();

if ($exists > 0) {
    $db->table('user_accounts')->where('Email', $email)->update([
        'Role'     => 'developer',
        'Password' => $password,
    ]);
    echo "Updated existing developer account.\n";
} else {
    $db->table('user_accounts')->insert([
        'First_Name' => 'System',
        'Last_Name'  => 'Developer',
        'Role'       => 'developer',
        'Email'      => $email,
        'College'    => 'USTP',
        'Department' => 'Information Technology',
        'account_id' => null,
        'Password'   => $password,
    ]);
    echo "Created developer account.\n";
}

$row = $db->table('user_accounts')->where('Email', $email)->get()->getRowArray();

echo "Login with:\n";
echo "  Email:    {$row['Email']}\n";
echo "  Password: {$password}\n";
echo "  Role:     {$row['Role']}\n";
