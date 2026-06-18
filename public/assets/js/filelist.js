/* =====================================================================
   File List View — AJAX list, sort, filters, pagination, columns,
   edit modal, soft-delete. Driven by window.FileListConfig.
   ===================================================================== */
(function () {
  'use strict';

  var cfg = window.FileListConfig || {};
  var currentPage = 1;

  var els = {};

  document.addEventListener('DOMContentLoaded', function () {
    els.tableContainer = document.getElementById('tableContainer');
    els.pagination     = document.getElementById('paginationContainer');
    els.activeFilters  = document.getElementById('activeFilters');
    els.recordCount    = document.getElementById('recordCount');
    els.overlay        = document.getElementById('loadingOverlay');
    els.filterForm     = document.getElementById('filterForm');
    els.columnsMenu    = document.getElementById('columnsMenu');

    bindFilters();
    bindSorting();
    bindPagination();
    bindColumns();
    bindRowActions();
    bindEditModal();
    bindDeleteModal();
  });

  // --- Core loader ----------------------------------------------------
  function buildParams(extra) {
    var params = new URLSearchParams(new FormData(els.filterForm));
    extra = extra || {};
    Object.keys(extra).forEach(function (k) {
      if (extra[k] !== undefined && extra[k] !== null) params.set(k, extra[k]);
    });
    return params;
  }

  function load(extra, opts) {
    opts = opts || {};
    if (extra && extra.page) currentPage = parseInt(extra.page, 10) || 1;
    var merged = Object.assign({ page: currentPage }, extra || {});
    var params = buildParams(merged);

    showLoading(true);
    fetch(cfg.dataUrl + '?' + params.toString(), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (data) {
        if (!data.ok) throw new Error('Bad response');
        els.tableContainer.innerHTML = data.table;
        els.pagination.innerHTML = data.pagination;
        els.activeFilters.innerHTML = data.active;
        els.recordCount.textContent = data.count;
      })
      .catch(function () {
        if (window.showToast) window.showToast('Could not load records.', 'error');
      })
      .finally(function () { showLoading(false); });
  }

  function showLoading(on) {
    if (els.overlay) els.overlay.classList.toggle('d-none', !on);
  }

  // --- Filters --------------------------------------------------------
  function bindFilters() {
    if (els.filterForm) {
      els.filterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        load({ page: 1 });
      });
    }

    // Clear all (button appears in toolbar and in chips row)
    document.addEventListener('click', function (e) {
      if (e.target.closest('.js-clear-filters')) {
        e.preventDefault();
        els.filterForm.reset();
        els.filterForm.querySelectorAll('select[multiple]').forEach(function (sel) {
          Array.prototype.forEach.call(sel.options, function (o) { o.selected = false; });
        });
        load({ page: 1 });
      }
    });

    // Remove a single chip
    els.activeFilters.addEventListener('click', function (e) {
      var btn = e.target.closest('.js-remove-filter');
      if (!btn) return;
      var chip = btn.closest('.active-filter-chip');
      clearFilter(chip.getAttribute('data-filter'), chip.getAttribute('data-value'));
      load({ page: 1 });
    });
  }

  function clearFilter(key, value) {
    var form = els.filterForm;
    if (key === 'keyword') { form.keyword.value = ''; }
    else if (key === 'date') { form.date_from.value = ''; form.date_to.value = ''; }
    else if (key === 'uploaded_by') { form.uploaded_by.value = '0'; }
    else if (key === 'has_attachments') { if (form.has_attachments) form.has_attachments.checked = false; }
    else if (key === 'has_history') { if (form.has_history) form.has_history.checked = false; }
    else {
      // multi-select (group / category / status): deselect the one value
      var sel = form.querySelector('select[name="' + key + '[]"]');
      if (sel) Array.prototype.forEach.call(sel.options, function (o) { if (o.value === value) o.selected = false; });
    }
  }

  // --- Sorting --------------------------------------------------------
  function bindSorting() {
    els.tableContainer.addEventListener('click', function (e) {
      var th = e.target.closest('th.sortable');
      if (!th) return;
      load({ page: 1, sort: th.getAttribute('data-sort-key'), dir: th.getAttribute('data-sort-dir') });
    });
  }

  // --- Pagination -----------------------------------------------------
  function bindPagination() {
    els.pagination.addEventListener('click', function (e) {
      var a = e.target.closest('a.page-link');
      if (!a) return;
      e.preventDefault();
      if (a.closest('.page-item.disabled') || a.closest('.page-item.active')) return;
      load({ page: a.getAttribute('data-page') });
    });
    els.pagination.addEventListener('change', function (e) {
      if (e.target.id === 'perPageSelect') {
        load({ page: 1, per_page: e.target.value });
      }
    });
  }

  // --- Column visibility ---------------------------------------------
  function bindColumns() {
    if (!els.columnsMenu) return;
    els.columnsMenu.addEventListener('change', function (e) {
      if (!e.target.classList.contains('js-col-toggle')) return;
      var checked = Array.prototype.slice.call(
        els.columnsMenu.querySelectorAll('.js-col-toggle:checked')
      ).map(function (c) { return c.value; });
      if (checked.length === 0) {           // keep at least one column
        e.target.checked = true;
        return;
      }
      load({ page: currentPage, columns: checked.join(',') });
    });
  }

  // --- Row actions: download / pdf (gated to later stages) -----------
  function bindRowActions() {
    els.tableContainer.addEventListener('click', function (e) {
      if (e.target.closest('.js-download')) {
        if (window.showToast) window.showToast('Attachment download arrives with the Work Area (Stage 5).', 'info');
      } else if (e.target.closest('.js-pdf')) {
        var pdfBtn = e.target.closest('.js-pdf');
        if (window.openPdfModal) window.openPdfModal(pdfBtn.getAttribute('data-id'));
      }
    });
  }

  // --- Edit modal -----------------------------------------------------
  function bindEditModal() {
    var modalEl = document.getElementById('editModal');
    var contentEl = document.getElementById('editModalContent');
    if (!modalEl) return;
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    els.tableContainer.addEventListener('click', function (e) {
      var btn = e.target.closest('.js-edit');
      if (!btn) return;
      var id = btn.getAttribute('data-id');
      contentEl.innerHTML = '<div class="modal-body text-center py-5"><div class="spinner-border text-primary"></div></div>';
      modal.show();
      fetch(cfg.editUrl + '?id=' + encodeURIComponent(id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      })
        .then(function (r) { if (!r.ok) throw new Error(); return r.text(); })
        .then(function (html) { contentEl.innerHTML = html; bindEditForm(modal); })
        .catch(function () {
          modal.hide();
          if (window.showToast) window.showToast('Could not load the record.', 'error');
        });
    });
  }

  function bindEditForm(modal) {
    var form = document.getElementById('editForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearFormErrors(form);
      var spinner = document.getElementById('editSpinner');
      var submitBtn = form.querySelector('button[type="submit"]');
      if (spinner) spinner.classList.remove('d-none');
      if (submitBtn) submitBtn.disabled = true;

      fetch(form.getAttribute('data-action'), {
        method: 'POST', body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      })
        .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
        .then(function (res) {
          if (res.body.ok) {
            modal.hide();
            if (window.showToast) window.showToast(res.body.message || 'Saved.', 'success');
            load({ page: currentPage });
          } else {
            showFormErrors(form, res.body.errors || {});
          }
        })
        .catch(function () {
          if (window.showToast) window.showToast('Could not save changes.', 'error');
        })
        .finally(function () {
          if (spinner) spinner.classList.add('d-none');
          if (submitBtn) submitBtn.disabled = false;
        });
    });
  }

  function clearFormErrors(form) {
    var box = form.querySelector('#editFormError');
    if (box) { box.classList.add('d-none'); box.textContent = ''; }
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
  }

  function showFormErrors(form, errors) {
    var general = [];
    Object.keys(errors).forEach(function (key) {
      var input = form.querySelector('[name="' + key + '"]');
      var feedback = form.querySelector('[data-error-for="' + key + '"]');
      if (input && feedback) {
        input.classList.add('is-invalid');
        feedback.textContent = errors[key];
      } else {
        general.push(errors[key]);
      }
    });
    if (general.length) {
      var box = form.querySelector('#editFormError');
      if (box) { box.textContent = general.join(' '); box.classList.remove('d-none'); }
    }
  }

  // --- Delete modal ---------------------------------------------------
  function bindDeleteModal() {
    var modalEl = document.getElementById('deleteModal');
    if (!modalEl) return;
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var pendingId = null;

    els.tableContainer.addEventListener('click', function (e) {
      var btn = e.target.closest('.js-delete');
      if (!btn) return;
      pendingId = btn.getAttribute('data-id');
      document.getElementById('deleteRef').textContent = btn.getAttribute('data-ref') || ('record #' + pendingId);
      modal.show();
    });

    var confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.addEventListener('click', function () {
      if (!pendingId) return;
      var spinner = document.getElementById('deleteSpinner');
      if (spinner) spinner.classList.remove('d-none');
      confirmBtn.disabled = true;

      var body = new FormData();
      body.append('id', pendingId);
      body.append('_csrf_token', cfg.csrfToken);

      fetch(cfg.deleteUrl, {
        method: 'POST', body: body,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          modal.hide();
          if (j.ok) {
            if (window.showToast) window.showToast(j.message || 'Record deleted.', 'success');
            load({ page: currentPage });
          } else {
            if (window.showToast) window.showToast(j.error || 'Could not delete.', 'error');
          }
        })
        .catch(function () { if (window.showToast) window.showToast('Could not delete.', 'error'); })
        .finally(function () {
          if (spinner) spinner.classList.add('d-none');
          confirmBtn.disabled = false;
          pendingId = null;
        });
    });
  }
})();
