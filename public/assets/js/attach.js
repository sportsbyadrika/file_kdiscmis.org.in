/* Attach PDFs tool. */
(function () {
  'use strict';
  var cfg = window.AttachConfig || {};

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('attachForm');
    document.getElementById('btnPreview').addEventListener('click', function () { run(true, this); });
    document.getElementById('btnAttach').addEventListener('click', function () { run(false, this); });

    function run(dry, btn) {
      var spin = btn.querySelector('[data-spin]');
      spin.classList.remove('d-none');
      document.getElementById('btnPreview').disabled = true;
      document.getElementById('btnAttach').disabled = true;

      var fd = new FormData(form);
      fd.append('dry_run', dry ? '1' : '0');

      fetch(cfg.runUrl, {
        method: 'POST', body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.error || 'Failed');
          render(j);
        })
        .catch(function (err) { if (window.showToast) window.showToast(err.message || 'Failed.', 'error'); })
        .finally(function () {
          spin.classList.add('d-none');
          document.getElementById('btnPreview').disabled = false;
          document.getElementById('btnAttach').disabled = false;
        });
    }

    function render(j) {
      document.getElementById('attachResult').classList.remove('d-none');
      var s = document.getElementById('attachSummary');
      s.className = 'alert ' + (j.failed ? 'alert-warning' : 'alert-success');
      s.innerHTML =
        (j.dry_run ? '<strong>Preview only — nothing written.</strong> ' : '') +
        'Found <strong>' + j.found + '</strong> PDF(s). ' +
        (j.dry_run ? 'Would attach' : 'Attached') + ' <strong>' + j.attached + '</strong>, ' +
        'already attached <strong>' + j.already + '</strong>, ' +
        'no matching record <strong>' + j.no_record + '</strong>, ' +
        'failed <strong>' + j.failed + '</strong>. ' +
        '(mappings: ' + j.map_count + ')';

      var issues = (j.results || []).filter(function (r) { return r.status === 'no_record' || r.status === 'failed'; });
      var box = document.getElementById('attachIssues');
      if (!issues.length) { box.innerHTML = ''; return; }
      var rows = issues.slice(0, 300).map(function (r) {
        return '<tr><td>' + esc(r.name) + '</td><td>' + esc(r.status) + '</td><td>' + esc(r.detail) + '</td></tr>';
      }).join('');
      box.innerHTML =
        '<div class="small text-muted mb-1">Issues (first ' + Math.min(issues.length, 300) + ' of ' + issues.length + '):</div>' +
        '<div class="table-responsive" style="max-height:40vh;overflow:auto"><table class="table table-sm">' +
        '<thead><tr><th>File</th><th>Status</th><th>Detail</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  });
})();
