<?php
// File: download.php
// Fasilitas download gambar

require_once __DIR__ . '/includes/config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Ambil data foto
$stmt = $koneksi->prepare('SELECT filename, judul FROM photos WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$photo = $stmt->get_result()->fetch_assoc();

if (!$photo) {
    header('Location: index.php');
    exit;
}

$filePath = UPLOAD_DIR . $photo['filename'];

if (!is_file($filePath)) {
    http_response_code(404);
    die('File tidak ditemukan!');
}

// Nama file untuk download: judul + ekstensi asli
$ext = strtolower(pathinfo($photo['filename'], PATHINFO_EXTENSION));
$downloadName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $photo['judul']) . '.' . $ext;

// Header untuk memaksa browser download (bukan tampil)
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
