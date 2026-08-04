// Digital Ali Pro OMS — small shared UI behaviors.

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('submit', function (e) {
      var message = el.getAttribute('data-confirm');
      if (!window.confirm(message)) {
        e.preventDefault();
      }
    });
  });
});
