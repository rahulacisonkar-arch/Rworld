<?php
/**
 * QuickBill POS - Multi-Tier Configurable Tax Engine
 *
 * Replicates the T1-T5 tax calculation logic from the original .NET application.
 * Supports: percentage tax, fixed amount, cascading (applied-on T1/T2/T3),
 * slab ranges, inclusive/exclusive tax, and GST CGST/SGST/IGST split.
 *
 * Compatible: PHP 7.0+
 */

class TaxEngine {

    // applied_on constants (mirrors T1on values from original grid)
    const ON_NET_VALUE  = 0;  // Apply on line net value (after discount)
    const ON_AFTER_T1   = 1;  // Apply on base + T1
    const ON_AFTER_T2   = 2;  // Apply on base + T1 + T2
    const ON_AFTER_T3   = 3;  // Apply on base + T1 + T2 + T3
    const ON_AFTER_T4   = 4;  // Apply on base + T1 + T2 + T3 + T4

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Load all tax components for a given tax_type_id
     * Returns ordered array of component rows
     */
    public function getComponents($taxTypeId) {
        return $this->db->fetchAll(
            "SELECT tc.*, tt.is_inclusive, tt.name AS tax_type_name
             FROM tax_components tc
             JOIN tax_types tt ON tt.id = tc.tax_type_id
             WHERE tc.tax_type_id = ? AND tc.is_active = 1
             ORDER BY tc.component_no ASC",
            [$taxTypeId]
        );
    }

    /**
     * Calculate taxes for a single line item.
     *
     * @param float $netValue     Line net value (after discount, before tax)
     * @param int   $taxTypeId    Tax type ID from tax_types table
     * @param bool  $isInclusive  Override inclusive flag (or read from tax_type)
     * @param float $qty          For slab-based calculation (some slabs are per-unit)
     *
     * @return array {
     *    t1_amount, t2_amount, t3_amount, t4_amount, t5_amount,
     *    total_tax, taxable_value, total_tax_perc,
     *    t1_contribution .. t5_contribution,
     *    t1_applied_on .. t5_applied_on,
     *    t1_is_rate .. t5_is_rate,
     *    t1_rate_or_amt .. t5_rate_or_amt,
     *    components (array of component details),
     *    is_inclusive,
     *    grand_total (netValue + totalTax for exclusive, or just netValue for inclusive)
     * }
     */
    public function calculate($netValue, $taxTypeId, $isInclusive = null, $qty = 1) {
        $result = $this->emptyResult();
        $result['taxable_value'] = $netValue;

        if (!$taxTypeId) {
            $result['grand_total'] = $netValue;
            return $result;
        }

        $components = $this->getComponents($taxTypeId);

        if (empty($components)) {
            $result['grand_total'] = $netValue;
            return $result;
        }

        // Use tax_type.is_inclusive if not explicitly provided
        $inclusive = $isInclusive ?? (int)$components[0]['is_inclusive'];
        $result['is_inclusive'] = $inclusive;

        // For inclusive tax: back-calculate the base value
        if ($inclusive) {
            $totalRate = $this->getTotalRate($components);
            if ($totalRate > 0) {
                $base = ($netValue * 100) / (100 + $totalRate);
                $netValue = $base;
                $result['taxable_value'] = round($base, 4);
            }
        }

        // Calculate each component
        $base      = $netValue;
        $cumulative = 0;

        foreach ($components as $i => $comp) {
            $n = $comp['component_no'];  // 1-5

            // Determine the base for this component based on applied_on
            $taxBase = $this->getTaxBase($base, $comp['applied_on'], $result);

            // Check slab
            if ($comp['slab_enabled'] && $comp['slab_end'] > 0) {
                if ($taxBase < $comp['slab_start'] || $taxBase > $comp['slab_end']) {
                    // Outside slab — zero tax
                    $amount = 0;
                } else {
                    $amount = $this->computeAmount($taxBase, $comp, $qty);
                }
            } else {
                $amount = $this->computeAmount($taxBase, $comp, $qty);
            }

            $amount = round($amount, 4);

            $result["t{$n}_amount"]      = $amount;
            $result["t{$n}_contribution"] = $amount;
            $result["t{$n}_applied_on"]  = (int)$comp['applied_on'];
            $result["t{$n}_is_rate"]     = (int)$comp['is_rate'];
            $result["t{$n}_rate_or_amt"] = (float)$comp['rate_or_amt'];
            $result["t{$n}_start_value"] = (float)$comp['slab_start'];
            $result["t{$n}_end_value"]   = (float)$comp['slab_end'];

            $cumulative += $amount;

            // Store component detail for breakdown display
            $result['components'][] = [
                'component_no' => $n,
                'name'         => $comp['name'],
                'short_name'   => $comp['short_name'],
                'rate'         => $comp['rate_or_amt'],
                'is_rate'      => $comp['is_rate'],
                'applied_on'   => $comp['applied_on'],
                'amount'       => $amount,
            ];
        }

        $result['total_tax']    = round($cumulative, 4);
        $result['tax_comp_count'] = count($components);

        // Total tax percentage (for display)
        if ($netValue > 0) {
            $result['total_tax_perc'] = round(($result['total_tax'] / $netValue) * 100, 4);
        }

        if ($inclusive) {
            $result['grand_total'] = $result['taxable_value'] + $result['total_tax'];
            // Adjust to original netValue (rounding)
            $diff = round($netValue - $result['taxable_value'], 4);
        } else {
            $result['grand_total'] = $netValue + $result['total_tax'];
        }

        return $result;
    }

