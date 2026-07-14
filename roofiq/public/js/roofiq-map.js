/**
 * ROOFIQ AI ENTERPRISE — MapLibre GL JS 2D Engine
 * Satellite imagery, building footprint rendering
 */

var maplibreMap = null;

function initMapLibre() {
  if (!ROOFIQ.lat || !ROOFIQ.lng) return;

  var style;
  if (ROOFIQ.maptilerKey) {
    style = 'https://api.maptiler.com/maps/hybrid/style.json?key=' + ROOFIQ.maptilerKey;
  } else {
    // Free OSM raster fallback
    style = {
      version: 8,
      sources: {
        'osm-tiles': {
          type: 'raster',
          tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
          tileSize: 256,
          attribution: '© OpenStreetMap',
        },
        'esri-sat': {
          type: 'raster',
          tiles: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'],
          tileSize: 256,
          attribution: '© Esri',
          maxzoom: 20,
        },
      },
      layers: [
        { id: 'esri-sat', type: 'raster', source: 'esri-sat' },
      ],
    };
  }

  maplibreMap = new maplibregl.Map({
    container: 'maplibre-container',
    style: style,
    center: [ROOFIQ.lng, ROOFIQ.lat],
    zoom: 18,
    pitch: 0,
    bearing: 0,
  });

  maplibreMap.addControl(new maplibregl.NavigationControl(), 'top-right');
  maplibreMap.addControl(new maplibregl.FullscreenControl(), 'top-right');

  maplibreMap.on('load', function() {
    // Add building footprint if available
    if (ROOFIQ.footprint) {
      maplibreMap.addSource('footprint', {
        type: 'geojson',
        data: { type: 'Feature', geometry: ROOFIQ.footprint, properties: {} }
      });
      maplibreMap.addLayer({
        id: 'footprint-fill',
        type: 'fill',
        source: 'footprint',
        paint: { 'fill-color': '#00d4ff', 'fill-opacity': 0.15 }
      });
      maplibreMap.addLayer({
        id: 'footprint-outline',
        type: 'line',
        source: 'footprint',
        paint: { 'line-color': '#00d4ff', 'line-width': 2, 'line-opacity': 0.9 }
      });
    }

    // Add center marker
    window.mapMarker = new maplibregl.Marker({ color: '#00d4ff', draggable: true })
      .setLngLat([ROOFIQ.lng, ROOFIQ.lat])
      .setPopup(new maplibregl.Popup({ closeButton: false }).setText(ROOFIQ.address))
      .addTo(maplibreMap);

    // Click handler to select roof location
    maplibreMap.on('click', function(e) {
      var lat = e.lngLat.lat;
      var lng = e.lngLat.lng;
      ROOFIQ.lat = lat;
      ROOFIQ.lng = lng;
      window.mapMarker.setLngLat([lng, lat]);
      
      // Update coordinates status display
      var coordsEl = document.getElementById('viewer-coords');
      if (coordsEl) {
        coordsEl.textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);
      }
      
      // If Cesium is active, sync camera destination
      if (window.viewer) {
        var hm = lat < 60 ? 250 : 300;
        window.viewer.camera.flyTo({
          destination: Cesium.Cartesian3.fromDegrees(lng, lat - 0.0003, hm),
          orientation: { heading: Cesium.Math.toRadians(0), pitch: Cesium.Math.toRadians(-45), roll: 0 }
        });
      }

      if (typeof showToast === 'function') {
        showToast('Selected roof at: ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + '. Click "Run Full AI Analysis" to begin.', 'info');
      }
    });
  });

  window.maplibreMap = maplibreMap;
}

document.addEventListener('DOMContentLoaded', function() {
  if (typeof maplibregl !== 'undefined') {
    initMapLibre();
  }
});
