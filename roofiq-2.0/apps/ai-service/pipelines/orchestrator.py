from typing import Dict, Any, List
from vision.models import SAM2Segmenter, YOLOv11OBBDetector
from llm.client import LocalLLMClient
from ocr.scanner import ShingleOcrScanner
from agents.cognitive import EstimatorAgent, InspectorAgent

class AIOrchestratorPipeline:
    def __init__(self):
        self.sam = SAM2Segmenter()
        self.yolo = YOLOv11OBBDetector()
        self.llm = LocalLLMClient()
        self.ocr = ShingleOcrScanner()
        
        # Agent setups
        self.estimator = EstimatorAgent()
        self.inspector = InspectorAgent()

    def execute_full_pipeline(self, image_data: bytes, document_data: bytes) -> Dict[str, Any]:
        # 1. Vision: Segmentation + Feature check
        facets = self.sam.segment_facets(image_data, [0.0, 0.0, 0.0, 0.0])
        obstructions = self.yolo.detect_obstructions(image_data)
        
        # 2. OCR: Text extracts
        ocr_text = self.ocr.scan_blueprint(document_data)
        
        # 3. Estimator & Inspector Agents
        estimated_area = sum(f["area_meters2"] for f in facets) * 10.7639 # convert to sqft
        estimation = self.estimator.estimate_cost(estimated_area, ["shingles"])
        inspection = self.inspector.inspect_damages(obstructions)
        
        # 4. LLM Summary
        summary = self.llm.generate_summary(f"Area: {estimated_area}, OCR: {ocr_text}", "Summarize status")

        return {
            "success": True,
            "estimated_area_sqft": estimated_area,
            "estimation": estimation,
            "inspection": inspection,
            "ocr_text": ocr_text,
            "ai_summary": summary
        }