    /**
     * Calculate taxes for the full invoice (header-level).
     * Processes all line items, aggregates by component.
     */
    public function calculateInvoice(array $lines, $billDiscPercent = 0, $addonBefTax = 0, $dednBefTax = 0, $addonAftTax = 0, $dednAftTax = 0) {

        $totals = [
            'gross_amount'    => 0,
            'bill_disc_amount'=> 0,
            'addon_bef_tax'   => $addonBefTax,
            'dedn_bef_tax'    => $dednBefTax,
            'taxable_amount'  => 0,
            'tax1_amount'     => 0,
            'tax2_amount'     => 0,
            'tax3_amount'     => 0,
            'tax4_amount'     => 0,
            'tax5_amount'     => 0,
            'total_tax'       => 0,
            'addon_aft_tax'   => $addonAftTax,
            'dedn_aft_tax'    => $dednAftTax,
            'round_off'       => 0,
            'net_amount'      => 0,
            'lines'           => [],
        ];

        foreach ($lines as &$line) {
            $gross      = (float)$line['value'];
            $discAmt    = (float)($line['disc_amount'] ?? 0);
            $netVal     = $gross - $discAmt;

            $taxResult  = $this->calculate($netVal, $line['tax_type_id'] ?? null, null, $line['qty'] ?? 1);

            $line['net_value']       = $netVal;
            $line['t1_amount']       = $taxResult['t1_amount'];
            $line['t2_amount']       = $taxResult['t2_amount'];
            $line['t3_amount']       = $taxResult['t3_amount'];
            $line['t4_amount']       = $taxResult['t4_amount'];
            $line['t5_amount']       = $taxResult['t5_amount'];
            $line['total_tax']       = $taxResult['total_tax'];
            $line['total_tax_perc']  = $taxResult['total_tax_perc'];
            $line['tax_components']  = $taxResult['components'];

            $totals['gross_amount'] += $gross;
        }
        unset($line);

        // Bill discount
        if ($billDiscPercent > 0) {
            $totals['bill_disc_amount'] = round($totals['gross_amount'] * $billDiscPercent / 100, 4);
        }

        // Allocate bill discount proportionally to lines and recalculate taxes
        if ($totals['bill_disc_amount'] > 0) {
            foreach ($lines as &$line) {
                $share = ($totals['gross_amount'] > 0)
                    ? ($line['value'] / $totals['gross_amount']) * $totals['bill_disc_amount']
                    : 0;
                $line['bill_disc_alloc'] = round($share, 4);
                $adjustedNet = $line['net_value'] - $line['bill_disc_alloc'];

                $taxResult = $this->calculate($adjustedNet, $line['tax_type_id'] ?? null);
                $line['t1_amount'] = $taxResult['t1_amount'];
                $line['t2_amount'] = $taxResult['t2_amount'];
                $line['t3_amount'] = $taxResult['t3_amount'];
                $line['t4_amount'] = $taxResult['t4_amount'];
                $line['t5_amount'] = $taxResult['t5_amount'];
                $line['total_tax'] = $taxResult['total_tax'];
            }
            unset($line);
        }

        // Sum up after discount
        foreach ($lines as $line) {
            $netAfterDisc = $line['net_value'] - ($line['bill_disc_alloc'] ?? 0);
            $totals['taxable_amount'] += $netAfterDisc;
            $totals['tax1_amount']    += $line['t1_amount'];
            $totals['tax2_amount']    += $line['t2_amount'];
            $totals['tax3_amount']    += $line['t3_amount'];
            $totals['tax4_amount']    += $line['t4_amount'];
            $totals['tax5_amount']    += $line['t5_amount'];
            $totals['total_tax']      += $line['total_tax'];
        }

        $totals['taxable_amount'] = round($totals['taxable_amount'] + $addonBefTax - $dednBefTax, 4);
        $totals['total_tax']      = round($totals['total_tax'], 4);

        $beforeRound = $totals['taxable_amount']
                     + $totals['total_tax']
                     + $addonAftTax
                     - $dednAftTax;

        $rounded  = round($beforeRound);
        $totals['round_off']  = round($rounded - $beforeRound, 4);
        $totals['net_amount'] = $rounded;
        $totals['lines']      = $lines;

        return $totals;
    }

