/* =====================================================================
   Bulk Upload wizard. Driven by window.BulkConfig.
   ===================================================================== */
(function () {
  'use strict';

  var cfg = window.BulkConfig || {};
  var state = { app: cfg.app || '', token: null, summary: null };

  document.addEventListener('DOMContentLoaded', function () {
    bindAppSelect();
    bindNav();
    bindUpload();
    bindConfirm();
    if (state.app) updateTemplate();
  });

  function showStep(n) {
    document.querySelectorAll('.wizard-pane').forEach(function (p) {
      p.classList.toggle('d-none', p.getAttribute('data-pane') !== String(n));
    });
    document.querySelectorAll('#wizardSteps li').forEach(function (li) {
      var s = parseInt(li.getAttribute('data-step'), 10);
      li.classList.toggle('active', s === n);
      li.classList.toggle('done', s < n);
    });
  }

  // --- Step 1: select app --------------------------------------------
  function bindAppSelect() {
    document.querySelectorAll('.app-select-card').forEach(function (card) {
      card.addEventListener('click', function () {
        document.querySelectorAll('.app-select-card').forEach(function (c) { c.classList.remove('selected'); });
        card.classList.add('selected');
        card.querySelector('input[type=radio]').checked = true;
        state.app = card.getAttribute('data-app');
        document.getElementById('step1Next').disabled = false;
      });
    });
    document.getElementById('step1Next').addEventListener('click', function () {
      if (!state.app) return;
      updateTemplate();
      showStep(2);
    });
  }

  function updateTemplate() {
    document.getElementById('tplXlsx').href = cfg.templateUrl + '?app=' + state.app + '&format=xlsx';
    document.getElementById('tplCsv').href = cfg.templateUrl + '?app=' + state.app + '&format=csv';
    var desc = (cfg.descriptions && cfg.descriptions[state.app]) || '';
    document.getElementById('templateDesc').textContent = desc;
  }

  // --- Navigation -----------------------------------------------------
  function bindNav() {
    document.querySelectorAll('.js-to-step').forEach(function (b) {
      b.addEventListener('click', function () { showStep(parseInt(b.getAttribute('data-step'), 10)); });
    });
    document.querySelectorAll('.js-back').forEach(function (b) {
      b.addEventListener('click', function () {
        var current = parseInt(document.querySelector('.wizard-pane:not(.d-none)').getAttribute('data-pane'), 10);
        showStep(Math.max(1, current - 1));
      });
    });
    var newImport = document.getElementById('newImport');
    if (newImport) newImport.addEventListener('click', function () { window.location.reload(); });
  }

  // --- Step 3: upload & validate -------------------------------------
  function bindUpload() {
    var form = document.getElementById('uploadForm');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(form);
      fd.append('app', state.app);
      fd.append('_csrf_token', cfg.csrfToken);
      var spinner = document.getElementById('validateSpinner');
      spinner.classList.remove('d-none');

      fetch(cfg.validateUrl, {
        method: 'POST', body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.error || 'Validation failed');
          state.token = j.token;
          state.summary = j.summary;
          renderValidation(j);
        })
        .catch(function (err) { if (window.showToast) window.showToast(err.message || 'Validation failed.', 'error'); })
        .finally(function () { spinner.classList.add('d-none'); });
    });

    document.getElementById('toConfirmBtn').addEventListener('click', function () {
      var s = state.summary;
      var parts = [];
      parts.push('INSERT ' + s.insert);
      parts.push('UPDATE ' + s.update);
      if (s.history_only) parts.push('HISTORY ' + s.history_only);
      document.getElementById('confirmText').textContent =
        'You are about to ' + parts.join(', ') + '. ' + (s.errors ? s.errors + ' row(s) with errors will be skipped. ' : '') + 'Continue?';
      showStep(4);
    });
  }

  function renderValidation(j) {
    document.getElementById('validateResult').classList.remove('d-none');
    var s = j.summary;
    var banner = document.getElementById('summaryBanner');
    banner.className = 'alert d-flex flex-wrap gap-3 align-items-center ' + (s.errors ? 'alert-warning' : 'alert-success');
    banner.innerHTML =
      '<span><strong>' + s.insert + '</strong> to insert</span>' +
      '<span><strong>' + s.update + '</strong> to update</span>' +
      (s.history_only ? '<span><strong>' + s.history_only + '</strong> history-only</span>' : '') +
      '<span class="text-danger"><strong>' + s.errors + '</strong> error(s)</span>' +
      (s.warnings ? '<span class="text-warning-emphasis"><strong>' + s.warnings + '</strong> warning(s)</span>' : '');
    var note = j.truncated
      ? '<div class="alert alert-info py-2 small mb-2">Showing the first ' + j.cap + ' rows. All ' +
        s.total + ' rows will be processed when you continue.</div>'
      : '';
    document.getElementById('previewTab').innerHTML = note + j.preview;
    document.getElementById('errorsTab').innerHTML = j.errors;
    document.getElementById('errCount').textContent = s.errors;
    document.getElementById('toConfirmBtn').disabled = !j.can_proceed;
  }

  // --- Step 4: confirm & process -------------------------------------
  function bindConfirm() {
    var phrase = document.getElementById('confirmPhrase');
    var btn = document.getElementById('processBtn');
    phrase.addEventListener('input', function () {
      btn.disabled = phrase.value.trim().toUpperCase() !== 'IMPORT';
    });

    btn.addEventListener('click', function () {
      if (!state.token) return;
      var spinner = document.getElementById('processSpinner');
      spinner.classList.remove('d-none');
      btn.disabled = true;
      var fd = new FormData();
      fd.append('token', state.token);
      fd.append('_csrf_token', cfg.csrfToken);

      fetch(cfg.processUrl, {
        method: 'POST', body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.error || 'Processing failed');
          renderSummary(j);
          showStep(5);
        })
        .catch(function (err) {
          if (window.showToast) window.showToast(err.message || 'Processing failed.', 'error');
          btn.disabled = false;
        })
        .finally(function () { spinner.classList.add('d-none'); });
    });
  }

  function renderSummary(j) {
    document.getElementById('summaryBatch').textContent = j.batch_id;
    var counts = [
      ['Total', j.total, 'secondary'],
      ['Inserted', j.inserted, 'success'],
      ['Updated', j.updated, 'primary'],
      ['History', j.history, 'info'],
      ['Skipped', j.skipped, 'warning'],
      ['Failed', j.failed, 'danger']
    ];
    document.getElementById('summaryCounts').innerHTML = counts.map(function (c) {
      return '<div class="col-6 col-md-2"><div class="border rounded p-2"><div class="h4 mb-0 text-' + c[2] + '">' +
        c[1] + '</div><div class="small text-muted">' + c[0] + '</div></div></div>';
    }).join('');
    var dl = document.getElementById('downloadReport');
    if (j.report_url) { dl.href = j.report_url; dl.classList.remove('d-none'); } else { dl.classList.add('d-none'); }
    document.getElementById('viewRecords').href = j.list_url;
    document.getElementById('viewAudit').href = j.audit_url;
  }
})();
