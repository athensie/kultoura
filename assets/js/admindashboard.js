function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
(function initAnalyticsFilter() {
  const bar = document.getElementById('analyticsFilterBar');
  if (!bar) return;
  const sections = document.querySelectorAll('.analytics-section');
  bar.addEventListener('click', function (e) {
    const pill = e.target.closest('.analytics-filter-pill');
    if (!pill) return;
    bar.querySelectorAll('.analytics-filter-pill').forEach(p => p.classList.remove('is-active'));
    pill.classList.add('is-active');
    const filter = pill.dataset.filter;
    sections.forEach(section => {
      const show = filter === 'all' || section.dataset.category === filter;
      section.classList.toggle('is-hidden', !show);
    });
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
  const zoomIn = chartSection?.querySelector('[data-chart-zoom-in]');
  const zoomOut = chartSection?.querySelector('[data-chart-zoom-out]');
  const zoomReset = chartSection?.querySelector('[data-chart-zoom-reset]');
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
    if (zoomIn) zoomIn.disabled = visibleCount <= minVisible;
    if (zoomOut) zoomOut.disabled = visibleCount >= series.length;
    if (zoomReset) zoomReset.disabled = visibleCount >= series.length;
  };

  zoomIn?.addEventListener('click', () => {
    if (visibleCount <= minVisible) return;
    const nextCount = Math.max(minVisible, Math.floor(visibleCount * .7));
    const center = firstVisible + visibleCount / 2;
    visibleCount = nextCount;
    firstVisible = Math.max(0, Math.min(series.length - visibleCount, Math.round(center - visibleCount / 2)));
    renderVisibleRange();
  });

  zoomOut?.addEventListener('click', () => {
    if (visibleCount >= series.length) return;
    const nextCount = Math.min(series.length, Math.ceil(visibleCount / .7));
    const center = firstVisible + visibleCount / 2;
    visibleCount = nextCount;
    firstVisible = Math.max(0, Math.min(series.length - visibleCount, Math.round(center - visibleCount / 2)));
    renderVisibleRange();
  });

  zoomReset?.addEventListener('click', () => {
    visibleCount = series.length;
    firstVisible = 0;
    renderVisibleRange();
  });

  canvas.addEventListener('wheel', event => {
    // Browsers report touchpad pinch gestures as Ctrl/Cmd + wheel events.
    if (!event.ctrlKey && !event.metaKey) return;
    event.preventDefault();
    const rect = canvas.getBoundingClientRect();
    const pointerX = event.clientX - rect.left;
    const { left, right } = chart.chartArea;
    if (pointerX < left || pointerX > right || visibleCount <= 0) return;

    const deltaScale = event.deltaMode === 1 ? 16 : (event.deltaMode === 2 ? rect.height : 1);
    const delta = event.deltaY * deltaScale;
    const nextCount = Math.max(minVisible, Math.min(series.length, Math.round(visibleCount * Math.exp(delta * .002))));
    if (nextCount === visibleCount) return;

    const pointerIndex = chart.scales.x.getValueForPixel(pointerX);
    const anchorFraction = visibleCount > 1 ? Math.max(0, Math.min(1, pointerIndex / (visibleCount - 1))) : .5;
    const anchorIndex = firstVisible + pointerIndex;
    visibleCount = nextCount;
    firstVisible = Math.max(0, Math.min(series.length - visibleCount, Math.round(anchorIndex - anchorFraction * (visibleCount - 1))));
    renderVisibleRange();
  }, { passive: false });

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
