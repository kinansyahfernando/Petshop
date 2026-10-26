<?php
require __DIR__ . '/config.php';
require_role('admin', true);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    json_out(['success' => false, 'message' => 'ID tidak valid']);
}

$stmt = mysqli_prepare($conn,
    "SELECT t.*, COALESCE(u.username, '-') AS kasir
     FROM transactions t LEFT JOIN users u ON t.user_id = u.id
     WHERE t.id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$tx = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$tx) {
    json_out(['success' => false, 'message' => 'Transaksi tidak ditemukan']);
}
$tx['created_at'] = date('d M Y H:i', strtotime($tx['created_at']));

$stmt = mysqli_prepare($conn,
    "SELECT ti.quantity, ti.price, ti.subtotal,
            COALESCE(p.name, 'Produk Dihapus') AS product_name,
            COALESCE(p.image, '📦') AS product_icon
     FROM transaction_items ti LEFT JOIN products p ON ti.product_id = p.id
     WHERE ti.transaction_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

json_out(['success' => true, 'transaction' => $tx, 'items' => $items]);
