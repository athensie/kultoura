(function () {
  window.toggleSidebar = function () { document.getElementById('sidebar')?.classList.toggle('open'); };
  if (typeof lucide !== 'undefined') lucide.createIcons();
  const customRangeButton = document.querySelector('[data-custom-range]');
  customRangeButton?.addEventListener('click', () => {
    customRangeButton.classList.add('active');
    customRangeButton.setAttribute('aria-pressed', 'true');
    customRangeButton.closest('.aw-periods')?.querySelectorAll('a').forEach(link => link.classList.remove('active'));
    document.querySelector('.date-range-form input[name="date_from"]')?.focus();
  });
  const tabs = Array.from(document.querySelectorAll('.aw-tab'));
  const panels = Array.from(document.querySelectorAll('.aw-section'));
  if (!tabs.length || !panels.length) return;

  // The selected tab lives in the URL (?aw_tab=destination), so it can be bookmarked or shared,
  // survives a refresh, works with Back/Forward, and is kept when the date filter changes.
  const tabKey = tab => tab.id.replace(/^aw-tab-/, '');
  function carryTabIntoFilters(key) {
    document.querySelectorAll('.aw-periods a[href]').forEach(link => {
      const url = new URL(link.getAttribute('href'), window.location.href);
      url.searchParams.set('aw_tab', key);
      link.href = url.toString();
    });
    document.querySelectorAll('form.date-range-form').forEach(form => {
      let input = form.querySelector('input[name="aw_tab"]');
      if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = 'aw_tab'; form.appendChild(input); }
      input.value = key;
    });
  }
  function rememberTab(key) {
    const url = new URL(window.location.href);
    if (url.searchParams.get('aw_tab') === key) return;
    url.searchParams.set('aw_tab', key);
    if (key !== 'community') { url.searchParams.delete('review_tab'); url.searchParams.delete('rating'); }
    url.hash = '';
    history.pushState({ awTab: key }, '', url);
  }
  function selectTab(tab, focus = false, updateUrl = true) {
    if (updateUrl) rememberTab(tabKey(tab));
    carryTabIntoFilters(tabKey(tab));
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
  const tabFromUrl = () => {
    const params = new URLSearchParams(window.location.search);
    const key = params.has('review_tab') ? 'community' : (params.get('aw_tab') || '');
    return tabs.find(tab => tabKey(tab) === key) || null;
  };
  const initialTab = tabFromUrl() || tabs.find(tab => tab.getAttribute('aria-selected') === 'true') || tabs[0];
  if (initialTab) selectTab(initialTab, false, false);
  window.addEventListener('popstate', () => selectTab(tabFromUrl() || tabs[0], false, false));

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
        const marker = L.circleMarker([point.lat, point.lng], { radius: Math.min(12, 5 + Math.log2(point.views + 1)), color: '#fff', weight: 2, fillColor: '#2c4a2e', fillOpacity: .9 }).addTo(map);
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

  Chart.defaults.font.family = "'DM Sans', system-ui, sans-serif";
  Chart.defaults.plugins.tooltip.displayColors = false;
  const workspaceStyle = getComputedStyle(document.querySelector('.analytics-workspace'));
  const textColor = workspaceStyle.getPropertyValue('--aw-chart-text').trim() || '#34443a';
  const mutedColor = workspaceStyle.getPropertyValue('--aw-muted').trim() || '#66746a';
  const lineColor = 'rgba(45,65,50,.14)';
  const colors = ['#2c4a2e', '#9fb88a', '#2c7a45', '#c8a96e', '#597854', '#b8c7aa', '#7a8a64', '#d5b57c'];
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
      backgroundColor: kind === 'gauge' ? ['#2c4a2e','rgba(44,74,46,.14)'] : (doughnut ? series.values.map((_, i) => colors[i % colors.length]) : (horizontal ? '#2c7a45' : 'rgba(44,122,69,.72)')),
      borderColor: doughnut ? '#fff' : (chartType === 'line' ? '#2c4a2e' : '#2c7a45'),
      borderWidth: doughnut ? 2 : 1,
      borderRadius: chartType === 'bar' && !horizontal ? 4 : 0,
      fill: area,
      tension: chartType === 'line' ? .32 : 0,
      pointRadius: chartType === 'line' ? 2 : 0,
      pointHoverRadius: chartType === 'line' ? 5 : 0
    }];
    if (Array.isArray(series.datasets)) datasets.splice(0, datasets.length, ...series.datasets.map(dataset => ({ ...dataset, borderWidth: 0, borderRadius: 3 })));
    if (chartType === 'scatter') { datasets[0].pointRadius = 5; datasets[0].pointHoverRadius = 7; datasets[0].backgroundColor = '#2c4a2e'; }
    // Let end points and peaks draw into the padding instead of being clipped at the plot edge.
    if (chartType === 'line') datasets.forEach(dataset => { dataset.clip = 8; });

    // Axis labels: every point gets a label. Dates use compact two-line labels so they fit:
    //   months "Jan 2025" -> "Jan" with the year underneath only where the year changes,
    //   days   "Nov 1"    -> "1"   with the month underneath on the 1st (and the first label),
    //   hours  "1 AM"     -> "1"   with AM/PM underneath where it changes.
    // Daily ranges longer than ~45 days label each week's first day (tooltips still show every day).
    // Other text labels are shortened on the axis and tilt if they still don't fit.
    const axisLabels = (series.labels || []).map(label => String(label ?? ''));
    const labelCount = axisLabels.length;
    const shorten = (text, max) => { const value = String(text ?? ''); return value.length > max ? `${value.slice(0, max - 1)}…` : value; };
    const monthPattern = /^([A-Z][a-z]{2}) (\d{4})$/;
    const dayPattern = /^([A-Z][a-z]{2}) (\d{1,2})$/;
    const hourPattern = /^(\d{1,2}) (AM|PM)$/;
    const everyLabelMatches = pattern => labelCount > 0 && axisLabels.every(label => pattern.test(label));
    const axisMode = everyLabelMatches(monthPattern) ? 'month' : (everyLabelMatches(dayPattern) ? 'day' : (everyLabelMatches(hourPattern) ? 'hour' : 'text'));
    // spacing = pixels available per point; date labels adapt to it instead of tilting.
    const axisLabel = (index, maxChars, spacing) => {
      const text = axisLabels[index] ?? '';
      if (axisMode === 'month') {
        // Top line must fit beside its neighbours; the year line is sparse so it can be wider.
        const step = spacing >= 9 ? 1 : Math.ceil(9 / spacing);
        if (step > 1 && index % step !== 0 && index !== labelCount - 1) return null;
        const [, month, year] = text.match(monthPattern);
        const previous = index >= step ? axisLabels[index - step].match(monthPattern) : null;
        const showYear = !previous || previous[2] !== year;
        return [spacing >= 20 ? month : month.charAt(0), showYear ? year : ''];
      }
      if (axisMode === 'day') {
        const step = spacing >= 16 ? 1 : (spacing >= 6.5 ? 2 : 7);
        if (step > 1 && index % step !== 0) return null;
        const [, month, day] = text.match(dayPattern);
        const previous = index >= step ? axisLabels[index - step].match(dayPattern) : null;
        return [day, !previous || previous[1] !== month ? month : ''];
      }
      if (axisMode === 'hour') {
        const step = spacing >= 15 ? 1 : (spacing >= 8 ? 2 : 3);
        if (step > 1 && index % step !== 0) return null;
        const [, hour, meridiem] = text.match(hourPattern);
        const previous = index >= step ? axisLabels[index - step].match(hourPattern) : null;
        return [hour, !previous || previous[2] !== meridiem ? meridiem : ''];
      }
      return shorten(text, maxChars);
    };
    const categoryTicks = maxChars => ({
      color: mutedColor, font: { size: 11 }, autoSkip: false, minRotation: 0, padding: 4,
      maxRotation: axisMode === 'text' ? 40 : 0,
      callback(value, index) {
        // Measure the chart box (re-read on every resize); subtract room for the value axis.
        const plotWidth = Math.max(120, (canvas.parentElement?.clientWidth || this.width || 600) - 60);
        return axisLabel(index, maxChars, plotWidth / Math.max(1, labelCount));
      }
    });
    const valueTicks = { color: mutedColor, precision: 0, maxTicksLimit: 6, font: { size: 11 }, callback: value => (series.valueFormat === 'duration' ? (Number(value) >= 60 ? `${Math.round(Number(value) / 60)}m` : `${Number(value)}s`) : Number(value).toLocaleString()) };
    const isScatter = chartType === 'scatter';
    const xAxisTicks = horizontal || isScatter ? { ...valueTicks, maxTicksLimit: horizontal ? 7 : 6 } : categoryTicks(labelCount > 7 ? 12 : 16);
    const yAxisTicks = horizontal ? { ...categoryTicks(26), autoSkip: false } : valueTicks;

    // Tooltips: full names, formatted numbers; weekday totals explain how many days they cover.
    const fullLabel = index => (series.fullLabels && series.fullLabels[index]) || series.labels[index];
    const tooltipLabel = context => {
      if (isScatter) {
        return canvas.dataset.awKey === 'cultureComments'
          ? `${context.raw.label}: ${context.raw.x} views, ${context.raw.y} comments`
          : `${context.raw.label}: ${context.raw.x} views, ${context.raw.y} / 5`;
      }
      const raw = context.parsed.x ?? context.parsed.y ?? context.parsed;
      const value = horizontal ? context.parsed.x : (doughnut ? context.parsed : context.parsed.y);
      const number = Number(value ?? raw);
      const asDuration = seconds => seconds >= 60 ? `${Math.floor(seconds / 60)}m ${Math.round(seconds % 60)}s` : `${Math.round(seconds)}s`;
      const formatted = !Number.isFinite(number) ? String(raw) : (series.valueFormat === 'duration' ? asDuration(number) : number.toLocaleString());
      const occurrences = series.occurrences && series.occurrences[context.dataIndex];
      if (occurrences) {
        const day = fullLabel(context.dataIndex);
        const average = Math.round(number / occurrences).toLocaleString();
        return [`${formatted} visits across ${occurrences} ${day}${occurrences === 1 ? '' : 's'}`, `Average ${average} per ${day}`];
      }
      const seriesName = series.seriesName || (context.dataset.label && context.dataset.label !== 'Records' ? context.dataset.label : 'Total');
      return `${seriesName}: ${formatted}`;
    };

    // Date axes get a minimum width per label so they never look crowded. If the card is
    // narrower than that, the chart scrolls sideways inside the card (starting at the latest data).
    const minLabelSpacing = { month: 38, day: 28, hour: 28 }[axisMode];
    const chartPlugins = [];
    if (minLabelSpacing && !horizontal && !doughnut && !isScatter) {
      // box (fixed) > scroller (scrolls sideways) > inner (as wide as the labels need) > canvas
      // plus a pinned copy of the value axis drawn over the left edge while scrolling.
      const box = canvas.parentElement;
      const scroller = document.createElement('div');
      scroller.className = 'aw-chart-scroll';
      const inner = document.createElement('div');
      inner.className = 'aw-chart-scroll-inner';
      box.appendChild(scroller);
      scroller.appendChild(inner);
      inner.appendChild(canvas);
      const pinnedAxis = document.createElement('canvas');
      pinnedAxis.className = 'aw-pinned-axis';
      pinnedAxis.setAttribute('aria-hidden', 'true');
      pinnedAxis.hidden = true;
      box.appendChild(pinnedAxis);
      chartPlugins.push({
        id: 'pinnedValueAxis',
        afterRender(chart) {
          if (!box.classList.contains('is-scrollable') || !chart.scales.y) { pinnedAxis.hidden = true; return; }
          const ratio = chart.currentDevicePixelRatio || 1;
          const width = Math.ceil(chart.scales.y.right + 4);
          const height = Math.min(chart.height, Math.ceil(chart.scales.y.bottom + 6)); // stop above the month labels
          pinnedAxis.width = Math.round(width * ratio);
          pinnedAxis.height = Math.round(height * ratio);
          pinnedAxis.style.width = `${width}px`;
          pinnedAxis.style.height = `${height}px`;
          const ctx = pinnedAxis.getContext('2d');
          ctx.fillStyle = '#fff';
          ctx.fillRect(0, 0, pinnedAxis.width, pinnedAxis.height);
          ctx.drawImage(chart.canvas, 0, 0, pinnedAxis.width, pinnedAxis.height, 0, 0, pinnedAxis.width, pinnedAxis.height);
          pinnedAxis.hidden = false;
        }
      });
      const hint = document.createElement('p');
      hint.className = 'aw-scroll-hint';
      hint.hidden = true;
      const unit = { month: 'months', day: 'days', hour: 'hours' }[axisMode];
      hint.textContent = `Scroll the chart sideways to see earlier ${unit}. Showing the latest first.`;
      box.insertAdjacentElement('afterend', hint);
      const neededWidth = labelCount * minLabelSpacing + 64;
      let wasScrollable = null;
      const fitToBox = () => {
        if (!box.clientWidth) return; // chart is on a hidden tab; re-run when it becomes visible
        const scrollable = box.clientWidth < neededWidth;
        if (!scrollable) pinnedAxis.hidden = true;
        inner.style.width = scrollable ? `${neededWidth}px` : '100%';
        box.classList.toggle('is-scrollable', scrollable);
        hint.hidden = !scrollable;
        if (scrollable && wasScrollable !== true) requestAnimationFrame(() => { scroller.scrollLeft = scroller.scrollWidth; });
        wasScrollable = scrollable;
      };
      fitToBox();
      if (typeof ResizeObserver === 'function') new ResizeObserver(fitToBox).observe(box);
      else window.addEventListener('resize', fitToBox);
    }
    new Chart(canvas, {
      type: chartType,
      plugins: kind === 'gauge' ? [{ id: 'gaugeCenter', afterDraw(chart) { const { ctx, chartArea } = chart; if (!chartArea) return; ctx.save(); ctx.fillStyle = textColor; ctx.font = '700 20px "DM Sans"'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(`${Number(series.values[0]).toFixed(1)} / 5`, (chartArea.left + chartArea.right) / 2, (chartArea.top + chartArea.bottom) / 2 + 12); ctx.restore(); } }] : chartPlugins,
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
          tooltip: { enabled: true, padding: 10, callbacks: { title: items => (isScatter || !items.length ? '' : fullLabel(items[0].dataIndex)), label: tooltipLabel } }
        },
        layout: { padding: doughnut ? 4 : { top: 12, right: 16, bottom: 0, left: 4 } },
        cutout: kind === 'gauge' ? '78%' : '62%',
        rotation: kind === 'gauge' ? 180 : undefined,
        circumference: kind === 'gauge' ? 180 : undefined,
        scales: doughnut ? {} : {
          x: { beginAtZero: true, stacked: kind === 'stacked', grace: horizontal ? '6%' : 0, grid: { display: horizontal || isScatter, color: lineColor }, ticks: xAxisTicks, border: { display: false } },
          y: { beginAtZero: true, stacked: kind === 'stacked', grace: horizontal ? 0 : '8%', grid: { display: !horizontal, color: lineColor }, ticks: yAxisTicks, border: { display: false } }
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

// Analytics tabs stay on one line; when they don't fit, the bar scrolls sideways.
// Fade the edge that has hidden tabs, let a normal mouse wheel scroll it, and keep the selected tab visible.
(function initTabBarOverflow() {
  const bar = document.querySelector('.aw-tabs');
  if (!bar) return;
  const update = () => {
    const max = bar.scrollWidth - bar.clientWidth;
    bar.classList.toggle('is-overflow-start', max > 1 && bar.scrollLeft > 1);
    bar.classList.toggle('is-overflow-end', max > 1 && bar.scrollLeft < max - 1);
  };
  const revealSelected = () => {
    const selected = bar.querySelector('[aria-selected="true"]');
    if (!selected || bar.scrollWidth <= bar.clientWidth) return;
    const left = selected.offsetLeft; // .aw-tabs is position:relative, so this is relative to the bar
    const right = left + selected.offsetWidth;
    if (left < bar.scrollLeft) bar.scrollLeft = left - 6;
    else if (right > bar.scrollLeft + bar.clientWidth) bar.scrollLeft = right - bar.clientWidth + 6;
  };
  bar.addEventListener('scroll', update, { passive: true });
  bar.addEventListener('wheel', event => {
    if (bar.scrollWidth <= bar.clientWidth || Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;
    event.preventDefault();
    bar.scrollLeft += event.deltaY;
  }, { passive: false });
  bar.addEventListener('click', event => {
    if (event.target.closest('.aw-tab')) requestAnimationFrame(revealSelected);
  });
  if (typeof ResizeObserver === 'function') new ResizeObserver(update).observe(bar);
  else window.addEventListener('resize', update);
  revealSelected();
  update();
})();
