function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
(function initAnalyticsFilter() {
  const bar = document.getElementById('analyticsFilterBar');
  const categoryOverview = document.querySelector('.tourism-category-overview');
  const trafficOverview = document.querySelector('.traffic-overview');
  const categoryVisuals = document.getElementById('categorySummaryVisuals');
  const overallKpis = document.querySelector('.tourism-overview-kpis');
  const comparisonTables = categoryOverview?.querySelectorAll('.category-comparison') || [];
  if (!bar || !categoryOverview) return;
  const sections = document.querySelectorAll('.analytics-section');
  const categoryButtons = categoryOverview.querySelectorAll('[data-dashboard-category]');
  const categoryPanels = categoryOverview.querySelectorAll('[data-category-panel]');
  let selectedCategory = 'all';
  function applyFilters() {
    const analysisType = bar.querySelector('.analytics-filter-pill.is-active')?.dataset.filter || 'all';
    bar.style.display = selectedCategory === 'all' ? '' : 'none';
    trafficOverview?.classList.toggle('is-hidden', selectedCategory !== 'all');
    categoryVisuals?.classList.toggle('is-hidden', selectedCategory !== 'all');
    overallKpis?.classList.toggle('is-hidden', selectedCategory !== 'all');
    comparisonTables.forEach(panel => panel.classList.toggle('is-hidden', selectedCategory !== 'all'));
    sections.forEach(section => {
      const show = selectedCategory === 'all' && (analysisType === 'all' || section.dataset.category === analysisType);
      section.classList.toggle('is-hidden', !show);
    });
    categoryPanels.forEach(panel => {
      panel.classList.toggle('is-hidden', panel.dataset.categoryPanel !== selectedCategory || selectedCategory === 'all');
    });
    categoryButtons.forEach(button => {
      const active = button.dataset.dashboardCategory === selectedCategory;
      button.classList.toggle('is-active', active);
      if (button.hasAttribute('aria-pressed')) button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }
  bar.addEventListener('click', function (e) {
    const pill = e.target.closest('.analytics-filter-pill');
    if (!pill) return;
    bar.querySelectorAll('.analytics-filter-pill').forEach(p => p.classList.remove('is-active'));
    pill.classList.add('is-active');
    applyFilters();
  });
  categoryOverview.addEventListener('click', function (e) {
    const button = e.target.closest('[data-dashboard-category]');
    if (!button) return;
    selectedCategory = button.dataset.dashboardCategory;
    applyFilters();
  });
  applyFilters();
})();
(function initTourismCategoryCharts() {
  const dataNode = document.getElementById('tourismCategoryChartData');
  if (!dataNode || typeof Chart === 'undefined') return;
  let data;
  try { data = JSON.parse(dataNode.textContent || '{}'); } catch (error) { return; }
  const labels = Array.isArray(data.labels) ? data.labels : [];
  const keys = Array.isArray(data.keys) ? data.keys : [];
  const selectCategory = index => {
    const key = keys[index];
    if (!key) return;
    document.querySelector(`[data-dashboard-category="${CSS.escape(key)}"]`)?.click();
    document.querySelector('.tourism-category-overview')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
  const colors = ['#9dbb86', '#d4ad62', '#bc684e', '#789c80', '#b9874e', '#668b69', '#d18465', '#a9c28e', '#8c7459', '#cfb887', '#527b5d'];
  const comparison = document.getElementById('tourismCategoryComparison');
  if (comparison && labels.length) new Chart(comparison, {
    type: 'bar',
    data: { labels, datasets: [
      { label: 'Views', data: data.views || [], backgroundColor: '#9dbb86' },
      { label: 'Clicks', data: data.clicks || [], backgroundColor: '#d4ad62' },
      { label: 'Searches', data: data.searches || [], backgroundColor: '#bc684e' }
    ] },
    options: { responsive: true, maintainAspectRatio: false, onClick: (event, elements) => { if (elements.length) selectCategory(elements[0].index); }, scales: { x: { ticks: { color: '#d9d4c5', maxRotation: 40, minRotation: 0 }, grid: { display: false } }, y: { beginAtZero: true, ticks: { color: '#b7b39f', precision: 0 }, grid: { color: 'rgba(230,225,205,.09)' } } }, plugins: { legend: { labels: { color: '#e8e1ce' } } } }
  });
  const engagement = document.getElementById('tourismCategoryEngagement');
  if (engagement && labels.length) new Chart(engagement, {
    type: 'doughnut', data: { labels, datasets: [{ data: data.engagement || [], backgroundColor: colors, borderColor: '#142017', borderWidth: 2 }] },
    options: { responsive: true, maintainAspectRatio: false, onClick: (event, elements) => { if (elements.length) selectCategory(elements[0].index); }, plugins: { legend: { position: 'bottom', labels: { color: '#e8e1ce', boxWidth: 10, padding: 10 } } } }
  });
  const ratings = document.getElementById('tourismRatingDistribution');
  if (ratings) new Chart(ratings, {
    type: 'doughnut', data: { labels: ['1 star', '2 stars', '3 stars', '4 stars', '5 stars'], datasets: [{ data: data.ratings || [], backgroundColor: ['#9c4737', '#bc684e', '#d4ad62', '#9dbb86', '#62845c'], borderColor: '#142017', borderWidth: 2 }] },
    options: { responsive: true, maintainAspectRatio: false, onClick: (event, elements) => { if (elements.length) window.location.href = `adminreviews.php?rating=${elements[0].index + 1}`; }, plugins: { legend: { position: 'bottom', labels: { color: '#e8e1ce', boxWidth: 10, padding: 12 } } } }
  });
})();
(function initLucide() {
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
  }
})();
(function initTrafficOverview() {
  const canvas = document.getElementById('trafficOverviewChart');
  const dataNode = document.getElementById('trafficOverviewData');
  if (!canvas || !dataNode || typeof Chart === 'undefined') return;

  let series;
  try {
    series = JSON.parse(dataNode.textContent || '[]');
  } catch (error) {
    return;
  }
  if (!Array.isArray(series) || !series.length) return;

  const styles = getComputedStyle(document.documentElement);
  const textColor = styles.getPropertyValue('--cream').trim() || '#F5EDD8';
  const clay = styles.getPropertyValue('--clay').trim() || '#C9572A';
  let dragState = null;
  const chart = new Chart(canvas, {
    type: 'line',
    plugins: [{
      id: 'trafficRangeSelection',
      afterDraw(currentChart) {
        if (dragState?.mode !== 'select' || dragState.currentX === undefined) return;
        const { ctx, chartArea } = currentChart;
        const left = Math.max(chartArea.left, Math.min(dragState.startX, dragState.currentX));
        const right = Math.min(chartArea.right, Math.max(dragState.startX, dragState.currentX));
        ctx.save();
        ctx.fillStyle = 'rgba(159, 184, 138, .2)';
        ctx.fillRect(left, chartArea.top, Math.max(0, right - left), chartArea.bottom - chartArea.top);
        ctx.strokeStyle = clay;
        ctx.lineWidth = 1;
        ctx.strokeRect(left, chartArea.top, Math.max(0, right - left), chartArea.bottom - chartArea.top);
        ctx.restore();
      }
    }],
    data: {
      labels: series.map(point => point.label),
      datasets: [{
        label: 'Page views',
        data: series.map(point => point.value),
        borderColor: clay,
        borderWidth: 2.5,
        backgroundColor: context => {
          const gradient = context.chart.ctx.createLinearGradient(0, 0, 0, context.chart.height);
          gradient.addColorStop(0, 'rgba(201, 87, 42, .28)');
          gradient.addColorStop(1, 'rgba(201, 87, 42, 0)');
          return gradient;
        },
        fill: true,
        tension: .36,
        pointRadius: 0,
        pointHoverRadius: 5,
        pointHoverBackgroundColor: clay,
        pointHoverBorderColor: textColor,
        pointHoverBorderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { intersect: false, mode: 'index' },
      plugins: {
        legend: { display: false },
        tooltip: {
          displayColors: false,
          padding: 11,
          backgroundColor: '#20170e',
          titleColor: '#F5EDD8',
          bodyColor: '#E8A842',
          callbacks: { label: item => `${item.parsed.y.toLocaleString()} page views` }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          border: { display: false },
          ticks: {
            color: 'rgba(245, 237, 216, .42)',
            maxTicksLimit: 10,
            maxRotation: 0,
            font: { family: 'DM Sans', size: 10 }
          }
        },
        y: {
          beginAtZero: true,
          border: { display: false },
          grid: { color: 'rgba(245, 237, 216, .07)' },
          ticks: {
            color: 'rgba(245, 237, 216, .42)',
            precision: 0,
            padding: 9,
            font: { family: 'DM Sans', size: 10 }
          }
        }
      }
    }
  });

  const chartSection = canvas.closest('.traffic-overview');
  const chartWrap = chartSection?.querySelector('.traffic-chart-wrap');
  const initialWindow = Number(dataNode.dataset.initialWindow || 0);
  let visibleCount = initialWindow > 0 ? Math.min(initialWindow, series.length) : series.length;
  let firstVisible = series.length - visibleCount;
  const minVisible = Math.min(3, series.length);

  const renderVisibleRange = () => {
    const visibleSeries = series.slice(firstVisible, firstVisible + visibleCount);
    chart.data.labels = visibleSeries.map(point => point.label);
    chart.data.datasets[0].data = visibleSeries.map(point => point.value);
    chart.update('none');
    chartWrap?.classList.toggle('is-zoomed', visibleCount < series.length);
  };

  canvas.addEventListener('wheel', event => {
    const rect = canvas.getBoundingClientRect();
    const pointerX = event.clientX - rect.left;
    const pointerY = event.clientY - rect.top;
    const { left, right, top, bottom } = chart.chartArea;
    if (pointerX < left || pointerX > right || pointerY < top || pointerY > bottom || visibleCount <= 0) return;
    event.preventDefault();

    const deltaScale = event.deltaMode === 1 ? 16 : (event.deltaMode === 2 ? rect.height : 1);
    const wheelDelta = event.deltaY * deltaScale;
    // Scrolling down zooms in. Ctrl/Cmd-wheel pinch directions are inverted by browsers.
    const zoomDelta = (event.ctrlKey || event.metaKey) ? -wheelDelta : wheelDelta;
    const nextCount = Math.max(minVisible, Math.min(series.length, Math.round(visibleCount * Math.exp(-zoomDelta * .002))));
    if (nextCount === visibleCount) return;

    const pointerIndex = chart.scales.x.getValueForPixel(pointerX);
    const anchorFraction = visibleCount > 1 ? Math.max(0, Math.min(1, pointerIndex / (visibleCount - 1))) : .5;
    const anchorIndex = firstVisible + pointerIndex;
    visibleCount = nextCount;
    firstVisible = Math.max(0, Math.min(series.length - visibleCount, Math.round(anchorIndex - anchorFraction * (visibleCount - 1))));
    renderVisibleRange();
  }, { passive: false });

  canvas.addEventListener('dblclick', () => {
    visibleCount = series.length;
    firstVisible = 0;
    renderVisibleRange();
  });

  canvas.style.touchAction = 'pan-y';
  canvas.addEventListener('pointerdown', event => {
    if ((event.button !== undefined && event.button !== 0)) return;
    const rect = canvas.getBoundingClientRect();
    const localX = event.clientX - rect.left;
    const { left, right } = chart.chartArea;
    if (localX < left || localX > right) return;
    const isZoomed = visibleCount < series.length;
    dragState = {
      mode: isZoomed ? 'pan' : 'select',
      pointerId: event.pointerId,
      startX: localX,
      currentX: localX,
      startIndex: firstVisible
    };
    canvas.classList.add(isZoomed ? 'is-dragging' : 'is-selecting');
    canvas.setPointerCapture(event.pointerId);
  });
  canvas.addEventListener('pointermove', event => {
    if (!dragState || dragState.pointerId !== event.pointerId) return;
    const rect = canvas.getBoundingClientRect();
    const localX = event.clientX - rect.left;
    const { left, right } = chart.chartArea;
    if (dragState.mode === 'select') {
      dragState.currentX = Math.max(left, Math.min(right, localX));
      chart.draw();
    } else {
      const plotWidth = right - left;
      if (plotWidth <= 0) return;
      const shift = Math.round((localX - dragState.startX) / plotWidth * visibleCount);
      firstVisible = Math.max(0, Math.min(series.length - visibleCount, dragState.startIndex - shift));
      renderVisibleRange();
    }
  });
  const stopDragging = (event, applySelection = false) => {
    if (!dragState || dragState.pointerId !== event.pointerId) return;
    const selection = dragState;
    if (applySelection && selection.mode === 'select' && Math.abs(selection.currentX - selection.startX) > 8) {
      const startIndex = chart.scales.x.getValueForPixel(Math.min(selection.startX, selection.currentX));
      const endIndex = chart.scales.x.getValueForPixel(Math.max(selection.startX, selection.currentX));
      const firstIndex = Math.max(0, Math.min(series.length - 1, Math.floor(startIndex)));
      const lastIndex = Math.max(firstIndex, Math.min(series.length - 1, Math.ceil(endIndex)));
      firstVisible = firstIndex;
      visibleCount = Math.max(minVisible, lastIndex - firstIndex + 1);
      if (firstVisible + visibleCount > series.length) firstVisible = series.length - visibleCount;
    }
    dragState = null;
    canvas.classList.remove('is-dragging', 'is-selecting');
    if (canvas.hasPointerCapture(event.pointerId)) canvas.releasePointerCapture(event.pointerId);
    if (applySelection) renderVisibleRange();
    else chart.draw();
  };
  canvas.addEventListener('pointerup', event => stopDragging(event, true));
  canvas.addEventListener('pointercancel', event => stopDragging(event));
  renderVisibleRange();

})();
