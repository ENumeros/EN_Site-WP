document.addEventListener('DOMContentLoaded', function () {
  var hamburger = document.getElementById('hamburger');
  var nav = document.getElementById('site-nav');
  if (!hamburger || !nav) return;

  hamburger.addEventListener('click', function () {
    var isOpen = nav.classList.toggle('is-open');
    hamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    var list = nav.querySelector('.site-nav__list');
    if (list) {
      list.style.display = isOpen ? 'flex' : 'none';
      list.style.flexDirection = 'column';
      list.style.position = 'absolute';
      list.style.top = '100%';
      list.style.left = '0';
      list.style.right = '0';
      list.style.background = 'var(--c-bg)';
      list.style.padding = '16px 20px';
      list.style.borderBottom = '1px solid var(--c-line)';
    }
  });
});

// ─── Alternar modo escuro ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.getElementById('theme-toggle');
  if (!toggle) return;

  function setPressed() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    toggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
  }
  setPressed();

  toggle.addEventListener('click', function () {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var next = isDark ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('cp-theme', next); } catch (e) {}
    setPressed();
  });
});
