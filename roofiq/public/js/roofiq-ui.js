/**
 * ROOFIQ AI ENTERPRISE — Main UI Controller
 * AI Analysis, Report Generation, Panel Switching, Procurement
 */

// Ensure ROOFIQ global is always defined (may be overridden by analysis.php)
window.ROOFIQ = window.ROOFIQ || {};

// ---- AI Analysis ----
function runAIAnalysis(customFootprint) {
  var btn = document.getElementById('btn-ai-full');
  var progress = document.getElementById('ai-progress');

  // If no propertyId is present, we must ensure we have coordinates
  if (!ROOFIQ.propertyId && (!ROOFIQ.lat || !ROOFIQ.lng)) {
    alert('Please search for a property or click on the map first.');
    return;
  }

  if (btn)      { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Analyzing...'; }
  if (progress) { progress.style.display = 'block'; }

  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'api/ai_analyze.php', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

  xhr.onreadystatechange = function() {
    if (xhr.readyState !== 4) return;

    if (btn)      { btn.disabled = false; btn.innerHTML = '<i class="fas fa-brain mr-2"></i> Run Full AI Analysis'; }
    if (progress) { progress.style.display = 'none'; }

    if (xhr.status === 200) {
      try {
        var data = JSON.parse(xhr.responseText);
        if (data.success) {
          // Sync dynamically resolved property registry details
          if (data.property_id) {
            ROOFIQ.propertyId = data.property_id;
          }
          if (data.address) {
            ROOFIQ.address = data.address;
            var addrInput = document.getElementById('address-input');
            if (addrInput) addrInput.value = data.address;
          }
          
          updateUIFromAnalysis(data);
          // Auto-refresh material takeoff with new measurements
          var typeSelect = document.getElementById('roof-type-select');
          var roofType = typeSelect ? typeSelect.value : 'Residential';
          updateTakeoff(roofType);
          showToast('AI Analysis complete! Results updated.', 'success');
        } else {
          showToast('Analysis error: ' + (data.error || 'Unknown'), 'error');
        }
      } catch(e) {
        showToast('Response parse error: ' + e.message, 'error');
      }
    } else {
      showToast('Server error: HTTP ' + xhr.status, 'error');
    }
  };

  var payload = {
    property_id: ROOFIQ.propertyId,
    lat:         ROOFIQ.lat,
    lng:         ROOFIQ.lng,
    address:     ROOFIQ.address,
  };
  if (customFootprint) {
    payload.footprint = customFootprint;
    var overPitch = document.getElementById('over-pitch');
    if (overPitch && overPitch.value) {
      payload.pitch_deg = parseFloat(overPitch.value);
    }
  }

  xhr.send(JSON.stringify(payload));
}

function updateUIFromAnalysis(data) {
  if (!data) return;

  // Store measurements in ROOFIQ.roofData for takeoff
  if (!ROOFIQ.roofData) ROOFIQ.roofData = {};
  if (data.roof_area_sqft)  ROOFIQ.roofData.roof_area_sqft  = data.roof_area_sqft;
  if (data.roof_squares)    ROOFIQ.roofData.roof_squares     = data.roof_squares;
  if (data.roof_pitch_deg)  ROOFIQ.roofData.roof_pitch_deg   = data.roof_pitch_deg;
  if (data.perimeter_ft)    ROOFIQ.roofData.perimeter_ft     = data.perimeter_ft;
  if (data.ridge_length_ft) ROOFIQ.roofData.ridge_length_ft  = data.ridge_length_ft;

  // Condition score
  if (data.condition_score) {
    var el = document.getElementById('score-display');
    if (el) el.textContent = data.condition_score;
    var el2 = document.getElementById('score-label');
    if (el2) { el2.textContent = data.condition_label || ''; el2.style.color = data.condition_color || '#00e676'; }
  }

  // Measurements
  var measMap = {
    'roof_area_sqft':   function(v) { return numberFmt(v, 1) + ' sf'; },
    'roof_squares':     function(v) { return numberFmt(v, 2); },
    'roof_pitch_deg':   function(v) { return numberFmt(v, 1) + '°'; },
    'ridge_length_ft':  function(v) { return numberFmt(v, 1) + ' ft'; },
  };

  Object.keys(measMap).forEach(function(key) {
    var els = document.querySelectorAll('.metric-' + key);
    els.forEach(function(el) {
      if (data[key] !== undefined && data[key] !== null) el.textContent = measMap[key](data[key]);
    });
  });

  // Populate override inputs
  var overArea = document.getElementById('over-area');
  var overPitch = document.getElementById('over-pitch');
  var overPerimeter = document.getElementById('over-perimeter');
  var overRidge = document.getElementById('over-ridge');

  if (overArea) overArea.value = Math.round(data.roof_area_sqft || 2500);
  if (overPitch) overPitch.value = parseFloat(data.roof_pitch_deg || 22).toFixed(1);
  if (overPerimeter) overPerimeter.value = Math.round(data.perimeter_ft || 220);
  if (overRidge) overRidge.value = Math.round(data.ridge_length_ft || 50);

  // Roof type
  if (data.roof_type) {
    var rtEl = document.getElementById('roof-type-display');
    if (rtEl) rtEl.textContent = data.roof_type;
    var rcEl = document.getElementById('roof-type-confidence');
    if (rcEl) rcEl.textContent = data.roof_type_confidence ? 'Confidence: ' + data.roof_type_confidence + '%' : '';
  }

  // Damage counts
  if (data.damage_counts) {
    var sev = ['critical','high','medium','low'];
    sev.forEach(function(s) {
      var el = document.getElementById('dmg-' + s);
      if (el) el.textContent = data.damage_counts[s] || 0;
    });
  }

  // Damage overlay
  if (data.detections && window.leafletMap) {
    renderDamageOverlay(data.detections);
  }

  // Update material total
  if (data.material_total) {
    var el = document.getElementById('material-total-quick');
    if (el) el.textContent = '$' + numberFmt(data.material_total, 2);
  }

  // ROOFIQ.analysisId for report
  if (data.analysis_id) {
    ROOFIQ.analysisId = data.analysis_id;
  }

  // Update Government Details panel dynamically
  var govNameEl = document.getElementById('gov-prop-name');
  var govAreaEl = document.getElementById('gov-land-area');
  if (govNameEl && ROOFIQ.address) {
    govNameEl.textContent = ROOFIQ.address.split(',')[0];
  }
  if (govAreaEl && data.roof_area_sqft) {
    // Land area is estimated as ~3.5x of the footprint base area (or ~3x of roof area)
    var landArea = Math.round(data.roof_area_sqft * 3);
    govAreaEl.textContent = numberFmt(landArea, 0) + ' sq ft';
  }

  // Update sections
  if (data.sections) {
    ROOFIQ.sections = data.sections;
    updateFacetsUI();
  }
}

// ---- Report Generation ----
function generateReport() {
  if (!ROOFIQ.propertyId) {
    alert('Please analyze a property first.');
    return;
  }

  var rtype = document.getElementById('report-type');
  var notes = document.getElementById('report-notes');
  var params = 'property_id=' + ROOFIQ.propertyId
    + (ROOFIQ.analysisId ? '&analysis_id=' + ROOFIQ.analysisId : '')
    + '&report_type=' + encodeURIComponent(rtype ? rtype.value : 'Full Analysis Report')
    + '&notes=' + encodeURIComponent(notes ? notes.value : '');

  window.open('report/generate.php?' + params, '_blank');
}

// ---- Vendor Selection ----
function selectVendor(vendorId) {
  document.querySelectorAll('.vendor-card').forEach(function(c) {
    c.classList.remove('selected');
  });
  var card = document.querySelector('[data-vendor="' + vendorId + '"]');
  if (card) card.classList.add('selected');
  window._selectedVendorId = vendorId;
}

// ---- Procurement ----
function createProcurement() {
  if (!ROOFIQ.propertyId) { alert('Analyze a property first.'); return; }
  var vendorId = window._selectedVendorId || '';
  window.location.href = 'projects.php?new=1&property_id=' + ROOFIQ.propertyId + '&vendor_id=' + vendorId;
}

// ---- Takeoff type change ----
function updateTakeoff(roofType) {
  var xhr = new XMLHttpRequest();
  xhr.open('GET', 'api/materials.php?action=takeoff&property_id=' + ROOFIQ.propertyId + '&roof_type=' + encodeURIComponent(roofType), true);
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
  xhr.onreadystatechange = function() {
    if (xhr.readyState !== 4 || xhr.status !== 200) return;
    try {
      var data = JSON.parse(xhr.responseText);
      if (data.html) {
        var el = document.getElementById('takeoff-container');
        if (el) el.innerHTML = data.html;
      }
    } catch(e) {}
  };
  xhr.send();
}

// ---- Toast Notifications ----
function showToast(message, type) {
  type = type || 'info';
  var colors = { success: '#00e676', error: '#ff1744', info: '#00d4ff', warning: '#ffd600' };
  var icons  = { success: 'check-circle', error: 'exclamation-triangle', info: 'info-circle', warning: 'exclamation-circle' };
  var color  = colors[type] || colors.info;
  var icon   = icons[type]  || icons.info;

  var el = document.createElement('div');
  el.style.cssText = [
    'position:fixed', 'bottom:24px', 'right:24px', 'z-index:9999',
    'background:#111827', 'border:1px solid ' + color,
    'border-radius:10px', 'padding:14px 20px', 'max-width:340px',
    'display:flex', 'align-items:center', 'gap:12px',
    'box-shadow:0 8px 32px rgba(0,0,0,0.5)',
    'font-family:Inter,sans-serif', 'font-size:0.85rem', 'color:#e2e8f0',
    'animation:slideUp 0.3s ease',
    'transition:opacity 0.3s',
  ].join(';');

  el.innerHTML = '<i class="fas fa-' + icon + '" style="color:' + color + ';font-size:1.1rem;"></i>' +
                 '<span>' + message + '</span>';

  // Add keyframe
  if (!document.getElementById('toast-style')) {
    var s = document.createElement('style');
    s.id = 'toast-style';
    s.textContent = '@keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}';
    document.head.appendChild(s);
  }

  document.body.appendChild(el);
  setTimeout(function() {
    el.style.opacity = '0';
    setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 300);
  }, 3500);
}

