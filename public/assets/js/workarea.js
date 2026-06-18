/* =====================================================================
   File Work Area — note editor (Quill), tab persistence, expand toggle,
   attachments (upload/preview/delete), and metadata edit.
   Driven by window.WorkAreaConfig.
   ===================================================================== */
(function () {
  'use strict';

  var cfg = window.WorkAreaConfig || {};
  var quill = null;

  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initExpand();
    initNote();
    initAttachments();
    initEditMeta();
    initPdf();
  });

  function csrfBody(extra) {
    var fd = new FormData();
    fd.append('_csrf_token', cfg.csrfToken);
    Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
    return fd;
  }

  // --- Tab persistence (per record) -----------------------------------
  function initTabs() {
    var key = 'wa_tab_' + cfg.app + '_' + cfg.id;
    var saved = sessionStorage.getItem(key);
    if (saved) {
      var btn = document.querySelector('#waTabs [data-tab="' + saved + '"]');
      if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
    }
    document.querySelectorAll('#waTabs [data-bs-toggle="tab"]').forEach(function (b) {
      b.addEventListener('shown.bs.tab', function () {
        sessionStorage.setItem(key, b.getAttribute('data-tab'));
      });
    });
  }

  // --- Expand toggle (left panel fills width) -------------------------
  function initExpand() {
    var btn = document.getElementById('btnExpandNote');
    var split = document.getElementById('workareaSplit');
    if (!btn || !split) return;
    btn.addEventListener('click', function () {
      split.classList.toggle('note-expanded');
      var i = btn.querySelector('i');
      var expanded = split.classList.contains('note-expanded');
      if (i) i.className = expanded ? 'bi bi-arrows-angle-contract' : 'bi bi-arrows-angle-expand';
      btn.title = expanded ? 'Collapse' : 'Expand';
    });
  }

  // --- Note editor ----------------------------------------------------
  function initNote() {
    var display = document.getElementById('noteDisplay');
    var editor = document.getElementById('noteEditor');
    var btnEdit = document.getElementById('btnEditNote');
    var btnCancel = document.getElementById('btnCancelNote');
    var btnSave = document.getElementById('btnSaveNote');
    if (!btnEdit) return;

    function ensureQuill() {
      if (quill || typeof Quill === 'undefined') return;
      quill = new Quill('#quillEditor', {
        theme: 'snow',
        modules: {
          toolbar: [
            [{ header: [1, 2, 3, false] }],
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'link', 'clean']
          ]
        }
      });
    }

    btnEdit.addEventListener('click', function () {
      ensureQuill();
      if (quill) quill.root.innerHTML = display.innerHTML.indexOf('No note recorded') !== -1 ? '' : display.innerHTML;
      display.classList.add('d-none');
      editor.classList.remove('d-none');
      btnEdit.classList.add('d-none');
    });

    btnCancel.addEventListener('click', function () {
      editor.classList.add('d-none');
      display.classList.remove('d-none');
      btnEdit.classList.remove('d-none');
    });

    btnSave.addEventListener('click', function () {
      if (!quill) return;
      var spinner = document.getElementById('noteSpinner');
      spinner.classList.remove('d-none');
      btnSave.disabled = true;

      fetch(cfg.noteUrl, {
        method: 'POST',
        body: csrfBody({ id: cfg.id, note: quill.root.innerHTML }),
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.error || 'Save failed');
          display.innerHTML = j.html && j.html.trim() !== '' ? j.html
            : '<p class="text-muted">No note recorded. Click <strong>Edit Note</strong> to add one.</p>';
          document.getElementById('noteCounts').textContent = j.chars + ' chars · ' + j.words + ' words';
          editor.classList.add('d-none');
          display.classList.remove('d-none');
          btnEdit.classList.remove('d-none');
          if (window.showToast) window.showToast(j.message || 'Note saved.', 'success');
        })
        .catch(function (err) { if (window.showToast) window.showToast(err.message || 'Could not save note.', 'error'); })
        .finally(function () { spinner.classList.add('d-none'); btnSave.disabled = false; });
    });
  }

  // --- Attachments ----------------------------------------------------
  function initAttachments() {
    var form = document.getElementById('attUploadForm');
    var container = document.getElementById('attachmentsContainer');

    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(form);
        fd.append('_csrf_token', cfg.csrfToken);
        fd.append('id', cfg.id);
        var spinner = document.getElementById('attSpinner');
        spinner.classList.remove('d-none');

        fetch(cfg.uploadUrl, {
          method: 'POST', body: fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
        })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (!j.ok) throw new Error(j.error || 'Upload failed');
            container.innerHTML = j.html;
            document.getElementById('attCount').textContent = j.count;
            form.reset();
            if (window.showToast) window.showToast(j.message || 'Uploaded.', 'success');
          })
          .catch(function (err) { if (window.showToast) window.showToast(err.message || 'Upload failed.', 'error'); })
          .finally(function () { spinner.classList.add('d-none'); });
      });
    }

    // Preview (event delegation)
    container.addEventListener('click', function (e) {
      var btn = e.target.closest('.js-att-preview');
      if (!btn) return;
      e.preventDefault();
      openPreview(btn.getAttribute('data-kind'), btn.getAttribute('data-url'), btn.getAttribute('data-name'));
    });

    // Delete
    var delModalEl = document.getElementById('attDeleteModal');
    var delModal = bootstrap.Modal.getOrCreateInstance(delModalEl);
    var pendingId = null;
    container.addEventListener('click', function (e) {
      var btn = e.target.closest('.js-att-delete');
      if (!btn) return;
      pendingId = btn.getAttribute('data-id');
      document.getElementById('attDeleteName').textContent = btn.getAttribute('data-name') || 'this file';
      delModal.show();
    });
    document.getElementById('attConfirmDelete').addEventListener('click', function () {
      if (!pendingId) return;
      fetch(cfg.attDeleteUrl, {
        method: 'POST', body: csrfBody({ attachment_id: pendingId }),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          delModal.hide();
          if (!j.ok) throw new Error(j.error || 'Delete failed');
          container.innerHTML = j.html;
          document.getElementById('attCount').textContent = j.count;
          if (window.showToast) window.showToast(j.message || 'Deleted.', 'success');
        })
        .catch(function (err) { delModal.hide(); if (window.showToast) window.showToast(err.message || 'Delete failed.', 'error'); })
        .finally(function () { pendingId = null; });
    });
  }

  function openPreview(kind, url, name) {
    var modalEl = document.getElementById('previewModal');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    document.getElementById('previewTitle').textContent = name || 'Preview';
    document.getElementById('previewDownload').setAttribute('href', url.replace('/attachment/preview', '/attachment/download'));
    var body = document.getElementById('previewBody');
    if (kind === 'image') {
      body.innerHTML = '<div class="text-center p-3"><img src="' + url + '" class="img-fluid" alt="preview"></div>';
    } else {
      body.innerHTML = '<iframe src="' + url + '" style="width:100%;height:75vh;border:0;"></iframe>';
    }
    modal.show();
  }

  // --- Edit metadata (reuses list edit endpoints) ---------------------
  function initEditMeta() {
    var btn = document.getElementById('btnEditMeta');
    if (!btn) return;
    var modalEl = document.getElementById('editModal');
    var contentEl = document.getElementById('editModalContent');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    btn.addEventListener('click', function () {
      contentEl.innerHTML = '<div class="modal-body text-center py-5"><div class="spinner-border text-primary"></div></div>';
      modal.show();
      fetch(cfg.editUrl + '?id=' + cfg.id, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      })
        .then(function (r) { if (!r.ok) throw new Error(); return r.text(); })
        .then(function (html) { contentEl.innerHTML = html; bindEditForm(modal); })
        .catch(function () { modal.hide(); if (window.showToast) window.showToast('Could not load the record.', 'error'); });
    });
  }

  function bindEditForm(modal) {
    var form = document.getElementById('editForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
      var box = form.querySelector('#editFormError');
      if (box) { box.classList.add('d-none'); box.textContent = ''; }

      fetch(form.getAttribute('data-action'), {
        method: 'POST', body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.ok) {
            if (window.showToast) window.showToast('Record updated.', 'success');
            setTimeout(function () { window.location.reload(); }, 400);
          } else {
            var errors = j.errors || {};
            var general = [];
            Object.keys(errors).forEach(function (key) {
              var input = form.querySelector('[name="' + key + '"]');
              var fb = form.querySelector('[data-error-for="' + key + '"]');
              if (input && fb) { input.classList.add('is-invalid'); fb.textContent = errors[key]; }
              else general.push(errors[key]);
            });
            if (general.length && box) { box.textContent = general.join(' '); box.classList.remove('d-none'); }
          }
        })
        .catch(function () { if (window.showToast) window.showToast('Could not save changes.', 'error'); });
    });
  }

  // --- Generate PDF (Stage 6) -----------------------------------------
  function initPdf() {
    var btn = document.getElementById('btnGenPdf');
    if (btn) btn.addEventListener('click', function () {
      if (window.showToast) window.showToast('PDF generation arrives in Stage 6.', 'info');
    });
  }
})();
