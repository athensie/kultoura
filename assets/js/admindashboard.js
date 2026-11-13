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
  const chart = new Chart(canvas, {
    type: 'line',
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
  const zoomIn = chartSection?.querySelector('[data-chart-zoom-in]');
  const zoomOut = chartSection?.querySelector('[data-chart-zoom-out]');
  const zoomReset = chartSection?.querySelector('[data-chart-zoom-reset]');
  let visibleCount = series.length;
  let firstVisible = 0;
  const minVisible = Math.min(3, series.length);

  const renderVisibleRange = () => {
    const visibleSeries = series.slice(firstVisible, firstVisible + visibleCount);
    chart.data.labels = visibleSeries.map(point => point.label);
    chart.data.datasets[0].data = visibleSeries.map(point => point.value);
    chart.update('none');
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
  renderVisibleRange();

})();
