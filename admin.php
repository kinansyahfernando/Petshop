<?php
require __DIR__ . '/config.php';
require_role('admin');

$admin_name = e($_SESSION['username']);

// ── Tangani AJAX actions (semua POST wajib token CSRF) ─────────────────────
$action = $_POST['action'] ?? '';

if ($action !== '') {
    require_role('admin', true);
    csrf_check();

    if ($action === 'delete_product') {
        $id = (int)($_POST['id'] ?? 0);
        // Riwayat transaksi TIDAK dihapus; FK ON DELETE SET NULL -> tampil "Produk Dihapus"
        $st = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
        mysqli_stmt_bind_param($st, 'i', $id);
        json_out(['success' => mysqli_stmt_execute($st)]);
    }

    if ($action === 'save_product') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $price    = (int)($_POST['price'] ?? 0);
        $category = trim($_POST['category'] ?? '') ?: 'Lainnya';
        $stock    = max(0, (int)($_POST['stock'] ?? 0));
        $image    = trim($_POST['image'] ?? '') ?: '📦';

        if ($name === '' || $price <= 0) {
            json_out(['success' => false, 'message' => 'Nama & harga wajib diisi']);
        }
        if ($id > 0) {
            $st = mysqli_prepare($conn, "UPDATE products SET name=?, price=?, category=?, stock=?, image=? WHERE id=?");
            mysqli_stmt_bind_param($st, 'sisisi', $name, $price, $category, $stock, $image, $id);
        } else {
            $st = mysqli_prepare($conn, "INSERT INTO products (name, price, category, stock, image) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($st, 'sisis', $name, $price, $category, $stock, $image);
        }
        $ok = mysqli_stmt_execute($st);
        json_out(['success' => $ok, 'message' => $ok ? '' : 'Gagal menyimpan produk']);
    }

    if ($action === 'add_kasir') {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || strlen($password) < 6) {
            json_out(['success' => false, 'message' => 'Username wajib diisi & password minimal 6 karakter']);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st = mysqli_prepare($conn, "INSERT INTO users (username, email, password, role) VALUES (?, NULL, ?, 'kasir')");
        mysqli_stmt_bind_param($st, 'ss', $username, $hash);
        if (mysqli_stmt_execute($st)) {
            json_out(['success' => true]);
        }
        json_out(['success' => false, 'message' => 'Username sudah dipakai']);
    }

    if ($action === 'delete_kasir') {
        $id = (int)($_POST['id'] ?? 0);
        $st = mysqli_prepare($conn, "DELETE FROM users WHERE id = ? AND role = 'kasir'");
        mysqli_stmt_bind_param($st, 'i', $id);
        json_out(['success' => mysqli_stmt_execute($st)]);
    }

    if ($action === 'reset_kasir_password') {
        $id = (int)($_POST['id'] ?? 0);
        $pw = (string)($_POST['new_password'] ?? '');
        if (strlen($pw) < 6) {
            json_out(['success' => false, 'message' => 'Password minimal 6 karakter']);
        }
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $st = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ? AND role = 'kasir'");
        mysqli_stmt_bind_param($st, 'si', $hash, $id);
        json_out(['success' => mysqli_stmt_execute($st)]);
    }

    json_out(['success' => false, 'message' => 'Aksi tidak dikenal'], 400);
}

// ── Data untuk halaman ─────────────────────────────────────────────────────
$products_r = mysqli_query($conn, "SELECT * FROM products ORDER BY category, name");
$grouped = [];
while ($gp = mysqli_fetch_assoc($products_r)) {
    $grouped[$gp['category']][] = $gp;
}
$cat_names_sorted = category_sort(array_keys($grouped));
$all_category_options = category_sort(array_unique(array_merge(array_keys(CATEGORIES), array_keys($grouped))));

// Statistik dashboard
$today_sales = (int) mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_amount),0) AS t FROM transactions WHERE DATE(created_at)=CURDATE()"))['t'];
$monthly_sales = (int) mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_amount),0) AS t FROM transactions
     WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())"))['t'];
$total_transactions = (int) mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM transactions"))['c'];
$total_products = (int) mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM products"))['c'];
$low_stock_count = (int) mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM products WHERE stock <= 10"))['c'];
$total_revenue = (int) mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_amount),0) AS t FROM transactions"))['t'];

// Kasir
$kasir_r = mysqli_query($conn, "SELECT id, username, created_at FROM users WHERE role='kasir' ORDER BY created_at DESC");

// ── DATA LAPORAN ───────────────────────────────────────────────────────────

// Transaksi terbaru 30 hari
$recent_trx = mysqli_query($conn,
    "SELECT t.id, t.transaction_code, t.created_at, t.total_amount,
            t.payment_amount, t.change_amount,
            COALESCE(u.username,'-') AS kasir_name,
            GROUP_CONCAT(CONCAT(COALESCE(p.name,'?'),' (',ti.quantity,'x)') ORDER BY p.name SEPARATOR ', ') AS products_list
     FROM transactions t
     LEFT JOIN users u              ON t.user_id       = u.id
     LEFT JOIN transaction_items ti ON t.id            = ti.transaction_id
     LEFT JOIN products p           ON ti.product_id   = p.id
     WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY t.id, t.transaction_code, t.created_at, t.total_amount,
              t.payment_amount, t.change_amount, u.username
     ORDER BY t.created_at DESC
     LIMIT 100"
);

// Kinerja per kasir (all time)
$kasir_stats_r = mysqli_query($conn,
    "SELECT COALESCE(u.username,'-') AS kasir_name,
            COUNT(t.id) AS total_trx,
            COALESCE(SUM(t.total_amount),0) AS total_sales,
            COALESCE(AVG(t.total_amount),0) AS avg_trx
     FROM transactions t
     LEFT JOIN users u ON t.user_id = u.id
     GROUP BY u.id, u.username
     ORDER BY total_sales DESC"
);

// Top 10 produk terlaris
$product_stats_r = mysqli_query($conn,
    "SELECT COALESCE(p.name,'Produk Dihapus') AS product_name,
            COALESCE(p.image,'📦') AS icon,
            SUM(ti.quantity) AS total_qty,
            SUM(ti.subtotal) AS total_revenue,
            COUNT(DISTINCT ti.transaction_id) AS trx_count
     FROM transaction_items ti
     LEFT JOIN products p ON ti.product_id = p.id
     GROUP BY ti.product_id, p.name, p.image
     ORDER BY total_qty DESC
     LIMIT 10"
);

// Produk stok menipis (<= 10)
$low_stock_r = mysqli_query($conn,
    "SELECT id, name, image, stock, category FROM products WHERE stock <= 10 ORDER BY stock ASC LIMIT 20"
);

// Chart: penjualan 30 hari terakhir (grouped per hari)
$chart30_r = mysqli_query($conn,
    "SELECT DATE(created_at) AS tgl, COALESCE(SUM(total_amount),0) AS total, COUNT(*) AS jumlah
     FROM transactions
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY DATE(created_at)
     ORDER BY tgl ASC"
);
$chart30_labels = []; $chart30_sales = []; $chart30_count = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart30_labels[$d] = date('d/m', strtotime("-$i days"));
    $chart30_sales[$d]  = 0;
    $chart30_count[$d]  = 0;
}
while ($row = mysqli_fetch_assoc($chart30_r)) {
    if (isset($chart30_sales[$row['tgl']])) {
        $chart30_sales[$row['tgl']] = (int)$row['total'];
        $chart30_count[$row['tgl']] = (int)$row['jumlah'];
    }
}

