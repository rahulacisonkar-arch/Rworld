<?php
/**
 * QuickBill POS - Reports Controller
 */
class ReportController extends Controller {

    public function sales() {
        $db = $this->db;
        $companyId = $this->getCompanyId();
        $branchId = $this->getBranchId();

        $startDate = $this->get('start_date', date('Y-m-01'));
        $endDate = $this->get('end_date', date('Y-m-d'));

        $invoices = $db->fetchAll(
            "SELECT s.*, COALESCE(s.customer_name, 'Walk-in Customer') AS display_name
             FROM sales_header s
             WHERE s.company_id = ? AND s.branch_id = ?
               AND s.doc_date BETWEEN ? AND ?
               AND s.status != 'cancelled'
             ORDER BY s.doc_date DESC, s.id DESC",
            [$companyId, $branchId, $startDate, $endDate]
        );

        $summary = $db->fetchOne(
            "SELECT 
                COUNT(*) AS total_transactions,
                COALESCE(SUM(gross_amount), 0) AS total_gross,
                COALESCE(SUM(total_tax), 0) AS total_tax,
                COALESCE(SUM(net_amount), 0) AS total_net,
                COALESCE(SUM(balance_due), 0) AS total_due
             FROM sales_header
             WHERE company_id = ? AND branch_id = ?
               AND doc_date BETWEEN ? AND ?
               AND status != 'cancelled'",
            [$companyId, $branchId, $startDate, $endDate]
        );

        $this->render('reports/sales', [
            'pageTitle' => 'Sales Report',
            'invoices' => $invoices,
            'summary' => $summary,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Reports', 'url' => '#'],
                ['label' => 'Sales Report', 'url' => '#']
            ]
        ]);
    }

    public function purchase() {
        $db = $this->db;
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        $startDate = $this->get('start_date', date('Y-m-01'));
        $endDate = $this->get('end_date', date('Y-m-d'));

        $purchases = $db->fetchAll(
            "SELECT ph.*, COALESCE(ph.supplier_name, s.name, 'Unknown') AS supplier_name
             FROM purchase_header ph
             LEFT JOIN suppliers s ON s.id = ph.supplier_id
             WHERE ph.company_id = ? AND ph.branch_id = ?
               AND ph.doc_date BETWEEN ? AND ?
               AND ph.status != 'cancelled'
             ORDER BY ph.doc_date DESC",
            [$cid, $bid, $startDate, $endDate]
        );

        $summary = $db->fetchOne(
            "SELECT COUNT(*) AS total_tx, COALESCE(SUM(net_amount),0) AS total_net
             FROM purchase_header
             WHERE company_id=? AND branch_id=? AND doc_date BETWEEN ? AND ? AND status!='cancelled'",
            [$cid, $bid, $startDate, $endDate]
        ) ?: ['total_tx'=>0,'total_net'=>0];

        $this->render('reports/purchase', [
            'pageTitle' => 'Purchase Report',
            'purchases' => $purchases,
            'summary' => $summary,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Reports', 'url' => '#'],
                ['label' => 'Purchase Report', 'url' => '#']
            ]
        ]);
    }

    public function stock() {
        $db = $this->db;
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();
        $page = max(1, (int)$this->get('page', 1));

        $sql = "SELECT i.stock_no, i.description, COALESCE(SUM(sl.qty_in - sl.qty_out), 0) AS stock_qty
                FROM items i
                LEFT JOIN stock_ledger sl ON sl.item_id = i.id AND sl.branch_id = ?
                WHERE i.company_id = ?
                GROUP BY i.id ORDER BY i.description ASC";

        $paged = $this->paginate($sql, [$bid, $cid], $page);

        $this->render('reports/stock', [
            'pageTitle' => 'Stock Report',
            'stock' => $paged,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Reports', 'url' => '#'],
                ['label' => 'Stock Report', 'url' => '#']
            ]
        ]);
    }

    public function gst() {
        $db = $this->db;
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        $startDate = $this->get('start_date', date('Y-m-01'));
        $endDate = $this->get('end_date', date('Y-m-d'));

        // Group tax collections by tax type name
        $taxSummary = $db->fetchAll(
            "SELECT COALESCE(tt.name, 'Exempt / No Tax') AS tax_name,
                    COALESCE(SUM(sd.t1_amount + sd.t2_amount + sd.t3_amount + sd.t4_amount + sd.t5_amount), 0) AS total_collected
             FROM sales_detail sd
             LEFT JOIN sales_header sh ON sh.id = sd.header_id
             LEFT JOIN tax_types tt ON tt.id = sd.tax_type_id
             WHERE sh.company_id=? AND sh.branch_id=?
               AND sh.doc_date BETWEEN ? AND ?
               AND sh.status != 'cancelled'
             GROUP BY sd.tax_type_id
             ORDER BY tax_name ASC",
            [$cid, $bid, $startDate, $endDate]
        );

        $this->render('reports/gst', [
            'pageTitle' => 'Tax Reports',
            'taxSummary' => $taxSummary,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Reports', 'url' => '#'],
                ['label' => 'Tax Reports', 'url' => '#']
            ]
        ]);
    }

    public function pl() {
        $db = $this->db;
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();

        $startDate = $this->get('start_date', date('Y-m-01'));
        $endDate = $this->get('end_date', date('Y-m-d'));

        $sales = (float)$db->fetchColumn("SELECT COALESCE(SUM(net_amount - total_tax), 0) FROM sales_header WHERE company_id=? AND branch_id=? AND doc_date BETWEEN ? AND ? AND status!='cancelled'", [$cid, $bid, $startDate, $endDate]);
        $purchases = (float)$db->fetchColumn("SELECT COALESCE(SUM(net_amount - total_tax), 0) FROM purchase_header WHERE company_id=? AND branch_id=? AND doc_date BETWEEN ? AND ? AND status!='cancelled'", [$cid, $bid, $startDate, $endDate]);
        // expenses table uses 'amount' not 'net_amount'
        $expenses = (float)$db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE company_id=? AND branch_id=? AND doc_date BETWEEN ? AND ?", [$cid, $bid, $startDate, $endDate]);

        $this->render('reports/pl', [
            'pageTitle' => 'Profit & Loss Statement',
            'sales' => $sales,
            'purchases' => $purchases,
            'expenses' => $expenses,
            'netProfit' => ($sales - $purchases - $expenses),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Reports', 'url' => '#'],
                ['label' => 'P&L Statement', 'url' => '#']
            ]
        ]);
    }

    public function daybook() {
        $db = $this->db;
        $cid = $this->getCompanyId();
        $bid = $this->getBranchId();
        $date = $this->get('date', date('Y-m-d'));

        $sales     = $db->fetchAll("SELECT doc_no, 'Sale' as type, net_amount FROM sales_header WHERE company_id=? AND branch_id=? AND doc_date=? AND status!='cancelled'", [$cid, $bid, $date]) ?: [];
        $purchases = $db->fetchAll("SELECT doc_no, 'Purchase' as type, net_amount FROM purchase_header WHERE company_id=? AND branch_id=? AND doc_date=? AND status!='cancelled'", [$cid, $bid, $date]) ?: [];
        // expenses table uses 'amount' not 'net_amount'
        $expenses  = $db->fetchAll("SELECT doc_no, 'Expense' as type, amount AS net_amount FROM expenses WHERE company_id=? AND branch_id=? AND doc_date=?", [$cid, $bid, $date]) ?: [];

        $transactions = array_merge($sales, $purchases, $expenses);

        $this->render('reports/daybook', [
            'pageTitle' => 'Day Book',
            'transactions' => $transactions,
            'date' => $date,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Reports', 'url' => '#'],
                ['label' => 'Day Book', 'url' => '#']
            ]
        ]);
    }
}
