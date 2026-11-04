<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class PaymentReportService
{
    /**
     * Build the PhpSpreadsheet instance with all sheets and formatting.
     *
     * @param array $payments List of payment records with joined relations
     * @param array $filters  Applied filter parameters
     * @return Spreadsheet
     */
    public function generate(array $payments, array $filters = []): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('USTP Alumni Tracer System')
            ->setLastModifiedBy('Head Admin')
            ->setTitle('Payment Transactions Report')
            ->setSubject('Financial Payment Records')
            ->setDescription('Official Payment Transactions Report generated from USTP Alumni Tracer System.')
            ->setKeywords('payment alumni tracer report financial transactions');

        // Build Sheet 1: Payment Transactions
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Payment Transactions');
        $this->buildTransactionsSheet($sheet1, $payments, $filters);

        // Build Sheet 2: Summary
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Summary');
        $this->buildSummarySheet($sheet2, $payments);

        // Set active sheet back to Sheet 1
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Build and format the primary transactions worksheet.
     */
    private function buildTransactionsSheet($sheet, array $payments, array $filters): void
    {
        // 1. Institutional Headers
        $sheet->setCellValue('A1', 'UNIVERSITY OF SCIENCE AND TECHNOLOGY OF SOUTHERN PHILIPPINES');
        $sheet->mergeCells('A1:O1');
        $sheet->getStyle('A1')->getFont()->setSize(13)->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1E3A8A'));
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Payment Transactions Report');
        $sheet->mergeCells('A2:O2');
        $sheet->getStyle('A2')->getFont()->setSize(16)->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0F172A'));
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Reporting period text
        $dateFrom = !empty($filters['date_from']) ? trim((string) $filters['date_from']) : null;
        $dateTo = !empty($filters['date_to']) ? trim((string) $filters['date_to']) : null;
        if ($dateFrom && $dateTo) {
            $periodText = "Reporting Period: {$dateFrom} to {$dateTo}";
        } elseif ($dateFrom) {
            $periodText = "Reporting Period: From {$dateFrom}";
        } elseif ($dateTo) {
            $periodText = "Reporting Period: Until {$dateTo}";
        } else {
            $periodText = "Reporting Period: All Available Records";
        }

        $sheet->setCellValue('A3', $periodText);
        $sheet->mergeCells('A3:O3');
        $sheet->getStyle('A3')->getFont()->setSize(11)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF475569'));
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $generatedAt = date('F d, Y h:i:s A');
        $sheet->setCellValue('A4', "Generated on: {$generatedAt} | USTP Alumni Tracer System");
        $sheet->mergeCells('A4:O4');
        $sheet->getStyle('A4')->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF64748B'));
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Row 5 is blank separator
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(26);
        $sheet->getRowDimension(3)->setRowHeight(18);
        $sheet->getRowDimension(4)->setRowHeight(16);
        $sheet->getRowDimension(5)->setRowHeight(10);

        // 2. Column Headers (Row 6)
        $headers = [
            'A6' => 'Transaction Reference',
            'B6' => 'Payment Date',
            'C6' => 'Payer Name',
            'D6' => 'Student Number / Alumni ID',
            'E6' => 'Campus',
            'F6' => 'College',
            'G6' => 'Payment Purpose / Type',
            'H6' => 'Amount',
            'I6' => 'Payment Method',
            'J6' => 'Channel Reference',
            'K6' => 'Status',
            'L6' => 'Recorded By',
            'M6' => 'Verified By',
            'N6' => 'Verification Date',
            'O6' => 'Notes',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $sheet->getRowDimension(6)->setRowHeight(28);

        // Header style: Navy background (#1E3A8A), White bold text, centered vertically
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A8A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '0F172A'],
                ],
            ],
        ];
        $sheet->getStyle('A6:O6')->applyFromArray($headerStyle);

        // 3. Populate Data Rows starting at Row 7
        $currentRow = 7;
        $totalTransactions = count($payments);
        $verifiedCount = 0;
        $pendingCount = 0;
        $rejectedCount = 0;
        $totalVerifiedAmount = 0.00;

        $currencyFormat = '"₱"#,##0.00';

        foreach ($payments as $p) {
            $statusRaw = strtolower(trim((string) ($p['status'] ?? 'pending')));
            $amountVal = (float) ($p['amount'] ?? 0.00);

            if ($statusRaw === 'verified') {
                $verifiedCount++;
                $totalVerifiedAmount += $amountVal;
            } elseif ($statusRaw === 'rejected') {
                $rejectedCount++;
            } else {
                $pendingCount++;
            }

            // 1. Transaction Reference (explicit text)
            $txnRef = trim((string) ($p['transaction_ref'] ?? ''));
            $sheet->setCellValueExplicit("A{$currentRow}", $txnRef !== '' ? $txnRef : 'N/A', DataType::TYPE_STRING);

            // 2. Payment Date
            $paymentDateStr = $this->formatDateValue($p['created_at'] ?? null);
            $sheet->setCellValue("B{$currentRow}", $paymentDateStr);

            // 3. Payer Name
            $payerName = trim((string) ($p['payer_name'] ?? ''));
            $sheet->setCellValue("C{$currentRow}", $payerName !== '' ? $payerName : 'N/A');

            // 4. Student Number / Alumni ID (explicit text)
            $studentNumber = trim((string) ($p['student_number'] ?? ''));
            $alumniId = trim((string) ($p['alumni_id'] ?? ''));
            $idDisplay = $studentNumber !== '' ? $studentNumber : ($alumniId !== '' ? $alumniId : 'N/A');
            $sheet->setCellValueExplicit("D{$currentRow}", $idDisplay, DataType::TYPE_STRING);

            // 5. Campus
            $campusName = trim((string) ($p['campus_name'] ?? ''));
            $sheet->setCellValue("E{$currentRow}", $campusName !== '' ? $campusName : 'N/A');

            // 6. College
            $collegeName = trim((string) ($p['college_name'] ?? ''));
            $sheet->setCellValue("F{$currentRow}", $collegeName !== '' ? $collegeName : 'N/A');

            // 7. Payment Purpose / Type
            $paymentType = !empty($p['payment_purpose_name'])
                ? $p['payment_purpose_name']
                : $this->formatPaymentType((string) ($p['payment_type'] ?? ''));
            $sheet->setCellValue("G{$currentRow}", $paymentType);

            // 8. Amount (numeric with currency format)
            $sheet->setCellValue("H{$currentRow}", $amountVal);
            $sheet->getStyle("H{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);

            // 9. Payment Method
            $paymentMethod = $this->formatPaymentMethod((string) ($p['payment_method'] ?? ''));
            $sheet->setCellValue("I{$currentRow}", $paymentMethod);

            // 10. Channel Reference
            $channelRef = trim((string) ($p['channel_reference'] ?? ''));
            $isCash = strtolower(trim((string) ($p['payment_method'] ?? ''))) === 'cash';
            if ($isCash || $channelRef === '') {
                $channelDisplay = 'N/A';
            } else {
                $channelDisplay = $channelRef;
            }
            $sheet->setCellValueExplicit("J{$currentRow}", $channelDisplay, DataType::TYPE_STRING);

            // 11. Status
            $statusDisplay = ucfirst($statusRaw);
            $sheet->setCellValue("K{$currentRow}", $statusDisplay);

            // 12. Recorded By
            $recordedBy = $this->formatRecordedByRole((string) ($p['submitted_by_role'] ?? ''));
            $sheet->setCellValue("L{$currentRow}", $recordedBy);

            // 13. Verified By
            $verifiedBy = $this->resolveVerifierName($p);
            $sheet->setCellValue("M{$currentRow}", $verifiedBy);

            // 14. Verification Date
            $verifiedAtStr = $this->formatDateValue($p['verified_at'] ?? null);
            $sheet->setCellValue("N{$currentRow}", $verifiedAtStr);

            // 15. Notes
            $notes = trim((string) ($p['notes'] ?? ''));
            $sheet->setCellValue("O{$currentRow}", $notes !== '' ? $notes : 'N/A');

            // Row height and alignment
            $sheet->getRowDimension($currentRow)->setRowHeight(20);

            $currentRow++;
        }

        $lastDataRow = $currentRow - 1;

        // Apply grid borders and alignments to data rows
        if ($lastDataRow >= 7) {
            $dataRange = "A7:O{$lastDataRow}";
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFE2E8F0'));

            // Text alignments
            $sheet->getStyle("A7:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B7:B{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D7:D{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H7:H{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("I7:I{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J7:J{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K7:K{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("L7:L{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("M7:M{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("N7:N{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("O7:O{$lastDataRow}")->getAlignment()->setWrapText(true);

            // Alternate zebra row styling for readability
            for ($r = 7; $r <= $lastDataRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:O{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
                }
            }
        }

        // Freeze header row at Row 7 (row 6 remains static above)
        $sheet->freezePane('A7');

        // Enable column filters on header row
        $sheet->setAutoFilter("A6:O" . max(7, $lastDataRow));

        // 4. Summary Section at the Bottom
        $summaryStartRow = $lastDataRow + 2;

        $sheet->setCellValue("B{$summaryStartRow}", 'EXECUTIVE SUMMARY');
        $sheet->mergeCells("B{$summaryStartRow}:D{$summaryStartRow}");
        $sheet->getStyle("B{$summaryStartRow}")->getFont()->setBold(true)->setSize(12)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1E3A8A'));

        $metrics = [
            'Total Number of Transactions' => [$totalTransactions, 'count'],
            'Total Verified Transactions'  => [$verifiedCount, 'count'],
            'Total Pending Transactions'   => [$pendingCount, 'count'],
            'Total Rejected Transactions'  => [$rejectedCount, 'count'],
            'Total Amount of Verified Payments' => [$totalVerifiedAmount, 'currency'],
        ];

        $metricRow = $summaryStartRow + 1;
        foreach ($metrics as $label => [$val, $type]) {
            $sheet->setCellValue("B{$metricRow}", $label);
            $sheet->mergeCells("B{$metricRow}:C{$metricRow}");
            $sheet->getStyle("B{$metricRow}")->getFont()->setBold(true);

            $sheet->setCellValue("D{$metricRow}", $val);
            if ($type === 'currency') {
                $sheet->getStyle("D{$metricRow}")->getNumberFormat()->setFormatCode($currencyFormat);
                $sheet->getStyle("D{$metricRow}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF15803D'));
            } else {
                $sheet->getStyle("D{$metricRow}")->getNumberFormat()->setFormatCode('#,##0');
            }
            $sheet->getStyle("D{$metricRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $metricRow++;
        }

        $summaryBoxRange = "B" . ($summaryStartRow) . ":D" . ($metricRow - 1);
        $sheet->getStyle($summaryBoxRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1E3A8A'));
        $sheet->getStyle("B" . ($summaryStartRow + 1) . ":D" . ($metricRow - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');

        // 5. Auto-size columns with reasonable maximums
        $maxColumnWidths = [
            'A' => 22, // Transaction Ref
            'B' => 20, // Payment Date
            'C' => 26, // Payer Name
            'D' => 24, // Student Number / Alumni ID
            'E' => 20, // Campus
            'F' => 30, // College
            'G' => 24, // Payment Type
            'H' => 18, // Amount
            'I' => 18, // Payment Method
            'J' => 22, // Channel Reference
            'K' => 16, // Status
            'L' => 18, // Recorded By
            'M' => 26, // Verified By
            'N' => 20, // Verification Date
            'O' => 38, // Notes
        ];

        foreach ($maxColumnWidths as $col => $maxW) {
            $sheet->getColumnDimension($col)->setWidth($maxW);
        }
    }

    /**
     * Build the secondary "Summary" worksheet with cross-tabulation statistics.
     */
    private function buildSummarySheet($sheet, array $payments): void
    {
        $currencyFormat = '"₱"#,##0.00';

        // Title
        $sheet->setCellValue('A1', 'Payment Report - Analytical Summary');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1E3A8A'));
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->setCellValue('A2', 'Aggregated breakdowns by Payment Type, Method, Status, and College Collections.');
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF64748B'));
        $sheet->getRowDimension(2)->setRowHeight(18);

        // Compute Aggregates
        $byType = [];
        $byMethod = [];
        $byStatus = [];
        $byCollege = [];

        foreach ($payments as $p) {
            $type = !empty($p['payment_purpose_name'])
                ? $p['payment_purpose_name']
                : $this->formatPaymentType((string) ($p['payment_type'] ?? ''));
            $method = $this->formatPaymentMethod((string) ($p['payment_method'] ?? ''));
            $status = ucfirst(strtolower(trim((string) ($p['status'] ?? 'pending'))));
            $college = trim((string) ($p['college_name'] ?? '')) ?: 'General / Unassigned';
            $amt = (float) ($p['amount'] ?? 0);
            $isVerified = strtolower(trim((string) ($p['status'] ?? ''))) === 'verified';

            $byType[$type] = ($byType[$type] ?? 0) + 1;
            $byMethod[$method] = ($byMethod[$method] ?? 0) + 1;
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;

            if (!isset($byCollege[$college])) {
                $byCollege[$college] = [
                    'count' => 0,
                    'verified_count' => 0,
                    'verified_amount' => 0.00,
                ];
            }
            $byCollege[$college]['count']++;
            if ($isVerified) {
                $byCollege[$college]['verified_count']++;
                $byCollege[$college]['verified_amount'] += $amt;
            }
        }

        // Table 1: Count by Status (Col A & B)
        $r = 4;
        $sheet->setCellValue("A{$r}", 'Transaction Status');
        $sheet->setCellValue("B{$r}", 'Count');
        $this->styleTableHeader($sheet, "A{$r}:B{$r}");
        $r++;
        $statusStart = $r;
        foreach ($byStatus as $st => $cnt) {
            $sheet->setCellValue("A{$r}", $st);
            $sheet->setCellValue("B{$r}", $cnt);
            $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $r++;
        }
        $this->styleTableBody($sheet, "A{$statusStart}:B" . ($r - 1));

        // Table 2: Count by Payment Method (Col D & E)
        $r2 = 4;
        $sheet->setCellValue("D{$r2}", 'Payment Method');
        $sheet->setCellValue("E{$r2}", 'Count');
        $this->styleTableHeader($sheet, "D{$r2}:E{$r2}");
        $r2++;
        $methodStart = $r2;
        foreach ($byMethod as $mth => $cnt) {
            $sheet->setCellValue("D{$r2}", $mth);
            $sheet->setCellValue("E{$r2}", $cnt);
            $sheet->getStyle("E{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $r2++;
        }
        $this->styleTableBody($sheet, "D{$methodStart}:E" . ($r2 - 1));

        // Table 3: Count by Payment Type (Col A & B, below status)
        $r3 = max($r, $r2) + 2;
        $sheet->setCellValue("A{$r3}", 'Payment Purpose / Type');
        $sheet->setCellValue("B{$r3}", 'Count');
        $this->styleTableHeader($sheet, "A{$r3}:B{$r3}");
        $r3++;
        $typeStart = $r3;
        foreach ($byType as $tp => $cnt) {
            $sheet->setCellValue("A{$r3}", $tp);
            $sheet->setCellValue("B{$r3}", $cnt);
            $sheet->getStyle("B{$r3}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $r3++;
        }
        $this->styleTableBody($sheet, "A{$typeStart}:B" . ($r3 - 1));

        // Table 4: Collections by College (Col D, E, F, below method)
        $r4 = max($r, $r2) + 2;
        $sheet->setCellValue("D{$r4}", 'College Name');
        $sheet->setCellValue("E{$r4}", 'Verified Trans.');
        $sheet->setCellValue("F{$r4}", 'Verified Collections');
        $this->styleTableHeader($sheet, "D{$r4}:F{$r4}");
        $r4++;
        $colStart = $r4;
        foreach ($byCollege as $clg => $data) {
            $sheet->setCellValue("D{$r4}", $clg);
            $sheet->setCellValue("E{$r4}", $data['verified_count']);
            $sheet->setCellValue("F{$r4}", $data['verified_amount']);
            $sheet->getStyle("E{$r4}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("F{$r4}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("F{$r4}")->getNumberFormat()->setFormatCode($currencyFormat);
            $r4++;
        }
        $this->styleTableBody($sheet, "D{$colStart}:F" . ($r4 - 1));

        // Adjust column widths on Summary Sheet
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(6);
        $sheet->getColumnDimension('D')->setWidth(36);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(24);
    }

    private function styleTableHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A8A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '0F172A'],
                ],
            ],
        ]);
    }

    private function styleTableBody($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    /**
     * Resolve and format the verifier complete name.
     */
    private function resolveVerifierName(array $p): string
    {
        if (empty($p['verified_by'])) {
            return 'N/A';
        }

        $uaFirst = trim((string) ($p['ua_first_name'] ?? ''));
        $uaLast = trim((string) ($p['ua_last_name'] ?? ''));
        if ($uaFirst !== '' || $uaLast !== '') {
            return trim("{$uaFirst} {$uaLast}");
        }

        $stFirst = trim((string) ($p['st_first_name'] ?? ''));
        $stLast = trim((string) ($p['st_last_name'] ?? ''));
        if ($stFirst !== '' || $stLast !== '') {
            return trim("{$stFirst} {$stLast}");
        }

        if (!empty($p['verifier_name'])) {
            return trim((string) $p['verifier_name']);
        }

        return 'Authorized Staff';
    }

    /**
     * Format payment type into user-friendly title.
     */
    private function formatPaymentType(string $type): string
    {
        $map = [
            'alumni_id'       => 'Alumni ID Card',
            'event_fee'       => 'Event Registration',
            'membership'      => 'Membership Dues',
            'graduation_fee'  => 'Graduation Fee',
            'donation'        => 'Donation / Endowment',
            'document_cert'   => 'Documents & Certifications',
            'other'           => 'Other Fee',
        ];

        return $map[$type] ?? ucwords(str_replace('_', ' ', $type ?: 'General Fee'));
    }

    /**
     * Format payment method nicely.
     */
    private function formatPaymentMethod(string $method): string
    {
        $map = [
            'cash'             => 'Cash',
            'gcash'            => 'GCash',
            'maya'             => 'Maya',
            'bank_transfer'    => 'Bank Transfer',
            'online_portal'    => 'Online Portal',
            'over_the_counter' => 'Over-the-Counter',
        ];

        return $map[$method] ?? ucwords(str_replace('_', ' ', $method ?: 'Cash'));
    }

    /**
     * Format submitted_by_role nicely.
     */
    private function formatRecordedByRole(string $role): string
    {
        $clean = strtolower(trim($role));
        if ($clean === 'head_admin' || $clean === 'admin' || $clean === 'head admin') {
            return 'Head Admin';
        }
        if ($clean === 'coordinator') {
            return 'Coordinator';
        }
        if ($clean === 'alumni') {
            return 'Alumni';
        }

        return ucwords(str_replace('_', ' ', $role ?: 'System'));
    }

    /**
     * Format date value safely into readable representation.
     */
    private function formatDateValue(?string $rawDate): string
    {
        if (!$rawDate || trim($rawDate) === '' || str_starts_with($rawDate, '0000-00-00')) {
            return 'N/A';
        }

        $time = strtotime($rawDate);
        if ($time === false) {
            return (string) $rawDate;
        }

        return date('Y-m-d H:i:s', $time);
    }

    /**
     * Generate sanitized filename according to specifications.
     */
    public function generateFilename(array $filters = []): string
    {
        $dateFrom = !empty($filters['date_from']) ? preg_replace('/[^0-9\-]/', '', (string) $filters['date_from']) : '';
        $dateTo = !empty($filters['date_to']) ? preg_replace('/[^0-9\-]/', '', (string) $filters['date_to']) : '';

        if ($dateFrom !== '' && $dateTo !== '') {
            return "Payment_Report_{$dateFrom}_to_{$dateTo}.xlsx";
        }

        return 'Payment_Report_' . date('Y-m-d_H-i-s') . '.xlsx';
    }
}
