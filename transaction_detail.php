<?php
require __DIR__ . '/config.php';
require_role('admin');

$transaction_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$transaction_id) {
    header("Location: report.php");
    exit;
}

$stmt = $conn->prepare(
    "SELECT t.*, COALESCE(u.username,'-') AS kasir_name
     FROM transactions t
     LEFT JOIN users u ON t.user_id = u.id
     WHERE t.id = ? LIMIT 1"
);
$stmt->bind_param('i', $transaction_id);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaction) {
    header("Location: report.php");
    exit;
}

$stmt = $conn->prepare(
    "SELECT ti.quantity, ti.price, ti.subtotal,
            COALESCE(p.name,'Produk Dihapus') AS product_name,
            COALESCE(p.image,'📦') AS product_icon
     FROM transaction_items ti
     LEFT JOIN products p ON ti.product_id = p.id
     WHERE ti.transaction_id = ?"
);
$stmt->bind_param('i', $transaction_id);
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Transaksi — <?= htmlspecialchars($transaction['transaction_code']) ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:Arial,sans-serif;background:linear-gradient(135deg,#667eea,#764ba2);min-height:100vh;padding:20px;}
        .container{max-width:800px;margin:0 auto;}
        .header{background:white;padding:18px 22px;border-radius:10px;margin-bottom:18px;
            box-shadow:0 4px 6px rgba(0,0,0,.1);display:flex;justify-content:space-between;align-items:center;}
        .header h1{color:#667eea;font-size:22px;}
        .btn-back{background:#6c757d;color:white;padding:9px 18px;border-radius:8px;text-decoration:none;font-weight:bold;}
        .btn-back:hover{background:#5a6268;}
        .btn-print{background:#4caf50;color:white;padding:9px 18px;border-radius:8px;border:none;
            font-weight:bold;cursor:pointer;margin-left:8px;}
        .btn-print:hover{background:#45a049;}
        .receipt{background:white;padding:38px;border-radius:10px;box-shadow:0 4px 6px rgba(0,0,0,.1);}
        .receipt-header{text-align:center;padding-bottom:18px;border-bottom:2px dashed #ddd;margin-bottom:18px;}
        .receipt-header h2{font-family:Impact,sans-serif;color:#667eea;font-size:30px;margin-bottom:8px;}
        .receipt-header p{color:#666;font-size:13px;}
        .receipt-info{margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid #e0e0e0;}
        .info-row{display:flex;justify-content:space-between;margin-bottom:7px;font-size:14px;}
        .info-label{font-weight:bold;color:#666;}
        table{width:100%;border-collapse:collapse;margin-bottom:18px;}
        thead{background:#f5f5f5;}
        th{padding:11px;text-align:left;font-weight:bold;color:#333;border-bottom:2px solid #ddd;}
        td{padding:11px;border-bottom:1px solid #e0e0e0;}
        .item-qty{text-align:center;}
        .item-price,.item-subtotal{text-align:right;font-weight:bold;}
        .receipt-summary{border-top:2px solid #ddd;padding-top:18px;}
        .summary-row{display:flex;justify-content:space-between;margin-bottom:9px;font-size:15px;}
        .summary-row.total{font-size:22px;font-weight:bold;color:#667eea;padding-top:13px;border-top:2px solid #ddd;}
        .receipt-footer{text-align:center;margin-top:26px;padding-top:18px;border-top:2px dashed #ddd;color:#666;font-size:13px;}
        @media print{body{background:white;padding:0;}.header{display:none;}.receipt{box-shadow:none;padding:15px;}}
    </style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>📄 Detail Transaksi</h1>
    <div>
      <a href="report.php" class="btn-back">⬅️ Kembali</a>
      <button onclick="window.print()" class="btn-print">🖨️ Print</button>
    </div>
  </div>

  <div class="receipt">
    <div class="receipt-header">
      <h2>🐕 PET SHOP</h2>
      <p>Jl. Contoh No. 123, Surabaya</p>
      <p>Telp: (031) 1234-5678</p>
    </div>

    <div class="receipt-info">
      <div class="info-row"><span class="info-label">No. Transaksi:</span><span><?= htmlspecialchars($transaction['transaction_code']) ?></span></div>
      <div class="info-row"><span class="info-label">Tanggal:</span><span><?= date('d/m/Y H:i:s', strtotime($transaction['created_at'])) ?></span></div>
      <div class="info-row"><span class="info-label">Kasir:</span><span><?= htmlspecialchars($transaction['kasir_name']) ?></span></div>
    </div>

    <table>
      <thead>
        <tr><th>Produk</th><th class="item-qty">Qty</th><th class="item-price">Harga</th><th class="item-subtotal">Subtotal</th></tr>
      </thead>
      <tbody>
        <?php while ($item = $items->fetch_assoc()): ?>
        <tr>
          <td><?= e($item['product_icon']) ?> <?= htmlspecialchars($item['product_name']) ?></td>
          <td class="item-qty"><?= $item['quantity'] ?></td>
          <td class="item-price">Rp <?= number_format($item['price'],0,',','.') ?></td>
          <td class="item-subtotal">Rp <?= number_format($item['subtotal'],0,',','.') ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <div class="receipt-summary">
      <div class="summary-row total"><span>TOTAL:</span><span>Rp <?= number_format($transaction['total_amount'],0,',','.') ?></span></div>
      <div class="summary-row" style="margin-top:13px;"><span>Bayar:</span><span>Rp <?= number_format($transaction['payment_amount'],0,',','.') ?></span></div>
      <div class="summary-row"><span>Kembalian:</span><span>Rp <?= number_format($transaction['change_amount'],0,',','.') ?></span></div>
    </div>

    <div class="receipt-footer">
      <p>Terima kasih atas kunjungan Anda!</p>
      <p>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan</p>
    </div>
  </div>
</div>
</body>
</html>