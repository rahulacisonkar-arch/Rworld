"""
ROOFIQ AI — Geo Processor
GeoPandas-based geospatial calculations for building footprints and roof measurements.
"""
import json
import math
import logging
from typing import Optional

logger = logging.getLogger(__name__)

try:
    import geopandas as gpd
    from shapely.geometry import shape, Polygon, Point, mapping
    from shapely.ops import transform
    import pyproj
    from functools import partial
    HAS_GEOPANDAS = True
except ImportError:
    HAS_GEOPANDAS = False
    logger.warning("GeoPandas not available — using fallback calculations")


def latlon_to_utm_projection(lat: float, lng: float):
    """Get the appropriate UTM CRS for a given lat/lng point."""
    zone = int((lng + 180) / 6) + 1
    hemisphere = 'north' if lat >= 0 else 'south'
    epsg = 32600 + zone if hemisphere == 'north' else 32700 + zone
    return f"EPSG:{epsg}"


def calculate_polygon_area_sqft(geojson_polygon: dict, lat: float, lng: float) -> float:
    """
    Calculate precise area of a GeoJSON polygon in square feet.
    Projects to UTM for accurate measurement.
    """
    if not HAS_GEOPANDAS:
        return _estimate_area_from_bbox(geojson_polygon)

    try:
        geom = shape(geojson_polygon)
        utm_crs = latlon_to_utm_projection(lat, lng)
        
        gdf = gpd.GeoDataFrame(geometry=[geom], crs="EPSG:4326")
        gdf_utm = gdf.to_crs(utm_crs)
        
        area_m2 = float(gdf_utm.geometry.iloc[0].area)
        area_sqft = area_m2 * 10.7639
        return round(area_sqft, 1)
    except Exception as e:
        logger.error(f"Area calculation failed: {e}")
        return _estimate_area_from_bbox(geojson_polygon)


def calculate_polygon_perimeter_ft(geojson_polygon: dict, lat: float, lng: float) -> float:
    """Calculate perimeter of polygon in linear feet."""
    if not HAS_GEOPANDAS:
        return _estimate_perimeter_from_bbox(geojson_polygon)

    try:
        geom = shape(geojson_polygon)
        utm_crs = latlon_to_utm_projection(lat, lng)
        
        gdf = gpd.GeoDataFrame(geometry=[geom], crs="EPSG:4326")
        gdf_utm = gdf.to_crs(utm_crs)
        
        perimeter_m = float(gdf_utm.geometry.iloc[0].length)
        perimeter_ft = perimeter_m * 3.28084
        return round(perimeter_ft, 1)
    except Exception as e:
        logger.error(f"Perimeter calculation failed: {e}")
        return _estimate_perimeter_from_bbox(geojson_polygon)


def calculate_roof_measurements(footprint_geojson: dict, lat: float, lng: float, pitch_deg: float = 22.0) -> dict:
    """
    Calculate full roof measurements from building footprint.
    
    Args:
        footprint_geojson: GeoJSON polygon of building footprint
        lat, lng: Property coordinates
        pitch_deg: Roof pitch in degrees (estimated or from Solar API)
    
    Returns:
        Dictionary of all roof measurements
    """
    base_area = calculate_polygon_area_sqft(footprint_geojson, lat, lng)
    perimeter = calculate_polygon_perimeter_ft(footprint_geojson, lat, lng)
    
    # Calculate actual roof area from base area + pitch
    pitch_factor = 1.0 / math.cos(math.radians(pitch_deg))
    roof_area = round(base_area * pitch_factor, 1)
    
    # Ridge / eave estimations
    # Assume simple gable roof: ridge ≈ long axis of footprint / 2
    # Eaves ≈ perimeter / 2
    ridge_length = round(perimeter / 4, 1)      # Approximate ridge length
    eave_length  = round(perimeter / 2, 1)      # Eave length (two long sides)
    rake_length  = round(perimeter / 4, 1)      # Rake edges (two short sides)
    
    # Hip roof variant (if pitch > 30°, likely hip)
    roof_type = "Hip Roof" if pitch_deg > 30 else "Gable Roof"
    
    # Number of squares (roofing unit = 100 sq ft)
    squares = round(roof_area / 100, 1)
    
    # Waste factor (standard 15% for standard cut, 20% for complex)
    waste_pct = 0.20 if pitch_deg > 30 else 0.15
    material_needed = round(roof_area * (1 + waste_pct), 1)
    
    return {
        'base_footprint_sqft': base_area,
        'roof_area_sqft':      roof_area,
        'perimeter_ft':        perimeter,
        'roof_perimeter_ft':   perimeter,
        'ridge_length_ft':     ridge_length,
        'eave_length_ft':      eave_length,
        'rake_length_ft':      rake_length,
        'roof_pitch_deg':      pitch_deg,
        'pitch_degrees':       pitch_deg,
        'roof_pitch_ratio':    f"{round(math.tan(math.radians(pitch_deg)) * 12, 1)}/12",
        'pitch_ratio':         f"{round(math.tan(math.radians(pitch_deg)) * 12, 1)}/12",
        'roof_type':           roof_type,
        'roof_squares':        squares,
        'roofing_squares':     squares,
        'facets_count':        4,
        'material_sqft_needed': material_needed,
        'waste_factor_pct':    round(waste_pct * 100),
    }