// Chart: penjualan 7 hari (untuk dashboard)
$chart7_labels = []; $chart7_values = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart7_labels[] = date('d/m', strtotime("-$i days"));
    $chart7_values[$d] = isset($chart30_sales[$d]) ? $chart30_sales[$d] : 0;
}

// Chart: penjualan per kategori produk
$cat_chart_r = mysqli_query($conn,
    "SELECT COALESCE(p.category,'Lainnya') AS category, SUM(ti.subtotal) AS total
     FROM transaction_items ti
     LEFT JOIN products p ON ti.product_id = p.id
     GROUP BY p.category ORDER BY total DESC"
);
$cat_labels = []; $cat_values = [];
while ($row = mysqli_fetch_assoc($cat_chart_r)) {
    $cat_labels[] = category_icon((string)$row['category']) . ' ' . $row['category'];
    $cat_values[] = (int)$row['total'];
}

// Chart: penjualan per bulan (12 bulan terakhir)
$monthly_r = mysqli_query($conn,
    "SELECT DATE_FORMAT(created_at,'%Y-%m') AS bulan,
            DATE_FORMAT(created_at,'%b %Y') AS label,
            COALESCE(SUM(total_amount),0) AS total,
            COUNT(*) AS jumlah
     FROM transactions
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY DATE_FORMAT(created_at,'%Y-%m'), DATE_FORMAT(created_at,'%b %Y')
     ORDER BY bulan ASC"
);
$monthly_labels = []; $monthly_sales_arr = []; $monthly_count_arr = [];
while ($row = mysqli_fetch_assoc($monthly_r)) {
    $monthly_labels[]      = $row['label'];
    $monthly_sales_arr[]   = (int)$row['total'];
    $monthly_count_arr[]   = (int)$row['jumlah'];
}

// JSON untuk JavaScript
$j7labels  = json_encode(array_values($chart7_labels));
$j7values  = json_encode(array_values($chart7_values));
$j30labels = json_encode(array_values($chart30_labels));
$j30sales  = json_encode(array_values($chart30_sales));
$j30count  = json_encode(array_values($chart30_count));
$jCatL     = json_encode($cat_labels);
$jCatV     = json_encode($cat_values);
$jMonL     = json_encode($monthly_labels);
$jMonS     = json_encode($monthly_sales_arr);
$jMonC     = json_encode($monthly_count_arr);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel — Pet Shop</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f2f5;min-height:100vh;display:flex;flex-direction:column;}

/* ── HEADER ── */
.header{background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:0 24px;
    display:flex;align-items:center;justify-content:space-between;height:64px;
    box-shadow:0 4px 12px rgba(102,126,234,.4);position:sticky;top:0;z-index:200;}
.header-left{display:flex;align-items:center;gap:14px;}
.header-logo{font-size:32px;}
.header-title h1{font-family:Impact,sans-serif;font-size:22px;letter-spacing:2px;}
.header-title p{font-size:12px;opacity:.8;}
.header-right{display:flex;align-items:center;gap:12px;}
.admin-badge{background:rgba(255,255,255,.2);padding:6px 14px;border-radius:20px;font-size:13px;}
.btn-logout{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.4);
    color:white;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;transition:all .2s;text-decoration:none;}
.btn-logout:hover{background:rgba(255,255,255,.3);}

/* ── LAYOUT ── */
.layout{display:flex;flex:1;}
.sidebar{width:230px;background:white;box-shadow:2px 0 8px rgba(0,0,0,.06);
    padding:20px 0;position:sticky;top:64px;height:calc(100vh - 64px);overflow-y:auto;flex-shrink:0;}
.sidebar-menu{list-style:none;}
.sidebar-item a,.sidebar-item button{
    display:flex;align-items:center;gap:12px;padding:13px 22px;
    color:#555;text-decoration:none;font-size:14px;font-weight:500;
    border:none;background:none;cursor:pointer;width:100%;text-align:left;transition:all .2s;}
