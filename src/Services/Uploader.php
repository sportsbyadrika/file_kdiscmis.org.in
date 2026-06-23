<?php
declare(strict_types=1);

namespace App\Services;

use App\Config;

/**
 * Validates and stores uploaded files against the configured whitelist.
 * Stored filenames are randomised; the original name is preserved in the DB.
 */
final class Uploader
{
    /**
     * @param array $file  A single entry from $_FILES
     * @return array{original:string,stored_path:string,mime:string,size:int}
     * @throws \RuntimeException on validation failure
     */
    public static function store(array $file, int $fileId): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new \RuntimeException('Invalid upload.');
        }
        switch ($file['error']) {
            case UPLOAD_ERR_OK: break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new \RuntimeException('The file is larger than the server allows.');
            case UPLOAD_ERR_NO_FILE:
                throw new \RuntimeException('No file was selected.');
            default:
                throw new \RuntimeException('Upload failed. Please try again.');
        }

        $maxSize = (int) Config::get('uploads.max_size_bytes', 25 * 1024 * 1024);
        if ($file['size'] <= 0) {
            throw new \RuntimeException('The file is empty.');
        }
        if ($file['size'] > $maxSize) {
            throw new \RuntimeException('The file exceeds the maximum size of ' . format_bytes($maxSize) . '.');
        }

        $original = (string) $file['name'];
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowedExt = array_map('strtolower', (array) Config::get('uploads.allowed_extensions', []));
        if ($ext === '' || !in_array($ext, $allowedExt, true)) {
            throw new \RuntimeException('This file type is not allowed.');
        }

        // Detect real MIME from content, then verify against the whitelist.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        $allowedMime = (array) Config::get('uploads.allowed_mime_types', []);
        if (!in_array($mime, $allowedMime, true)) {
            // Some CSV/plain types are reported inconsistently; allow text/* for csv/txt.
            $textyOk = in_array($ext, ['csv', 'txt'], true) && str_starts_with($mime, 'text/');
            if (!$textyOk) {
                throw new \RuntimeException('The file content type (' . $mime . ') is not allowed.');
            }
        }

        $baseDir = rtrim((string) Config::get('storage.uploads'), '/');
        $destDir = $baseDir . '/' . $fileId;
        if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            throw new \RuntimeException('Could not prepare the storage directory.');
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath   = $destDir . '/' . $storedName;
        $relPath    = $fileId . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            // Fallback for non-HTTP contexts (kept for completeness).
            if (!@rename($file['tmp_name'], $destPath)) {
                throw new \RuntimeException('Could not save the uploaded file.');
            }
        }
        @chmod($destPath, 0640);

        return [
            'original'    => self::sanitizeName($original),
            'stored_path' => $relPath,
            'mime'        => $mime,
            'size'        => (int) $file['size'],
        ];
    }

    /** Absolute path for a stored relative path (guards against traversal). */
    public static function absolutePath(string $relPath): ?string
    {
        $baseDir = rtrim((string) Config::get('storage.uploads'), '/');
        $full = $baseDir . '/' . $relPath;
        $realBase = realpath($baseDir);
        $realFull = realpath($full);
        if ($realBase === false || $realFull === false) {
            return null;
        }
        if (!str_starts_with($realFull, $realBase . DIRECTORY_SEPARATOR)) {
            return null; // outside the uploads dir
        }
        return $realFull;
    }

    private static function sanitizeName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^\w.\- ]+/u', '_', $name) ?? 'file';
        return mb_substr(trim($name), 0, 200) ?: 'file';
    }
}
