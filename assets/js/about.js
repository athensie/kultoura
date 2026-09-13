// KulToura — About page: reveal sections as they scroll into view

document.addEventListener('DOMContentLoaded', function () {
  const sections = document.querySelectorAll('.a-section');

  if (!('IntersectionObserver' in window) || sections.length === 0) {
    sections.forEach(s => s.classList.add('a-visible'));
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('a-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15 });

  sections.forEach(s => observer.observe(s));
});