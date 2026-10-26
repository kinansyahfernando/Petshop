<?php
/**
 * Proses login (admin pakai email, kasir pakai username).
 */
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$role = $_POST['role'] ?? '';
if (!in_array($role, ['admin', 'kasir'], true)) {
    header('Location: login.php?error=invalid');
    exit;
}

$password = (string)($_POST['password'] ?? '');

if ($role === 'admin') {
    $ident = trim($_POST['email'] ?? '');
    $sql   = "SELECT id, username, email, password FROM users WHERE email = ? AND role = 'admin' LIMIT 1";
} else {
    $ident = trim($_POST['username'] ?? '');
    $sql   = "SELECT id, username, email, password FROM users WHERE username = ? AND role = 'kasir' LIMIT 1";
}

if ($ident === '' || $password === '') {
    header("Location: login.php?error=invalid&role=$role");
    exit;
}

$user = null;
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $ident);
    mysqli_stmt_execute($stmt);
    $res  = mysqli_stmt_get_result($stmt);
    $user = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
} else {
    error_log('login prepare gagal: ' . mysqli_error($conn));
}

if ($user && password_verify($password, $user['password'])) {
    session_regenerate_id(true);
    $_SESSION['user_id']  = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];
    $_SESSION['role']     = $role;
    log_login(ucfirst($role) . " login OK: $ident");
    header('Location: ' . ($role === 'admin' ? 'admin.php' : 'kasir.php'));
    exit;
}

log_login(ucfirst($role) . " login GAGAL: $ident");
usleep(400000); // perlambat brute-force sederhana
header("Location: login.php?error=invalid&role=$role");
exit;
