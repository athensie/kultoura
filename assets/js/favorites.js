// KulToura — Favorites page

function filterFavorites(val) {
  const term = val.toLowerCase();
  document.querySelectorAll('.f-card').forEach(card => {
    card.style.display = card.textContent.toLowerCase().includes(term) ? '' : 'none';
  });
}

function filterFavoritesByType(val) {
  document.querySelectorAll('.f-card').forEach(card => {
    if (val === 'all') { card.style.display = ''; return; }
    card.style.display = card.dataset.type === val ? '' : 'none';
  });
}

let _favToastTimer;
function showFavToast(msg) {
  const t = document.getElementById('favToast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(_favToastTimer);
  _favToastTimer = setTimeout(() => t.classList.remove('show'), 3200);
}