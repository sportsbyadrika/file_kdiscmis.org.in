/* =====================================================================
   PDF Options modal. Exposes window.openPdfModal(recordId).
   Builds the /{app}/pdf URL from the selected options and either
   downloads or previews the document. Requires window.PdfConfig.url.
   ===================================================================== */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('pdfModal');
    if (!modalEl) return;
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var form = document.getElementById('pdfForm');
    var minNote = document.getElementById('minimumNote');
    var incAtt = document.getElementById('incAtt');
    var incHist = document.getElementById('incHist');

    function syncMode() {
      var minimum = form.querySelector('input[name="mode"]:checked').value === 'minimum';
      // Minimum excludes attachments + history regardless of the checkboxes.
      [incAtt, incHist].forEach(function (cb) {
        cb.disabled = minimum;
        cb.closest('.form-check').classList.toggle('text-muted', minimum);
      });
      minNote.classList.toggle('d-none', !minimum);
    }

    form.querySelectorAll('input[name="mode"]').forEach(function (r) {
      r.addEventListener('change', syncMode);
    });

    window.openPdfModal = function (id) {
      document.getElementById('pdfRecordId').value = id;
      syncMode();
      modal.show();
    };

    document.getElementById('pdfGenerateBtn').addEventListener('click', function () {
      var id = document.getElementById('pdfRecordId').value;
      if (!id) return;
      var mode = form.querySelector('input[name="mode"]:checked').value;
      var action = form.querySelector('input[name="action"]:checked').value;
      var minimum = mode === 'minimum';

      var params = new URLSearchParams();
      params.set('id', id);
      params.set('mode', mode);
      params.set('attachments', (!minimum && incAtt.checked) ? '1' : '0');
      params.set('history', (!minimum && incHist.checked) ? '1' : '0');
      params.set('qr', document.getElementById('incQr').checked ? '1' : '0');
      params.set('action', action);

      var url = window.PdfConfig.url + '?' + params.toString();
      if (action === 'preview') {
        window.open(url, '_blank');
      } else {
        window.location.href = url;
      }
      modal.hide();
    });
  });
})();
