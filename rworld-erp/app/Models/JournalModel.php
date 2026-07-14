<?php
/**
 * QuickBill POS - Automated General Ledger Journal Voucher Model
 * Milestone 1: Automated GAAP Double-Entry Posting Core
 */

class JournalModel {

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Post a confirmed sales invoice to the General Ledger
     * 
     * POS Cash Sale entry:
     * - Debit: Cash-in-Hand (ledger_id = 1) for net_amount
     * - Credit: Sales Account (ledger_id = 2) for gross_amount - bill_disc_amount
     * - Credit: Sales Tax Payable (ledger_id = 4) for total_tax
     */
    public function postSale($companyId, $branchId, $saleId) {
        $sale = $this->db->fetchOne(
            "SELECT * FROM sales_header WHERE id = ? AND company_id = ?",
            [(int)$saleId, $companyId]
        );

        if (!$sale) {
            return false;
        }

        $grossAmt = (float)$sale['gross_amount'];
        $discAmt  = (float)$sale['bill_disc_amount'];
        $taxAmt   = (float)$sale['total_tax'];
        $netAmt   = (float)$sale['net_amount'];
        $docNo    = $sale['doc_no'];

        // Formula check
        $calculatedSales = $grossAmt - $discAmt;
        
        $postings = [];

        // 1. Debit Cash
        if ($netAmt > 0) {
            $postings[] = [
                'ledger_id' => 1, // Cash-in-Hand
                'debit'     => $netAmt,
                'credit'    => 0.0000,
                'narration' => "Cash received for Invoice #{$docNo}"
            ];
        }

        // 2. Credit Sales Account
        if ($calculatedSales > 0) {
            $postings[] = [
                'ledger_id' => 2, // Sales Account
                'debit'     => 0.0000,
                'credit'    => $calculatedSales,
                'narration' => "Sales Revenue for Invoice #{$docNo}"
            ];
        }

        // 3. Credit Sales Tax Payable
        if ($taxAmt > 0) {
            $postings[] = [
                'ledger_id' => 4, // Sales Tax Payable
                'debit'     => 0.0000,
                'credit'    => $taxAmt,
                'narration' => "Sales Tax collected for Invoice #{$docNo}"
            ];
        }

        if (empty($postings)) {
            return false;
        }

        // Double-entry validation: sum(debit) must equal sum(credit)
        $totalDebit  = array_sum(array_column($postings, 'debit'));
        $totalCredit = array_sum(array_column($postings, 'credit'));

        if (abs($totalDebit - $totalCredit) > 0.001) {
            // Handle round-off difference
            $diff = $totalDebit - $totalCredit;
            if (abs($diff) < 1.0) {
                // Adjust minor round-off on sales revenue row
                foreach ($postings as &$post) {
                    if ($post['ledger_id'] === 2) {
                        $post['credit'] += $diff;
                        break;
                    }
                }
            } else {
                // Large discrepancy — log warning and abort posting
                error_log("Discrepancy in double-entry calculations for POS Sale ID {$saleId}. Debit: {$totalDebit}, Credit: {$totalCredit}");
                return false;
            }
        }

        // Generate Voucher Document No
        $lastVal = $this->db->fetchColumn(
            "SELECT doc_no FROM vouchers WHERE company_id = ? AND voucher_type = 'journal' ORDER BY id DESC LIMIT 1",
            [$companyId]
        );
        $num = $lastVal ? ((int)preg_replace('/\D/', '', $lastVal) + 1) : 1;
        $jvDocNo = 'JV' . date('Y') . str_pad($num, 5, '0', STR_PAD_LEFT);

        // 1. Insert Voucher Header
        $voucherId = $this->db->insert(
            "INSERT INTO vouchers (company_id, branch_id, doc_no, doc_date, voucher_type, narration, total_amount, status)
             VALUES (?, ?, ?, CURDATE(), 'journal', ?, ?, 'confirmed')",
            [
                $companyId, $branchId, $jvDocNo,
                "Automated Sales Posting for Invoice #{$docNo}",
                $netAmt
            ]
        );

        // 2. Insert Voucher Details
        foreach ($postings as $post) {
            $this->db->execute(
                "INSERT INTO voucher_detail (voucher_id, ledger_id, debit, credit, narration)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $voucherId, $post['ledger_id'],
                    $post['debit'], $post['credit'],
                    $post['narration']
                ]
            );
        }

        return $jvDocNo;
    }
}
