document.addEventListener('DOMContentLoaded', function () {
  var hamburger = document.getElementById('hamburger');
  var nav = document.getElementById('site-nav');
  if (!hamburger || !nav) return;

  // O aspeto do menu aberto vive em main.css (.site-nav.is-open); aqui só
  // se alterna a classe e o estado acessível.
  function setOpen(isOpen) {
    nav.classList.toggle('is-open', isOpen);
    hamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    hamburger.setAttribute('aria-label', isOpen ? 'Fechar menu' : 'Abrir menu');
  }

  hamburger.addEventListener('click', function () {
    setOpen(!nav.classList.contains('is-open'));
  });

  // Fechar ao escolher um link (importante para âncoras como #contacto).
  nav.addEventListener('click', function (e) {
    if (e.target.closest('a')) setOpen(false);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && nav.classList.contains('is-open')) {
      setOpen(false);
      hamburger.focus();
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