    // ── Private Helpers ───────────────────────────────────────────────────

    private function computeAmount($base, $comp, $qty) {
        if ($comp['is_rate']) {
            return $base * (float)$comp['rate_or_amt'] / 100;
        } else {
            // Fixed amount per unit or flat
            return (float)$comp['rate_or_amt'] * $qty;
        }
    }

    private function getTaxBase($base, $appliedOn, $result) {
        switch ((int)$appliedOn) {
            case self::ON_AFTER_T1:
                return $base + $result['t1_amount'];
            case self::ON_AFTER_T2:
                return $base + $result['t1_amount'] + $result['t2_amount'];
            case self::ON_AFTER_T3:
                return $base + $result['t1_amount'] + $result['t2_amount'] + $result['t3_amount'];
            case self::ON_AFTER_T4:
                return $base + $result['t1_amount'] + $result['t2_amount'] + $result['t3_amount'] + $result['t4_amount'];
            default:
                return $base;
        }
    }

    private function getTotalRate($components) {
        $total = 0;
        foreach ($components as $comp) {
            if ($comp['is_rate'] && (int)$comp['applied_on'] === 0) {
                $total += (float)$comp['rate_or_amt'];
            }
        }
        return $total;
    }

    private function emptyResult() {
        return [
            't1_amount'      => 0.0, 't1_contribution' => 0.0, 't1_applied_on' => 0, 't1_is_rate' => 1, 't1_rate_or_amt' => 0.0, 't1_start_value' => 0.0, 't1_end_value' => 0.0,
            't2_amount'      => 0.0, 't2_contribution' => 0.0, 't2_applied_on' => 0, 't2_is_rate' => 1, 't2_rate_or_amt' => 0.0, 't2_start_value' => 0.0, 't2_end_value' => 0.0,
            't3_amount'      => 0.0, 't3_contribution' => 0.0, 't3_applied_on' => 0, 't3_is_rate' => 1, 't3_rate_or_amt' => 0.0, 't3_start_value' => 0.0, 't3_end_value' => 0.0,
            't4_amount'      => 0.0, 't4_contribution' => 0.0, 't4_applied_on' => 0, 't4_is_rate' => 1, 't4_rate_or_amt' => 0.0, 't4_start_value' => 0.0, 't4_end_value' => 0.0,
            't5_amount'      => 0.0, 't5_contribution' => 0.0, 't5_applied_on' => 0, 't5_is_rate' => 1, 't5_rate_or_amt' => 0.0, 't5_start_value' => 0.0, 't5_end_value' => 0.0,
            'total_tax'      => 0.0,
            'total_tax_perc' => 0.0,
            'tax_comp_count' => 0,
            'is_inclusive'   => 0,
            'taxable_value'  => 0.0,
            'grand_total'    => 0.0,
            'components'     => [],
        ];
    }

    /**
     * Determine if sale is intra-state or inter-state (for CGST/SGST vs IGST)
     */
    public static function getDestTaxType($companyStateCode, $customerStateCode) {
        if (empty($customerStateCode) || $companyStateCode === $customerStateCode) {
            return 'CGST_SGST';
        }
        return 'IGST';
    }

    /**
     * Get GST breakdown for a tax type given dest type
     * Returns the appropriate tax_type_id for IGST if inter-state
     */
    public function resolveGstTaxType($taxTypeId, $destType) {
        if ($destType === 'IGST') {
            // Look up the IGST equivalent
            $type = $this->db->fetchOne(
                "SELECT code FROM tax_types WHERE id = ?", [$taxTypeId]
            );
            if ($type) {
                $code = $type['code'];
                // Map GST-X to IGST-X
                $igstCode = str_replace('GST-', 'IGST-', preg_replace('/-INC$/', '', $code));
                $igstType = $this->db->fetchOne(
                    "SELECT id FROM tax_types WHERE code = ? AND company_id = ?",
                    [$igstCode, $_SESSION['company_id'] ?? 1]
                );
                if ($igstType) return $igstType['id'];
            }
        }
        return $taxTypeId;
    }
}
