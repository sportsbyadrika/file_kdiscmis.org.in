/* Attach PDFs tool — scan then process in chunks with a progress bar. */
(function () {
  'use strict';
  var cfg = window.AttachConfig || {};
  var stop = false;
  var totals = { found: 0, attached: 0, already: 0, no_record: 0, ambiguous: 0, failed: 0 };
  var issues = [];

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('attachForm');
    var modeSel = document.getElementById('modeSel');
    var mapWrap = document.getElementById('mapWrap');
    var appSel = document.getElementById('appSel');

    modeSel.addEventListener('change', function () {
      mapWrap.style.display = modeSel.value === 'map' ? '' : 'none';
    });

    // Keep the displayed staging folder + count in sync with the chosen app.
    appSel.addEventListener('change', function () {
      var info = (cfg.dirs || {})[appSel.value];
      if (!info) return;
      var pathEl = document.getElementById('stagePath');
      var cntEl = document.getElementById('pdfCount');
      if (pathEl) pathEl.textContent = info.path;
      if (cntEl) cntEl.textContent = info.count;
    });

    document.getElementById('btnStart').addEventListener('click', start);
    document.getElementById('btnStop').addEventListener('click', function () {
      stop = true;
      document.getElementById('progressLabel').textContent = 'Stopping…';
    });

    function resetTotals() {
      totals = { found: 0, attached: 0, already: 0, no_record: 0, ambiguous: 0, failed: 0 };
      issues = [];
      stop = false;
    }

    function start() {
      resetTotals();
      var startSpin = document.getElementById('startSpin');
      startSpin.classList.remove('d-none');
      document.getElementById('btnStart').disabled = true;
      document.getElementById('totalsWrap').classList.add('d-none');

      fetch(cfg.scanUrl, {
        method: 'POST', body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.error || 'Scan failed');
          totals.found = j.total;
          document.getElementById('progressWrap').classList.remove('d-none');
          document.getElementById('btnStop').classList.remove('d-none');
          var dry = j.dry_run;
          setLabel(0, j.total, dry);
          runChunk(j.token, 0, j.total, j.chunk, dry);
        })
        .catch(function (err) {
          if (window.showToast) window.showToast(err.message || 'Scan failed.', 'error');
          finishUi();
        })
        .finally(function () { startSpin.classList.add('d-none'); });
    }

    function runChunk(token, offset, total, chunk, dry) {
      if (stop) { finish(dry, true); return; }
      var body = new FormData();
      body.append('_csrf_token', cfg.csrfToken);
      body.append('token', token);
      body.append('offset', offset);

      fetch(cfg.runUrl, {
        method: 'POST', body: body,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok) throw new Error(j.error || 'Run failed');
          ['attached', 'already', 'no_record', 'ambiguous', 'failed'].forEach(function (k) { totals[k] += j[k] || 0; });
          (j.results || []).forEach(function (x) { if (issues.length < 2000) issues.push(x); });
          var doneCount = Math.min(j.next_offset, total);
          setLabel(doneCount, total, dry);
          if (j.done || stop) { finish(dry, stop); }
          else { runChunk(token, j.next_offset, total, chunk, dry); }
        })
        .catch(function (err) {
          if (window.showToast) window.showToast(err.message || 'Run failed.', 'error');
          finish(dry, true);
        });
    }

    function setLabel(done, total, dry) {
      var pct = total ? Math.round((done / total) * 100) : 100;
      document.getElementById('progressBar').style.width = pct + '%';
      document.getElementById('progressPct').textContent = pct + '%';
      document.getElementById('progressLabel').textContent =
        (dry ? 'Previewing ' : 'Attaching ') + done + ' / ' + total;
    }

    function finish(dry, stopped) {
      var bar = document.getElementById('progressBar');
      bar.classList.remove('progress-bar-animated');
      finishUi();

      var w = document.getElementById('totalsWrap');
      w.classList.remove('d-none');
      var b = document.getElementById('totalsBanner');
      b.className = 'alert ' + (totals.failed ? 'alert-warning' : 'alert-success');
      b.innerHTML =
        (dry ? '<strong>Preview only — nothing was attached.</strong> ' : '') +
        (stopped ? '<strong>Stopped.</strong> ' : '') +
        'Found <strong>' + totals.found + '</strong>. ' +
        (dry ? 'Would attach' : 'Attached') + ' <strong>' + totals.attached + '</strong>, ' +
        'already <strong>' + totals.already + '</strong>, ' +
        'no record <strong>' + totals.no_record + '</strong>, ' +
        'ambiguous <strong>' + totals.ambiguous + '</strong>, ' +
        'failed <strong>' + totals.failed + '</strong>.' +
        (dry && totals.attached ? ' <br>Untick “Preview only” and run again to attach for real.' : '');

      var box = document.getElementById('issuesBox');
      var probs = issues.filter(function (x) { return x.status !== 'would_attach'; });
      if (!probs.length) { box.innerHTML = ''; return; }
      var rows = probs.slice(0, 500).map(function (x) {
        return '<tr><td>' + esc(x.name) + '</td><td>' + esc(x.status) + '</td><td>' + esc(x.detail) + '</td></tr>';
      }).join('');
      box.innerHTML =
        '<div class="small text-muted mb-1">Unmatched / issues (first ' + Math.min(probs.length, 500) + ' of ' + probs.length + '):</div>' +
        '<div class="table-responsive" style="max-height:40vh;overflow:auto"><table class="table table-sm">' +
        '<thead><tr><th>File</th><th>Status</th><th>Detail</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
    }

    function finishUi() {
      document.getElementById('btnStart').disabled = false;
      document.getElementById('btnStop').classList.add('d-none');
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  });
})();
