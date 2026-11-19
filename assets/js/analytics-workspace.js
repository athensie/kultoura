(function () {
  window.toggleSidebar = function () { document.getElementById('sidebar')?.classList.toggle('open'); };
  if (typeof lucide !== 'undefined') lucide.createIcons();
  const tabs = Array.from(document.querySelectorAll('.aw-tab'));
  const panels = Array.from(document.querySelectorAll('.aw-section'));
  if (!tabs.length || !panels.length) return;

  function selectTab(tab, focus = false) {
    const target = tab.getAttribute('aria-controls');
    tabs.forEach(item => item.setAttribute('aria-selected', item === tab ? 'true' : 'false'));
    panels.forEach(panel => {
      const active = panel.id === target;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
    if (typeof Chart !== 'undefined') requestAnimationFrame(() => panels.find(panel => panel.id === target)?.querySelectorAll('canvas').forEach(canvas => Chart.getChart(canvas)?.resize()));
    requestAnimationFrame(() => { const panel = panels.find(item => item.id === target); if (panel && typeof initMaps === 'function') initMaps(panel); });
    if (focus) tab.focus();
  }
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => selectTab(tab));
    tab.addEventListener('keydown', event => {
      let next = index;
      if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
      else if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
      else if (event.key === 'Home') next = 0;
      else if (event.key === 'End') next = tabs.length - 1;
      else return;
      event.preventDefault();
      selectTab(tabs[next], true);
    });
  });

  const dataNode = document.getElementById('aw-chart-data');
  if (!dataNode) return;
  let allData;
  try { allData = JSON.parse(dataNode.textContent || '{}'); } catch (error) { return; }
  const initializedMaps = new WeakSet();
  function initMaps(container) {
    if (typeof L === 'undefined') return;
    container?.querySelectorAll('.aw-leaflet-map').forEach(element => {
      if (initializedMaps.has(element)) return;
      const points = allData[element.dataset.awMap] || [];
      if (!points.length) return;
      const map = L.map(element, { scrollWheelZoom: false, preferCanvas: true });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
      const markers = points.map(point => {
        const marker = L.circleMarker([point.lat, point.lng], { radius: Math.min(12, 5 + Math.log2(point.views + 1)), color: '#fff', weight: 2, fillColor: '#1689ed', fillOpacity: .88 }).addTo(map);
        const popup = document.createElement('div');
        popup.textContent = `${point.name} — ${Number(point.views).toLocaleString()} views`;
        marker.bindPopup(popup);
        return marker;
      });
      if (markers.length === 1) map.setView(markers[0].getLatLng(), 13);
      else map.fitBounds(L.featureGroup(markers).getBounds().pad(.15));
      initializedMaps.add(element);
      requestAnimationFrame(() => map.invalidateSize());
    });
  }
  initMaps(document.querySelector('.aw-section.is-active'));
  if (typeof Chart === 'undefined') return;

  const workspaceStyle = getComputedStyle(document.querySelector('.analytics-workspace'));
  const textColor = workspaceStyle.getPropertyValue('--aw-chart-text').trim() || '#34443a';
  const mutedColor = workspaceStyle.getPropertyValue('--aw-muted').trim() || '#66746a';
  const lineColor = 'rgba(45,65,50,.14)';
  const colors = ['#1689ed', '#4ccca0', '#ffb844', '#b36cf2', '#ff6674', '#59b9e8', '#7789a8', '#f28c52'];
  const horizontalKinds = new Set(['rank', 'rank-low', 'wordcloud']);
  document.querySelectorAll('canvas[data-aw-chart]').forEach(canvas => {
    const kind = canvas.dataset.awChart;
    const series = allData[canvas.dataset.awKey];
    if (!series || !series.labels || !series.values) return;
    const horizontal = horizontalKinds.has(kind);
    const doughnut = kind === 'donut' || kind === 'gauge';
    const area = kind === 'area';
    const chartType = doughnut ? 'doughnut' : (kind === 'scatter' ? 'scatter' : (kind === 'line' || area ? 'line' : 'bar'));
    if (kind === 'scatter' && !(series.points || []).length) return;
    const datasets = [{
      label: kind === 'destinationRated' || kind === 'cultureRated' ? 'Average rating / 5' : (doughnut ? 'Views' : 'Records'),
      data: chartType === 'scatter' ? series.points : series.values,
      backgroundColor: kind === 'gauge' ? ['#1689ed','rgba(75,99,132,.14)'] : (doughnut ? series.values.map((_, i) => colors[i % colors.length]) : (horizontal ? '#3b9af0' : 'rgba(59,154,240,.72)')),
      borderColor: doughnut ? '#fff' : (chartType === 'line' ? '#1689ed' : '#3b9af0'),
      borderWidth: doughnut ? 2 : 1,
      borderRadius: chartType === 'bar' && !horizontal ? 4 : 0,
      fill: area,
      tension: chartType === 'line' ? .32 : 0,
      pointRadius: chartType === 'line' ? 2 : 0,
      pointHoverRadius: chartType === 'line' ? 5 : 0
    }];
    if (Array.isArray(series.datasets)) datasets.splice(0, datasets.length, ...series.datasets.map(dataset => ({ ...dataset, borderWidth: 0, borderRadius: 3 })));
    if (chartType === 'scatter') { datasets[0].pointRadius = 5; datasets[0].pointHoverRadius = 7; datasets[0].backgroundColor = '#1689ed'; }
    new Chart(canvas, {
      type: chartType,
      plugins: kind === 'gauge' ? [{ id: 'gaugeCenter', afterDraw(chart) { const { ctx, chartArea } = chart; if (!chartArea) return; ctx.save(); ctx.fillStyle = textColor; ctx.font = '700 20px "DM Sans"'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(`${Number(series.values[0]).toFixed(1)} / 5`, (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2 + 12); ctx.restore(); } }] : [],
      data: { labels: series.labels, datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: horizontal ? 'y' : 'x',
        interaction: { intersect: false, mode: 'index' },
        onClick: (event, elements) => {
          if (!elements.length) return;
          const index = elements[0].index;
          canvas.dispatchEvent(new CustomEvent('analytics-point-select', { bubbles: true, detail: { label: series.labels[index], value: series.values[index], chart: kind } }));
        },
        plugins: {
          legend: { display: (doughnut && kind !== 'gauge') || kind === 'stacked', position: 'bottom', labels: { color: textColor, boxWidth: 10, padding: 12, font: { family: 'DM Sans', size: 11 } } },
          tooltip: { enabled: true, callbacks: { label: context => chartType === 'scatter' ? `${context.raw.label}: ${context.raw.x} views, ${context.raw.y} / 5` : `${context.label}: ${context.parsed.x ?? context.parsed.y ?? context.parsed}` } }
        },
        cutout: kind === 'gauge' ? '78%' : '62%',
        rotation: kind === 'gauge' ? 180 : undefined,
        circumference: kind === 'gauge' ? 180 : undefined,
        scales: doughnut ? {} : {
          x: { beginAtZero: true, stacked: kind === 'stacked', grid: { display: horizontal, color: lineColor }, ticks: { color: mutedColor, precision: 0, maxRotation: 0 }, border: { display: false } },
          y: { beginAtZero: true, stacked: kind === 'stacked', grid: { display: !horizontal, color: lineColor }, ticks: { color: mutedColor, precision: 0, autoSkip: !horizontal, maxTicksLimit: 9 }, border: { display: false } }
        }
      }
    });
  });
  document.addEventListener('analytics-point-select', event => {
    const card = event.target.closest('.aw-card');
    const output = card?.querySelector('.aw-drilldown');
    if (output) output.textContent = `${event.detail.label}: ${Number(event.detail.value).toLocaleString()}`;
  });
})();
