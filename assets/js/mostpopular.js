// KulToura — Most Popular page

let _popActiveType = 'all';

function _popCardMatches(card, term) {
  const typeOk = _popActiveType === 'all' || card.dataset.type === _popActiveType;
  const textOk = !term || card.textContent.toLowerCase().includes(term);
  return typeOk && textOk;
}

function filterPopular(val) {
  const term = val.toLowerCase();
  document.querySelectorAll('.m-card').forEach(card => {
    card.style.display = _popCardMatches(card, term) ? '' : 'none';
  });
}

function filterPopularByType(val, btn) {
  _popActiveType = val;

  document.querySelectorAll('.m-cat-item').forEach(item => {
    item.classList.toggle('is-active', item === btn);
  });

  const term = (document.getElementById('popSearch')?.value || '').toLowerCase();
  document.querySelectorAll('.m-card').forEach(card => {
    card.style.display = _popCardMatches(card, term) ? '' : 'none';
  });
}
