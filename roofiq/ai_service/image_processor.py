"""
ROOFIQ AI — Image Processor
OpenCV-based preprocessing for roof imagery before YOLO/SAM2 inference.
"""
import cv2
import numpy as np
from PIL import Image
import io
import base64
import requests
import logging

logger = logging.getLogger(__name__)


def base64_to_numpy(b64_string: str) -> np.ndarray:
    """Decode base64 image string to numpy array (BGR for OpenCV)."""
    # Strip data URI prefix if present
    if ',' in b64_string:
        b64_string = b64_string.split(',', 1)[1]
    img_bytes = base64.b64decode(b64_string)
    img_array = np.frombuffer(img_bytes, dtype=np.uint8)
    img = cv2.imdecode(img_array, cv2.IMREAD_COLOR)
    return img


def numpy_to_base64(img: np.ndarray, quality: int = 90) -> str:
    """Encode numpy array (BGR) to base64 JPEG string."""
    success, buffer = cv2.imencode('.jpg', img, [cv2.IMWRITE_JPEG_QUALITY, quality])
    if not success:
        raise ValueError("Failed to encode image")
    return base64.b64encode(buffer.tobytes()).decode('utf-8')


def fetch_tile_image(lat: float, lng: float, zoom: int = 19, size: int = 640) -> np.ndarray:
    """
    Fetch a satellite map tile as a numpy array.
    Uses OpenStreetMap tile coordinates (fallback, no API key needed).
    For production: swap with EagleView/Nearmap tile URL.
    """
    # Convert lat/lng to tile XY at given zoom
    import math
    lat_r = math.radians(lat)
    n = 2 ** zoom
    xtile = int((lng + 180.0) / 360.0 * n)
    ytile = int((1.0 - math.log(math.tan(lat_r) + 1 / math.cos(lat_r)) / math.pi) / 2.0 * n)

    # Try to get a satellite tile
    headers = {'User-Agent': 'RoofIQ-AI/1.0 (roof analysis system)'}
    
    # Use ArcGIS World Imagery (free, no key for basic use)
    url = f"https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{zoom}/{ytile}/{xtile}"
    
    try:
        resp = requests.get(url, headers=headers, timeout=10)
        resp.raise_for_status()
        img_array = np.frombuffer(resp.content, dtype=np.uint8)
        img = cv2.imdecode(img_array, cv2.IMREAD_COLOR)
        if img is not None:
            img = cv2.resize(img, (size, size))
            return img
    except Exception as e:
        logger.warning(f"Failed to fetch satellite tile: {e}")

    # Return a placeholder gradient image if fetch fails
    img = np.zeros((size, size, 3), dtype=np.uint8)
    img[:, :, 0] = 60   # Blue channel
    img[:, :, 1] = 80   # Green channel
    img[:, :, 2] = 100  # Red channel
    cv2.putText(img, "NO IMAGERY", (150, 320), cv2.FONT_HERSHEY_SIMPLEX, 1, (200, 200, 200), 2)
    return img


def preprocess_roof_image(img: np.ndarray) -> np.ndarray:
    """
    Full preprocessing pipeline for roof damage detection:
    1. Normalize exposure
    2. Enhance contrast (CLAHE)
    3. Reduce noise
    4. Sharpen edges
    """
    if img is None:
        raise ValueError("Invalid image input")

    # 1. Normalize brightness
    lab = cv2.cvtColor(img, cv2.COLOR_BGR2LAB)
    l_ch, a_ch, b_ch = cv2.split(lab)

    # 2. CLAHE contrast enhancement on L channel
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    l_ch = clahe.apply(l_ch)
    lab = cv2.merge([l_ch, a_ch, b_ch])
    img = cv2.cvtColor(lab, cv2.COLOR_LAB2BGR)

    # 3. Gaussian blur for noise reduction
    img = cv2.GaussianBlur(img, (3, 3), 0)

    # 4. Unsharp masking for edge sharpening
    gaussian = cv2.GaussianBlur(img, (9, 9), 2.0)
    img = cv2.addWeighted(img, 1.5, gaussian, -0.5, 0)

    return img


def detect_vegetation(img: np.ndarray) -> dict:
    """
    Detect vegetation / moss on roof using NDVI-inspired color analysis.
    Returns: {'vegetation_pct': float, 'mask': numpy array}
    """
    # In satellite imagery, use HSV green detection
    hsv = cv2.cvtColor(img, cv2.COLOR_BGR2HSV)
    
    # Green range in HSV
    lower_green = np.array([35, 40, 40])
    upper_green = np.array([85, 255, 255])
    mask = cv2.inRange(hsv, lower_green, upper_green)
    
    vegetation_pct = round((np.count_nonzero(mask) / mask.size) * 100, 1)
    
    return {
        'vegetation_pct': vegetation_pct,
        'mask': mask,
    }


def detect_water_ponding(img: np.ndarray) -> dict:
    """
    Detect standing water / ponding on roof using blue channel dominance.
    """
    b, g, r = cv2.split(img)
    # Water tends to be dark with blue dominance
    water_mask = (b.astype(int) > r.astype(int) + 20) & (b.astype(int) > g.astype(int) + 20)
    water_mask = water_mask.astype(np.uint8) * 255
    ponding_pct = round((np.count_nonzero(water_mask) / water_mask.size) * 100, 1)
    
    return {
        'ponding_pct': ponding_pct,
        'mask': water_mask,
    }


def extract_roof_region(img: np.ndarray, footprint_coords: list = None) -> np.ndarray:
    """
    Mask the image to only the roof region using provided polygon coordinates.
    If no footprint provided, returns full image.
    """
    if not footprint_coords or len(footprint_coords) < 3:
        return img

    mask = np.zeros(img.shape[:2], dtype=np.uint8)
    pts = np.array(footprint_coords, dtype=np.int32)
    cv2.fillPoly(mask, [pts], 255)
    
    result = img.copy()
    result[mask == 0] = 0
    return result


def generate_damage_heatmap(detections: list, img_size: tuple = (640, 640)) -> np.ndarray:
    """
    Generate a heatmap image from YOLO damage detections.
    Returns an RGBA numpy array suitable for Leaflet overlay.
    """
    heatmap = np.zeros((img_size[1], img_size[0], 4), dtype=np.uint8)
    
    severity_colors = {
        'cracked_shingle':   [255, 100,   0, 180],
        'missing_shingle':   [255,   0,   0, 200],
        'moss_growth':       [ 50, 200,  50, 150],
        'ponding_water':     [  0, 100, 255, 160],
        'flashing_damage':   [255, 200,   0, 180],
        'general_damage':    [200,  80,  80, 160],
    }
    
    for det in detections:
        x1, y1, x2, y2 = int(det.get('x1', 0)), int(det.get('y1', 0)), \
                          int(det.get('x2', 100)), int(det.get('y2', 100))
        cls = det.get('class', 'general_damage')
        color = severity_colors.get(cls, severity_colors['general_damage'])
        
        # Draw filled rectangle
        heatmap[y1:y2, x1:x2] = color
        
        # Add Gaussian blur for heat effect
        roi = heatmap[max(0, y1-20):min(img_size[1], y2+20),
                      max(0, x1-20):min(img_size[0], x2+20)]
        if roi.size > 0:
            roi_blur = cv2.GaussianBlur(roi, (21, 21), 0)
            heatmap[max(0, y1-20):min(img_size[1], y2+20),
                    max(0, x1-20):min(img_size[0], x2+20)] = roi_blur

    return heatmap
