/* =====================================================================
   File Repository — global front-end behaviour
   ===================================================================== */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    initToasts();
    initPasswordToggles();
  });

  /** Auto-show any server-rendered toasts. */
  function initToasts() {
    if (typeof bootstrap === 'undefined') return;
    document.querySelectorAll('#toastContainer .toast').forEach(function (el) {
      bootstrap.Toast.getOrCreateInstance(el).show();
    });
  }

  /** Show/hide password fields via the adjacent toggle button. */
  function initPasswordToggles() {
    document.querySelectorAll('.toggle-password').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = document.querySelector(btn.getAttribute('data-target'));
        if (!target) return;
        var icon = btn.querySelector('i');
        if (target.type === 'password') {
          target.type = 'text';
          if (icon) { icon.classList.remove('bi-eye'); icon.classList.add('bi-eye-slash'); }
        } else {
          target.type = 'password';
          if (icon) { icon.classList.remove('bi-eye-slash'); icon.classList.add('bi-eye'); }
        }
      });
    });
  }

  /**
   * Programmatic toast helper for AJAX flows in later stages.
   *   window.showToast('Saved', 'success');
   */
  window.showToast = function (message, type) {
    var container = document.getElementById('toastContainer');
    if (!container || typeof bootstrap === 'undefined') return;

    var map = {
      success: 'text-bg-success',
      warning: 'text-bg-warning',
      error: 'text-bg-danger',
      info: 'text-bg-info'
    };
    var cls = map[type] || 'text-bg-secondary';

    var wrap = document.createElement('div');
    wrap.className = 'toast align-items-center ' + cls + ' border-0';
    wrap.setAttribute('role', 'alert');
    wrap.setAttribute('aria-live', 'assertive');
    wrap.setAttribute('aria-atomic', 'true');
    wrap.setAttribute('data-bs-delay', '5000');
    wrap.innerHTML =
      '<div class="d-flex">' +
        '<div class="toast-body"></div>' +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
      '</div>';
    wrap.querySelector('.toast-body').textContent = message;
    container.appendChild(wrap);

    var t = bootstrap.Toast.getOrCreateInstance(wrap);
    wrap.addEventListener('hidden.bs.toast', function () { wrap.remove(); });
    t.show();
  };
})();
