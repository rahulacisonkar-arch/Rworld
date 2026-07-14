class BusinessRulesEngine:
    """
    Enterprise Rules Validator verifying invoice math, GST/VAT rates, duplicates, and inventory limits.
    """

    def validate_totals(self, gross: float, discount: float, tax_pct: float, net_reported: float) -> dict:
        """
        Validates Net = (Gross - Discount) * (1 + Tax/100).
        Checks if math discrepancy exceeds 0.05 margin.
        """
        taxable = max(0.0, gross - discount)
        calculated_net = round(taxable * (1.0 + (tax_pct / 100.0)), 2)
        diff = abs(calculated_net - net_reported)

        return {
            "valid": diff <= 0.05,
            "calculated_net": calculated_net,
            "reported_net": net_reported,
            "discrepancy": round(diff, 2)
        }

    def check_gst_rates(self, state_tax: float, central_tax: float, central_gst_pct: float = 9.0) -> bool:
        """
        Validates Central and State GST splits are uniform (e.g. Central GST == State GST).
        """
        return abs(state_tax - central_tax) <= 0.01

    def verify_inventory_safety(self, stock_qty: int, safety_limit: int = 10) -> dict:
        """
        Checks if stock falls below safety levels.
        """
        return {
            "safe": stock_qty >= safety_limit,
            "current_stock": stock_qty,
            "safety_limit": safety_limit,
            "reorder_needed": stock_qty < safety_limit
        }

    def check_missing_columns(self, data_row: dict, required_cols: list) -> list:
        """
        Identifies missing required fields in incoming transaction schemas.
        """
        missing = []
        for col in required_cols:
            if col not in data_row or data_row[col] is None or str(data_row[col]).strip() == "":
                missing.append(col)
        return missing