// ---- Save Overrides ----
function saveMeasurementOverrides(e) {
  e.preventDefault();
  if (!ROOFIQ.analysisId) {
    alert('Please run AI analysis first to create an analysis record before overriding.');
    return;
  }

  var btn = document.getElementById('btn-save-overrides');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...'; }

  var payload = {
    analysis_id:     ROOFIQ.analysisId,
    roof_area_sqft:  parseFloat(document.getElementById('over-area').value),
    roof_pitch_deg:  parseFloat(document.getElementById('over-pitch').value),
    perimeter_ft:    parseFloat(document.getElementById('over-perimeter').value),
    ridge_length_ft: parseFloat(document.getElementById('over-ridge').value),
  };

  var xhr = new XMLHttpRequest();
  xhr.open('POST', 'api/save_measurements.php', true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

  xhr.onreadystatechange = function() {
    if (xhr.readyState !== 4) return;
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i> Save & Recalculate'; }

    if (xhr.status === 200) {
      try {
        var data = JSON.parse(xhr.responseText);
        if (data.success) {
          showToast('Measurements updated! Recalculating takeoff...', 'success');
          
          // Update local ROOFIQ values
          if (!ROOFIQ.roofData) ROOFIQ.roofData = {};
          ROOFIQ.roofData.roof_area_sqft = payload.roof_area_sqft;
          ROOFIQ.roofData.roof_squares = data.squares;
          ROOFIQ.roofData.roof_pitch_deg = payload.roof_pitch_deg;
          ROOFIQ.roofData.perimeter_ft = payload.perimeter_ft;
          ROOFIQ.roofData.ridge_length_ft = payload.ridge_length_ft;

          // Update main UI displays
          updateUIFromAnalysis(ROOFIQ.roofData);

          // Update takeoff
          var typeSelect = document.getElementById('roof-type-select');
          var roofType = typeSelect ? typeSelect.value : 'Residential';
          updateTakeoff(roofType);
        } else {
          showToast('Override error: ' + (data.error || 'Unknown'), 'error');
        }
      } catch(err) {
        showToast('Error parsing server response.', 'error');
      }
    } else {
      showToast('HTTP server error: ' + xhr.status, 'error');
    }
  };
  xhr.send(JSON.stringify(payload));
}

// ---- Helpers ----
function numberFmt(n, d) {
  return parseFloat(n).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });
}