def split_roof_into_sections(footprint_geojson: dict, lat: float, lng: float) -> list:
    """
    Divide a building footprint into roof sections (facets).
    Returns list of section dicts with area and orientation.
    """
    base_area = calculate_polygon_area_sqft(footprint_geojson, lat, lng)

    # Simplified: divide into typical sections based on roof type
    sections = [
        {'name': 'Front Slope',  'area_sqft': round(base_area * 0.35), 'orientation': 'S', 'pitch_deg': 22},
        {'name': 'Rear Slope',   'area_sqft': round(base_area * 0.35), 'orientation': 'N', 'pitch_deg': 22},
        {'name': 'Left Gable',   'area_sqft': round(base_area * 0.15), 'orientation': 'W', 'pitch_deg': 22},
        {'name': 'Right Gable',  'area_sqft': round(base_area * 0.15), 'orientation': 'E', 'pitch_deg': 22},
    ]

    if HAS_GEOPANDAS:
        try:
            geom = shape(footprint_geojson)
            bbox = geom.bounds  # (minx, miny, maxx, maxy)
            width  = (bbox[2] - bbox[0]) * 111320 * math.cos(math.radians(lat))  # metres
            height = (bbox[3] - bbox[1]) * 111320  # metres

            # Determine primary axis
            if width > height:
                sections[0]['name'] = 'South Slope'
                sections[1]['name'] = 'North Slope'
                sections[2]['name'] = 'West Hip'
                sections[3]['name'] = 'East Hip'
            else:
                sections[0]['name'] = 'East Slope'
                sections[1]['name'] = 'West Slope'
                sections[2]['name'] = 'North Hip'
                sections[3]['name'] = 'South Hip'
        except Exception as e:
            logger.warning(f"Could not determine roof orientation: {e}")

    return sections


def point_in_footprint(lat: float, lng: float, footprint_geojson: dict) -> bool:
    """Check if a coordinate point lies inside a building footprint."""
    if not HAS_GEOPANDAS:
        return True  # Optimistic fallback

    try:
        polygon = shape(footprint_geojson)
        point   = Point(lng, lat)
        return polygon.contains(point)
    except Exception:
        return True


# ============================================================
# Fallback calculations (no GeoPandas)
# ============================================================

def _haversine_distance(lat1, lng1, lat2, lng2) -> float:
    """Calculate distance in metres between two lat/lng points."""
    R = 6371000  # Earth radius in metres
    phi1 = math.radians(lat1)
    phi2 = math.radians(lat2)
    dphi = math.radians(lat2 - lat1)
    dlam = math.radians(lng2 - lng1)
    a = math.sin(dphi/2)**2 + math.cos(phi1)*math.cos(phi2)*math.sin(dlam/2)**2
    return R * 2 * math.atan2(math.sqrt(a), math.sqrt(1-a))


def _estimate_area_from_bbox(geojson_polygon: dict) -> float:
    """Estimate area from bounding box of GeoJSON polygon."""
    try:
        coords = geojson_polygon.get('coordinates', [[]])[0]
        if not coords:
            return 1850.0  # Default estimate
        
        lats = [c[1] for c in coords]
        lngs = [c[0] for c in coords]
        
        height_m = _haversine_distance(min(lats), min(lngs), max(lats), min(lngs))
        width_m  = _haversine_distance(min(lats), min(lngs), min(lats), max(lngs))
        
        area_m2   = height_m * width_m * 0.785  # ~78.5% fill factor
        area_sqft = area_m2 * 10.7639
        return round(area_sqft, 1)
    except Exception:
        return 1850.0


def _estimate_perimeter_from_bbox(geojson_polygon: dict) -> float:
    """Estimate perimeter from bounding box."""
    try:
        coords = geojson_polygon.get('coordinates', [[]])[0]
        if len(coords) < 2:
            return 180.0

        perimeter_m = 0.0
        for i in range(len(coords) - 1):
            perimeter_m += _haversine_distance(
                coords[i][1], coords[i][0],
                coords[i+1][1], coords[i+1][0]
            )
        return round(perimeter_m * 3.28084, 1)
    except Exception:
        return 180.0
