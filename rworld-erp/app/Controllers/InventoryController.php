<?php
/**
 * QuickBill POS - Inventory Controller
 * Features: Robust Bulk Upload aligning with the multi-store fabric inventory format
 */

class InventoryController extends Controller {

    public function bulkupload() {
        $this->render('inventory/bulkupload', [
            'pageTitle' => 'Bulk Inventory Upload',
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Inventory', 'url' => '#'],
                ['label' => 'Bulk Upload', 'url' => '#']
            ]
        ]);
    }

    public function processUpload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('inventory/bulkupload');
        }

        $this->validateCsrf();

        if (empty($_FILES['csv_file']['tmp_name'])) {
            $this->flash('error', 'Please choose a CSV file to upload.');
            $this->redirect('inventory/bulkupload');
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) {
            $this->flash('error', 'Failed to open the uploaded file.');
            $this->redirect('inventory/bulkupload');
        }

        $db = $this->db;
        $companyId = $this->getCompanyId();
        $defaultBranchId = $this->getBranchId();

        // Read header row
        $header = fgetcsv($handle);
        
        // Define default column index mappings matching user screenshot
        $colStore = 0;
        $colStock = 1;
        $colDesc = 2;
        $colBatch = 3;
        $colCategory = 4;
        $colCostPrice = 5;
        $colQty = 6;

        // Try dynamically mapping columns based on header titles if header row exists
        if ($header) {
            foreach ($header as $idx => $cell) {
                $cellClean = strtolower(trim($cell));
                if (strpos($cellClean, 'store') !== false) {
                    $colStore = $idx;
                } elseif (strpos($cellClean, 'stock') !== false || strpos($cellClean, 'item code') !== false) {
                    $colStock = $idx;
                } elseif (strpos($cellClean, 'description') !== false || strpos($cellClean, 'item name') !== false) {
                    $colDesc = $idx;
                } elseif (strpos($cellClean, 'batch') !== false) {
                    $colBatch = $idx;
                } elseif (strpos($cellClean, 'category') !== false) {
                    $colCategory = $idx;
                } elseif (strpos($cellClean, 'cost') !== false || strpos($cellClean, 'price') !== false) {
                    $colCostPrice = $idx;
                } elseif (strpos($cellClean, 'qty') !== false || strpos($cellClean, 'quantity') !== false || strpos($cellClean, 'closing bal') !== false) {
                    $colQty = $idx;
                }
            }
        }

        $successRows = 0;
        $errorRows = 0;
        $errors = [];

        $db->beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 2) continue;

                $storeName = trim($row[$colStore] ?? '');
                $stockNo = trim($row[$colStock] ?? '');
                $description = trim($row[$colDesc] ?? '');
                $batchNo = trim($row[$colBatch] ?? 'NO BATCH');
                $categoryName = trim($row[$colCategory] ?? '');
                $costPrice = (float)($row[$colCostPrice] ?? 0);
                $qty = (float)($row[$colQty] ?? 0);

                if (empty($stockNo) || empty($description)) {
                    $errorRows++;
                    $errors[] = "Row skipped: Stock Number and Description are required.";
                    continue;
                }

                // 1. Resolve Branch (Store Name)
                $rowBranchId = $defaultBranchId;
                if (!empty($storeName)) {
                    $branch = $db->fetchOne("SELECT id FROM branches WHERE company_id = ? AND (name = ? OR code = ?)", [$companyId, $storeName, $storeName]);
                    if ($branch) {
                        $rowBranchId = $branch['id'];
                    } else {
                        // Dynamically create the branch if it doesn't exist
                        $cleanCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $storeName));
                        if (strlen($cleanCode) > 10) {
                            $cleanCode = substr($cleanCode, 0, 10);
                        }
                        if (empty($cleanCode)) {
                            $cleanCode = 'BR' . rand(100, 999);
                        }
                        
                        $rowBranchId = $db->insert(
                            "INSERT INTO branches (company_id, code, name, is_active, is_warehouse, is_head_office)
                             VALUES (?, ?, ?, 1, 0, 0)",
                            [$companyId, $cleanCode, $storeName]
                        );
                    }
                }

                // 2. Resolve Category
                $categoryId = null;
                if (!empty($categoryName)) {
                    $category = $db->fetchOne("SELECT id FROM categories WHERE company_id = ? AND (code = ? OR name = ?)", [$companyId, $categoryName, $categoryName]);
                    if ($category) {
                        $categoryId = $category['id'];
                    } else {
                        // Dynamically create category
                        $categoryId = $db->insert(
                            "INSERT INTO categories (company_id, code, name, level_no, sort_order, is_active)
                             VALUES (?, ?, ?, 1, 0, 1)",
                            [$companyId, $categoryName, $categoryName]
                        );
                    }
                }

                // 3. Resolve / Insert Item
                $itemId = $db->fetchColumn("SELECT id FROM items WHERE company_id = ? AND stock_no = ?", [$companyId, $stockNo]);
                $hasBatch = ($batchNo && strtoupper($batchNo) !== 'NO BATCH') ? 1 : 0;
                
                // We set base selling price (price1) to 0.00 so cost price is not shown in POS
                $sellingPrice = 0.00;

                if (!$itemId) {
                    $itemId = $db->insert(
                        "INSERT INTO items (company_id, stock_no, description, cost_price, price1, price2, price3, price4, price5, cat1_id, has_batch, maintain_inventory, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)",
                        [$companyId, $stockNo, $description, $costPrice, $sellingPrice, $sellingPrice, $sellingPrice, $sellingPrice, $sellingPrice, $categoryId, $hasBatch]
                    );
                } else {
                    $db->execute(
                        "UPDATE items SET description = ?, cost_price = ?, price1 = ?, cat1_id = ?, has_batch = ? WHERE id = ?",
                        [$description, $costPrice, $sellingPrice, $categoryId, $hasBatch, $itemId]
                    );
                }

                // 4. Batch Master Entry (if batch_no is provided)
                if ($hasBatch) {
                    $existsBatch = $db->fetchOne("SELECT id FROM batch_master WHERE item_id = ? AND branch_id = ? AND batch_no = ?", [$itemId, $rowBranchId, $batchNo]);
                    if (!$existsBatch) {
                        $db->execute(
                            "INSERT INTO batch_master (item_id, branch_id, batch_no, cost_price, mrp, qty_in, qty_out, is_active)
                             VALUES (?, ?, ?, ?, ?, ?, 0.0000, 1)",
                            [$itemId, $rowBranchId, $batchNo, $costPrice, $sellingPrice, $qty]
                        );
                    } else {
                        $db->execute(
                            "UPDATE batch_master SET qty_in = qty_in + ?, cost_price = ? WHERE id = ?",
                            [$qty, $costPrice, $existsBatch['id']]
                        );
                    }
                }

                // 5. Stock Ledger Entry
                if ($qty > 0) {
                    $db->execute(
                        "INSERT INTO stock_ledger (company_id, branch_id, item_id, txn_date, txn_type, doc_no, qty_in, qty_out, rate, value, balance_qty)
                         VALUES (?, ?, ?, CURDATE(), 'BULK_UPLOAD', 'BULK', ?, 0.0000, ?, ?, COALESCE((SELECT SUM(qty_in - qty_out) FROM stock_ledger sl WHERE sl.item_id = ? AND sl.branch_id = ?), 0) + ?)",
                        [
                            $companyId, $rowBranchId, $itemId, $qty, 
                            $costPrice, ($qty * $costPrice), $itemId, $rowBranchId, $qty
                        ]
                    );
                }

                $successRows++;
            }
            fclose($handle);

            // Log import activity
            $db->execute(
                "INSERT INTO import_logs (company_id, branch_id, filename, import_type, total_rows, success_rows, error_rows, error_details, status)
                 VALUES (?, ?, ?, 'ITEMS', ?, ?, ?, ?, 'completed')",
                [
                    $companyId, $defaultBranchId, $_FILES['csv_file']['name'], 
                    ($successRows + $errorRows), $successRows, $errorRows, 
                    json_encode($errors)
                ]
            );

            $db->commit();
            $this->flash('success', "Import completed. $successRows item(s) imported/updated successfully. $errorRows row(s) failed.");
        } catch (Throwable $e) {
            $db->rollback();
            fclose($handle);
            $this->flash('error', "Database error during bulk upload: " . $e->getMessage());
        }

        $this->redirect('inventory/bulkupload');
    }
}
