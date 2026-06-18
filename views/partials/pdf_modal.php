<?php
/**
 * PDF Options modal (stepped: view mode -> includes -> output action).
 * Shared by the File List and Work Area. Driven by public/assets/js/pdf.js.
 *
 * Requires window.PdfConfig = { url: '/{app}/pdf' } to be set on the page.
 */
?>
<div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-pdf me-2"></i>Generate PDF</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="pdfForm">
          <input type="hidden" name="id" id="pdfRecordId" value="">

          <!-- Step 1: View mode -->
          <div class="mb-3">
            <div class="fw-semibold mb-2"><span class="step-num">1</span> View Mode</div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="mode" id="modeMin" value="minimum">
              <label class="form-check-label" for="modeMin">
                <strong>Minimum</strong> — one-page summary (ref, title, date, status, dept/type)
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="mode" id="modeStd" value="standard" checked>
              <label class="form-check-label" for="modeStd">
                <strong>Standard</strong> — all metadata + attachment list (no notes)
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="mode" id="modeDet" value="detailed">
              <label class="form-check-label" for="modeDet">
                <strong>Detailed with Notes</strong> — all metadata + attachments + full note + history
              </label>
            </div>
          </div>

          <!-- Step 2: Includes -->
          <div class="mb-3">
            <div class="fw-semibold mb-2"><span class="step-num">2</span> Include</div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="attachments" id="incAtt" value="1" checked>
              <label class="form-check-label" for="incAtt">Attachments list</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="history" id="incHist" value="1" checked>
              <label class="form-check-label" for="incHist">Transaction history</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="qr" id="incQr" value="1" checked>
              <label class="form-check-label" for="incQr">QR code (links to the file's URL)</label>
            </div>
            <div class="form-text d-none" id="minimumNote">
              <i class="bi bi-info-circle"></i> Minimum view is a one-page cover slip — attachments and history are excluded.
            </div>
          </div>

          <!-- Step 3: Output -->
          <div class="mb-1">
            <div class="fw-semibold mb-2"><span class="step-num">3</span> Output</div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="action" id="actDownload" value="download" checked>
              <label class="form-check-label" for="actDownload"><i class="bi bi-download me-1"></i>Download</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="action" id="actPreview" value="preview">
              <label class="form-check-label" for="actPreview"><i class="bi bi-box-arrow-up-right me-1"></i>Preview in new tab</label>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="pdfGenerateBtn"><i class="bi bi-gear me-1"></i>Generate</button>
      </div>
    </div>
  </div>
</div>
