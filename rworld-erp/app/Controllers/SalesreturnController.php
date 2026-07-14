<?php
/**
 * QuickBill POS - Sales Returns Controller
 */

class SalesreturnController extends Controller {

    public function index() {
        $db = $this->db;
        $companyId = $this->getCompanyId();
        $branchId = $this->getBranchId();

        $returns = $db->fetchAll(
            "SELECT r.*, COALESCE(r.customer_name, 'Walk-in Customer') AS display_name
             FROM sales_returns r
             WHERE r.company_id = ? AND r.branch_id = ?
             ORDER BY r.id DESC",
            [$companyId, $branchId]
        );

        $this->render('salesreturn/index', [
            'pageTitle' => 'Sales Returns List',
            'returns' => $returns
        ]);
    }

    public function create() {
        $db = $this->db;
        $companyId = $this->getCompanyId();

        // Get confirmed sales to link returns to
        $sales = $db->fetchAll(
            "SELECT id, doc_no, doc_date, customer_name, net_amount 
             FROM sales_header 
             WHERE company_id = ? AND status = 'confirmed'
             ORDER BY id DESC",
            [$companyId]
        );

        // Get return reason codes
        $reasons = $db->fetchAll(
            "SELECT * FROM reason_codes WHERE company_id = ? AND module = 'sales_return'",
            [$companyId]
        );

        $this->render('salesreturn/create', [
            'pageTitle' => 'Process Sales Return',
            'sales' => $sales,
            'reasons' => $reasons
        ]);
    }

    public function getSaleDetails() {
        $saleId = $this->get('sale_id');
        if (!$saleId) {
            $this->json(['success' => false, 'message' => 'Sale ID is required'], 400);
        }

        $db = $this->db;
        $sale = $db->fetchOne(
            "SELECT * FROM sales_header WHERE id = ?",
            [$saleId]
        );

        if (!$sale) {
            $this->json(['success' => false, 'message' => 'Invoice not found'], 404);
        }

        $items = $db->fetchAll(
            "SELECT sd.*, i.stock_no, i.description 
             FROM sales_detail sd
             JOIN items i ON i.id = sd.item_id
             WHERE sd.header_id = ?",
            [$saleId]
        );

        $this->json([
            'success' => true,
            'sale' => $sale,
            'items' => $items
        ]);
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('salesreturn');
        }

        $this->validateCsrf();

        $db = $this->db;
        $companyId = $this->getCompanyId();
        $branchId = $this->getBranchId();

        $saleId = (int)$this->post('orig_sale_id');
        $reasonId = (int)$this->post('reason_id');
        $remarks = $this->post('remarks');
        
        $quantities = $_POST['qty'] ?? []; // Array of returned item quantities keyed by detail_id

        if (empty($quantities)) {
            $this->flash('error', 'No items selected for return.');
            $this->redirect('salesreturn/create');
        }

        // Fetch original sale info
        $sale = $db->fetchOne("SELECT * FROM sales_header WHERE id = ?", [$saleId]);
        if (!$sale) {
            $this->flash('error', 'Original invoice not found.');
            $this->redirect('salesreturn/create');
        }

        $db->beginTransaction();
        try {
            // Generate return doc no
            $docNo = 'SR-' . str_pad(time() % 1000000, 6, '0', STR_PAD_LEFT);

            // Insert Sales Return Header
            $returnId = $db->insert(
                "INSERT INTO sales_returns (company_id, branch_id, doc_no, doc_date, customer_id, customer_name, orig_sale_id, orig_doc_no, reason_id, gross_amount, total_tax, net_amount, status, remarks)
                 VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 0, 0, 0, 'confirmed', ?)",
                [
                    $companyId, $branchId, $docNo, 
                    $sale['customer_id'] ?? null, 
                    $sale['customer_name'] ?? 'Walk-in Customer',
                    $saleId, $sale['doc_no'], $reasonId ?: null, $remarks
                ]
            );

            $grossAmt = 0;
            $totalTax = 0;

            foreach ($quantities as $detailId => $retQty) {
                $retQty = (float)$retQty;
                if ($retQty <= 0) continue;

                // Original detail item
                $sd = $db->fetchOne("SELECT * FROM sales_detail WHERE id = ?", [$detailId]);
                if (!$sd) continue;

                $lineVal = $sd['rate'] * $retQty;
                // Pro-rate discount if any
                $lineDiscAmt = ($sd['qty'] > 0) ? ($sd['disc_amount'] / $sd['qty']) * $retQty : 0;
                $lineNetVal = $lineVal - $lineDiscAmt;

                // Tax pro-ration
                $t1 = ($sd['qty'] > 0) ? ($sd['t1_amount'] / $sd['qty']) * $retQty : 0;
                $t2 = ($sd['qty'] > 0) ? ($sd['t2_amount'] / $sd['qty']) * $retQty : 0;
                $t3 = ($sd['qty'] > 0) ? ($sd['t3_amount'] / $sd['qty']) * $retQty : 0;
                $t4 = ($sd['qty'] > 0) ? ($sd['t4_amount'] / $sd['qty']) * $retQty : 0;
                $t5 = ($sd['qty'] > 0) ? ($sd['t5_amount'] / $sd['qty']) * $retQty : 0;
                $lineTax = $t1 + $t2 + $t3 + $t4 + $t5;

                $grossAmt += $lineNetVal;
                $totalTax += $lineTax;

                // Insert Sales Return Detail
                $db->execute(
                    "INSERT INTO sales_return_detail (header_id, item_id, qty, unit_id, rate, value, disc_amount, net_value, t1_amount, t2_amount, t3_amount, t4_amount, t5_amount, orig_detail_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $returnId, $sd['item_id'], $retQty, $sd['unit_id'], $sd['rate'], 
                        $lineVal, $lineDiscAmt, $lineNetVal, $t1, $t2, $t3, $t4, $t5, $detailId
                    ]
                );

                // Return stock ledger adjustment
                $db->execute(
                    "INSERT INTO stock_ledger (company_id, branch_id, item_id, txn_date, txn_type, doc_no, qty_in, qty_out, rate, value, balance_qty)
                     VALUES (?, ?, ?, CURDATE(), 'SALES_RETURN', ?, ?, 0.0000, ?, ?, COALESCE((SELECT SUM(qty_in - qty_out) FROM stock_ledger sl WHERE sl.item_id = ?), 0) + ?)",
                    [
                        $companyId, $branchId, $sd['item_id'], $docNo, $retQty, 
                        $sd['rate'], $lineNetVal, $sd['item_id'], $retQty
                    ]
                );
            }

            $netAmt = $grossAmt + $totalTax;

            // Update Return Header with correct totals
            $db->execute(
                "UPDATE sales_returns SET gross_amount = ?, total_tax = ?, net_amount = ? WHERE id = ?",
                [$grossAmt, $totalTax, $netAmt, $returnId]
            );

            $db->commit();
            $this->flash('success', "Sales Return processed successfully. Return Document: $docNo");
        } catch (Exception $e) {
            $db->rollback();
            $this->flash('error', 'Error processing sales return: ' . $e->getMessage());
        }

        $this->redirect('salesreturn');
    }
}
