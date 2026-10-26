<?php
/**
 * Proses transaksi kasir.
 * Harga & total dihitung ulang dari DATABASE (tidak percaya data dari browser).
 */
require __DIR__ . '/config.php';
require_role('kasir', true);
csrf_check();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['cart']) || !is_array($input['cart'])) {
    json_out(['success' => false, 'message' => 'Data tidak valid']);
}

// Gabungkan item duplikat & validasi qty
$qtyById = [];
foreach ($input['cart'] as $item) {
    $pid = (int)($item['id'] ?? 0);
    $qty = (int)($item['quantity'] ?? 0);
    if ($pid <= 0 || $qty <= 0) {
        json_out(['success' => false, 'message' => 'Item keranjang tidak valid']);
    }
    $qtyById[$pid] = ($qtyById[$pid] ?? 0) + $qty;
}

$payment = (int)($input['payment'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

mysqli_begin_transaction($conn);

try {
    $lines = [];
    $total = 0;

    // Kunci baris produk (urut id agar tidak deadlock), ambil harga & stok asli
    ksort($qtyById);
    $sel = mysqli_prepare($conn, "SELECT id, name, price, stock FROM products WHERE id = ? FOR UPDATE");
    foreach ($qtyById as $pid => $qty) {
        mysqli_stmt_bind_param($sel, 'i', $pid);
        mysqli_stmt_execute($sel);
        $p = mysqli_fetch_assoc(mysqli_stmt_get_result($sel));
        if (!$p) {
            throw new Exception("Produk ID $pid tidak ditemukan.");
        }
        if ((int)$p['stock'] < $qty) {
            throw new Exception("Stok \"{$p['name']}\" tidak cukup (sisa {$p['stock']}).");
        }
        $price    = (int)$p['price'];
        $subtotal = $price * $qty;
        $total   += $subtotal;
        $lines[]  = [$pid, $qty, $price, $subtotal];
    }
    mysqli_stmt_close($sel);

    if ($payment < $total) {
        throw new Exception('Jumlah bayar kurang dari total (Rp ' . number_format($total, 0, ',', '.') . ').');
    }
    $change = $payment - $total;

    $code = 'TRX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

    $st = mysqli_prepare($conn,
        "INSERT INTO transactions (transaction_code, user_id, total_amount, payment_amount, change_amount)
         VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($st, 'siiii', $code, $user_id, $total, $payment, $change);
    if (!mysqli_stmt_execute($st)) {
        throw new Exception('Gagal menyimpan transaksi.');
    }
    $trx_id = mysqli_insert_id($conn);
    mysqli_stmt_close($st);

    $insItem = mysqli_prepare($conn,
        "INSERT INTO transaction_items (transaction_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
    $updStock = mysqli_prepare($conn, "UPDATE products SET stock = stock - ? WHERE id = ?");
    foreach ($lines as [$pid, $qty, $price, $subtotal]) {
        mysqli_stmt_bind_param($insItem, 'iiiii', $trx_id, $pid, $qty, $price, $subtotal);
        if (!mysqli_stmt_execute($insItem)) {
            throw new Exception('Gagal menyimpan item transaksi.');
        }
        mysqli_stmt_bind_param($updStock, 'ii', $qty, $pid);
        mysqli_stmt_execute($updStock);
    }
    mysqli_stmt_close($insItem);
    mysqli_stmt_close($updStock);

    mysqli_commit($conn);

    json_out([
        'success'          => true,
        'transaction_code' => $code,
        'transaction_id'   => $trx_id,
        'total'            => $total,
        'change'           => $change,
    ]);
} catch (Exception $ex) {
    mysqli_rollback($conn);
    json_out(['success' => false, 'message' => $ex->getMessage()]);
}
