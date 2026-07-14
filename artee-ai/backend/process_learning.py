import json
import time

class ProcessLearningEngine:
    """
    Records and learns semantic business processes (e.g. creating invoice, adding vendor)
    with control metadata rather than static mouse coordinate clicks alone.
    Enables auto-adaptation if form locations change.
    """

    def __init__(self):
        # Workflows memory layout: name -> list of semantic steps
        self.workflows = {}

    def create_workflow(self, name: str) -> None:
        self.workflows[name] = []

    def record_step(self, name: str, window_title: str, control_id: str, ocr_text: str, 
                    action_type: str, payload: str = "", coords: tuple = (0,0), 
                    ai_explanation: str = "", user_correction: str = "") -> dict:
        """
        Records a single workflow step with rich metadata for adaptive replays.
        """
        step = {
            "step_id": len(self.workflows.get(name, [])) + 1,
            "window_title": window_title,
            "control_id": control_id,      # pywinauto control identifier
            "ocr_text": ocr_text,          # surrounding text label
            "action_type": action_type,    # click, type, select, press
            "payload": payload,            # input text or keys
            "coordinates": coords,         # fallback relative coordinate offset
            "timestamp": time.time(),
            "ai_explanation": ai_explanation,
            "user_correction": user_correction
        }
        if name in self.workflows:
            self.workflows[name].append(step)
        return step

    def get_workflow(self, name: str) -> list:
        return self.workflows.get(name, [])

    def adapt_step_to_ui(self, step: dict, current_controls: list, current_ocr_items: list) -> dict:
        """
        Adapts a recorded step to the current UI controls layout.
        Priority:
        1. Match by Control ID.
        2. Match by OCR Text coordinates.
        3. Match by original relative coordinates.
        """
        # 1. Search Control ID match
        for ctrl in current_controls:
            if ctrl.get("control_id") == step["control_id"]:
                return {
                    "method": "pywinauto_control",
                    "target": ctrl.get("control_id"),
                    "coordinates": ctrl.get("coordinates"),
                    "details": "Matched by Control ID"
                }

        # 2. Search OCR text match
        target_text = step["ocr_text"].lower()
        if target_text:
            for item in current_ocr_items:
                if target_text in item.get("text", "").lower():
                    return {
                        "method": "ocr_vision",
                        "target": item.get("text"),
                        "coordinates": item.get("coordinates"),
                        "details": f"Matched surrounding text: '{item.get('text')}'"
                    }

        # 3. Fallback to original relative coordinates
        return {
            "method": "pyautogui_coordinates",
            "target": "fallback",
            "coordinates": step["coordinates"],
            "details": "Fallback to original relative coordinates"
        }