.sidebar-item a:hover,.sidebar-item button:hover{background:#f0f2f5;color:#667eea;}
.sidebar-item.active a,.sidebar-item.active button{background:linear-gradient(90deg,rgba(102,126,234,.12),transparent);
    color:#667eea;border-left:3px solid #667eea;}
.sidebar-divider{margin:10px 22px;border:none;border-top:1px solid #e0e0e0;}
.main{flex:1;padding:24px;overflow-x:hidden;}

/* ── BUG FIX: Tab hanya hide/show pada level tab utama, tidak nested ── */
.tab-content{display:none;}
.tab-content.active{display:block;}

/* ── STAT CARDS ── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;}
.stat-card{background:white;border-radius:14px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.07);
    display:flex;align-items:center;gap:14px;position:relative;overflow:hidden;transition:transform .2s;}
.stat-card:hover{transform:translateY(-3px);}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;}
.stat-card.blue::before{background:linear-gradient(90deg,#667eea,#764ba2);}
.stat-card.green::before{background:linear-gradient(90deg,#4caf50,#2e7d32);}
.stat-card.orange::before{background:linear-gradient(90deg,#ff9800,#e65100);}
.stat-card.red::before{background:linear-gradient(90deg,#f44336,#b71c1c);}
.stat-card.purple::before{background:linear-gradient(90deg,#9c27b0,#6a1b9a);}
.stat-icon{font-size:34px;width:54px;height:54px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon.blue{background:rgba(102,126,234,.12);}
.stat-icon.green{background:rgba(76,175,80,.12);}
.stat-icon.orange{background:rgba(255,152,0,.12);}
.stat-icon.red{background:rgba(244,67,54,.12);}
.stat-icon.purple{background:rgba(156,39,176,.12);}
.stat-info h4{font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px;}
.stat-info p{font-size:18px;font-weight:700;color:#333;}

/* ── CARD ── */
.card{background:white;border-radius:14px;padding:22px;box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:20px;}
.card-header{display:flex;justify-content:space-between;align-items:center;
    margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #f0f2f5;flex-wrap:wrap;gap:10px;}
.card-header h2{font-size:17px;color:#333;display:flex;align-items:center;gap:8px;}

/* ── BUTTONS ── */
.btn{padding:9px 16px;border-radius:8px;border:none;cursor:pointer;font-size:13px;
    font-weight:600;display:inline-flex;align-items:center;gap:6px;transition:all .2s;text-decoration:none;}
.btn-primary{background:#667eea;color:white;}
.btn-primary:hover{background:#5568d3;transform:translateY(-1px);}
.btn-success{background:#4caf50;color:white;}
.btn-success:hover{background:#45a049;}
.btn-warning{background:#ff9800;color:white;}
.btn-warning:hover{background:#e65100;}
.btn-danger{background:#f44336;color:white;}
.btn-danger:hover{background:#d32f2f;}
.btn-secondary{background:#6c757d;color:white;}
.btn-secondary:hover{background:#5a6268;}
.btn-info{background:#2196f3;color:white;}
.btn-info:hover{background:#1976d2;}
.btn-sm{padding:5px 12px;font-size:12px;}

/* ── TABLE ── */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead{background:linear-gradient(135deg,#667eea,#764ba2);color:white;}
th{padding:11px 13px;text-align:left;font-size:12px;white-space:nowrap;font-weight:600;}
td{padding:11px 13px;border-bottom:1px solid #f0f2f5;font-size:13px;vertical-align:middle;}
tbody tr:hover{background:#fafafa;}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
.badge-success{background:#e8f5e9;color:#2e7d32;}
.badge-warning{background:#fff3e0;color:#e65100;}
.badge-danger{background:#ffebee;color:#c62828;}
.badge-info{background:#e3f2fd;color:#1565c0;}

/* ── FORM ── */
.form-group{margin-bottom:15px;}
.form-group label{display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;}
.form-control{width:100%;padding:10px 13px;border:2px solid #e0e0e0;border-radius:8px;
    font-size:14px;transition:border-color .2s;}
.form-control:focus{outline:none;border-color:#667eea;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:13px;}

/* ── MODAL ── */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;
    display:none;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.open{display:flex;}
.modal{background:white;border-radius:16px;padding:28px;width:100%;max-width:480px;
    box-shadow:0 20px 50px rgba(0,0,0,.3);max-height:90vh;overflow-y:auto;}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;}
.modal-header h3{font-size:18px;color:#333;}
.modal-close{background:none;border:none;font-size:22px;cursor:pointer;color:#999;padding:4px;}
.modal-close:hover{color:#333;}
.modal-footer{display:flex;justify-content:flex-end;gap:10px;margin-top:18px;padding-top:14px;border-top:1px solid #e0e0e0;}

/* ── KATEGORI PRODUK (dikelompokkan) ── */
.cat-row td{background:linear-gradient(90deg,#eef0ff,#f8f9ff);border-top:2px solid #dfe3ff;border-bottom:1px solid #dfe3ff;
    padding:12px 13px;cursor:pointer;user-select:none;}
.cat-row:hover td{background:#e6e9ff;}
.cat-toggle{display:inline-block;font-size:10px;color:#667eea;margin-right:8px;transition:transform .2s;}
.cat-row.collapsed .cat-toggle{transform:rotate(-90deg);}
.cat-icon{font-size:22px;margin-right:6px;vertical-align:middle;}
.cat-title{font-size:15px;font-weight:700;color:#3b3f8c;vertical-align:middle;}
.cat-meta{font-size:12px;color:#888;margin-left:10px;vertical-align:middle;}
.product-row.group-collapsed{display:none;}
.emoji-picks{display:flex;flex-wrap:wrap;gap:6px;}
.emoji-pick{font-size:22px;width:44px;height:44px;border:2px solid #e0e0e0;border-radius:10px;background:#fff;cursor:pointer;transition:all .15s;}
.emoji-pick:hover{border-color:#667eea;transform:translateY(-1px);}
.emoji-pick.active{border-color:#667eea;background:rgba(102,126,234,.12);}

/* ── CHART ── */
.chart-wrap{position:relative;}
.chart-wrap.h260{height:260px;}
.chart-wrap.h300{height:300px;}

/* ── SEARCH ── */
.search-bar{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;}
.search-input{flex:1;min-width:180px;padding:9px 13px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;}
.search-input:focus{outline:none;border-color:#667eea;}

/* ── LAPORAN SUB-TABS ── */
.report-tabs{display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid #e0e0e0;overflow-x:auto;padding-bottom:2px;}
.report-tab{padding:10px 18px;border:none;background:none;cursor:pointer;font-size:14px;
    color:#777;font-weight:500;border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .2s;white-space:nowrap;}
.report-tab.active,.report-tab:hover{color:#667eea;border-bottom-color:#667eea;}
/* BUG FIX: sub-tab laporan pakai class berbeda agar tidak tertimpa showTab() */
.rtab-content{display:none;}
.rtab-content.active{display:block;}

/* ── KINERJA KASIR ── */
.kasir-stat-card{display:flex;align-items:center;justify-content:space-between;
    padding:14px 18px;border-radius:12px;border:1px solid #e0e0e0;margin-bottom:10px;
    background:#fafafa;transition:all .2s;}
.kasir-stat-card:hover{border-color:#667eea;background:#f8f9ff;}
.kasir-stat-name{font-weight:600;color:#333;font-size:15px;}
.kasir-stat-sub{font-size:12px;color:#888;margin-top:3px;}
.kasir-stat-value{text-align:right;}
.kasir-stat-value .amount{font-size:16px;font-weight:700;color:#4caf50;}
.kasir-stat-value .trx{font-size:12px;color:#888;margin-top:2px;}

/* ── TOP PRODUK ── */
.top-product-row{display:flex;align-items:center;gap:13px;padding:12px 0;border-bottom:1px solid #f0f2f5;}
.top-product-row:last-child{border-bottom:none;}

/* ── STOK ALERT ── */
.stock-alert-item{display:flex;align-items:center;justify-content:space-between;
    padding:12px 16px;border-radius:10px;margin-bottom:8px;border:1px solid;}
.stock-alert-item.danger{background:#fff5f5;border-color:#fecaca;}
.stock-alert-item.warning{background:#fffbeb;border-color:#fde68a;}

/* ── MISC ── */
.no-data{text-align:center;padding:36px 20px;color:#aaa;}
.no-data-icon{font-size:44px;margin-bottom:10px;}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;display:none;}
.alert.show{display:block;}
.alert-success{background:#e8f5e9;color:#2e7d32;border-left:4px solid #4caf50;}
.alert-error  {background:#ffebee;color:#c62828;border-left:4px solid #f44336;}

.charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
.summary-band{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;}
.summary-item{background:#f8f9ff;border:1px solid #e0e7ff;border-radius:10px;padding:14px;text-align:center;}
.summary-item .si-label{font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.7px;margin-bottom:5px;}
.summary-item .si-value{font-size:17px;font-weight:700;color:#667eea;}

@media(max-width:1100px){.charts-grid{grid-template-columns:1fr;}}
@media(max-width:900px){.sidebar{display:none;}.header-right .admin-badge{display:none;}}
@media(max-width:600px){.stats-grid{grid-template-columns:1fr 1fr;}.form-row{grid-template-columns:1fr;}}
@media print{.sidebar,.header,.search-bar,.card-header .btn,.modal-overlay,.report-tabs{display:none!important;}
    body,.main{background:white;}}
</style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="header-left">
        <div class="header-logo">🐕</div>
        <div class="header-title"><h1>PET SHOP</h1><p>Admin Dashboard</p></div>
    </div>
    <div class="header-right">
        <span class="admin-badge">👨‍💼 <?= $admin_name ?></span>
        <a href="logout.php" class="btn-logout">🚪 Logout</a>
    </div>
</div>

<div class="layout">
<!-- SIDEBAR -->
<div class="sidebar">
    <ul class="sidebar-menu">
        <li class="sidebar-item active" id="nav-dashboard">
            <a href="#" onclick="showTab('dashboard');return false;">📊 Dashboard</a>
        </li>
        <li class="sidebar-item" id="nav-products">
            <a href="#" onclick="showTab('products');return false;">📦 Kelola Produk</a>
        </li>
        <li class="sidebar-item" id="nav-kasir">
            <a href="#" onclick="showTab('kasir');return false;">👥 Kelola Kasir</a>
        </li>
        <li class="sidebar-item" id="nav-laporan">
            <a href="#" onclick="showTab('laporan');return false;">📈 Laporan</a>
        </li>
        <hr class="sidebar-divider">
        <li class="sidebar-item">
            <a href="backup.php">💾 Backup Database</a>
        </li>
        <li class="sidebar-item">
            <a href="logout.php">🚪 Logout</a>
        </li>
    </ul>
</div>

<!-- MAIN -->
<div class="main">
<div id="globalAlert" class="alert"></div>

<!-- ═══════════════════ TAB: DASHBOARD ═══════════════════ -->
<div id="tab-dashboard" class="tab-content active">
    <h2 style="margin-bottom:20px;color:#333;">📊 Dashboard</h2>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon blue">📅</div>
            <div class="stat-info"><h4>Penjualan Hari Ini</h4><p>Rp <?= number_format($today_sales,0,',','.') ?></p></div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon green">📆</div>
            <div class="stat-info"><h4>Penjualan Bulan Ini</h4><p>Rp <?= number_format($monthly_sales,0,',','.') ?></p></div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon purple">💰</div>
            <div class="stat-info"><h4>Total Omzet</h4><p>Rp <?= number_format($total_revenue,0,',','.') ?></p></div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon orange">🧾</div>
            <div class="stat-info"><h4>Total Transaksi</h4><p><?= number_format($total_transactions) ?></p></div>
        </div>
        <div class="stat-card <?= $low_stock_count > 0 ? 'red' : 'green' ?>">
            <div class="stat-icon <?= $low_stock_count > 0 ? 'red' : 'green' ?>">📦</div>
            <div class="stat-info"><h4>Stok Menipis</h4><p><?= $low_stock_count ?> produk</p></div>
        </div>
    </div>

    <!-- Chart 7 hari -->
    <div class="card">
        <div class="card-header"><h2>📈 Grafik Penjualan 7 Hari Terakhir</h2></div>
        <div class="chart-wrap h260"><canvas id="salesChart7"></canvas></div>
    </div>

    <!-- Transaksi terbaru -->
    <div class="card">
        <div class="card-header">
            <h2>🧾 Transaksi Terbaru</h2>
            <button class="btn btn-primary btn-sm" onclick="showTab('laporan')">Lihat Semua →</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Kode</th><th>Kasir</th><th>Total</th><th>Waktu</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php
                $dash_trx = mysqli_query($conn,
                    "SELECT t.id, t.transaction_code, COALESCE(u.username,'-') AS kasir, t.total_amount, t.created_at
                     FROM transactions t LEFT JOIN users u ON t.user_id=u.id
                     ORDER BY t.created_at DESC LIMIT 8");
                if ($dash_trx && mysqli_num_rows($dash_trx) > 0):
                    while ($r = mysqli_fetch_assoc($dash_trx)):
                ?>
                <tr>
                    <td style="font-weight:600;color:#667eea;"><?= htmlspecialchars($r['transaction_code']) ?></td>
                    <td><?= htmlspecialchars($r['kasir']) ?></td>
                    <td style="font-weight:700;color:#4caf50;">Rp <?= number_format($r['total_amount'],0,',','.') ?></td>
                    <td style="color:#999;font-size:12px;"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                    <td><a href="transaction_detail.php?id=<?= (int)$r['id'] ?>" class="btn btn-primary btn-sm">👁️</a></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="5"><div class="no-data"><div class="no-data-icon">🧾</div><p>Belum ada transaksi</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Stok menipis (dashboard widget) -->
    <?php if ($low_stock_count > 0): ?>
    <div class="card">
        <div class="card-header">
            <h2>⚠️ Peringatan Stok Menipis</h2>
            <button class="btn btn-warning btn-sm" onclick="showTab('products')">Kelola Produk →</button>
        </div>
        <?php
        $low_dash = mysqli_query($conn, "SELECT name, image, stock, category FROM products WHERE stock <= 10 ORDER BY stock ASC LIMIT 6");
        while ($lp = mysqli_fetch_assoc($low_dash)):
            $cls = $lp['stock'] == 0 ? 'danger' : 'warning';
        ?>
        <div class="stock-alert-item <?= $cls ?>">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:22px;"><?= e($lp['image']) ?></span>
                <div>
                    <div style="font-weight:600;font-size:14px;"><?= htmlspecialchars($lp['name']) ?></div>
                    <div style="font-size:12px;color:#888;"><?= e(category_icon((string)$lp['category'])) ?> <?= htmlspecialchars($lp['category']) ?></div>
                </div>
            </div>
            <span class="badge <?= $lp['stock'] == 0 ? 'badge-danger' : 'badge-warning' ?>">
                <?= $lp['stock'] == 0 ? 'Habis' : 'Sisa '.$lp['stock'] ?>
            </span>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════ TAB: PRODUK ═══════════════════ -->
<div id="tab-products" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2>📦 Kelola Produk</h2>
            <button class="btn btn-success" onclick="openProductModal()">➕ Tambah Produk</button>
        </div>
        <div class="search-bar">
            <span style="font-size:20px;color:#667eea;">🔍</span>
            <input type="text" id="productSearch" class="search-input" placeholder="Cari nama atau kategori produk..." oninput="filterProducts()">
            <select id="categoryFilter" class="search-input" style="flex:0 0 190px;" onchange="filterProducts()">
                <option value="">Semua Kategori</option>
                <?php foreach ($all_category_options as $cn): ?>
                <option value="<?= e($cn) ?>"><?= e(category_icon($cn)) ?> <?= e($cn) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="stockFilter" class="search-input" style="flex:0 0 150px;" onchange="filterProducts()">
                <option value="">Semua Stok</option>
                <option value="low">Stok Menipis (≤10)</option>
                <option value="empty">Stok Habis</option>
            </select>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th style="width:64px;">Icon</th><th>Nama Produk</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr></thead>
                <tbody id="productTableBody">
                <?php if (empty($grouped)): ?>
                <tr><td colspan="5"><div class="no-data"><div class="no-data-icon">📦</div><p>Belum ada produk</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($cat_names_sorted as $catName):
                    $rows       = $grouped[$catName];
                    $catKey     = strtolower(htmlspecialchars($catName));
                    $catStock   = array_sum(array_column($rows, 'stock'));
                    $catLow     = count(array_filter($rows, fn($r) => (int)$r['stock'] <= 10));
                ?>
                <tr class="cat-row" data-group="<?= $catKey ?>" title="Klik untuk lipat / buka">
                    <td colspan="5">
                        <span class="cat-toggle">▼</span>
                        <span class="cat-icon"><?= e(category_icon($catName)) ?></span>
                        <span class="cat-title"><?= e($catName) ?></span>
                        <span class="cat-meta"><?= count($rows) ?> produk · total stok <?= number_format($catStock) ?></span>
                        <?php if ($catLow > 0): ?><span class="badge badge-warning" style="margin-left:8px;"><?= $catLow ?> stok menipis</span><?php endif; ?>
                    </td>
                </tr>
                <?php foreach ($rows as $p):
                    $sc = $p['stock'] <= 0 ? 'badge-danger' : ($p['stock'] <= 10 ? 'badge-warning' : 'badge-success');
                ?>
                <tr class="product-row" data-id="<?= (int)$p['id'] ?>"
                    data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>"
                    data-cat="<?= $catKey ?>"
                    data-stock="<?= (int)$p['stock'] ?>">
                    <td style="font-size:22px;padding-left:22px;"><?= e($p['image']) ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></td>
                    <td style="font-weight:700;color:#4caf50;">Rp <?= number_format($p['price'],0,',','.') ?></td>
                    <td><span class="badge <?= $sc ?>"><?= $p['stock'] == 0 ? 'Habis' : $p['stock'] ?></span></td>
                    <td style="white-space:nowrap;">
                        <button class="btn btn-warning btn-sm btn-edit-product"
                            data-id="<?= (int)$p['id'] ?>"
                            data-name="<?= e($p['name']) ?>"
                            data-price="<?= (int)$p['price'] ?>"
                            data-category="<?= e($p['category']) ?>"
                            data-stock="<?= (int)$p['stock'] ?>"
                            data-image="<?= e($p['image']) ?>">✏️ Edit</button>
                        <button class="btn btn-danger btn-sm btn-del-product" data-id="<?= (int)$p['id'] ?>" data-name="<?= e($p['name']) ?>">🗑️ Hapus</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════ TAB: KASIR ═══════════════════ -->
<div id="tab-kasir" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2>👥 Kelola Kasir</h2>
            <button class="btn btn-success" onclick="openKasirModal()">➕ Tambah Kasir</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>#</th><th>Username</th><th>Dibuat</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php $no=1; mysqli_data_seek($kasir_r, 0); while ($k = mysqli_fetch_assoc($kasir_r)): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td style="font-weight:600;">👤 <?= htmlspecialchars($k['username']) ?></td>
                    <td style="color:#999;font-size:12px;"><?= date('d/m/Y', strtotime($k['created_at'])) ?></td>
                    <td style="white-space:nowrap;">
                        <button class="btn btn-info btn-sm btn-reset-kasir" data-id="<?= (int)$k['id'] ?>" data-name="<?= e($k['username']) ?>">🔑 Reset PW</button>
                        <button class="btn btn-danger btn-sm btn-del-kasir" data-id="<?= (int)$k['id'] ?>" data-name="<?= e($k['username']) ?>">🗑️ Hapus</button>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════ TAB: LAPORAN ═══════════════════ -->
<div id="tab-laporan" class="tab-content">
    <h2 style="margin-bottom:16px;color:#333;">📈 Laporan Penjualan</h2>

    <!-- Ringkasan angka -->
    <div class="summary-band">
        <div class="summary-item">
            <div class="si-label">Hari Ini</div>
            <div class="si-value">Rp <?= number_format($today_sales,0,',','.') ?></div>
        </div>
        <div class="summary-item">
            <div class="si-label">Bulan Ini</div>
            <div class="si-value">Rp <?= number_format($monthly_sales,0,',','.') ?></div>
        </div>
        <div class="summary-item">
            <div class="si-label">Total Omzet</div>
            <div class="si-value">Rp <?= number_format($total_revenue,0,',','.') ?></div>
        </div>
        <div class="summary-item">
            <div class="si-label">Total Transaksi</div>
            <div class="si-value"><?= number_format($total_transactions) ?></div>
        </div>
        <div class="summary-item">
            <div class="si-label">Rata-rata/Transaksi</div>
            <div class="si-value">Rp <?= $total_transactions > 0 ? number_format(intdiv($total_revenue, $total_transactions),0,',','.') : 0 ?></div>
        </div>
    </div>

    <!-- Sub-tabs laporan -->
    <div class="report-tabs">
        <button class="report-tab active" onclick="showReportTab('trx',this)">🧾 Riwayat Transaksi</button>
        <button class="report-tab" onclick="showReportTab('charts',this)">📊 Grafik & Analitik</button>
        <button class="report-tab" onclick="showReportTab('kasir-perf',this)">👥 Kinerja Kasir</button>
        <button class="report-tab" onclick="showReportTab('top-product',this)">🏆 Produk Terlaris</button>
        <button class="report-tab" onclick="showReportTab('stok',this)">⚠️ Stok Menipis</button>
    </div>

    <!-- ── SUB-TAB: Transaksi ── -->
    <div id="rtab-trx" class="rtab-content active">
        <div class="card">
            <div class="card-header">
                <h2>🧾 Riwayat Transaksi (30 Hari)</h2>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button onclick="exportLaporan()" class="btn btn-success btn-sm">📥 Export CSV</button>
                    <a href="report.php" class="btn btn-secondary btn-sm" target="_blank">📋 Halaman Penuh</a>
                </div>
            </div>
            <div class="search-bar">
                <input type="text" id="laporanSearch" class="search-input" placeholder="Cari kode atau kasir..." oninput="filterLaporan()">
                <input type="date" id="laporanFrom" class="search-input" style="flex:0 0 145px;">
                <span style="color:#888;">—</span>
                <input type="date" id="laporanTo" class="search-input" style="flex:0 0 145px;">
                <button class="btn btn-primary btn-sm" onclick="filterLaporanDate()">Filter</button>
                <button class="btn btn-secondary btn-sm" onclick="resetLaporan()">Reset</button>
            </div>
            <div class="table-wrap">
                <table id="laporanTable">
                    <thead><tr><th>Kode Transaksi</th><th>Kasir</th><th>Produk</th><th>Total</th><th>Bayar</th><th>Kembalian</th><th>Tanggal</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php if ($recent_trx && mysqli_num_rows($recent_trx) > 0):
                        while ($t = mysqli_fetch_assoc($recent_trx)): ?>
                    <tr class="laporan-row"
                        data-code="<?= strtolower(htmlspecialchars($t['transaction_code'])) ?>"
                        data-kasir="<?= strtolower(htmlspecialchars($t['kasir_name'])) ?>"
                        data-date="<?= date('Y-m-d', strtotime($t['created_at'])) ?>">
                        <td style="font-weight:600;color:#667eea;"><?= htmlspecialchars($t['transaction_code']) ?></td>
                        <td><?= htmlspecialchars($t['kasir_name']) ?></td>
                        <td style="max-width:200px;color:#666;font-size:12px;"><?= htmlspecialchars($t['products_list'] ?? '-') ?></td>
                        <td style="font-weight:700;color:#4caf50;">Rp <?= number_format($t['total_amount'],0,',','.') ?></td>
                        <td style="color:#555;">Rp <?= number_format($t['payment_amount'],0,',','.') ?></td>
                        <td style="color:#555;">Rp <?= number_format($t['change_amount'],0,',','.') ?></td>
                        <td style="color:#999;font-size:12px;"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                        <td><a href="transaction_detail.php?id=<?= (int)$t['id'] ?>" class="btn btn-primary btn-sm">👁️</a></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="8"><div class="no-data"><div class="no-data-icon">🧾</div><p>Belum ada transaksi</p></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── SUB-TAB: Grafik & Analitik ── -->
    <div id="rtab-charts" class="rtab-content">
        <!-- Grafik 30 hari -->
        <div class="card">
            <div class="card-header"><h2>📈 Penjualan 30 Hari Terakhir</h2></div>
            <div class="chart-wrap h300"><canvas id="chart30"></canvas></div>
        </div>

        <div class="charts-grid">
            <!-- Grafik per bulan -->
            <div class="card">
                <div class="card-header"><h2>📅 Penjualan per Bulan (12 Bulan)</h2></div>
                <div class="chart-wrap h260"><canvas id="chartMonthly"></canvas></div>
            </div>
            <!-- Grafik per kategori -->
            <div class="card">
                <div class="card-header"><h2>🗂️ Omzet per Kategori</h2></div>
                <div class="chart-wrap h260"><canvas id="chartCategory"></canvas></div>
            </div>
        </div>

        <!-- Jumlah transaksi 30 hari -->
        <div class="card">
            <div class="card-header"><h2>🔢 Jumlah Transaksi per Hari (30 Hari)</h2></div>
            <div class="chart-wrap h260"><canvas id="chartTrxCount"></canvas></div>
        </div>
    </div>

    <!-- ── SUB-TAB: Kinerja Kasir ── -->
    <div id="rtab-kasir-perf" class="rtab-content">
        <div class="card">
            <div class="card-header"><h2>👥 Kinerja per Kasir (Semua Waktu)</h2></div>
            <?php
            $kasir_arr = [];
            while ($ks = mysqli_fetch_assoc($kasir_stats_r)) $kasir_arr[] = $ks;
            if (!empty($kasir_arr)):
                foreach ($kasir_arr as $idx => $ks):
                    $medals = ['🥇','🥈','🥉'];
                    $m = $medals[$idx] ?? '#'.($idx+1);
            ?>
            <div class="kasir-stat-card">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span style="font-size:22px;"><?= $m ?></span>
                    <div>
                        <div class="kasir-stat-name">👤 <?= htmlspecialchars($ks['kasir_name']) ?></div>
                        <div class="kasir-stat-sub"><?= number_format($ks['total_trx']) ?> transaksi · Rata-rata Rp <?= number_format($ks['avg_trx'],0,',','.') ?>/trx</div>
                    </div>
                </div>
                <div class="kasir-stat-value">
                    <div class="amount">Rp <?= number_format($ks['total_sales'],0,',','.') ?></div>
                    <div class="trx">Total penjualan</div>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div class="no-data"><div class="no-data-icon">👥</div><p>Belum ada data</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── SUB-TAB: Produk Terlaris ── -->
    <div id="rtab-top-product" class="rtab-content">
        <div class="card">
            <div class="card-header"><h2>🏆 Top 10 Produk Terlaris</h2></div>
            <?php
            $rank = 1;
            $medals2 = ['🥇','🥈','🥉'];
            while ($pr = mysqli_fetch_assoc($product_stats_r)):
                $med = $medals2[$rank-1] ?? "#{$rank}";
            ?>
            <div class="top-product-row">
                <div style="font-size:20px;width:32px;text-align:center;"><?= $med ?></div>
                <div style="font-size:26px;width:38px;text-align:center;"><?= e($pr['icon']) ?></div>
                <div style="flex:1;">
                    <div style="font-weight:600;font-size:14px;color:#333;"><?= htmlspecialchars($pr['product_name']) ?></div>
                    <div style="font-size:12px;color:#888;margin-top:2px;"><?= number_format($pr['total_qty']) ?> unit terjual · <?= number_format($pr['trx_count']) ?> transaksi</div>
                </div>
                <div style="text-align:right;font-weight:700;color:#667eea;font-size:14px;">
                    Rp <?= number_format($pr['total_revenue'],0,',','.') ?>
                </div>
            </div>
            <?php $rank++; endwhile; ?>
            <?php if ($rank === 1): ?>
            <div class="no-data"><div class="no-data-icon">🏆</div><p>Belum ada data penjualan</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── SUB-TAB: Stok Menipis ── -->
    <div id="rtab-stok" class="rtab-content">
        <div class="card">
            <div class="card-header">
                <h2>⚠️ Produk Stok Menipis / Habis</h2>
                <button class="btn btn-warning btn-sm" onclick="showTab('products')">➕ Kelola Produk</button>
            </div>
            <?php
            $low_list = [];
            while ($lp = mysqli_fetch_assoc($low_stock_r)) $low_list[] = $lp;
            if (!empty($low_list)):
                foreach ($low_list as $lp):
                    $cls = $lp['stock'] == 0 ? 'danger' : 'warning';
            ?>
            <div class="stock-alert-item <?= $cls ?>">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span style="font-size:24px;"><?= e($lp['image']) ?></span>
                    <div>
                        <div style="font-weight:600;font-size:14px;"><?= htmlspecialchars($lp['name']) ?></div>
                        <div style="font-size:12px;color:#888;"><?= e(category_icon((string)$lp['category'])) ?> <?= htmlspecialchars($lp['category']) ?> · ID: <?= $lp['id'] ?></div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="badge <?= $lp['stock'] == 0 ? 'badge-danger' : 'badge-warning' ?>">
                        <?= $lp['stock'] == 0 ? '🚫 Habis' : '⚠️ Sisa '.$lp['stock'] ?>
                    </span>
                    <button class="btn btn-warning btn-sm"
                        onclick="editProductById(<?= $lp['id'] ?>)">✏️ Update Stok</button>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div class="no-data"><div class="no-data-icon">✅</div><p>Semua produk stok aman!</p></div>
            <?php endif; ?>
        </div>
    </div>
</div><!-- end tab-laporan -->

</div><!-- end main -->
</div><!-- end layout -->

<!-- ═══ MODAL: PRODUK ═══ -->
<div class="modal-overlay" id="productModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="productModalTitle">Tambah Produk</h3>
            <button class="modal-close" onclick="closeModal('productModal')">✕</button>
        </div>
        <form id="productForm">
            <input type="hidden" id="productId" value="0">
            <div class="form-row">
                <div class="form-group">
                    <label>Nama Produk *</label>
                    <input type="text" id="productName" class="form-control" placeholder="cth: Dog Food Premium" required>
                </div>
                <div class="form-group">
                    <label>Icon/Emoji</label>
                    <input type="text" id="productImage" class="form-control" placeholder="cth: 🦴" maxlength="10" value="📦">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Harga (Rp) *</label>
                    <input type="number" id="productPrice" class="form-control" placeholder="cth: 50000" min="0" required>
                </div>
                <div class="form-group">
                    <label>Stok</label>
                    <input type="number" id="productStock" class="form-control" placeholder="cth: 10" min="0" value="0">
                </div>
            </div>
            <div class="form-group">
                <label>Kategori</label>
                <select id="productCategory" class="form-control">
                    <?php foreach (CATEGORIES as $cn => $cm): ?>
                    <option value="<?= e($cn) ?>"><?= e($cm['icon']) ?> <?= e($cn) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Pilih Icon Produk <span id="emojiCatLabel" style="font-weight:400;color:#888;"></span></label>
                <div class="emoji-picks" id="emojiPicks"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('productModal')">Batal</button>
                <button type="submit" class="btn btn-success">💾 Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ MODAL: KASIR ═══ -->
<div class="modal-overlay" id="kasirModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Tambah Akun Kasir</h3>
            <button class="modal-close" onclick="closeModal('kasirModal')">✕</button>
        </div>
        <form id="kasirForm">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" id="kasirUsername" class="form-control" placeholder="cth: kasir2" required>
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" id="kasirPassword" class="form-control" placeholder="Minimal 6 karakter" minlength="6" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('kasirModal')">Batal</button>
                <button type="submit" class="btn btn-success">💾 Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ MODAL: RESET PASSWORD KASIR ═══ -->
<div class="modal-overlay" id="resetPwModal">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <h3>🔑 Reset Password Kasir</h3>
            <button class="modal-close" onclick="closeModal('resetPwModal')">✕</button>
        </div>
        <p id="resetPwName" style="color:#555;margin-bottom:16px;font-size:14px;"></p>
        <form id="resetPwForm">
            <input type="hidden" id="resetPwId">
            <div class="form-group">
                <label>Password Baru *</label>
                <input type="password" id="resetPwValue" class="form-control" placeholder="Minimal 6 karakter" minlength="6" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resetPwModal')">Batal</button>
                <button type="submit" class="btn btn-info">🔑 Reset</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ MODAL: KONFIRMASI HAPUS ═══ -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal" style="max-width:360px;">
        <div class="modal-header">
            <h3>⚠️ Konfirmasi</h3>
            <button class="modal-close" onclick="closeModal('confirmModal')">✕</button>
        </div>
        <p id="confirmMessage" style="color:#555;font-size:15px;line-height:1.5;"></p>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('confirmModal')">Batal</button>
            <button class="btn btn-danger" id="confirmBtn">Ya, Hapus</button>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const CAT = <?= json_encode(CATEGORIES) ?>;
const POST_OPTS = fd => ({method:'POST', body:fd, headers:{'X-CSRF-Token':CSRF}});

// ── Tab navigation — simpan hash di URL agar tidak hilang saat reload ──
function showTab(name) {
    document.querySelectorAll('#tab-dashboard,#tab-products,#tab-kasir,#tab-laporan')
        .forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.sidebar-item').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    const nav = document.getElementById('nav-' + name);
    if (nav) nav.classList.add('active');
    // Simpan tab aktif ke URL hash
    history.replaceState(null, '', '#' + name);
    // Inisialisasi chart saat tab laporan dibuka
    if (name === 'laporan') initLaporanCharts();
}

// Restore tab dari URL hash saat halaman load
(function restoreTab() {
    const hash = window.location.hash.replace('#', '');
    const valid = ['dashboard','products','kasir','laporan'];
    if (hash && valid.includes(hash)) {
        showTab(hash);
    }
})();

// ── BUG FIX: Sub-tab laporan — pakai class .rtab-content, bukan .tab-content ──
function showReportTab(name, btn) {
    document.querySelectorAll('.rtab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.report-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('rtab-' + name).classList.add('active');
    btn.classList.add('active');
    // Init chart saat tab grafik dibuka
    if (name === 'charts') initLaporanCharts();
}

// ── MODAL ──────────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

// ── ALERT ──────────────────────────────────────────────────────────────────
function showAlert(msg, type='success') {
    const el = document.getElementById('globalAlert');
    el.textContent = (type === 'success' ? '✅ ' : '❌ ') + msg;
    el.className = 'alert alert-' + type + ' show';
    window.scrollTo({top:0, behavior:'smooth'});
    setTimeout(() => el.classList.remove('show'), 4500);
}

// ── PRODUK ──────────────────────────────────────────────────────────────────
let imageTouched = false;

function emojisFor(cat) {
    return (CAT[cat] && CAT[cat].emojis) || CAT['Lainnya'].emojis;
}

function renderEmojiPicks() {
    const cat = document.getElementById('productCategory').value;
    const cur = document.getElementById('productImage').value;
    document.getElementById('emojiCatLabel').textContent = '— saran untuk ' + ((CAT[cat] ? CAT[cat].icon : '🏷️') + ' ' + cat);
    document.getElementById('emojiPicks').innerHTML = emojisFor(cat).map(em =>
        `<button type="button" class="emoji-pick ${em === cur ? 'active' : ''}" data-e="${em}">${em}</button>`
    ).join('');
}

document.getElementById('emojiPicks').addEventListener('click', function(ev) {
    const b = ev.target.closest('.emoji-pick');
    if (!b) return;
    document.getElementById('productImage').value = b.dataset.e;
    imageTouched = true;
    renderEmojiPicks();
});
document.getElementById('productImage').addEventListener('input', function() { imageTouched = true; renderEmojiPicks(); });
document.getElementById('productCategory').addEventListener('change', function() {
    // produk baru: icon otomatis mengikuti kategori selama belum diubah manual
    if (!imageTouched && document.getElementById('productId').value == 0) {
        document.getElementById('productImage').value = emojisFor(this.value)[0];
    }
    renderEmojiPicks();
});

function openProductModal() {
    document.getElementById('productModalTitle').textContent = 'Tambah Produk';
    document.getElementById('productId').value = 0;
    document.getElementById('productForm').reset();
    imageTouched = false;
    document.getElementById('productImage').value = emojisFor(document.getElementById('productCategory').value)[0];
    renderEmojiPicks();
    openModal('productModal');
}

function editProduct(id, name, price, category, stock, image) {
    document.getElementById('productModalTitle').textContent = 'Edit Produk';
    document.getElementById('productId').value = id;
    document.getElementById('productName').value = name;
    document.getElementById('productPrice').value = price;
    const sel = document.getElementById('productCategory');
    if (![...sel.options].some(o => o.value === category)) sel.add(new Option('🏷️ ' + category, category));
    sel.value = category;
    document.getElementById('productStock').value = stock;
    document.getElementById('productImage').value = image;
    imageTouched = true;
    renderEmojiPicks();
    openModal('productModal');
}

// BUG FIX: Helper untuk edit dari tab stok (pindah ke tab produk + buka modal)
function editProductById(id) {
    showTab('products');
    const btn = document.querySelector('.btn-edit-product[data-id="' + id + '"]');
    if (btn) btn.click();
}

document.addEventListener('click', function(ev) {
    const b = ev.target.closest('button');
    if (!b) return;
    const d = b.dataset;
    if (b.classList.contains('btn-edit-product'))  editProduct(d.id, d.name, d.price, d.category, d.stock, d.image);
    if (b.classList.contains('btn-del-product'))   deleteProduct(d.id, d.name);
    if (b.classList.contains('btn-reset-kasir'))   openResetPasswordModal(d.id, d.name);
    if (b.classList.contains('btn-del-kasir'))     deleteKasir(d.id, d.name);
});

document.getElementById('productForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action','save_product');
    fd.append('id',    document.getElementById('productId').value);
    fd.append('name',  document.getElementById('productName').value);
    fd.append('price', document.getElementById('productPrice').value);
    fd.append('category', document.getElementById('productCategory').value);
    fd.append('stock', document.getElementById('productStock').value);
    fd.append('image', document.getElementById('productImage').value);
    const res  = await fetch('admin.php', POST_OPTS(fd));
    const data = await res.json();
    closeModal('productModal');
    if (data.success) { showAlert('Produk berhasil disimpan!'); setTimeout(()=>location.reload(),900); }
    else showAlert(data.message || 'Gagal menyimpan produk', 'error');
});

function deleteProduct(id, name) {
    document.getElementById('confirmMessage').textContent = `Hapus produk "${name}"? Tindakan ini tidak dapat dibatalkan.`;
    document.getElementById('confirmBtn').onclick = async function() {
        const fd = new FormData();
        fd.append('action','delete_product');
        fd.append('id', id);
        const res  = await fetch('admin.php', POST_OPTS(fd));
        const data = await res.json();
        closeModal('confirmModal');
        if (data.success) {
            // Hapus baris langsung dari DOM — tidak perlu reload
            const row = document.querySelector(`.product-row[data-id="${id}"]`);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
            showAlert('Produk berhasil dihapus!');
        } else {
            showAlert('Gagal menghapus produk','error');
        }
    };
    openModal('confirmModal');
}

function filterProducts() {
    const q    = document.getElementById('productSearch').value.toLowerCase();
    const cat  = document.getElementById('categoryFilter').value.toLowerCase();
    const stok = document.getElementById('stockFilter').value;
    const filtering = !!(q || cat || stok);

    // saat memfilter, buka semua grup supaya hasil tidak tersembunyi
    if (filtering) {
        document.querySelectorAll('.cat-row').forEach(r => r.classList.remove('collapsed'));
        document.querySelectorAll('.product-row').forEach(r => r.classList.remove('group-collapsed'));
    }

    const visibleGroups = new Set();
    document.querySelectorAll('.product-row').forEach(r => {
        const matchQ   = r.dataset.name.includes(q) || r.dataset.cat.includes(q);
        const matchCat = !cat || r.dataset.cat === cat;
        const stock    = parseInt(r.dataset.stock);
        const matchStk = !stok || (stok==='low' && stock<=10) || (stok==='empty' && stock===0);
        const show = matchQ && matchCat && matchStk;
        r.style.display = show ? '' : 'none';
        if (show) visibleGroups.add(r.dataset.cat);
    });
    // sembunyikan judul kategori yang tidak punya produk cocok
    document.querySelectorAll('.cat-row').forEach(r => {
        r.style.display = visibleGroups.has(r.dataset.group) ? '' : 'none';
    });
}

// Klik judul kategori = lipat / buka grupnya
document.addEventListener('click', function(ev) {
    const row = ev.target.closest('.cat-row');
    if (!row) return;
    const collapsed = row.classList.toggle('collapsed');
    document.querySelectorAll('.product-row').forEach(r => {
        if (r.dataset.cat === row.dataset.group) r.classList.toggle('group-collapsed', collapsed);
    });
});

// ── KASIR ──────────────────────────────────────────────────────────────────
function openKasirModal() {
    document.getElementById('kasirForm').reset();
    openModal('kasirModal');
}

document.getElementById('kasirForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action','add_kasir');
    fd.append('username', document.getElementById('kasirUsername').value);
    fd.append('password', document.getElementById('kasirPassword').value);
    const res  = await fetch('admin.php', POST_OPTS(fd));
    const data = await res.json();
    closeModal('kasirModal');
    if (data.success) { showAlert('Akun kasir berhasil ditambahkan!'); setTimeout(()=>location.reload(),900); }
    else showAlert(data.message || 'Gagal menambah kasir','error');
});

function deleteKasir(id, username) {
    document.getElementById('confirmMessage').textContent = `Hapus akun kasir "${username}"?`;
    document.getElementById('confirmBtn').onclick = async function() {
        const fd = new FormData();
        fd.append('action','delete_kasir');
        fd.append('id', id);
        const res  = await fetch('admin.php', POST_OPTS(fd));
        const data = await res.json();
        closeModal('confirmModal');
        if (data.success) { showAlert('Akun kasir berhasil dihapus!'); setTimeout(()=>location.reload(),900); }
        else showAlert('Gagal menghapus kasir','error');
    };
    openModal('confirmModal');
}

// BUG FIX: Reset password kasir (fitur baru)
function openResetPasswordModal(id, username) {
    document.getElementById('resetPwId').value = id;
    document.getElementById('resetPwName').textContent = `👤 Reset password untuk: ${username}`;
    document.getElementById('resetPwForm').reset();
    openModal('resetPwModal');
}

document.getElementById('resetPwForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action','reset_kasir_password');
    fd.append('id', document.getElementById('resetPwId').value);
    fd.append('new_password', document.getElementById('resetPwValue').value);
    const res  = await fetch('admin.php', POST_OPTS(fd));
    const data = await res.json();
    closeModal('resetPwModal');
    if (data.success) showAlert('Password kasir berhasil direset!');
    else showAlert(data.message || 'Gagal reset password','error');
});

// ── LAPORAN ──────────────────────────────────────────────────────────────────
function filterLaporan() {
    const q = document.getElementById('laporanSearch').value.toLowerCase();
    document.querySelectorAll('.laporan-row').forEach(r => {
        r.style.display = (r.dataset.code.includes(q) || r.dataset.kasir.includes(q)) ? '' : 'none';
    });
}

function filterLaporanDate() {
    const from = document.getElementById('laporanFrom').value;
    const to   = document.getElementById('laporanTo').value;
    if (!from || !to) { alert('Pilih rentang tanggal'); return; }
    document.querySelectorAll('.laporan-row').forEach(r => {
        r.style.display = (r.dataset.date >= from && r.dataset.date <= to) ? '' : 'none';
    });
}

function resetLaporan() {
    document.getElementById('laporanSearch').value = '';
    document.getElementById('laporanFrom').value   = '';
    document.getElementById('laporanTo').value     = '';
    document.querySelectorAll('.laporan-row').forEach(r => r.style.display = '');
}

function exportLaporan() {
    const rows = document.querySelectorAll('#laporanTable tr');
    let csv = [];
    rows.forEach(r => {
        if (r.style.display === 'none') return;
        const cells = r.querySelectorAll('td,th');
        const cols  = Array.from(cells).slice(0,-1).map(c => '"' + c.innerText.replace(/"/g,'""') + '"');
        csv.push(cols.join(','));
    });
    const blob = new Blob(['\uFEFF' + csv.join('\n')], {type:'text/csv;charset=utf-8;'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'laporan_penjualan_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
}

// ── CHARTS ──────────────────────────────────────────────────────────────────
const COLORS = ['#667eea','#764ba2','#4caf50','#ff9800','#f44336','#2196f3','#9c27b0','#00bcd4','#ff5722','#607d8b'];

function fmtRp(v) { return 'Rp ' + v.toLocaleString('id-ID'); }
function fmtK(v)  { return 'Rp ' + (v/1000).toFixed(0) + 'k'; }

// Chart 7 hari (dashboard) — diinit langsung
(function() {
    const ctx = document.getElementById('salesChart7').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= $j7labels ?>,
            datasets: [{
                label: 'Penjualan',
                data: <?= $j7values ?>,
                backgroundColor: 'rgba(102,126,234,.7)',
                borderColor: '#667eea',
                borderWidth: 2,
                borderRadius: 6,
            }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label: c => fmtRp(c.parsed.y) }}},
            scales:{ y:{ beginAtZero:true, ticks:{ callback: v => fmtK(v) }, grid:{color:'#f0f2f5'} }, x:{grid:{display:false}} }
        }
    });
})();

// Charts laporan — lazy init agar tidak error saat canvas belum visible
let laporanChartsInited = false;
function initLaporanCharts() {
    if (laporanChartsInited) return;
    laporanChartsInited = true;

    // Chart 30 hari
    new Chart(document.getElementById('chart30').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= $j30labels ?>,
            datasets: [{
                label: 'Penjualan (Rp)',
                data: <?= $j30sales ?>,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102,126,234,.12)',
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label: c => fmtRp(c.parsed.y) }}},
            scales:{ y:{ beginAtZero:true, ticks:{ callback: v => fmtK(v) }, grid:{color:'#f0f2f5'} }, x:{grid:{display:false}, ticks:{maxTicksLimit:10}} }
        }
    });

    // Chart bulanan
    new Chart(document.getElementById('chartMonthly').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= $jMonL ?>,
            datasets: [{
                label: 'Penjualan',
                data: <?= $jMonS ?>,
                backgroundColor: COLORS.slice(0,<?= count($monthly_labels) ?>),
                borderRadius: 6,
            }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label: c => fmtRp(c.parsed.y) }}},
            scales:{ y:{ beginAtZero:true, ticks:{ callback: v => fmtK(v) }, grid:{color:'#f0f2f5'} }, x:{grid:{display:false}} }
        }
    });

    // Chart kategori (doughnut)
    new Chart(document.getElementById('chartCategory').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= $jCatL ?>,
            datasets: [{
                data: <?= $jCatV ?>,
                backgroundColor: COLORS,
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{ position:'bottom', labels:{font:{size:12}} },
                tooltip:{ callbacks:{ label: c => c.label + ': ' + fmtRp(c.parsed) }}}
        }
    });

    // Chart jumlah transaksi per hari
    new Chart(document.getElementById('chartTrxCount').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= $j30labels ?>,
            datasets: [{
                label: 'Jumlah Transaksi',
                data: <?= $j30count ?>,
                backgroundColor: 'rgba(76,175,80,.7)',
                borderColor: '#4caf50',
                borderWidth: 2,
                borderRadius: 6,
            }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label: c => c.parsed.y + ' transaksi' }}},
            scales:{ y:{ beginAtZero:true, ticks:{stepSize:1}, grid:{color:'#f0f2f5'} }, x:{grid:{display:false}, ticks:{maxTicksLimit:10}} }
        }
    });
}
</script>
</body>
</html>