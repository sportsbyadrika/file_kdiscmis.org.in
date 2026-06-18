# Vendored libraries

These libraries are committed directly (not installed via Composer) because the
target shared-hosting environment has no Composer. They are dependency-free,
single-purpose, and used only for PDF generation.

## fpdf/ — FPDF 1.8.6
- Pure-PHP PDF generator. No license restrictions (free to use for any purpose).
- Site: http://www.fpdf.org/
- Only the core Helvetica/Courier/Times font metric files are included.

## qrcode/ — QR Code generator (qrcode.php)
- Copyright (c) 2009 Kazuhiko Arase. **MIT licensed.**
- Source: https://github.com/kazuhikoarase/qrcode-generator
- Used to compute the QR module matrix; the matrix is drawn as vector
  rectangles directly into the PDF (no raster image is produced).
