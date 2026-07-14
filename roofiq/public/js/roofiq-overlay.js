/**
 * ROOFIQ AI ENTERPRISE — Leaflet Damage Overlay Engine
 * Renders AI damage detections as colored bounding boxes on satellite imagery
 */

var leafletMap = null;
var damageMarkers = [];

function initLeafletDamageMap() {
  if (!ROOFIQ.lat || !ROOFIQ.lng) return;

  leafletMap = L.map('leaflet-container', {
    center: [ROOFIQ.lat, ROOFIQ.lng],
    zoom: 19,
  });

  // ESRI satellite tiles
  L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: '© Esri',
    maxZoom: 21,
  }).addTo(leafletMap);

  // Add footprint polygon
  if (ROOFIQ.footprint && ROOFIQ.footprint.coordinates) {
    var latlngs = ROOFIQ.footprint.coordinates[0].map(function(c) { return [c[1], c[0]]; });
    L.polygon(latlngs, {
      color: '#00d4ff',
      fillColor: '#00d4ff',
      fillOpacity: 0.1,
      weight: 2,
    }).addTo(leafletMap);
  }

  // FeatureGroup is to store editable layers
  var drawnItems = new L.FeatureGroup();
  leafletMap.addLayer(drawnItems);
  window.drawnItems = drawnItems;

  // Initialize drawing/editing handlers programmatically
  window.drawPolygonHandler = new L.Draw.Polygon(leafletMap, {
    allowIntersection: false,
    showArea: true,
    shapeOptions: {
      color: '#00d4ff',
      fillColor: '#00d4ff',
      fillOpacity: 0.15,
      weight: 3
    }
  });

  window.editPolygonHandler = new L.EditToolbar.Edit(leafletMap, {
    featureGroup: drawnItems
  });

  // Listen to draw events
  leafletMap.on(L.Draw.Event.CREATED, function (e) {
    var layer = e.layer;
    drawnItems.addLayer(layer);
    updateDrawnGeoJSON();
    if (typeof showToast === 'function') {
      showToast('Roof area traced successfully! Click "Analyze Drawn Area" to calculate.', 'success');
    }
  });

  leafletMap.on(L.Draw.Event.EDITED, function (e) {
    updateDrawnGeoJSON();
  });

  leafletMap.on(L.Draw.Event.DELETED, function (e) {
    updateDrawnGeoJSON();
  });

  function updateDrawnGeoJSON() {
    var geojson = drawnItems.toGeoJSON();
    if (geojson.features.length > 0) {
      window.drawnGeoJSON = geojson.features[0].geometry;
    } else {
      window.drawnGeoJSON = null;
    }
  }

  // Initial marker
  window.leafletMarker = L.marker([ROOFIQ.lat, ROOFIQ.lng], { draggable: true }).addTo(leafletMap);

  // Click handler to select roof location
  leafletMap.on('click', function(e) {
    if (window.currentTracingMode === 'manual') {
      // Do not change location when drawing or manually tracing
      return;
    }

    var lat = e.latlng.lat;
    var lng = e.latlng.lng;
    ROOFIQ.lat = lat;
    ROOFIQ.lng = lng;
    window.leafletMarker.setLatLng([lat, lng]);

    // Update coordinates status display
    var coordsEl = document.getElementById('viewer-coords');
    if (coordsEl) {
      coordsEl.textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);
    }

    // Sync MapLibre marker if active
    if (window.mapMarker) {
      window.mapMarker.setLngLat([lng, lat]);
    }

    // Sync Cesium camera if active
    if (window.viewer) {
      var hm = lat < 60 ? 250 : 300;
      window.viewer.camera.flyTo({
        destination: Cesium.Cartesian3.fromDegrees(lng, lat - 0.0003, hm),
        orientation: { heading: Cesium.Math.toRadians(0), pitch: Cesium.Math.toRadians(-45), roll: 0 }
      });
    }

    if (typeof showToast === 'function') {
      showToast('Selected roof location. Running AI analysis...', 'info');
    }
    
    // Auto run analysis for the newly clicked location
    runAIAnalysis();
  });

  window.leafletMap = leafletMap;
}

window.currentTracingMode = 'auto';

