/**
 * ROOFIQ AI ENTERPRISE — CesiumJS 3D Viewer Engine
 * Handles: Eagle Eye, Top, N/S/E/W, Isometric, Street, Auto-Rotate
 */

var viewer = null;
var rotateInterval = null;
var isRotating = false;
var currentView = 'eagle';

function initCesiumViewer() {
  if (!ROOFIQ.lat || !ROOFIQ.lng) {
    var loadingEl = document.getElementById('viewer-loading');
    if (loadingEl) loadingEl.style.display = 'none';
    return;
  }

  if (ROOFIQ.cesiumToken) {
    Cesium.Ion.defaultAccessToken = ROOFIQ.cesiumToken;
  } else {
    Cesium.Ion.defaultAccessToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJqdGkiOiJlYWE1OWUxNy1mMWZiLTQzYjYtYTQ0OS1kMWFjYmFkNjc5YzciLCJpZCI6NTc3MzMsImlhdCI6MTYyNzg0NTE4Mn0.XcKpgANiY19MC4bdFUXMVEBToBmqS8kuYpUlxJHYZxk';
  }

  try {
    var viewerOptions = {
      baseLayerPicker:  false,
      geocoder:         false,
      homeButton:       false,
      sceneModePicker:  false,
      navigationHelpButton: false,
      animation:        false,
      timeline:         false,
      fullscreenButton: false,
      vrButton:         false,
      infoBox:          false,
      selectionIndicator: false,
      creditContainer:  document.createElement('div'),
    };

    try {
      if (typeof Cesium.createWorldTerrain === 'function') {
        viewerOptions.terrainProvider = Cesium.createWorldTerrain({
          requestVertexNormals: true,
          requestWaterMask: false,
        });
      }
    } catch (terrainError) {
      console.warn('Cesium terrain provider failed, using default ellipsoid:', terrainError);
    }

    viewer = new Cesium.Viewer('cesiumContainer', viewerOptions);

    try {
      viewer.imageryLayers.removeAll();
      // Use Esri World Imagery (high-resolution, keyless satellite maps)
      viewer.imageryLayers.addImageryProvider(new Cesium.UrlTemplateImageryProvider({
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        maximumLevel: 19,
        credit: 'Tiles © Esri — Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
      }));
    } catch (imageryError) {
      console.warn('Satellite imagery provider failed, using default:', imageryError);
    }

    viewer.scene.globe.enableLighting = true;
    setView('eagle');

    if (ROOFIQ.footprint) {
      addFootprintOverlay();
    }

    setTimeout(function() {
      var el = document.getElementById('viewer-loading');
      if (el) { el.style.opacity = '0'; setTimeout(function(){ el.style.display = 'none'; }, 400); }
    }, 2000);

    viewer.camera.changed.addEventListener(function() {
      var c = viewer.camera.positionCartographic;
      var coordsEl = document.getElementById('viewer-coords');
      if (coordsEl) {
        coordsEl.textContent =
          Cesium.Math.toDegrees(c.latitude).toFixed(5) + ', ' + Cesium.Math.toDegrees(c.longitude).toFixed(5);
      }
    });

  } catch (globalError) {
    console.error('CesiumJS initialization failed globally:', globalError);
    var container = document.getElementById('cesiumContainer');
    if (container) {
      container.innerHTML = '<div style="padding:40px;color:#ff6b6b;text-align:center;background:#111827;border-radius:12px;border:1px solid rgba(255,23,68,0.2);margin-top:20px;">' +
        '<h5 style="font-weight:700;"><i class="fas fa-exclamation-triangle mr-2"></i>3D Viewer Error</h5>' +
        '<p style="font-size:0.82rem;color:#94a3b8;margin-top:8px;">CesiumJS failed to load or initialize. ' +
        'Please check your network connection and ensure your API credentials (Cesium Ion Token) are configured in Settings.</p>' +
        '<p style="font-size:0.75rem;color:rgba(255,255,255,0.4);margin-top:8px;">Error: ' + globalError.message + '</p>' +
        '</div>';
    }
    var loadingEl = document.getElementById('viewer-loading');
    if (loadingEl) loadingEl.style.display = 'none';
  }
}

function getPropertyDestination(heightMeters) {
  return {
    destination: Cesium.Cartesian3.fromDegrees(ROOFIQ.lng, ROOFIQ.lat, heightMeters),
  };
}

