(function () {
  var storageKey = 'kt-admin-filter-state:' + window.location.pathname;
  var selector = '.filter-row input:not([type="hidden"]):not([type="file"]), .filter-row select, .filter-row textarea';

  function controls() {
    return Array.prototype.slice.call(document.querySelectorAll(selector));
  }

  function save() {
    try {
      sessionStorage.setItem(storageKey, JSON.stringify(controls().map(function (field) {
        return field.value;
      })));
    } catch (e) {}
  }

  function restore() {
    var values;
    try {
      values = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    } catch (e) {
      return;
    }
    if (!Array.isArray(values)) return;

    controls().forEach(function (field, index) {
      if (typeof values[index] !== 'string') return;
      field.value = values[index];
      var eventName = field.tagName === 'SELECT' ? 'change' : 'input';
      field.dispatchEvent(new Event(eventName, { bubbles: true }));
    });
  }

  document.addEventListener('input', function (event) {
    if (event.target && event.target.matches(selector)) save();
  });
  document.addEventListener('change', function (event) {
    if (event.target && event.target.matches(selector)) save();
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', restore, { once: true });
  } else {
    restore();
  }
})();
