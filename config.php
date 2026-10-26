<?php
/**
 * config.php — Konfigurasi, koneksi database (Laragon) & helper.
 * Di-include oleh SEMUA halaman. Session + koneksi diatur di sini saja.
 */

// ── Pengaturan Database (default Laragon: user root, password kosong) ──────
const DB_HOST = '127.0.0.1';   // pakai 127.0.0.1 (lebih stabil daripada "localhost" di Windows)
const DB_PORT = 3306;          // ganti jika MySQL Laragon kamu pakai port lain (cek di Menu > MySQL)
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'petshop_db';

// Kompatibilitas kode lama (backup.php memakai $database)
$database = DB_NAME;

// ── Zona waktu (WIB) ───────────────────────────────────────────────────────
date_default_timezone_set('Asia/Jakarta');

// ── Error handling: jangan tampilkan error ke browser, simpan ke logs/ ─────
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
    @file_put_contents($logDir . '/.htaccess', "Require all denied\nDeny from all\n");
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/php_error.log');

// PHP 8.1+ melempar exception untuk error mysqli. Kode ini memakai pola
// "cek return false", jadi matikan mode exception.
mysqli_report(MYSQLI_REPORT_OFF);

// ── Session ────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('PETSHOP_SESSION');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Koneksi ────────────────────────────────────────────────────────────────
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if (!$conn) {
    $err = mysqli_connect_error();
    error_log('DB connect gagal: ' . $err);
    http_response_code(500);
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
        || ($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
        exit;
    }
    echo '<!DOCTYPE html><meta charset="utf-8"><body style="font-family:Arial;padding:40px;max-width:640px;margin:auto">'
       . '<h2>⚠️ Koneksi database gagal</h2>'
       . '<p>Pastikan <b>MySQL di Laragon sudah Start</b> dan database <code>' . htmlspecialchars(DB_NAME)
       . '</code> sudah di-import (lihat README.md).</p>'
       . '<p style="color:#888">Detail: ' . htmlspecialchars($err) . '</p></body>';
    exit;
}

mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "SET time_zone = '+07:00'");   // samakan CURDATE()/NOW() dengan WIB

// ── Kategori produk: emoji penanda + pilihan emoji produk ──────────────────
const CATEGORIES = [
    'Makanan'   => ['icon' => '🍖', 'emojis' => ['🦴', '🍖', '🐟', '🌾', '🥫', '🥩', '🐾']],
    'Aksesoris' => ['icon' => '🎀', 'emojis' => ['🎀', '🦮', '🧳', '🛏️', '🥣', '🧣', '📿']],
    'Perawatan' => ['icon' => '🧴', 'emojis' => ['🧴', '🪣', '🧼', '🪥', '🧽', '🛁', '✂️']],
    'Mainan'    => ['icon' => '🎾', 'emojis' => ['⚽', '🎾', '🪀', '🧸', '🪵', '🦴', '🪁']],
    'Kesehatan' => ['icon' => '💊', 'emojis' => ['💊', '💉', '🩺', '🩹', '🌡️', '🧪', '🧬']],
    'Lainnya'   => ['icon' => '📦', 'emojis' => ['📦', '🏷️', '🐕', '🐈', '🐦', '🐠', '🐹']],
];

/** Emoji penanda sebuah kategori (kategori tak dikenal => 🏷️). */
function category_icon(string $cat): string {
    return CATEGORIES[$cat]['icon'] ?? '🏷️';
}

/** Urutkan nama kategori: sesuai daftar CATEGORIES dulu, sisanya alfabetis. */
function category_sort(array $names): array {
    $order = array_keys(CATEGORIES);
    usort($names, function ($a, $b) use ($order) {
        $ia = array_search($a, $order, true); $ia = $ia === false ? 999 : $ia;
        $ib = array_search($b, $order, true); $ib = $ib === false ? 999 : $ib;
        return $ia === $ib ? strcasecmp($a, $b) : $ia <=> $ib;
    });
    return $names;
}

// ── Helper ─────────────────────────────────────────────────────────────────

/** Escape output HTML. */
function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** Kirim respons JSON lalu berhenti. */
function json_out(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/** Wajib login dengan role tertentu. $json=true untuk endpoint AJAX. */
function require_role(string $role, bool $json = false): void {
    if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== $role) {
        if ($json) {
            json_out(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        header('Location: login.php');
        exit;
    }
}

/** Token CSRF untuk request POST/AJAX. */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        json_out(['success' => false, 'message' => 'Sesi tidak valid, muat ulang halaman.'], 403);
    }
}

/** Catat aktivitas login (TANPA menyimpan password/hash). */
function log_login(string $msg): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
    @file_put_contents(__DIR__ . '/logs/login.log', date('[Y-m-d H:i:s] ') . "$msg | IP: $ip" . PHP_EOL, FILE_APPEND);
}