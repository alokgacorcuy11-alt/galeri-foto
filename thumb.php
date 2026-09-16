<?php
// File: thumb.php
// Thumbnail on-demand: foto feed di-scale ke lebar requested (default 640) lalu
// disimpan di uploads/thumbs dan dilayani dengan cache header panjang.
// Tanpa koneksi DB/sesi agar ringan; aman: hanya basename + whitelist ekstensi.

$f   = basename((string)($_GET['f'] ?? ''));
$w   = (int)($_GET['w'] ?? 640);
$w   = max(120, min(1200, $w));
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
$src = __DIR__ . '/uploads/' . $f;

if ($f === '' || !in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) || !is_file($src)) {
    http_response_code(404);
    exit;
}

function serve_original(string $src, string $ext): void
{
    $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'][$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=2592000, immutable');
    header('Content-Length: ' . filesize($src));
    readfile($src);
    exit;
}

// Kalau aslinya sudah lebih kecil dari permintaan, layani aslinya saja.
$info = @getimagesize($src);
if ($info === false || $info[0] <= $w || !function_exists('imagecreatetruecolor')) {
    serve_original($src, $ext);
}

$thumbDir = __DIR__ . '/uploads/thumbs';
if (!is_dir($thumbDir)) {
    @mkdir($thumbDir, 0755, true);
}
$out = $thumbDir . '/' . $w . '_' . $f . '.jpg';
if (!is_file($out) || filemtime($out) < filemtime($src)) {
    $img = match ($ext) {
        'png'  => @imagecreatefrompng($src),
        'gif'  => @imagecreatefromgif($src),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default => @imagecreatefromjpeg($src),
    };
    if (!$img) {
        serve_original($src, $ext);
    }
    $ow = imagesx($img);
    $oh = imagesy($img);
    $nw = $w;
    $nh = (int)round($oh * $w / $ow);
    $dst = imagecreatetruecolor($nw, $nh);
    // canvas putih: transparansi PNG/WEBP jadi JPG rata
    imagefill($dst, 0, 0, imagecolorallocate($dst, 11, 6, 32)); // warna tema bg (bukan putih kosong)
    if (imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $ow, $oh) && imagejpeg($dst, $out, 82)) {
        imagedestroy($dst);
        imagedestroy($img);
    } else {
        @unlink($out);
        imagedestroy($dst);
        imagedestroy($img);
        serve_original($src, $ext);
    }
}

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=2592000, immutable');
header('Content-Length: ' . filesize($out));
readfile($out);
