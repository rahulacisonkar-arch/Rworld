<?php
/**
 * QuickBill POS - Shift & Cash Drawer Till Controller
 * Milestone 2: Cashier register counting control
 */

class ShiftController extends Controller {

    /**
     * Start register shift (Cashier count-in)
     */
    public function open() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();
        $uid = $_SESSION['user_id'] ?? 0;

        // Check for existing open shift session
        $openShift = $this->db->fetchOne(
            "SELECT * FROM shift_closure WHERE company_id = ? AND branch_id = ? AND user_id = ? AND closed_at IS NULL",
            [$cid, $bid, $uid]
        );

        if ($openShift) {
            $_SESSION['shift_id'] = $openShift['id'];
            $this->redirect('sales/create');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();
            $openingCash = (float)$this->post('opening_cash', 0);

            $this->db->execute(
                "INSERT INTO shift_closure (company_id, branch_id, user_id, shift_date, opened_at, opening_cash, total_sales, total_returns, total_cash, total_card, total_upi, total_credit, variance)
                 VALUES (?, ?, ?, CURDATE(), NOW(), ?, 0, 0, 0, 0, 0, 0, 0)",
                [$cid, $bid, $uid, $openingCash]
            );

            $_SESSION['shift_id'] = $this->db->lastInsertId();
            $this->flash('success', 'Cash register shift opened successfully. Start checkout!');
            $this->redirect('sales/create');
        }

        $this->render('shift/open', [
            'pageTitle' => 'Open Register Drawer Shift',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Open Shift', 'url' => '#']
            ]
        ]);
    }

    /**
     * Close register shift (Cashier count-out)
     */
    public function close() {
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();
        $uid = $_SESSION['user_id'] ?? 0;

        $shift = $this->db->fetchOne(
            "SELECT * FROM shift_closure WHERE company_id = ? AND branch_id = ? AND user_id = ? AND closed_at IS NULL",
            [$cid, $bid, $uid]
        );

        if (!$shift) {
            $this->flash('warning', 'No active open shift found to close.');
            $this->redirect('dashboard');
        }

        // Calculate expected sales totals during this active shift
        $openedAt = $shift['opened_at'];

        // 1. Expected Cash Sales
        $expectedCashSales = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM payments_received 
             WHERE company_id = ? AND branch_id = ? AND created_at >= ? AND created_by = ? AND payment_mode_id = 1",
            [$cid, $bid, $openedAt, $uid]
        );

        // 2. Expected Card Sales (Debit & Credit Card)
        $expectedCardSales = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM payments_received 
             WHERE company_id = ? AND branch_id = ? AND created_at >= ? AND created_by = ? AND payment_mode_id IN (2, 3)",
            [$cid, $bid, $openedAt, $uid]
        );

        // 3. Expected UPI / Digital Payments
        $expectedUpiSales = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM payments_received 
             WHERE company_id = ? AND branch_id = ? AND created_at >= ? AND created_by = ? AND payment_mode_id = 4",
            [$cid, $bid, $openedAt, $uid]
        );

        // 4. Expected Total Credit (Due Sales)
        $expectedCreditSales = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(net_amount - paid_amount), 0) FROM sales_header 
             WHERE company_id = ? AND branch_id = ? AND created_at >= ? AND created_by = ?",
            [$cid, $bid, $openedAt, $uid]
        );

        // Total sales net amount
        $totalSales = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(net_amount), 0) FROM sales_header 
             WHERE company_id = ? AND branch_id = ? AND created_at >= ? AND created_by = ?",
            [$cid, $bid, $openedAt, $uid]
        );

        // Expected cash in drawer = Opening Cash Bank + Cash Sales
        $expectedCashDrawer = (float)$shift['opening_cash'] + $expectedCashSales;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $actualCash = (float)$this->post('actual_cash', 0);
            $actualCard = (float)$this->post('actual_card', 0);
            $actualUpi  = (float)$this->post('actual_upi', 0);
            $notes      = $this->post('notes', '');

            // Calculate drawer variance
            $variance = $actualCash - $expectedCashDrawer;

            $this->db->execute(
                "UPDATE shift_closure 
                 SET closed_at = NOW(),
                     closing_cash = ?,
                     total_sales = ?,
                     total_cash = ?,
                     total_card = ?,
                     total_upi = ?,
                     total_credit = ?,
                     variance = ?,
                     notes = ?
                 WHERE id = ?",
                [
                    $actualCash,
                    $totalSales,
                    $expectedCashSales,
                    $expectedCardSales,
                    $expectedUpiSales,
                    $expectedCreditSales,
                    $variance,
                    $notes,
                    $shift['id']
                ]
            );

            unset($_SESSION['shift_id']);
            $this->flash('success', "Shift closed. Cash Short/Over Variance: $" . number_format($variance, 2));
            $this->redirect('dashboard');
        }

        $this->render('shift/close', [
            'pageTitle' => 'Reconcile & Close Cash Shift',
            'shift' => $shift,
            'expectedCashSales' => $expectedCashSales,
            'expectedCardSales' => $expectedCardSales,
            'expectedUpiSales' => $expectedUpiSales,
            'expectedCreditSales' => $expectedCreditSales,
            'expectedCashDrawer' => $expectedCashDrawer,
            'totalSales' => $totalSales,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Close Shift', 'url' => '#']
            ]
        ]);
    }
}
