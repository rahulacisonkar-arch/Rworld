import sys
import json
import os
import re
import base64
import numpy as np
import cv2
try:
    import fitz  # PyMuPDF
except ImportError:
    fitz = None

# Disable tensorflow/paddle verbose logs
os.environ['GLOG_minloglevel'] = '3'
os.environ['FLAGS_allocator_strategy'] = 'naive_best_fit'
os.environ['PADDLE_PDX_DISABLE_MODEL_SOURCE_CHECK'] = 'True'

from fastapi import FastAPI, UploadFile, File, Form, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List, Optional
import uvicorn
import requests
from rapidocr import RapidOCR
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.naive_bayes import MultinomialNB
from sklearn.ensemble import IsolationForest

# Training data for local NLP Classifier
nlp_training_data = [
    ("who was late?", "late"),
    ("late arrivals", "late"),
    ("who came late?", "late"),
    ("late employees last week", "late"),
    ("recent late entries", "late"),
    ("show me late check-ins", "late"),
    ("who clocked in late?", "late"),
    ("overtime hours", "overtime"),
    ("who worked overtime?", "overtime"),
    ("employees with OT", "overtime"),
    ("recent OT shifts", "overtime"),
    ("excessive hours log", "overtime"),
    ("show me overtime", "overtime"),
    ("working extra hours", "overtime"),
    ("who is absent today?", "absent"),
    ("absent employees", "absent"),
    ("who missed work?", "absent"),
    ("non-attendance list", "absent"),
    ("no show today", "absent"),
    ("unexcused absence", "absent"),
    ("payroll estimate", "payroll"),
    ("store payout", "payroll"),
    ("bi-weekly labor cost", "payroll"),
    ("earnings forecast", "payroll"),
    ("wages calculation", "payroll"),
    ("location payroll", "payroll"),
    ("how much is estimated pay?", "payroll")
]

# Initialize and fit NLP classifier
print("Fitting local NLP intent classifier...")
texts = [x[0] for x in nlp_training_data]
labels = [x[1] for x in nlp_training_data]
vectorizer = TfidfVectorizer(lowercase=True)
X_train = vectorizer.fit_transform(texts)
clf = MultinomialNB()
clf.fit(X_train, labels)

app = FastAPI(title="Artée Attendance Enterprise OCR Server", version="1.0")

# Enable CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize RapidOCR
print("Initializing RapidOCR...")
ocr = RapidOCR()

NVIDIA_API_KEY = "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"
NVIDIA_URL = "https://integrate.api.nvidia.com/v1/chat/completions"

class ScanRequest(BaseModel):
    image_data: str  # Base64 encoded image or PDF
    employees: List[dict]  # List of DB employees to assist in matching

