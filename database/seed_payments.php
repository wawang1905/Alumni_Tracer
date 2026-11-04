<?php
$mysqli = new mysqli("localhost", "root", "", "alumni_tracer");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Execute schema
$schema = file_get_contents(__DIR__ . '/schema/payments.sql');
$mysqli->multi_query($schema);
while ($mysqli->next_result()) {;} // flush multi queries

// Check count of existing payments
$countRes = $mysqli->query("SELECT COUNT(*) FROM payments");
$existingCount = (int)$countRes->fetch_row()[0];

if ($existingCount === 0) {
    // Get colleges and alumni to reference
    $colleges = [];
    $resCol = $mysqli->query("SELECT College_ID, College_Name, Campus_ID FROM colleges LIMIT 10");
    while ($r = $resCol->fetch_assoc()) {
        $colleges[] = $r;
    }

    $alumni = [];
    $resAlum = $mysqli->query("SELECT id, alumni_id, FirstName, LastName, Email, College_ID FROM alumni LIMIT 20");
    while ($r = $resAlum->fetch_assoc()) {
        $alumni[] = $r;
    }

    $samplePayments = [
        [
            'transaction_ref' => 'TXN-2026-00101',
            'alumni_id' => $alumni[0]['id'] ?? 1,
            'student_number' => $alumni[0]['alumni_id'] ?? '2020-30012',
            'payer_name' => !empty($alumni[0]) ? ($alumni[0]['FirstName'] . ' ' . $alumni[0]['LastName']) : 'Maria Santos',
            'payer_email' => $alumni[0]['Email'] ?? 'maria.santos@gmail.com',
            'payer_contact' => '09171234567',
            'campus_id' => $colleges[0]['Campus_ID'] ?? 1,
            'college_id' => $colleges[0]['College_ID'] ?? 1,
            'payment_type' => 'alumni_id',
            'description' => 'Alumni Physical ID Card - PVC Holographic with RFID',
            'amount' => 250.00,
            'payment_method' => 'gcash',
            'channel_reference' => 'GC-90182837192',
            'status' => 'pending',
            'proof_file' => 'receipt_sample_1.jpg',
            'submitted_by_role' => 'alumni',
            'coordinator_id' => null,
            'notes' => 'Applied online via Alumni Portal. Awaiting Head Admin validation of GCash transfer.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'transaction_ref' => 'TXN-2026-00102',
            'alumni_id' => $alumni[1]['id'] ?? 2,
            'student_number' => $alumni[1]['alumni_id'] ?? '2019-20415',
            'payer_name' => !empty($alumni[1]) ? ($alumni[1]['FirstName'] . ' ' . $alumni[1]['LastName']) : 'John Kenneth Reyes',
            'payer_email' => $alumni[1]['Email'] ?? 'jk.reyes@yahoo.com',
            'payer_contact' => '09289876543',
            'campus_id' => $colleges[0]['Campus_ID'] ?? 1,
            'college_id' => $colleges[0]['College_ID'] ?? 1,
            'payment_type' => 'event_fee',
            'description' => 'USTP Grand Alumni Homecoming 2026 - Gala Ticket',
            'amount' => 1200.00,
            'payment_method' => 'maya',
            'channel_reference' => 'MY-8827361928',
            'status' => 'verified',
            'proof_file' => 'receipt_sample_2.jpg',
            'submitted_by_role' => 'coordinator',
            'coordinator_id' => 1,
            'notes' => 'Batch endorsement verified with bank statement reconciliation.',
            'verified_by' => 1,
            'verified_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ],
        [
            'transaction_ref' => 'TXN-2026-00103',
            'alumni_id' => $alumni[2]['id'] ?? 3,
            'student_number' => $alumni[2]['alumni_id'] ?? '2021-10552',
            'payer_name' => !empty($alumni[2]) ? ($alumni[2]['FirstName'] . ' ' . $alumni[2]['LastName']) : 'Bea Alonzo',
            'payer_email' => $alumni[2]['Email'] ?? 'bea.alonzo@outlook.com',
            'payer_contact' => '09355551234',
            'campus_id' => $colleges[1]['Campus_ID'] ?? 1,
            'college_id' => $colleges[1]['College_ID'] ?? 2,
            'payment_type' => 'membership',
            'description' => 'USTP Alumni Association Lifetime Membership Fee',
            'amount' => 1500.00,
            'payment_method' => 'bank_transfer',
            'channel_reference' => 'BDO-TRX-77635241',
            'status' => 'verified',
            'proof_file' => 'receipt_sample_3.jpg',
            'submitted_by_role' => 'alumni',
            'coordinator_id' => null,
            'notes' => 'Official Receipt #OR-2026-041 issued.',
            'verified_by' => 1,
            'verified_at' => date('Y-m-d H:i:s', strtotime('-4 hours')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ],
        [
            'transaction_ref' => 'TXN-2026-00104',
            'alumni_id' => $alumni[3]['id'] ?? 4,
            'student_number' => $alumni[3]['alumni_id'] ?? '2018-40911',
            'payer_name' => !empty($alumni[3]) ? ($alumni[3]['FirstName'] . ' ' . $alumni[3]['LastName']) : 'Carlos Jose Dizon',
            'payer_email' => $alumni[3]['Email'] ?? 'carlos.dizon@gmail.com',
            'payer_contact' => '09154432109',
            'campus_id' => $colleges[1]['Campus_ID'] ?? 1,
            'college_id' => $colleges[1]['College_ID'] ?? 2,
            'payment_type' => 'document_cert',
            'description' => 'Authentication & Alumni Certification Records',
            'amount' => 350.00,
            'payment_method' => 'cash',
            'channel_reference' => 'OR-CASH-9912',
            'status' => 'verified',
            'proof_file' => null,
            'submitted_by_role' => 'walk_in',
            'coordinator_id' => null,
            'notes' => 'Walk-in payment received at Alumni Office counter.',
            'verified_by' => 1,
            'verified_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours'))
        ],
        [
            'transaction_ref' => 'TXN-2026-00105',
            'alumni_id' => $alumni[4]['id'] ?? 5,
            'student_number' => $alumni[4]['alumni_id'] ?? '2022-50123',
            'payer_name' => !empty($alumni[4]) ? ($alumni[4]['FirstName'] . ' ' . $alumni[4]['LastName']) : 'Angelo Gabriel Gomez',
            'payer_email' => $alumni[4]['Email'] ?? 'angelo.gomez@gmail.com',
            'payer_contact' => '09198887766',
            'campus_id' => $colleges[0]['Campus_ID'] ?? 1,
            'college_id' => $colleges[0]['College_ID'] ?? 1,
            'payment_type' => 'alumni_id',
            'description' => 'Alumni ID Replacement Card (Lost Card)',
            'amount' => 300.00,
            'payment_method' => 'gcash',
            'channel_reference' => 'GC-1122334455',
            'status' => 'pending',
            'proof_file' => 'receipt_sample_4.jpg',
            'submitted_by_role' => 'coordinator',
            'coordinator_id' => 1,
            'notes' => 'Forwarded by Coordinator for Head Admin approval. Affidavit of loss attached.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours'))
        ],
        [
            'transaction_ref' => 'TXN-2026-00106',
            'alumni_id' => $alumni[5]['id'] ?? 6,
            'student_number' => $alumni[5]['alumni_id'] ?? '2017-10022',
            'payer_name' => !empty($alumni[5]) ? ($alumni[5]['FirstName'] . ' ' . $alumni[5]['LastName']) : 'Kristine Joy Mendoza',
            'payer_email' => $alumni[5]['Email'] ?? 'kj.mendoza@gmail.com',
            'payer_contact' => '09087776655',
            'campus_id' => $colleges[0]['Campus_ID'] ?? 1,
            'college_id' => $colleges[0]['College_ID'] ?? 1,
            'payment_type' => 'donation',
            'description' => 'Alumni Scholarship Endowment Fund Contribution',
            'amount' => 5000.00,
            'payment_method' => 'bank_transfer',
            'channel_reference' => 'BPI-DEP-4491028',
            'status' => 'verified',
            'proof_file' => 'deposit_slip_1.jpg',
            'submitted_by_role' => 'alumni',
            'coordinator_id' => null,
            'notes' => 'Special acknowledgement letter sent by Alumni Director.',
            'verified_by' => 1,
            'verified_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
        ],
        [
            'transaction_ref' => 'TXN-2026-00107',
            'alumni_id' => $alumni[6]['id'] ?? 7,
            'student_number' => $alumni[6]['alumni_id'] ?? '2023-33100',
            'payer_name' => !empty($alumni[6]) ? ($alumni[6]['FirstName'] . ' ' . $alumni[6]['LastName']) : 'Mark Anthony Cruz',
            'payer_email' => $alumni[6]['Email'] ?? 'mark.cruz@gmail.com',
            'payer_contact' => '09223334444',
            'campus_id' => $colleges[0]['Campus_ID'] ?? 1,
            'college_id' => $colleges[0]['College_ID'] ?? 1,
            'payment_type' => 'alumni_id',
            'description' => 'Alumni ID Card',
            'amount' => 250.00,
            'payment_method' => 'gcash',
            'channel_reference' => 'GC-0000000000',
            'status' => 'rejected',
            'proof_file' => 'blur_receipt.jpg',
            'submitted_by_role' => 'alumni',
            'coordinator_id' => null,
            'notes' => 'Rejected: Uploaded screenshot is unreadable and reference number could not be verified in GCash merchant portal. Please re-upload.',
            'verified_by' => 1,
            'verified_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ],
        [
            'transaction_ref' => 'TXN-2026-00108',
            'alumni_id' => $alumni[7]['id'] ?? 8,
            'student_number' => $alumni[7]['alumni_id'] ?? '2016-12903',
            'payer_name' => !empty($alumni[7]) ? ($alumni[7]['FirstName'] . ' ' . $alumni[7]['LastName']) : 'Patricia Mae Del Rosario',
            'payer_email' => $alumni[7]['Email'] ?? 'patricia.delrosario@yahoo.com',
            'payer_contact' => '09176543210',
            'campus_id' => $colleges[0]['Campus_ID'] ?? 1,
            'college_id' => $colleges[0]['College_ID'] ?? 1,
            'payment_type' => 'graduation_fee',
            'description' => 'Yearbook & Graduation Keepsake Delivery',
            'amount' => 850.00,
            'payment_method' => 'maya',
            'channel_reference' => 'MY-9928172635',
            'status' => 'pending',
            'proof_file' => 'receipt_sample_5.jpg',
            'submitted_by_role' => 'alumni',
            'coordinator_id' => null,
            'notes' => 'Awaiting Head Admin confirmation of courier dispatch payment.',
            'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes'))
        ]
    ];

    $stmt = $mysqli->prepare("INSERT INTO payments (
        transaction_ref, alumni_id, student_number, payer_name, payer_email, payer_contact,
        campus_id, college_id, payment_type, description, amount, payment_method, channel_reference,
        status, proof_file, submitted_by_role, coordinator_id, notes, verified_by, verified_at, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($samplePayments as $p) {
        $verified_by = $p['verified_by'] ?? null;
        $verified_at = $p['verified_at'] ?? null;
        $stmt->bind_param(
            "sissssiissdssssisssss",
            $p['transaction_ref'],
            $p['alumni_id'],
            $p['student_number'],
            $p['payer_name'],
            $p['payer_email'],
            $p['payer_contact'],
            $p['campus_id'],
            $p['college_id'],
            $p['payment_type'],
            $p['description'],
            $p['amount'],
            $p['payment_method'],
            $p['channel_reference'],
            $p['status'],
            $p['proof_file'],
            $p['submitted_by_role'],
            $p['coordinator_id'],
            $p['notes'],
            $verified_by,
            $verified_at,
            $p['created_at']
        );
        $stmt->execute();
    }
    echo "Successfully seeded " . count($samplePayments) . " payments!\n";
} else {
    echo "Payments table already has $existingCount records.\n";
}
