<?php
require __DIR__ . '/config.php';
require_role('admin');

$today_sales = (int) mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_amount),0) AS t FROM transactions WHERE DATE(created_at)=CURDATE()"
))['t'];

$monthly_sales = (int) mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_amount),0) AS t FROM transactions
     WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())"
))['t'];

$total_sales = (int) mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(total_amount),0) AS t FROM transactions"
))['t'];

$total_transactions = (int) mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM transactions"
))['c'];

$transactions = mysqli_query($conn,
    "SELECT t.id, t.transaction_code, t.created_at,
            t.total_amount, t.payment_amount, t.change_amount,
            COALESCE(u.username,'-') AS kasir_name,
            GROUP_CONCAT(CONCAT(COALESCE(p.name,'?'),' (',ti.quantity,'x)') ORDER BY p.name SEPARATOR ', ') AS products_list
     FROM transactions t
     LEFT JOIN users u              ON t.user_id        = u.id
     LEFT JOIN transaction_items ti ON t.id             = ti.transaction_id
     LEFT JOIN products p           ON ti.product_id    = p.id
     GROUP BY t.id, t.transaction_code, t.created_at,
              t.total_amount, t.payment_amount, t.change_amount, u.username
     ORDER BY t.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan — Pet Shop</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',sans-serif;background:linear-gradient(135deg,#667eea,#764ba2);min-height:100vh;}
        .header{background:white;box-shadow:0 4px 12px rgba(0,0,0,.12);position:sticky;top:0;z-index:100;}
        .header-content{max-width:1400px;margin:0 auto;padding:18px 20px;
            display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
        .logo-section{display:flex;align-items:center;gap:14px;}
        .logo{font-size:44px;}
        .logo-text h1{font-family:Impact,sans-serif;color:#667eea;font-size:26px;letter-spacing:2px;}
        .logo-text p{color:#666;font-size:13px;}
        .header-actions{display:flex;gap:10px;align-items:center;}
        .btn{padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:bold;border:none;cursor:pointer;
             font-size:14px;display:inline-flex;align-items:center;gap:6px;transition:all .2s;}
        .btn-primary{background:#4caf50;color:white;}
        .btn-primary:hover{background:#45a049;transform:translateY(-1px);}
        .btn-secondary{background:#2196f3;color:white;}
        .btn-secondary:hover{background:#1976d2;transform:translateY(-1px);}
        .container{max-width:1400px;margin:0 auto;padding:20px;}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;margin-bottom:26px;}
        .stat-card{background:white;padding:24px;border-radius:14px;box-shadow:0 4px 12px rgba(0,0,0,.08);
            transition:all .3s;position:relative;overflow:hidden;}
        .stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;
            background:linear-gradient(90deg,#667eea,#764ba2);}
        .stat-card:hover{transform:translateY(-4px);box-shadow:0 8px 20px rgba(0,0,0,.12);}
        .stat-content{display:flex;align-items:center;gap:18px;}
        .stat-icon{font-size:42px;background:linear-gradient(135deg,#667eea,#764ba2);
            width:74px;height:74px;border-radius:14px;display:flex;align-items:center;justify-content:center;}
        .stat-info h3{font-size:13px;color:#666;margin-bottom:7px;text-transform:uppercase;letter-spacing:1px;}
        .stat-info p{font-size:22px;font-weight:bold;color:#333;}
        .search-box{background:white;padding:18px;border-radius:14px;box-shadow:0 4px 12px rgba(0,0,0,.08);margin-bottom:18px;}
        .search-container{display:flex;gap:12px;align-items:center;flex-wrap:wrap;}
        .search-input{flex:1;min-width:240px;padding:11px 18px;border:2px solid #e0e0e0;border-radius:8px;font-size:15px;transition:border-color .2s;}
        .search-input:focus{outline:none;border-color:#667eea;}
        .date-filter{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
        .date-input{padding:10px 13px;border:2px solid #e0e0e0;border-radius:8px;font-size:14px;}
        .card{background:white;padding:28px;border-radius:14px;box-shadow:0 4px 12px rgba(0,0,0,.08);margin-bottom:18px;}
        .card-header{display:flex;justify-content:space-between;align-items:center;
            margin-bottom:22px;padding-bottom:18px;border-bottom:2px solid #e0e0e0;}
        .card-header h2{color:#333;font-size:22px;display:flex;align-items:center;gap:10px;}
        .table-container{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;}
        thead{background:linear-gradient(135deg,#667eea,#764ba2);color:white;}
        th{padding:14px;text-align:left;font-weight:600;white-space:nowrap;}
        td{padding:14px;border-bottom:1px solid #e0e0e0;}
        tbody tr{transition:background .2s;}
        tbody tr:hover{background:#f5f5f5;}
        .transaction-code{font-weight:bold;color:#667eea;}
        .total-amount{font-weight:bold;color:#4caf50;font-size:15px;}
        .products-list{color:#666;font-size:13px;max-width:280px;}
        .date-time{color:#999;font-size:13px;}
        .no-data{text-align:center;padding:56px 20px;color:#999;}
        .no-data-icon{font-size:60px;margin-bottom:16px;opacity:.3;}
        .detail-btn{padding:6px 12px;background:#667eea;color:white;border:none;border-radius:6px;
            cursor:pointer;font-size:12px;text-decoration:none;display:inline-block;}
        .detail-btn:hover{background:#5568d3;}
        @media(max-width:768px){
            .header-content{flex-direction:column;align-items:flex-start;}
            .stats-grid{grid-template-columns:1fr;}
            .search-container{flex-direction:column;align-items:stretch;}
            .date-filter{flex-direction:column;}
        }
        @media print{body{background:white;}.header,.search-box,.header-actions{display:none!important;}}
    </style>
</head>
<body>
<div class="header">
  <div class="header-content">
    <div class="logo-section">
      <div class="logo">🐕</div>
      <div class="logo-text"><h1>PET SHOP</h1><p>Laporan Penjualan</p></div>
    </div>
    <div class="header-actions">
      <a href="admin.php" class="btn btn-secondary">⬅️ Kembali</a>
      <button onclick="exportCSV()" class="btn btn-primary">📥 Export CSV</button>
      <button onclick="window.print()" class="btn btn-primary">🖨️ Cetak</button>
    </div>
  </div>
</div>

<div class="container">
  <div class="stats-grid">
    <div class="stat-card"><div class="stat-content">
      <div class="stat-icon">📅</div>
      <div class="stat-info"><h3>Penjualan Hari Ini</h3><p>Rp <?= number_format($today_sales,0,',','.') ?></p></div>
    </div></div>
    <div class="stat-card"><div class="stat-content">
      <div class="stat-icon">📆</div>
      <div class="stat-info"><h3>Penjualan Bulan Ini</h3><p>Rp <?= number_format($monthly_sales,0,',','.') ?></p></div>
    </div></div>
    <div class="stat-card"><div class="stat-content">
      <div class="stat-icon">💰</div>
      <div class="stat-info"><h3>Total Penjualan</h3><p>Rp <?= number_format($total_sales,0,',','.') ?></p></div>
    </div></div>
    <div class="stat-card"><div class="stat-content">
      <div class="stat-icon">🧾</div>
      <div class="stat-info"><h3>Total Transaksi</h3><p><?= number_format($total_transactions) ?></p></div>
    </div></div>
  </div>

  <div class="search-box">
    <div class="search-container">
      <span style="font-size:22px;color:#667eea;">🔍</span>
      <input type="text" id="searchInput" class="search-input" placeholder="Cari kode transaksi atau kasir...">
      <div class="date-filter">
        <input type="date" id="dateFrom" class="date-input">
        <span>—</span>
        <input type="date" id="dateTo" class="date-input">
        <button onclick="filterByDate()" class="btn btn-secondary">Filter</button>
        <button onclick="resetFilter()" class="btn btn-primary">Reset</button>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2>📊 Riwayat Transaksi</h2></div>
    <div class="table-container">
      <table id="transactionsTable">
        <thead>
          <tr>
            <th>Kode Transaksi</th><th>Tanggal &amp; Waktu</th><th>Kasir</th>
            <th>Produk</th><th>Total</th><th>Bayar</th><th>Kembalian</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody id="transactionsBody">
          <?php if ($transactions && mysqli_num_rows($transactions) > 0): ?>
            <?php while ($t = mysqli_fetch_assoc($transactions)): ?>
            <tr class="transaction-row"
                data-code="<?= strtolower(htmlspecialchars($t['transaction_code'])) ?>"
                data-kasir="<?= strtolower(htmlspecialchars($t['kasir_name'])) ?>"
                data-date="<?= date('Y-m-d', strtotime($t['created_at'])) ?>">
              <td class="transaction-code"><?= htmlspecialchars($t['transaction_code']) ?></td>
              <td class="date-time"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
              <td><?= htmlspecialchars($t['kasir_name']) ?></td>
              <td class="products-list"><?= htmlspecialchars($t['products_list'] ?? '-') ?></td>
              <td class="total-amount">Rp <?= number_format($t['total_amount'],0,',','.') ?></td>
              <td>Rp <?= number_format($t['payment_amount'],0,',','.') ?></td>
              <td>Rp <?= number_format($t['change_amount'],0,',','.') ?></td>
              <td><a href="transaction_detail.php?id=<?= (int)$t['id'] ?>" class="detail-btn">👁️ Detail</a></td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8"><div class="no-data">
              <div class="no-data-icon">📊</div><p>Belum ada transaksi</p>
            </div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.getElementById('searchInput').addEventListener('input', function(){
    const s = this.value.toLowerCase();
    document.querySelectorAll('.transaction-row').forEach(r => {
        r.style.display = (r.dataset.code.includes(s) || r.dataset.kasir.includes(s)) ? '' : 'none';
    });
});
function filterByDate(){
    const from=document.getElementById('dateFrom').value;
    const to  =document.getElementById('dateTo').value;
    if(!from||!to){alert('Pilih tanggal mulai dan akhir');return;}
    document.querySelectorAll('.transaction-row').forEach(r=>{
        r.style.display=(r.dataset.date>=from&&r.dataset.date<=to)?'':'none';
    });
}
function resetFilter(){
    document.getElementById('dateFrom').value='';
    document.getElementById('dateTo').value='';
    document.getElementById('searchInput').value='';
    document.querySelectorAll('.transaction-row').forEach(r=>r.style.display='');
}
function exportCSV(){
    const rows=document.querySelectorAll('#transactionsTable tr');
    let csv=[];
    rows.forEach(r=>{
        if(r.style.display==='none')return;
        const cells=r.querySelectorAll('td,th');
        const cols=Array.from(cells).slice(0,-1).map(c=>'"'+c.innerText.replace(/"/g,'""')+'"');
        csv.push(cols.join(','));
    });
    const blob=new Blob(['\uFEFF'+csv.join('\n')],{type:'text/csv;charset=utf-8;'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download='laporan_penjualan_'+new Date().toISOString().split('T')[0]+'.csv';
    a.click();
}
</script>
</body>
</html>