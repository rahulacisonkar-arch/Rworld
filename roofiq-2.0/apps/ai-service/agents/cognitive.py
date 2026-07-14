from typing import Dict, Any, List

class EstimatorAgent:
    def estimate_cost(self, area: float, materials: List[str]) -> Dict[str, Any]:
        return {"estimated_squares": area / 100, "estimated_material_cost": area * 4.5}

class InspectorAgent:
    def inspect_damages(self, yolo_detections: List[Dict[str, Any]]) -> Dict[str, Any]:
        return {"has_leak_signs": False, "recommended_maintenance": ["Seal chimney flashings"]}

class ProcurementAgent:
    def generate_bom(self, area: float) -> List[Dict[str, Any]]:
        return [{"material": "Asphalt Shingles", "quantity": round(area / 100 * 3), "unit": "Bundles"}]

class ProposalAgent:
    def format_proposal(self, cost_estimate: Dict[str, Any]) -> str:
        return f"RoofIQ Proposal Summary - Total Estimate: ${cost_estimate.get('estimated_material_cost', 0):,.2f}"

class PermitAgent:
    def verify_permits(self, permit_records: List[Dict[str, Any]]) -> bool:
        return len(permit_records) > 0

class WeatherAgent:
    def assess_risk(self, peak_wind: float) -> str:
        return "High Risk" if peak_wind > 55 else "Low Risk"

class ComplianceAgent:
    def check_building_codes(self, rag_results: List[Dict[str, Any]]) -> bool:
        return len(rag_results) > 0

class ProjectManagerAgent:
    def schedule_crew(self, analysis_results: Dict[str, Any]) -> Dict[str, Any]:
        return {"crew_assigned": "Crew Alpha", "work_days": 2}