def preprocess_image(image_bytes: bytes) -> np.ndarray:
    """
    Advanced OpenCV Preprocessing Pipeline:
    1. Decode Image
    2. Grayscale & Contrast Enhancement (CLAHE)
    3. Noise Removal (Bilateral Filter)
    4. Auto-Deskew & Rotation Correction
    5. Sharpening
    """
    nparr = np.frombuffer(image_bytes, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    if img is None:
        raise ValueError("Failed to decode image.")

    # 1. Grayscale conversion
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

    # 2. Noise Removal (Bilateral filter to preserve edges while smoothing background noise)
    denoised = cv2.bilateralFilter(gray, 9, 75, 75)

    # 3. Contrast Enhancement (CLAHE - Contrast Limited Adaptive Histogram Equalization)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    enhanced = clahe.apply(denoised)

    # 4. Automatic Deskewing
    # Threshold the image to binary for rotation detection
    thresh = cv2.threshold(enhanced, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)[1]
    coords = np.column_stack(np.where(thresh > 0))
    if len(coords) > 0:
        angle = cv2.minAreaRect(coords)[-1]
        # Normalize angle to -45 to 45 degrees
        if angle < -45:
            angle = -(90 + angle)
        else:
            angle = -angle
        
        # Apply rotation if deskewing is non-trivial (greater than 0.5 degrees)
        if abs(angle) > 0.5 and abs(angle) < 45:
            (h, w) = img.shape[:2]
            center = (w // 2, h // 2)
            M = cv2.getRotationMatrix2D(center, angle, 1.0)
            enhanced = cv2.warpAffine(enhanced, M, (w, h), flags=cv2.INTER_CUBIC, borderMode=cv2.BORDER_REPLICATE)

    # 5. Sharpening (Filter2D with custom sharpening kernel)
    kernel = np.array([[0, -1, 0], [-1, 5, -1], [0, -1, 0]])
    sharpened = cv2.filter2D(enhanced, -1, kernel)

    # Convert back to standard color image for OCR detection
    color_processed = cv2.cvtColor(sharpened, cv2.COLOR_GRAY2BGR)
    return color_processed

def convert_pdf_to_images(pdf_bytes: bytes) -> List[bytes]:
    """Converts a PDF document to a list of high-resolution JPEG image bytes using PyMuPDF."""
    if not fitz:
        raise ImportError("PyMuPDF (fitz) is not available.")
    
    doc = fitz.open(stream=pdf_bytes, filetype="pdf")
    image_list = []
    for page_num in range(len(doc)):
        page = doc.load_page(page_num)
        # Use high zoom factor (2.0) for better OCR accuracy
        zoom = 2.0
        mat = fitz.Matrix(zoom, zoom)
        pix = page.get_pixmap(matrix=mat)
        img_data = pix.tobytes("jpeg")
        image_list.append(img_data)
    return image_list

def perform_ocr(img: np.ndarray) -> tuple:
    """Executes RapidOCR on the preprocessed image and extracts raw text + bounding boxes."""
    # Write image temporarily to run OCR
    temp_path = "ocr_tmp_server.jpg"
    cv2.imwrite(temp_path, img)
    
    try:
        res = ocr(temp_path)
        extracted_text = []
        boxes = []
        
        if res and res.txts:
            for idx, text in enumerate(res.txts):
                confidence = float(res.scores[idx]) if res.scores else 0.90
                box_coords = res.boxes[idx].tolist() if res.boxes is not None else []
                extracted_text.append(text)
                boxes.append({"text": text, "box": box_coords, "confidence": confidence})
        
        full_text = "\n".join(extracted_text)
        return full_text, boxes
    finally:
        if os.path.exists(temp_path):
            os.remove(temp_path)

def parse_local_timesheet(raw_text: str, employees: list) -> dict:
    """Parses raw timesheet text locally using OpenCV/Regex/DateTime rules without calling any API."""
    import datetime
    
    # Split text into lines
    lines = [line.strip() for line in raw_text.split('\n') if line.strip()]
    
    # 1. Match employee name locally against the list of database employees
    matched_employee = None
    matched_employee_id = None
    
    for line in lines:
        line_upper = line.upper()
        for emp in employees:
            emp_name = emp['name']
            if emp_name.upper() in line_upper or line_upper in emp_name.upper():
                if len(emp_name) >= 3:
                    matched_employee = emp_name
                    matched_employee_id = emp['id']
                    break
        if matched_employee:
            break
            
    # 2. Extract dates sheet-wide
    parsed_dates = []
    week_ending = None
    
    # Look for week ending specifically
    for line in lines:
        if re.search(r'(week|ending|date|period)', line, re.IGNORECASE):
            date_match = re.search(r'\b(\d{1,2})[\/\-]\d{1,2}[\/\-]\d{2,4}\b|\b\d{4}-\d{2}-\d{2}\b', line)
            if date_match:
                week_ending = format_date(date_match.group(0))
                break

    all_dates_found = []
    for line in lines:
        matches = re.findall(r'\b(\d{1,2})[\/\-]\d{1,2}[\/\-]\d{2,4}\b|\b\d{4}-\d{2}-\d{2}\b', line)
        for m in matches:
            d_str = m if isinstance(m, str) else m[0]
            if d_str:
                formatted = format_date(d_str)
                if formatted and formatted not in all_dates_found:
                    all_dates_found.append(formatted)
                    
    if not week_ending and all_dates_found:
        week_ending = max(all_dates_found)
    elif not week_ending:
        week_ending = datetime.date.today().strftime('%Y-%m-%d')
        
    # 3. Extract shift entries
    logs = []
    days_of_week = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY']
    time_pattern = r'\b((?:0?[1-9]|1[0-2]):[0-5][0-9]\s*(?:AM|PM|am|pm)?)\b|\b((?:[0-1]?[0-9]|2[0-3]):[0-5][0-9])\b'
    
    for i, line in enumerate(lines):
        date_match = re.search(r'\b(\d{1,2})[\/\-]\d{1,2}[\/\-]\d{2,4}\b|\b\d{4}-\d{2}-\d{2}\b', line)
        if date_match:
            date_str = format_date(date_match.group(0))
            
            # Find the day of the week
            day_found = None
            for day in days_of_week:
                if day in line.upper():
                    day_found = day.capitalize()
                    break
            
            # Look in adjacent lines if not in same line
            if not day_found:
                for offset in [-1, 1]:
                    if 0 <= i + offset < len(lines):
                        adj_line = lines[i + offset]
                        for day in days_of_week:
                            if day in adj_line.upper():
                                day_found = day.capitalize()
                                break
                        if day_found:
                            break
            if not day_found:
                try:
                    dt = datetime.datetime.strptime(date_str, '%Y-%m-%d')
                    day_found = dt.strftime('%A')
                except:
                    day_found = 'Monday'
            
            # Extract times from the line or adjacent lines
            time_candidates = []
            matches = re.findall(time_pattern, line)
            for m in matches:
                t = m[0] or m[1]
                if t and t not in time_candidates:
                    time_candidates.append(t)
                    
            if len(time_candidates) < 2 and i + 1 < len(lines):
                next_line = lines[i + 1]
                if not re.search(r'\b(\d{1,2})[\/\-]\d{1,2}[\/\-]\d{2,4}\b', next_line):
                    next_matches = re.findall(time_pattern, next_line)
                    for m in next_matches:
                        t = m[0] or m[1]
                        if t and t not in time_candidates:
                            time_candidates.append(t)
                            
            clock_in = ""
            clock_out = ""
            daily_hours = 0.00
            
            if len(time_candidates) >= 2:
                clock_in = normalize_time(time_candidates[0])
                clock_out = normalize_time(time_candidates[1])
                daily_hours = calculate_hours_diff(clock_in, clock_out)
            elif len(time_candidates) == 1:
                clock_in = normalize_time(time_candidates[0])
                
            # Deduct standard break if total shift is longer than 5 hours and not explicitly given
            if daily_hours > 5.0:
                daily_hours -= 0.50 # 30 min lunch break logic
                
            if not any(log['date'] == date_str for log in logs):
                logs.append({
                    "date": date_str,
                    "day": day_found,
                    "clock_in": clock_in,
                    "clock_out": clock_out,
                    "daily_hours": round(max(0.00, daily_hours), 2),
                    "is_late": False,
                    "is_early_departure": False,
                    "missing_punch": (clock_in == "" or clock_out == "")
                })

    logs.sort(key=lambda x: x['date'])
    
    total_hours = sum(log['daily_hours'] for log in logs)
    if total_hours > 40.0:
        regular_hours = 40.0
        overtime_hours = total_hours - 40.0
    else:
        regular_hours = total_hours
        overtime_hours = 0.0
        
    errors = []
    for log in logs:
        if log['missing_punch']:
            errors.append(f"Missing punch on {log['date']} ({log['day']})")
            
    return {
        "employee": matched_employee,
        "employee_id": matched_employee_id,
        "week_ending": week_ending,
        "regular_hours": round(regular_hours, 2),
        "overtime_hours": round(overtime_hours, 2),
        "total_hours": round(total_hours, 2),
        "logs": logs,
        "errors": errors,
        "confidence": 0.90
    }

def format_date(d_str: str) -> str:
    d_str = d_str.strip()
    if re.match(r'^\d{4}-\d{2}-\d{2}$', d_str):
        return d_str
    parts = re.split(r'[\/\-]', d_str)
    if len(parts) == 3:
        if len(parts[0]) == 4:
            return f"{parts[0]}-{parts[1].zfill(2)}-{parts[2].zfill(2)}"
        else:
            m = parts[0].zfill(2)
            d = parts[1].zfill(2)
            y = parts[2]
            if len(y) == 2:
                y = "20" + y
            return f"{y}-{m}-{d}"
    return d_str

def normalize_time(t_str: str) -> str:
    t_str = t_str.strip()
    match = re.match(r'^(\d{1,2}):(\d{2})\s*(AM|PM|am|pm)?$', t_str)
    if match:
        h = match[1]
        m = match[2]
        ampm = match[3]
        if ampm:
            return f"{h.zfill(2)}:{m} {ampm.upper()}"
        else:
            h_int = int(h)
            if h_int >= 9 and h_int < 12:
                return f"{h.zfill(2)}:{m} AM"
            elif h_int < 9 or h_int == 12:
                return f"{h.zfill(2)}:{m} PM"
            elif h_int > 12:
                h_int -= 12
                return f"{str(h_int).zfill(2)}:{m} PM"
    return t_str

def calculate_hours_diff(in_str: str, out_str: str) -> float:
    import datetime
    try:
        t_in = parse_time(in_str)
        t_out = parse_time(out_str)
        if t_in and t_out:
            diff = (t_out - t_in).total_seconds() / 3600.0
            if diff < 0:
                diff += 24.0
            return diff
    except:
        pass
    return 0.0

def parse_time(time_str: str):
    import datetime
    match = re.match(r'^(\d{1,2}):(\d{2})\s*(AM|PM)?$', time_str, re.IGNORECASE)
    if not match:
        return None
    h = int(match[1])
    m = int(match[2])
    ampm = match[3]
    if ampm:
        ampm = ampm.upper()
        if ampm == 'PM' and h < 12:
            h += 12
        if ampm == 'AM' and h == 12:
            h = 0
    return datetime.datetime(2000, 1, 1, h, m)

@app.post("/scan")
async def scan_document(request: ScanRequest):
    try:
        data_str = request.image_data
        if "," in data_str:
            data_str = data_str.split(",")[1]
        
        file_bytes = base64.b64decode(data_str)
        is_pdf = file_bytes[:4] == b'%PDF'
        
        full_text_list = []
        all_boxes = []
        
        if is_pdf:
            if not fitz:
                raise HTTPException(status_code=500, detail="PyMuPDF library is missing on server.")
            images = convert_pdf_to_images(file_bytes)
            for idx, img_bytes in enumerate(images):
                preprocessed = preprocess_image(img_bytes)
                text, boxes = perform_ocr(preprocessed)
                full_text_list.append(text)
                for b in boxes:
                    b["page"] = idx + 1
                    all_boxes.append(b)
        else:
            preprocessed = preprocess_image(file_bytes)
            text, boxes = perform_ocr(preprocessed)
            full_text_list.append(text)
            for b in boxes:
                b["page"] = 1
                all_boxes.append(b)
                
        full_text = "\n\n".join(full_text_list)
        
        # Local heuristic parser execution (Zero API usage)
        structured_data = parse_local_timesheet(full_text, request.employees)
        
        structured_data["raw_text"] = full_text
        structured_data["boxes"] = all_boxes
        
        return structured_data
    except Exception as e:
        import traceback
        return {"error": str(e), "traceback": traceback.format_exc()}

class NLPRequest(BaseModel):
    query: str

class ExcelRequest(BaseModel):
    file_data: str
    employees: list

class AnomalyItem(BaseModel):
    log_id: int
    employee_name: str
    date: str
    check_in_variance: float
    break_ratio: float
    is_manually_edited: int
    calculated_hours: float

@app.post("/parse_excel")
async def parse_excel_endpoint(request: ExcelRequest):
    import io
    import pandas as pd
    try:
        data_str = request.file_data
        if "," in data_str:
            data_str = data_str.split(",")[1]
        file_bytes = base64.b64decode(data_str)
        
        try:
            df = pd.read_excel(io.BytesIO(file_bytes))
        except Exception:
            df = pd.read_csv(io.BytesIO(file_bytes))
            
        df.columns = [str(col).strip() for col in df.columns]
        
        name_col = None
        date_col = None
        in_col = None
        out_col = None
        hours_col = None
        
        for col in df.columns:
            col_lower = col.lower()
            if any(x in col_lower for x in ["name", "employee", "staff", "associate"]):
                name_col = col
            elif any(x in col_lower for x in ["date", "day"]):
                date_col = col
            elif any(x in col_lower for x in ["clock in", "login", "arrival", "time in", "check in"]):
                in_col = col
            elif any(x in col_lower for x in ["clock out", "logout", "departure", "time out", "check out"]):
                out_col = col
            elif any(x in col_lower for x in ["hour", "duration", "worked"]):
                hours_col = col

        if not name_col and len(df.columns) > 0:
            name_col = df.columns[0]
        if not date_col and len(df.columns) > 1:
            date_col = df.columns[1]
        if not in_col and len(df.columns) > 2:
            in_col = df.columns[2]
        if not out_col and len(df.columns) > 3:
            out_col = df.columns[3]
            
        logs = []
        errors = []
        
        for idx, row in df.iterrows():
            row_name = str(row[name_col]).strip() if name_col and pd.notna(row[name_col]) else ""
            row_date = str(row[date_col]).strip() if date_col and pd.notna(row[date_col]) else ""
            row_in = str(row[in_col]).strip() if in_col and pd.notna(row[in_col]) else ""
            row_out = str(row[out_col]).strip() if out_col and pd.notna(row[out_col]) else ""
            
            row_hours = None
            if hours_col and pd.notna(row[hours_col]):
                try:
                    row_hours = float(row[hours_col])
                except:
                    pass
            
            if not row_name or not row_date:
                continue
                
            matched_emp = None
            matched_emp_id = None
            for emp in request.employees:
                if emp['name'].upper() in row_name.upper() or row_name.upper() in emp['name'].upper():
                    matched_emp = emp['name']
                    matched_emp_id = emp['id']
                    break
                    
            if not matched_emp_id:
                errors.append(f"Row {idx+2}: Employee '{row_name}' not matched in database.")
                continue
                
            formatted_date = format_date(row_date)
            if formatted_date == row_date:
                try:
                    formatted_date = pd.to_datetime(row_date).strftime('%Y-%m-%d')
                except:
                    pass
                    
            normalized_in = normalize_time(row_in) if row_in else ""
            normalized_out = normalize_time(row_out) if row_out else ""
            
            if row_hours is None:
                row_hours = calculate_hours_diff(normalized_in, normalized_out)
                if row_hours > 5.0:
                    row_hours -= 0.50
                    
            try:
                day_name = pd.to_datetime(formatted_date).strftime('%A')
            except:
                day_name = "Monday"
                
            logs.append({
                "employee_id": matched_emp_id,
                "employee_name": matched_emp,
                "date": formatted_date,
                "day": day_name,
                "clock_in": normalized_in,
                "clock_out": normalized_out,
                "daily_hours": round(max(0.00, row_hours), 2)
            })
            
        return {"success": True, "logs": logs, "errors": errors}
    except Exception as e:
        import traceback
        return {"success": False, "error": str(e), "traceback": traceback.format_exc()}

@app.post("/classify_nlp")
async def classify_nlp(request: NLPRequest):
    q = request.query.lower().strip()
    if not q:
        return {"intent": "unknown"}
    
    X_test = vectorizer.transform([q])
    probs = clf.predict_proba(X_test)[0]
    max_idx = np.argmax(probs)
    max_prob = probs[max_idx]
    pred_label = clf.classes_[max_idx]
    
    if max_prob < 0.25:
        if "late" in q:
            pred_label = "late"
        elif "overtime" in q or "ot" in q:
            pred_label = "overtime"
        elif "absent" in q:
            pred_label = "absent"
        elif "payroll" in q or "payout" in q:
            pred_label = "payroll"
        else:
            pred_label = "unknown"
            
    return {"intent": pred_label, "confidence": float(max_prob)}

@app.post("/detect_anomalies")
async def detect_anomalies(items: List[AnomalyItem]):
    if len(items) < 3:
        anomalies = []
        for item in items:
            reasons = []
            severity = "Low"
            if abs(item.check_in_variance) > 1.5:
                reasons.append(f"Clock-in variance of {abs(item.check_in_variance):.1f} hours from scheduled start time")
                severity = "Medium"
            if item.break_ratio > 0.20:
                reasons.append(f"Excessive break duration ratio ({item.break_ratio*100:.1f}%)")
                severity = "Medium"
            if item.calculated_hours > 12:
                reasons.append(f"Extremely long shift duration ({item.calculated_hours:.1f} hours)")
                severity = "High"
            if item.is_manually_edited == 1:
                reasons.append("Shift logs contain manual supervisor corrections")
                
            if reasons:
                anomalies.append({
                    "log_id": item.log_id,
                    "employee_name": item.employee_name,
                    "date": item.date,
                    "reasons": reasons,
                    "severity": severity
                })
        return {"anomalies": anomalies}

    X = np.array([
        [item.check_in_variance, item.break_ratio, item.is_manually_edited, item.calculated_hours]
        for item in items
    ])
    
    clf_if = IsolationForest(n_estimators=50, random_state=42, contamination=0.15)
    preds = clf_if.fit_predict(X)
    
    anomalies = []
    for idx, pred in enumerate(preds):
        item = items[idx]
        reasons = []
        severity = "Low"
        
        is_outlier = (pred == -1)
        
        if abs(item.check_in_variance) > 1.5:
            reasons.append(f"Clock-in variance of {abs(item.check_in_variance):.1f} hours from schedule")
            severity = "Medium"
        if item.break_ratio > 0.15:
            reasons.append(f"High break ratio ({item.break_ratio*100:.1f}%)")
            severity = "Medium"
        if item.calculated_hours > 12:
            reasons.append(f"Excessive shift length ({item.calculated_hours:.1f} hours)")
            severity = "High"
        if item.is_manually_edited == 1:
            reasons.append("Manual punch correction")
            
        if is_outlier and not reasons:
            reasons.append("Multi-dimensional shift variance outlier")
            severity = "Medium"
            
        if is_outlier or len(reasons) > 0:
            anomalies.append({
                "log_id": item.log_id,
                "employee_name": item.employee_name,
                "date": item.date,
                "reasons": reasons if reasons else ["Outlier detected by ML model"],
                "severity": severity
            })
            
    return {"anomalies": anomalies}

if __name__ == "__main__":
    uvicorn.run(app, host="127.0.0.1", port=5000)
