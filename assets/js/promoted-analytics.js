(function () {
    const canvas = document.getElementById('promotedEffectivenessChart');
    const dataNode = document.getElementById('promotedEffectivenessData');
    if (!canvas || !dataNode || !window.Chart) return;
    let items = [];
    try {
        items = JSON.parse(dataNode.textContent || '[]');
    } catch (error) {
        return;
    }
    const styles = getComputedStyle(document.documentElement);
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: items.map(item => item.name),
            datasets: [
                { label: 'Visible impressions', data: items.map(item => item.impressions), backgroundColor: styles.getPropertyValue('--clay').trim() || '#9FB88A', borderRadius: 5 },
                { label: 'Modal opens', data: items.map(item => item.clicks), backgroundColor: styles.getPropertyValue('--gold').trim() || '#C8A96E', borderRadius: 5 }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { beginAtZero: true, grid: { color: 'rgba(255,255,255,.07)' }, ticks: { color: 'rgba(240,235,216,.55)', precision: 0 } },
                y: { grid: { display: false }, ticks: { color: 'rgba(240,235,216,.7)' } }
            },
            plugins: {
                legend: { labels: { color: 'rgba(240,235,216,.65)', usePointStyle: true, boxWidth: 8 } },
                tooltip: { callbacks: { label: context => `${context.dataset.label}: ${Number(context.parsed.x).toLocaleString()}` } }
            }
        }
    });
})();