function analyzeDrawnArea() {
  if (!window.drawnGeoJSON) {
    showToast('Please trace a roof polygon first using [Draw Roof].', 'warning');
    return;
  }
  showToast('Analyzing drawn area...', 'info');
  runAIAnalysis(window.drawnGeoJSON);
}

function generateTakeoffFromDrawn() {
  var select = document.getElementById('roof-type-select');
  var roofType = select ? select.value : 'Residential';
  updateTakeoff(roofType);
  showToast('Takeoff generated for drawn area!', 'success');
}

function getCardinalDirection(azimuth) {
  var directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
  var index = Math.round(((azimuth % 360) / 45)) % 8;
  return directions[index];
}

function getConditionColor(score) {
  if (score >= 90) return '#00e676';
  if (score >= 75) return '#69f0ae';
  if (score >= 60) return '#ffca28';
  return '#ff6d00';
}

function updateFacetsUI() {
  if (ROOFIQ.sections && ROOFIQ.sections.length > 0) {
    var tbody = document.getElementById('roof-facets-tbody');
    var badge = document.getElementById('facets-count-badge');
    if (badge) {
      badge.textContent = ROOFIQ.sections.length + ' Facet' + (ROOFIQ.sections.length !== 1 ? 's' : '');
    }
    if (tbody) {
      var html = '';
      ROOFIQ.sections.forEach(function(sec) {
        var area = sec.area_sqft || sec.area || 0;
        var pitch = sec.pitch_deg || (ROOFIQ.roofData ? ROOFIQ.roofData.roof_pitch_deg : 22) || 22;
        var azimuth = sec.azimuth !== undefined ? sec.azimuth : 0;
        if (sec.name === 'South Slope') azimuth = 180;
        else if (sec.name === 'North Slope') azimuth = 0;
        else if (sec.name === 'East Slope') azimuth = 90;
        else if (sec.name === 'West Slope') azimuth = 270;

        var orient = sec.orientation || getCardinalDirection(azimuth);
        var cond = sec.condition_score || sec.condition || 80;

        html += '<tr>' +
          '<td style="font-weight:600;"><i class="fas fa-play text-cyan mr-1" style="font-size:0.65rem;"></i>' + sec.name + '</td>' +
          '<td class="text-right" style="font-weight:700;">' + numberFmt(area, 1) + ' sf</td>' +
          '<td class="text-right">' + parseFloat(pitch).toFixed(1) + '°</td>' +
          '<td class="text-right">' + azimuth + '°</td>' +
          '<td><span class="badge badge-dark">' + orient + '</span></td>' +
          '<td class="text-right" style="font-weight:700; color:' + getConditionColor(cond) + ';">' + cond + '</td>' +
          '</tr>';
      });
      tbody.innerHTML = html;
    }
  }
}

