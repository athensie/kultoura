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
  const range = document.querySelector('.traffic-range-switch');
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

  if (!range) return;
  range.addEventListener('click', event => {
    const button = event.target.closest('[data-days]');
    if (!button) return;
    const days = Number(button.dataset.days);
    if (![7, 30, 90].includes(days)) return;
    range.querySelectorAll('[data-days]').forEach(option => option.classList.toggle('is-active', option === button));
    const visible = series.slice(-days);
    chart.data.labels = visible.map(point => point.label);
    chart.data.datasets[0].data = visible.map(point => point.value);
    chart.update('active');
  });
})();
