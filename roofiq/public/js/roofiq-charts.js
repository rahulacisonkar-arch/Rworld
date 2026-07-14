/**
 * ROOFIQ AI ENTERPRISE — Chart.js Dashboard Charts
 */

// Chart defaults
Chart.defaults.color = '#94a3b8';
Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';

function initDashboardCharts() {
  // Daily analyses chart
  var dailyCanvas = document.getElementById('dailyChart');
  if (dailyCanvas && typeof dailyLabels !== 'undefined') {
    if (window.dailyChartInstance) {
      window.dailyChartInstance.destroy();
    }
    window.dailyChartInstance = new Chart(dailyCanvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels: dailyLabels,
        datasets: [{
          label: 'Analyses',
          data: dailyCounts,
          backgroundColor: 'rgba(0,212,255,0.25)',
          borderColor: '#00d4ff',
          borderWidth: 2,
          borderRadius: 6,
          hoverBackgroundColor: 'rgba(0,212,255,0.5)',
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#111827',
            borderColor: 'rgba(0,212,255,0.3)',
            borderWidth: 1,
          }
        },
        scales: {
          x: { grid: { color: 'rgba(255,255,255,0.04)' } },
          y: { grid: { color: 'rgba(255,255,255,0.04)' }, beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  }

  // Project status donut
  var projCanvas = document.getElementById('projectChart');
  if (projCanvas && typeof projLabels !== 'undefined') {
    var chartColors = ['#00d4ff','#00e676','#ffd600','#ff6b35','#a78bfa','#ff1744','#69F0AE','#ff8f00'];
    var displayLabels = projLabels.length > 0 ? projLabels : ['No Projects'];
    var displayVals   = projVals.length > 0   ? projVals   : [1];
    if (window.projectChartInstance) {
      window.projectChartInstance.destroy();
    }
    window.projectChartInstance = new Chart(projCanvas.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: displayLabels,
        datasets: [{
          data: displayVals,
          backgroundColor: chartColors.slice(0, displayLabels.length),
          borderColor: '#111827',
          borderWidth: 3,
          hoverOffset: 8,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { padding: 12, font: { size: 11 }, boxWidth: 12 }
          },
          tooltip: {
            backgroundColor: '#111827',
            borderColor: 'rgba(255,255,255,0.12)',
            borderWidth: 1,
          }
        },
        cutout: '60%',
      }
    });
  }

  // Solar chart (analysis page)
  var solarCanvas = document.getElementById('solarChart');
  if (solarCanvas) {
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var peak   = [3.2, 3.8, 4.5, 5.1, 5.6, 6.0, 5.9, 5.7, 5.0, 4.2, 3.5, 3.1];
    var labelName = 'Avg Sun Hours (Daily)';
    
    if (typeof ROOFIQ !== 'undefined' && ROOFIQ.roofData && ROOFIQ.roofData.weather && ROOFIQ.roofData.weather.monthly_solar_kwh_m2) {
      var monthlyRadiation = ROOFIQ.roofData.weather.monthly_solar_kwh_m2;
      var areaSqft = ROOFIQ.roofData.roof_area_sqft;
      var areaM2 = areaSqft / 10.7639;
      var usableAreaM2 = areaM2 * 0.5; // 50% usable area
      var efficiency = 0.18;
      var pr = 0.75;
      
      peak = monthlyRadiation.map(function(rad) {
        return Math.round(rad * usableAreaM2 * efficiency * pr);
      });
      labelName = 'Estimated Monthly Production (kWh)';
    }

    if (window.solarChartInstance) {
      window.solarChartInstance.destroy();
    }
    window.solarChartInstance = new Chart(solarCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: months,
        datasets: [{
          label: labelName,
          data: peak,
          fill: true,
          borderColor: '#ffd600',
          backgroundColor: 'rgba(255,214,0,0.08)',
          tension: 0.4,
          borderWidth: 2,
          pointRadius: 3,
          pointBackgroundColor: '#ffd600',
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: true, position: 'top' },
          tooltip: { backgroundColor: '#111827', borderColor: 'rgba(255,214,0,0.3)', borderWidth: 1 }
        },
        scales: {
          x: { grid: { color: 'rgba(255,255,255,0.04)' } },
          y: { grid: { color: 'rgba(255,255,255,0.04)' }, beginAtZero: true }
        }
      }
    });
  }
}

document.addEventListener('DOMContentLoaded', function() {
  if (typeof Chart !== 'undefined') {
    initDashboardCharts();
  }
});
