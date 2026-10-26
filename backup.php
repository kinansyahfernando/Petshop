<?php
require __DIR__ . '/config.php';
require_role('admin');

function csrf_check_form(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Token tidak valid. Kembali dan muat ulang halaman.');
    }
}

/**
 * Generate SQL backup string — tanpa mengeksekusi DROP TABLE ke database.
 */
function backupDatabase($conn, string $database): string {
    $out  = "-- Pet Shop Database Backup\n";
    $out .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
    $out .= "-- Database: $database\n\n";
    $out .= "SET NAMES utf8mb4;\n";
    $out .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $out .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $out .= "SET time_zone = \"+00:00\";\n\n";

    $tables_r = mysqli_query($conn, "SHOW TABLES");
    while ($tr = mysqli_fetch_row($tables_r)) {
        $table = $tr[0];

        $out .= "-- --------------------------------------------------------\n";
        $out .= "-- Table structure for `$table`\n";
        $out .= "-- --------------------------------------------------------\n\n";
        $out .= "DROP TABLE IF EXISTS `$table`;\n";

        $create_r = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
        $create   = mysqli_fetch_row($create_r);
        $out .= $create[1] . ";\n\n";

        $data_r   = mysqli_query($conn, "SELECT * FROM `$table`");
        $num_rows = mysqli_num_rows($data_r);

        if ($num_rows > 0) {
            $out .= "-- Dumping data for `$table`\n\n";
            $out .= "INSERT INTO `$table` VALUES\n";

            $i = 0;
            while ($row = mysqli_fetch_row($data_r)) {
                $i++;
                $vals = array_map(function ($v) use ($conn) {
                    if ($v === null) return 'NULL';
                    return "'" . mysqli_real_escape_string($conn, $v) . "'";
                }, $row);
                $out .= '(' . implode(',', $vals) . ')';
                $out .= ($i < $num_rows) ? ",\n" : ";\n\n";
            }
        }
    }

    $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    return $out;
}

if (isset($_POST['backup'])) {
    csrf_check_form();
    $backup   = backupDatabase($conn, $database);
    $filename = "backup_petshop_" . date('Y-m-d_H-i-s') . ".sql";

    header('Content-Type: application/octet-stream');
    header('Content-Transfer-Encoding: Binary');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $backup;
    exit;
}

// Stats
$total_products     = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM products"))['t'];
$total_transactions = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM transactions"))['t'];
$total_users        = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM users"))['t'];
$db_size_r  = mysqli_query($conn, "SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) AS s
                                   FROM information_schema.TABLES WHERE table_schema='" . mysqli_real_escape_string($conn, $database) . "'");
$db_size    = mysqli_fetch_assoc($db_size_r)['s'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup — Pet Shop</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:Arial,sans-serif;background:linear-gradient(135deg,#667eea,#764ba2);
             min-height:100vh;padding:20px;display:flex;align-items:center;justify-content:center;}
        .container{max-width:600px;width:100%;}
        .card{background:white;padding:40px;border-radius:15px;box-shadow:0 10px 30px rgba(0,0,0,.3);}
        .header{text-align:center;margin-bottom:30px;}
        .header h1{color:#667eea;font-size:32px;margin-bottom:10px;}
        .header p{color:#666;font-size:14px;}
        .info-box{background:#e3f2fd;border-left:4px solid #2196f3;padding:20px;border-radius:8px;margin-bottom:30px;}
        .info-box h3{color:#1976d2;margin-bottom:10px;}
        .info-box ul{margin-left:20px;color:#555;}
        .info-box li{margin-bottom:8px;}
        .warning-box{background:#fff3e0;border-left:4px solid #ff9800;padding:15px;border-radius:8px;
                     margin-bottom:30px;color:#e65100;}
        .btn-container{display:flex;gap:15px;flex-wrap:wrap;}
        .btn{flex:1;padding:15px;border:none;border-radius:8px;font-size:16px;font-weight:bold;
             cursor:pointer;transition:all .3s;text-align:center;text-decoration:none;display:inline-block;}
        .btn-backup{background:#4caf50;color:white;}
        .btn-backup:hover{background:#45a049;transform:translateY(-2px);}
        .btn-back{background:#6c757d;color:white;}
        .btn-back:hover{background:#5a6268;}
        .stats{display:grid;grid-template-columns:repeat(2,1fr);gap:15px;margin-bottom:30px;}
        .stat-item{background:#f5f5f5;padding:15px;border-radius:8px;text-align:center;}
        .stat-item h4{color:#666;font-size:12px;margin-bottom:8px;text-transform:uppercase;}
        .stat-item p{color:#333;font-size:24px;font-weight:bold;}
    </style>
</head>
<body>
<div class="container">
  <div class="card">
    <div class="header">
      <h1>💾 Database Backup</h1>
      <p>Backup database Pet Shop</p>
    </div>

    <div class="stats">
      <div class="stat-item"><h4>📦 Produk</h4><p><?= $total_products ?></p></div>
      <div class="stat-item"><h4>💰 Transaksi</h4><p><?= $total_transactions ?></p></div>
      <div class="stat-item"><h4>👥 Users</h4><p><?= $total_users ?></p></div>
      <div class="stat-item"><h4>💾 Ukuran DB</h4><p><?= $db_size ?> MB</p></div>
    </div>

    <div class="info-box">
      <h3>ℹ️ Informasi Backup</h3>
      <ul>
        <li>Backup menghasilkan file .sql</li>
        <li>Berisi semua tabel dan data</li>
        <li>Format nama: backup_petshop_YYYY-MM-DD_HH-MM-SS.sql</li>
        <li>Simpan file backup di tempat aman</li>
      </ul>
    </div>

    <div class="warning-box">
      <strong>⚠️ Penting:</strong> Lakukan backup secara berkala untuk mencegah kehilangan data!
    </div>

    <form method="POST">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="btn-container">
        <button type="submit" name="backup" class="btn btn-backup">💾 Download Backup</button>
        <a href="admin.php" class="btn btn-back">⬅️ Kembali</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>