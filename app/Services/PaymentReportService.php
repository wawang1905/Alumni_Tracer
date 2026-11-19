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

        // Build Payment Transactions Sheet
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Payment Transactions');
        $this->buildTransactionsSheet($sheet1, $payments, $filters);

        return $spreadsheet;
    }

    /**
     * Build and format the primary transactions worksheet separated by College.
     */
    private function buildTransactionsSheet($sheet, array $payments, array $filters): void
    {
        // 1. Report Header (Columns A to E)
        $sheet->setCellValue('A1', 'UNIVERSITY OF SCIENCE AND TECHNOLOGY OF SOUTHERN PHILIPPINES');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setSize(12)->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1E3A8A'));
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Payment Transactions Report');
        $sheet->mergeCells('A2:E2');
        $sheet->getStyle('A2')->getFont()->setSize(15)->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0F172A'));
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
        $sheet->mergeCells('A3:E3');
        $sheet->getStyle('A3')->getFont()->setSize(10.5)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF475569'));
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $generatedAt = date('F d, Y h:i:s A');
        $sheet->setCellValue('A4', "Generated on: {$generatedAt} | USTP Alumni Tracer System");
        $sheet->mergeCells('A4:E4');
        $sheet->getStyle('A4')->getFont()->setSize(9.5)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF64748B'));
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(24);
        $sheet->getRowDimension(3)->setRowHeight(18);
        $sheet->getRowDimension(4)->setRowHeight(16);
        $sheet->getRowDimension(5)->setRowHeight(12);

        // 2. Group payments by College
        $paymentsByCollege = [];
        foreach ($payments as $p) {
            $collegeName = trim((string) ($p['college_name'] ?? ''));
            if ($collegeName === '') {
                $collegeName = 'Unassigned / General';
            }
            $paymentsByCollege[$collegeName][] = $p;
        }
        ksort($paymentsByCollege);

        $tableHeaderStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10.5,
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

        $collegeTitleStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '0F172A'],
                'size' => 11.5,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ];

        $currencyFormat = '"₱"#,##0.00';
        $currentRow = 6;
        $grandTotalAmount = 0.00;

        // 3. Render a separate table for each College
        foreach ($paymentsByCollege as $collegeName => $collegePayments) {
            // College Header Banner
            $sheet->setCellValue("A{$currentRow}", strtoupper($collegeName));
            $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
            $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray($collegeTitleStyle);
            $sheet->getRowDimension($currentRow)->setRowHeight(24);
            $currentRow++;

            // Table Header Row
            $sheet->setCellValue("A{$currentRow}", 'Alumni ID');
            $sheet->setCellValue("B{$currentRow}", 'Full Name');
            $sheet->setCellValue("C{$currentRow}", 'Payment Purpose');
            $sheet->setCellValue("D{$currentRow}", 'Payment Method');
            $sheet->setCellValue("E{$currentRow}", 'Amount');
            $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray($tableHeaderStyle);
            $sheet->getRowDimension($currentRow)->setRowHeight(24);
            $currentRow++;

            $dataStartRow = $currentRow;
            $collegeSubtotal = 0.00;

            foreach ($collegePayments as $idx => $p) {
                $amountVal = (float) ($p['amount'] ?? 0.00);
                $collegeSubtotal += $amountVal;
                $grandTotalAmount += $amountVal;

                // 1. Alumni ID
                $studentNumber = trim((string) ($p['student_number'] ?? ''));
                $alumniId = trim((string) ($p['alumni_id'] ?? ''));
                $idDisplay = $studentNumber !== '' ? $studentNumber : ($alumniId !== '' ? $alumniId : 'N/A');
                $sheet->setCellValueExplicit("A{$currentRow}", $idDisplay, DataType::TYPE_STRING);

                // 2. Full Name
                $payerName = trim((string) ($p['payer_name'] ?? ''));
                $sheet->setCellValue("B{$currentRow}", $payerName !== '' ? $payerName : 'N/A');

                // 3. Payment Purpose
                $paymentPurpose = !empty($p['payment_purpose_name'])
                    ? $p['payment_purpose_name']
                    : $this->formatPaymentType((string) ($p['payment_type'] ?? ''));
                $sheet->setCellValue("C{$currentRow}", $paymentPurpose);

                // 4. Payment Method
                $paymentMethod = $this->formatPaymentMethod((string) ($p['payment_method'] ?? ''));
                $sheet->setCellValue("D{$currentRow}", $paymentMethod);

                // 5. Amount
                $sheet->setCellValue("E{$currentRow}", $amountVal);
                $sheet->getStyle("E{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);

                // Zebra striping within each college table
                if ($idx % 2 === 1) {
                    $sheet->getStyle("A{$currentRow}:E{$currentRow}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('F8FAFC');
                }

                $sheet->getRowDimension($currentRow)->setRowHeight(20);
                $currentRow++;
            }

            $dataEndRow = $currentRow - 1;
            if ($dataEndRow >= $dataStartRow) {
                $sheet->getStyle("A{$dataStartRow}:E{$dataEndRow}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)
                    ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFE2E8F0'));

                $sheet->getStyle("A{$dataStartRow}:A{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$dataStartRow}:C{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("D{$dataStartRow}:D{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$dataStartRow}:E{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A{$dataStartRow}:E{$dataEndRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }

            // College Subtotal Row
            $sheet->setCellValue("A{$currentRow}", "Total ({$collegeName})");
            $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
            $sheet->setCellValue("E{$currentRow}", $collegeSubtotal);
            $sheet->getStyle("E{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '0F172A'],
                    'size' => 10,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F1F5F9'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1'],
                    ],
                ],
            ]);
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getRowDimension($currentRow)->setRowHeight(21);
            $currentRow++;

            // Blank separator row between college tables
            $sheet->getRowDimension($currentRow)->setRowHeight(14);
            $currentRow++;
        }

        // Grand Total Row at the bottom if there are records
        if (!empty($paymentsByCollege)) {
            $sheet->setCellValue("A{$currentRow}", 'GRAND TOTAL');
            $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
            $sheet->setCellValue("E{$currentRow}", $grandTotalAmount);
            $sheet->getStyle("E{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
            $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '15803D'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F0FDF4'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => '16A34A'],
                    ],
                ],
            ]);
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getRowDimension($currentRow)->setRowHeight(24);
        }

        // 4. Column Widths (A to E)
        $columnWidths = [
            'A' => 22, // Alumni ID
            'B' => 30, // Full Name
            'C' => 28, // Payment Purpose
            'D' => 22, // Payment Method
            'E' => 20, // Amount
        ];

        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
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