// ---- Init ----
document.addEventListener('DOMContentLoaded', function() {
  var form = document.getElementById('search-form');
  if (form) {
    form.addEventListener('submit', function() {
      var btn = document.getElementById('btn-analyze');
      if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Analyzing...'; }
    });
  }

  var firstBtn = document.querySelector('.panel-nav-btn');
  if (firstBtn) {
    firstBtn.style.background = 'rgba(0,212,255,0.12)';
    firstBtn.style.color      = '#00d4ff';
  }

  // Populate initial inputs if available
  if (ROOFIQ.roofData) {
    var overArea = document.getElementById('over-area');
    var overPitch = document.getElementById('over-pitch');
    var overPerimeter = document.getElementById('over-perimeter');
    var overRidge = document.getElementById('over-ridge');

    if (overArea && !overArea.value) overArea.value = Math.round(ROOFIQ.roofData.roof_area_sqft || 2500);
    if (overPitch && !overPitch.value) overPitch.value = parseFloat(ROOFIQ.roofData.roof_pitch_deg || 22).toFixed(1);
    if (overPerimeter && !overPerimeter.value) overPerimeter.value = Math.round(ROOFIQ.roofData.perimeter_ft || 220);
    if (overRidge && !overRidge.value) overRidge.value = Math.round(ROOFIQ.roofData.ridge_length_ft || 50);
  }

  if (ROOFIQ.sections) {
    updateFacetsUI();
  }
});
