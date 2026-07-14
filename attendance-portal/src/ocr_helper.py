import sys
import json
import os
import re

# Disable tensorflow and paddle verbose logging
os.environ['GLOG_minloglevel'] = '3'
os.environ['FLAGS_allocator_strategy'] = 'naive_best_fit'

try:
    from paddleocr import PaddleOCR
except ImportError as e:
    print(json.dumps({"error": f"Failed to import PaddleOCR: {str(e)}"}))
    sys.exit(1)

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No image path provided."}))
        sys.exit(1)
        
    image_path = sys.argv[1]
    if not os.path.exists(image_path):
        print(json.dumps({"error": f"Image file not found: {image_path}"}))
        sys.exit(1)
        
    try:
        # Initialize PaddleOCR with English language and angle classification (auto-orientation)
        ocr = PaddleOCR(use_angle_cls=True, lang='en')
        result = ocr.ocr(image_path)
        
        extracted_text = []
        if result and len(result) > 0:
            res_item = result[0]
            if isinstance(res_item, dict) and 'rec_texts' in res_item:
                # PaddleOCR v3 / PaddleX dictionary structure
                extracted_text = res_item['rec_texts']
            elif isinstance(res_item, list):
                # Traditional PaddleOCR v2 nested list structure
                for line in res_item:
                    if isinstance(line, list) and len(line) > 1 and isinstance(line[1], (list, tuple)):
                        extracted_text.append(line[1][0])
                
        full_text = "\n".join(extracted_text)
        
        # Simple extraction heuristics
        matched_name = None
        matched_date = None
        times = []
        
        lines = full_text.split('\n')
        for line in lines:
            # Match employee name
            if re.search(r'(name|employee)', line, re.IGNORECASE):
                clean = re.sub(r'(employee|name|[:_])', '', line, flags=re.IGNORECASE).strip()
                if len(clean) > 3 and len(clean) < 40:
                    matched_name = clean
                    
            # Match date MM/DD/YY or MM/DD/YYYY
            date_match = re.search(r'\b(\d{1,2})[\/\-]\d{1,2}[\/\-]\d{2,4}\b', line)
            if date_match and not matched_date:
                parts = re.split(r'[\/\-]', date_match.group(0))
                if len(parts) == 3:
                    y = parts[2]
                    if len(y) == 2:
                        y = "20" + y
                    m = parts[0].zfill(2)
                    d = parts[1].zfill(2)
                    matched_date = f"{y}-{m}-{d}"
                    
            # Match clock times
            time_matches = re.findall(r'\b(?:(?:0?[1-9]|1[0-2]):[0-5][0-9]\s*(?:AM|PM|am|pm)?)\b|\b(?:(?:[0-1]?[0-9]|2[0-3]):[0-5][0-9])\b', line)
            for t in time_matches:
                if ':' in t and t not in times:
                    times.append(t)
                    
        print(json.dumps({
            "success": True,
            "text": full_text,
            "matched_name": matched_name,
            "matched_date": matched_date,
            "times": times
        }))
        
    except Exception as e:
        import traceback
        print(json.dumps({"error": f"OCR extraction failed: {str(e)}", "traceback": traceback.format_exc()}))
        sys.exit(1)

if __name__ == "__main__":
    main()
