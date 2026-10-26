<?php
/**
 * Reset password admin — HANYA lewat terminal (CLI), tidak bisa dibuka dari browser.
 * Laragon: klik kanan ikon Laragon > Terminal, lalu:
 *     php tools/reset_admin.php PasswordBaru123
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
require __DIR__ . '/../config.php';

$new = $argv[1] ?? '';
if (strlen($new) < 6) {
    exit("Pemakaian: php tools/reset_admin.php PasswordBaru (min. 6 karakter)\n");
}
$hash = password_hash($new, PASSWORD_DEFAULT);
$stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE email = 'admin@petshop.com' AND role = 'admin'");
mysqli_stmt_bind_param($stmt, 's', $hash);
mysqli_stmt_execute($stmt);
echo mysqli_stmt_affected_rows($stmt) > 0
    ? "Password admin berhasil diubah.\n"
    : "Gagal: akun admin@petshop.com tidak ditemukan.\n";