function setTracingMode(mode) {
  window.currentTracingMode = mode;
  var drawActions = document.getElementById('manual-draw-actions');
  if (mode === 'manual') {
    if (drawActions) drawActions.style.display = 'flex';
    if (typeof showToast === 'function') {
      showToast('Manual Tracing Mode active. Draw custom roof facets on the satellite imagery.', 'info');
    }
  } else {
    if (drawActions) drawActions.style.display = 'none';
    if (window.drawnItems) window.drawnItems.clearLayers();
    window.drawnGeoJSON = null;
  }
}

function triggerDrawPolygon() {
  if (window.drawPolygonHandler) {
    window.drawPolygonHandler.enable();
  }
}

function triggerEditPolygon() {
  if (window.editPolygonHandler) {
    window.editPolygonHandler.enable();
    var saveBtn = document.getElementById('btn-save-edit');
    if (saveBtn) saveBtn.style.display = 'inline-block';
    var editBtn = document.getElementById('btn-edit-roof');
    if (editBtn) editBtn.style.display = 'none';
    if (typeof showToast === 'function') {
      showToast('Click and drag the vertices to edit the boundary. Click [Save Edit] when done.', 'warning');
    }
  }
}

function saveEditedPolygon() {
  if (window.editPolygonHandler) {
    window.editPolygonHandler.save();
    window.editPolygonHandler.disable();
    var saveBtn = document.getElementById('btn-save-edit');
    if (saveBtn) saveBtn.style.display = 'none';
    var editBtn = document.getElementById('btn-edit-roof');
    if (editBtn) editBtn.style.display = 'inline-block';
  }
}

// Add event listener for when editing finishes
document.addEventListener('DOMContentLoaded', function() {
  if (window.leafletMap) {
    window.leafletMap.on('draw:edited', function (e) {
      // Access updated layers
      var geojson = window.drawnItems.toGeoJSON();
      if (geojson.features.length > 0) {
        window.drawnGeoJSON = geojson.features[0].geometry;
      }
      if (typeof showToast === 'function') {
        showToast('Roof boundary updated successfully!', 'success');
      }
    });
  }
});

function triggerDeletePolygon() {
  if (window.drawnItems) {
    window.drawnItems.clearLayers();
  }
  window.drawnGeoJSON = null;
  if (typeof showToast === 'function') {
    showToast('Drawn area cleared.', 'info');
  }
}

function renderDamageOverlay(detections) {
  if (!leafletMap) return;

  // Clear old markers
  damageMarkers.forEach(function(m) { leafletMap.removeLayer(m); });
  damageMarkers = [];

  var bounds = leafletMap.getBounds();
  var ne     = bounds.getNorthEast();
  var sw     = bounds.getSouthWest();
  var imgW   = ne.lng - sw.lng;
  var imgH   = ne.lat - sw.lat;

  var severityColors = {
    'Critical': '#FF1744',
    'High':     '#FF6D00',
    'Medium':   '#FFCA28',
    'Low':      '#69F0AE',
    'critical': '#FF1744',
    'high':     '#FF6D00',
    'medium':   '#FFCA28',
    'low':      '#69F0AE',
  };

  detections.forEach(function(det) {
    // det.bbox_pct: {x1,y1,x2,y2} in 0-1 range
    var bp = det.bbox_pct;
    if (!bp) return;

    var lat1 = sw.lat + (1 - bp.y2) * imgH;
    var lat2 = sw.lat + (1 - bp.y1) * imgH;
    var lng1 = sw.lng + bp.x1 * imgW;
    var lng2 = sw.lng + bp.x2 * imgW;

    var color = severityColors[det.severity] || '#FFCA28';
    var rect = L.rectangle([[lat1, lng1], [lat2, lng2]], {
      color: color,
      fillColor: color,
      fillOpacity: 0.2,
      weight: 2,
      dashArray: '4',
    }).addTo(leafletMap);

    var conf = det.confidence ? Math.round(det.confidence * 100) + '%' : '';
    rect.bindPopup(
      '<div style="font-family:Inter,sans-serif;min-width:180px;">' +
      '<b style="color:' + color + ';">' + (det.label || det.class) + '</b><br>' +
      'Severity: <b>' + det.severity + '</b><br>' +
      'Confidence: ' + conf +
      '</div>',
      { maxWidth: 220 }
    );

    damageMarkers.push(rect);
  });
}

document.addEventListener('DOMContentLoaded', function() {
  if (typeof L !== 'undefined') {
    initLeafletDamageMap();
  }
});
