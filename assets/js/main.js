// Digital Ali Pro OMS — small shared UI behaviors.

(function () {
  'use strict';

  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
  }

  function currentTheme() {
    var stored = localStorage.getItem('theme');
    if (stored === 'light' || stored === 'dark') {
      return stored;
    }
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  // Applied immediately by an inline snippet in <head> to avoid a flash of
  // the wrong theme; this listener just wires up the toggle button once
  // the DOM is ready.
  document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('themeToggle');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        localStorage.setItem('theme', next);
      });
    }

    // Mobile sidebar drawer
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var hamburger = document.getElementById('hamburgerBtn');
    var closeBtn = document.getElementById('sidebarClose');

    function openSidebar() {
      if (sidebar) sidebar.classList.add('open');
      if (overlay) overlay.classList.add('open');
    }
    function closeSidebar() {
      if (sidebar) sidebar.classList.remove('open');
      if (overlay) overlay.classList.remove('open');
    }

    if (hamburger) hamburger.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // Confirm-before-submit for destructive/important actions
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
      el.addEventListener('submit', function (e) {
        var message = el.getAttribute('data-confirm');
        if (!window.confirm(message)) {
          e.preventDefault();
        }
      });
    });
  });
})();
