<?php
require __DIR__ . '/config.php';

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'kasir.php'));
    exit;
}
header('Location: login.php');
exit;