function setView(viewName) {
  // Update active button
  document.querySelectorAll('.view-preset-btn').forEach(function(b) {
    b.style.background = 'rgba(0, 0, 0, 0.4)';
    b.style.color = '#94a3b8';
    b.style.borderColor = 'rgba(255,255,255,0.06)';
  });
  
  // Highlight clicked preset button
  var clickedBtn = Array.from(document.querySelectorAll('.view-preset-btn')).find(function(b) {
    return b.textContent.includes(viewName === 'eagle' ? 'Eagle' : (viewName === 'iso' ? 'Isometric' : (viewName === 'street' ? 'Street' : (viewName === 'top' ? 'Top' : viewName.toUpperCase()))));
  });
  if (clickedBtn) {
    clickedBtn.style.background = 'rgba(0, 212, 255, 0.12)';
    clickedBtn.style.color = '#00d4ff';
    clickedBtn.style.borderColor = 'rgba(0, 212, 255, 0.3)';
  }

  if (!viewer) {
    // If not in 3D mode, notify user and toggle to 3D mode
    setViewMode('3d');
    if (!viewer) return;
  }
  
  currentView = viewName;
  var hm = ROOFIQ.lat ? (ROOFIQ.lat < 60 ? 250 : 300) : 250; // Height in meters

  var flyOpts;
  switch (viewName) {
    case 'eagle':
      flyOpts = {
        destination: Cesium.Cartesian3.fromDegrees(ROOFIQ.lng, ROOFIQ.lat - 0.0003, hm),
        orientation: { heading: Cesium.Math.toRadians(0), pitch: Cesium.Math.toRadians(-45), roll: 0 }
      };
      document.getElementById('viewer-status').textContent = '🦅 Eagle Eye View';
      break;
    case 'top':
      flyOpts = {
        destination: Cesium.Cartesian3.fromDegrees(ROOFIQ.lng, ROOFIQ.lat, hm * 0.8),
        orientation: { heading: Cesium.Math.toRadians(0), pitch: Cesium.Math.toRadians(-90), roll: 0 }
      };
      document.getElementById('viewer-status').textContent = '⬆ Top-Down View';
      break;
    case 'north':
      flyOpts = {
        destination: Cesium.Cartesian3.fromDegrees(ROOFIQ.lng, ROOFIQ.lat - 0.0004, hm * 0.6),
        orientation: { heading: Cesium.Math.toRadians(0), pitch: Cesium.Math.toRadians(-30), roll: 0 }
      };
      document.getElementById('viewer-status').textContent = '↑ North View';
      break;
    case 'south':
      flyOpts = {
        destination: Cesium.Cartesian3.fromDegrees(ROOFIQ.lng, ROOFIQ.lat + 0.0004, hm * 0.6),
        orientation: { heading: Cesium.Math.toRadians(180), pitch: Cesium.Math.toRadians(-30), roll: 0 }
      };
      document.getElementById('viewer-status').textContent = '↓ South View';
      break;
    case 'east':
      flyOpts = {
        destination: Cesium.Cartesian3.fromDegrees(ROOFIQ.lng - 0.0004, ROOFIQ.lat, hm * 0.6),
        orientation: { heading: Cesium.Math.toRadians(90), pitch: Cesium.Math.toRadians(-30), roll: 0 }
      };
      document.getElementById('viewer-status').textContent = '→ East View';
      break;
    case 'west':
      flyOpts = {
        destination: Cesium.Cartesian3.fromDegrees(ROOFIQ.lng + 0.0004, ROOFIQ.lat, hm * 0.6),
        orientation: { heading: Cesium.Math.toRadians(270), pitch: Cesium.Math.toRadians(-30), roll: 0 }
      };
      document.getElementById('viewer-status').textContent = '← West View';
      break;
    case 'iso':
      flyOpts = {
        destination: Cesium.Cartesian3.fromDegrees(ROOFIQ.lng - 0.0003, ROOFIQ.lat - 0.0003, hm * 0.7),
        orientation: { heading: Cesium.Math.toRadians(45), pitch: Cesium.Math.toRadians(-35), roll: 0 }
      };
      document.getElementById('viewer-status').textContent = '◱ Isometric View';
      break;
    case 'street':
      flyOpts = {
        destination: Cesium.Cartesian3.fromDegrees(ROOFIQ.lng, ROOFIQ.lat - 0.0006, 60),
        orientation: { heading: Cesium.Math.toRadians(0), pitch: Cesium.Math.toRadians(-15), roll: 0 }
      };
      document.getElementById('viewer-status').textContent = '🚶 Street View';
      break;
    default:
      return;
  }

  viewer.camera.flyTo(Object.assign({ duration: 1.5 }, flyOpts));
}

function resetCamera() {
  setView('eagle');
}

function zoomIn() {
  if (!viewer) return;
  viewer.camera.zoomIn(viewer.camera.positionCartographic.height * 0.3);
}

function zoomOut() {
  if (!viewer) return;
  viewer.camera.zoomOut(viewer.camera.positionCartographic.height * 0.4);
}

