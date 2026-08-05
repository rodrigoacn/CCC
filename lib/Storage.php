<?php
// ─────────────────────────────────────────────────────────────────────────────
//  Storage.php — Shared upload path abstraction
//  Resolves upload paths across VMs (local, NFS, S3-compatible)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('storagePath')) {

// Base path for uploads — configurable per VM
function storagePath(string $subpath = ''): string {
    $base = getenv('STORAGE_PATH') ?: (__DIR__ . '/../uploads');
    return $base . '/' . ltrim($subpath, '/');
}

// Public URL for serving uploads
function storageUrl(string $subpath = ''): string {
    $base = getenv('STORAGE_URL') ?: '';
    if (!$base) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/CCC/uploads';
    }
    return $base . '/' . ltrim($subpath, '/');
}

// Ensure directory exists
function storageEnsureDir(string $subpath): bool {
    $dir = storagePath($subpath);
    if (is_dir($dir)) return true;
    return mkdir($dir, 0755, true);
}

// Move uploaded file to storage
function storagePut(string $subpath, string $tmpName): bool {
    storageEnsureDir(dirname($subpath));
    return move_uploaded_file($tmpName, storagePath($subpath));
}

// Write raw content to storage (for base64 mobile uploads)
function storageWrite(string $subpath, string $content): bool {
    storageEnsureDir(dirname($subpath));
    return file_put_contents(storagePath($subpath), $content) !== false;
}

// Check if file exists
function storageExists(string $subpath): bool {
    return file_exists(storagePath($subpath));
}

// Delete file
function storageDelete(string $subpath): bool {
    $path = storagePath($subpath);
    return file_exists($path) ? unlink($path) : true;
}

} // End function_exists check