function toggleRotate() {
  if (!viewer) {
    setViewMode('3d');
    if (!viewer) return;
  }
  
  var btn = document.getElementById('btn-rotate');
  if (isRotating) {
    clearInterval(rotateInterval);
    rotateInterval = null;
    isRotating = false;
    if (btn) btn.style.color = '';
    document.getElementById('viewer-status').textContent = '3D View';
    // Clear camera lock range
    viewer.camera.lookAtTransform(Cesium.Matrix4.IDENTITY);
  } else {
    isRotating = true;
    if (btn) btn.style.color = '#00d4ff';
    document.getElementById('viewer-status').textContent = '⟳ Auto-Rotating...';
    var heading = viewer.camera.heading;
    rotateInterval = setInterval(function() {
      if (!viewer) return;
      heading += Cesium.Math.toRadians(0.4);
      var h = viewer.camera.positionCartographic.height;
      viewer.camera.lookAt(
        Cesium.Cartesian3.fromDegrees(ROOFIQ.lng, ROOFIQ.lat),
        new Cesium.HeadingPitchRange(heading, Cesium.Math.toRadians(-40), h)
      );
    }, 50);
  }
}

function toggleFullscreen() {
  var el = document.getElementById('cesium-viewer-container');
  if (!document.fullscreenElement) {
    el.requestFullscreen();
  } else {
    document.exitFullscreen();
  }
}

function screenshotViewer() {
  if (!viewer) return;
  viewer.render();
  var canvas = viewer.scene.canvas;
  var link = document.createElement('a');
  link.download = 'roofiq-screenshot-' + Date.now() + '.png';
  link.href = canvas.toDataURL();
  link.click();
}

function addFootprintOverlay() {
  if (!viewer || !ROOFIQ.footprint) return;
  try {
    var fp = ROOFIQ.footprint;
    var positions = [];
    if (fp.coordinates && fp.coordinates[0]) {
      fp.coordinates[0].forEach(function(pt) {
        positions.push(Cesium.Cartesian3.fromDegrees(pt[0], pt[1]));
      });
    }
    if (positions.length > 2) {
      viewer.entities.add({
        polygon: {
          hierarchy: positions,
          material: new Cesium.ColorMaterialProperty(Cesium.Color.fromCssColorString('#00d4ff').withAlpha(0.15)),
          outline: true,
          outlineColor: Cesium.Color.fromCssColorString('#00d4ff'),
          outlineWidth: 2,
          heightReference: Cesium.HeightReference.CLAMP_TO_GROUND,
        }
      });
    }
  } catch(e) {
    console.warn('Footprint overlay error:', e);
  }
}

// ---- Mode switching ----
function setViewMode(mode) {
  // Update tab buttons
  document.querySelectorAll('.view-mode-btn').forEach(function(b) {
    var active = b.getAttribute('data-mode') === mode;
    b.style.background = active ? 'rgba(0,212,255,0.12)' : 'transparent';
    b.style.color = active ? '#00d4ff' : '#94a3b8';
    b.style.borderColor = active ? 'rgba(0,212,255,0.3)' : 'rgba(255,255,255,0.12)';
  });

  var cesiumEl  = document.getElementById('cesium-viewer-container');
  var mapEl     = document.getElementById('maplibre-container');
  var leafletEl = document.getElementById('leaflet-wrapper');

  cesiumEl.style.display  = mode === '3d'     ? 'block' : 'none';
  mapEl.style.display     = mode === '2d'     ? 'block' : 'none';
  leafletEl.style.display = mode === 'damage' ? 'flex'  : 'none';

  if (mode === '2d' && window.maplibreMap) {
    window.maplibreMap.resize();
  }
  if (mode === 'damage' && window.leafletMap) {
    window.leafletMap.invalidateSize();
  }
}

function switchPanel(panelId) {
  document.querySelectorAll('.analysis-panel').forEach(function(p) {
    p.style.display = 'none';
  });
  var target = document.getElementById(panelId);
  if (target) {
    target.style.display = 'block';
  }

  // Update sidebar buttons
  document.querySelectorAll('.panel-nav-btn').forEach(function(b) {
    var active = b.getAttribute('data-panel') === panelId;
    b.style.background = active ? 'rgba(0,212,255,0.12)' : 'transparent';
    b.style.color = active ? '#00d4ff' : '#94a3b8';
  });
}

// ---- Initialize on page load ----
document.addEventListener('DOMContentLoaded', function() {
  if (typeof Cesium !== 'undefined' && ROOFIQ.lat) {
    initCesiumViewer();
  } else if (ROOFIQ.lat) {
    // Cesium not loaded — try MapLibre
    setViewMode('2d');
  }
});
