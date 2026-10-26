<?php
require __DIR__ . '/config.php';
require_role('kasir');

$products_query = mysqli_query($conn, "SELECT id, name, price, category, stock, image FROM products WHERE stock > 0 ORDER BY category, name ASC");
$productsArray  = [];
while ($row = mysqli_fetch_assoc($products_query)) {
    $row['id']    = (int)$row['id'];
    $row['price'] = (int)$row['price'];
    $row['stock'] = (int)$row['stock'];
    $productsArray[] = $row;
}

$categories_query = mysqli_query($conn, "SELECT DISTINCT category FROM products WHERE stock > 0 ORDER BY category ASC");
$cat_list = [];
while ($row = mysqli_fetch_assoc($categories_query)) {
    $cat_list[] = $row['category'];
}
$categories = array_merge(['Semua'], category_sort($cat_list));

$categoryIcons = [];
foreach ($categories as $cn) {
    $categoryIcons[$cn] = ($cn === 'Semua') ? '🐾' : category_icon($cn);
}

$JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Pet Shop — Kasir</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --orange:   #f97316;
            --orange-d: #c2410c;
            --orange-l: #fff7ed;
            --orange-m: #fed7aa;
            --slate:    #0f172a;
            --slate-m:  #334155;
            --slate-l:  #94a3b8;
            --bg:       #f1f5f9;
            --card:     #ffffff;
            --border:   #e2e8f0;
            --green:    #16a34a;
            --green-l:  #dcfce7;
            --red:      #ef4444;
            --red-l:    #fee2e2;
            --amber-l:  #fef3c7;
            --amber-d:  #b45309;
            --radius:   16px;
            --shadow:   0 1px 2px rgba(15,23,42,.05), 0 6px 20px rgba(15,23,42,.06);
            --hdr:      64px;
        }
        * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
        html { scroll-behavior:smooth; }
        body {
            font-family:'DM Sans',system-ui,sans-serif; background:var(--bg); color:var(--slate);
            min-height:100vh; font-variant-numeric:tabular-nums;
        }
        button { font-family:inherit; }

        /* ── Harga: "Rp" kecil + angka tebal ── */
        .price { display:inline-flex; align-items:baseline; gap:.28em; white-space:nowrap; line-height:1; }
        .price .cur { font-size:.55em; font-weight:700; letter-spacing:.04em; opacity:.65; }
        .price .amt { font-family:'Syne',sans-serif; font-weight:800; letter-spacing:-.01em; }

        /* ── Header ── */
        .header {
            background:var(--slate); height:var(--hdr); padding:0 24px;
            display:flex; justify-content:space-between; align-items:center;
            position:sticky; top:0; z-index:50; box-shadow:0 2px 16px rgba(0,0,0,.18);
        }
        .brand { display:flex; align-items:center; gap:10px; }
        .brand .logo { font-size:26px; }
        .brand h1 { font-family:'Syne',sans-serif; font-size:19px; font-weight:800; color:#fff; letter-spacing:.2px; }
        .brand h1 span { color:var(--orange); }
        .header-right { display:flex; align-items:center; gap:10px; }
        .user-pill { background:rgba(255,255,255,.1); color:#fff; padding:7px 14px; border-radius:99px; font-size:13px; font-weight:600; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .btn-logout { background:rgba(239,68,68,.15); color:#fecaca; padding:8px 14px; border-radius:10px; text-decoration:none; font-weight:600; font-size:13px; border:1px solid rgba(239,68,68,.4); transition:.2s; }
        .btn-logout:hover { background:var(--red); color:#fff; }

        /* ── Layout ── */
        .main { display:grid; grid-template-columns:minmax(0,1fr) 390px; align-items:start; }
        .products-panel { min-width:0; padding-bottom:24px; }

        .toolbar {
            position:sticky; top:var(--hdr); z-index:30; background:rgba(241,245,249,.95);
            backdrop-filter:blur(8px); padding:16px 24px 12px; border-bottom:1px solid var(--border);
        }
        .search-wrap { position:relative; margin-bottom:12px; }
        .search-wrap .ico { position:absolute; left:16px; top:50%; transform:translateY(-50%); font-size:16px; opacity:.5; pointer-events:none; }
        .search-wrap input {
            width:100%; padding:13px 44px 13px 44px; border:1.5px solid var(--border); border-radius:12px;
            font-size:15px; background:#fff; transition:.2s; font-family:inherit;
        }
        .search-wrap input:focus { outline:none; border-color:var(--orange); box-shadow:0 0 0 4px rgba(249,115,22,.12); }
        .search-clear { position:absolute; right:10px; top:50%; transform:translateY(-50%); border:none; background:#e2e8f0; color:var(--slate-m); width:26px; height:26px; border-radius:50%; cursor:pointer; font-size:14px; display:none; }
        .search-clear.show { display:block; }

        .category-strip { display:flex; gap:8px; overflow-x:auto; scrollbar-width:none; padding-bottom:2px; }
        .category-strip::-webkit-scrollbar { display:none; }
        .cat-btn {
            flex:0 0 auto; padding:8px 16px; border:1.5px solid var(--border); border-radius:99px;
            background:#fff; font-size:13px; font-weight:600; cursor:pointer; transition:.2s; color:var(--slate-m);
        }
        .cat-btn:hover { border-color:var(--orange-m); background:var(--orange-l); }
        .cat-btn.active { background:var(--orange); color:#fff; border-color:var(--orange); box-shadow:0 4px 12px rgba(249,115,22,.3); }

        .products-grid {
            display:grid; grid-template-columns:repeat(auto-fill,minmax(176px,1fr)); gap:16px; padding:20px 24px 0;
        }

        /* ── Kartu produk ── */
        .product-card {
            background:var(--card); border:1.5px solid transparent; border-radius:var(--radius);
            padding:16px; display:flex; flex-direction:column; box-shadow:var(--shadow);
            transition:transform .2s, border-color .2s, box-shadow .2s; position:relative; overflow:hidden;
        }
        .product-card:hover { border-color:var(--orange-m); transform:translateY(-3px); box-shadow:0 10px 28px rgba(249,115,22,.14); }
        .product-card.in-cart { border-color:var(--orange); }
        .in-cart-badge {
            position:absolute; top:10px; right:10px; background:var(--orange); color:#fff;
            font-size:11px; font-weight:700; padding:3px 9px; border-radius:99px;
        }
        .emoji-wrap {
            width:76px; height:76px; margin:4px auto 12px; border-radius:50%;
            background:radial-gradient(circle at 30% 25%, #fff 0%, var(--orange-l) 70%);
            display:flex; align-items:center; justify-content:center; font-size:40px;
        }
        .cat-badge { align-self:flex-start; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--slate-l); margin-bottom:5px; }
        .product-card .name { font-weight:700; font-size:14.5px; line-height:1.3; min-height:38px; margin-bottom:10px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .product-card .price-row { display:flex; align-items:flex-end; justify-content:space-between; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
        .product-card .price { font-size:21px; color:var(--orange-d); }
        .stock-pill { font-size:11px; font-weight:700; padding:3px 9px; border-radius:99px; background:var(--green-l); color:var(--green); white-space:nowrap; }
        .stock-pill.low  { background:var(--amber-l); color:var(--amber-d); }
        .stock-pill.zero { background:var(--red-l); color:var(--red); }
        .add-btn {
            margin-top:auto; width:100%; padding:11px; background:var(--slate); color:#fff; border:none;
            border-radius:11px; font-weight:700; font-size:14px; cursor:pointer; transition:.2s;
            display:flex; align-items:center; justify-content:center; gap:6px;
        }
        .add-btn:hover:not(:disabled) { background:var(--orange); transform:translateY(-1px); }
        .add-btn:active:not(:disabled) { transform:scale(.97); }
        .add-btn:disabled { background:#e2e8f0; color:var(--slate-l); cursor:not-allowed; }

        .empty-state { grid-column:1/-1; text-align:center; padding:70px 20px; color:var(--slate-l); }
        .empty-state .icon { font-size:52px; margin-bottom:10px; opacity:.6; }

        /* ── Judul grup kategori ── */
        .group-head { grid-column:1/-1; display:flex; align-items:center; gap:10px; margin-top:10px; }
        .group-head:first-child { margin-top:0; }
        .group-head::after { content:''; flex:1; height:1px; background:var(--border); order:3; }
        .gh-icon { width:38px; height:38px; border-radius:12px; background:#fff; box-shadow:var(--shadow); display:flex; align-items:center; justify-content:center; font-size:20px; }
        .gh-name { font-family:'Syne',sans-serif; font-weight:800; font-size:17px; }
        .gh-count { font-size:12px; font-weight:600; color:var(--slate-l); background:#e2e8f0; padding:3px 10px; border-radius:99px; }

        /* ── Keranjang ── */
        .cart-panel {
            background:var(--card); border-left:1px solid var(--border);
            display:flex; flex-direction:column;
            position:sticky; top:var(--hdr); height:calc(100vh - var(--hdr)); height:calc(100dvh - var(--hdr));
        }
        .cart-head { padding:18px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .cart-head h2 { font-family:'Syne',sans-serif; font-size:18px; font-weight:800; display:flex; align-items:center; gap:8px; }
        .cart-badge { background:var(--orange); color:#fff; border-radius:99px; padding:2px 10px; font-size:12px; font-weight:700; }
        .cart-badge.hidden { display:none; }
        .cart-actions { display:flex; align-items:center; gap:8px; }
        .btn-clear { background:none; border:none; color:var(--red); font-size:12.5px; font-weight:600; cursor:pointer; padding:6px 8px; border-radius:8px; }
        .btn-clear:hover { background:var(--red-l); }
        .btn-clear.hidden { display:none; }
        .sheet-close { display:none; background:#f1f5f9; border:none; width:34px; height:34px; border-radius:50%; font-size:18px; cursor:pointer; color:var(--slate-m); }

        .cart-items { flex:1; overflow-y:auto; padding:12px 16px; overscroll-behavior:contain; }
        .cart-empty { text-align:center; padding:60px 20px; color:var(--slate-l); }
        .cart-empty .icon { font-size:48px; margin-bottom:10px; opacity:.6; }
        .cart-empty p { font-size:14px; }

        .cart-item { display:grid; grid-template-columns:44px 1fr auto; gap:12px; align-items:center; padding:12px 0; border-bottom:1px dashed var(--border); }
        .cart-item:last-child { border-bottom:none; }
        .ci-emoji { width:44px; height:44px; border-radius:12px; background:var(--orange-l); display:flex; align-items:center; justify-content:center; font-size:23px; }
        .ci-info { min-width:0; }
        .ci-name { font-weight:700; font-size:13.5px; line-height:1.3; margin-bottom:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .ci-unit { font-size:12px; color:var(--slate-l); margin-bottom:8px; }
        .ci-unit .price .amt { font-family:'DM Sans',sans-serif; font-weight:600; }
        .qty { display:inline-flex; align-items:center; background:#f1f5f9; border-radius:10px; padding:2px; }
        .qty button { width:30px; height:30px; border:none; border-radius:8px; background:transparent; font-weight:700; font-size:17px; cursor:pointer; color:var(--slate-m); }
        .qty button:hover:not(:disabled) { background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.1); }
        .qty button:disabled { opacity:.35; cursor:not-allowed; }
        .qty span { min-width:30px; text-align:center; font-weight:700; font-size:14px; }
        .ci-right { text-align:right; display:flex; flex-direction:column; align-items:flex-end; gap:10px; }
        .ci-right .price { font-size:15px; color:var(--slate); }
        .ci-del { background:none; border:none; font-size:15px; cursor:pointer; color:var(--slate-l); padding:4px; border-radius:6px; }
        .ci-del:hover { color:var(--red); background:var(--red-l); }

        .cart-foot { padding:16px 20px calc(16px + env(safe-area-inset-bottom)); border-top:1px solid var(--border); background:#fff; }
        .sum-row { display:flex; justify-content:space-between; align-items:center; font-size:13px; color:var(--slate-l); margin-bottom:6px; }
        .total-row { display:flex; justify-content:space-between; align-items:flex-end; margin:10px 0 14px; }
        .total-label { font-weight:700; font-size:13px; letter-spacing:.8px; color:var(--slate-m); }
        .total-value { font-size:32px; color:var(--slate); }
        .total-value .cur { font-size:.42em; }
        .btn-checkout {
            width:100%; padding:16px; background:linear-gradient(135deg,#22c55e,#16a34a); color:#fff; border:none;
            border-radius:14px; font-family:'Syne',sans-serif; font-size:16px; font-weight:800; cursor:pointer;
            transition:.2s; box-shadow:0 6px 18px rgba(22,163,74,.3);
        }
        .btn-checkout:hover:not(:disabled) { transform:translateY(-1px); box-shadow:0 10px 24px rgba(22,163,74,.38); }
        .btn-checkout:disabled { background:#e2e8f0; color:var(--slate-l); box-shadow:none; cursor:not-allowed; }

        /* ── Bar mobile + backdrop ── */
        .mobile-bar { display:none; }
        .backdrop { display:none; position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:90; opacity:0; transition:opacity .25s; }

        /* ── Modal ── */
        .modal { display:none; position:fixed; inset:0; z-index:200; background:rgba(15,23,42,.6); overflow-y:auto; padding:20px; }
        .modal.open { display:flex; align-items:flex-start; justify-content:center; }
        .modal-content { background:var(--card); margin:auto; border-radius:22px; width:100%; max-width:440px; box-shadow:0 24px 70px rgba(0,0,0,.3); animation:pop .25s ease; overflow:hidden; }
        @keyframes pop { from{opacity:0;transform:translateY(14px) scale(.98)} to{opacity:1;transform:none} }
        .modal-head { padding:18px 24px; background:var(--slate); color:#fff; display:flex; justify-content:space-between; align-items:center; }
        .modal-head h2 { font-family:'Syne',sans-serif; font-size:18px; font-weight:800; }
        .modal-close { background:rgba(255,255,255,.12); border:none; color:#fff; width:32px; height:32px; border-radius:50%; font-size:18px; cursor:pointer; }
        .modal-close:hover { background:rgba(255,255,255,.25); }
        .modal-body { padding:24px; }

        .pay-total { background:linear-gradient(135deg,var(--orange-l),#ffedd5); border:1px solid var(--orange-m); border-radius:16px; padding:18px; text-align:center; margin-bottom:20px; }
        .pay-total-label { font-size:12px; font-weight:700; letter-spacing:.8px; text-transform:uppercase; color:var(--orange-d); margin-bottom:8px; }
        .pay-total .price { font-size:38px; color:var(--orange-d); justify-content:center; }
        .pay-total .price .cur { font-size:.4em; }

        .field-label { display:block; font-weight:700; font-size:12.5px; color:var(--slate-m); margin-bottom:8px; }
        .pay-input-wrap { position:relative; margin-bottom:12px; }
        .pay-input-wrap span { position:absolute; left:16px; top:50%; transform:translateY(-50%); font-weight:700; color:var(--slate-l); font-size:15px; }
        .pay-input-wrap input {
            width:100%; padding:14px 16px 14px 48px; border:2px solid var(--border); border-radius:14px;
            font-family:'Syne',sans-serif; font-size:24px; font-weight:800; color:var(--slate); transition:.2s;
        }
        .pay-input-wrap input:focus { outline:none; border-color:var(--orange); box-shadow:0 0 0 4px rgba(249,115,22,.12); }
        .quick-amounts { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; margin-bottom:18px; }
        .quick-btn { padding:10px 8px; border:1.5px solid var(--border); background:#fff; border-radius:11px; font-weight:700; font-size:13.5px; cursor:pointer; color:var(--slate-m); transition:.15s; }
        .quick-btn:hover { border-color:var(--orange); background:var(--orange-l); color:var(--orange-d); }
        .quick-btn.exact { grid-column:1/-1; background:var(--slate); color:#fff; border-color:var(--slate); }
        .quick-btn.exact:hover { background:var(--orange); border-color:var(--orange); }

        .change-box { display:flex; justify-content:space-between; align-items:center; background:#f8fafc; border:1px solid var(--border); border-radius:14px; padding:14px 18px; margin-bottom:12px; }
        .change-label { font-size:13px; font-weight:700; color:var(--slate-m); }
        .change-value { font-size:24px; color:var(--green); }
        .change-value .cur { font-size:.5em; }
        .change-box.short { background:var(--red-l); border-color:#fecaca; }
        .change-box.short .change-value, .change-box.short .change-label { color:var(--red); }

        .modal-actions { display:flex; gap:10px; margin-top:6px; }
        .modal-btn { flex:1; padding:15px; border:none; border-radius:13px; font-family:'Syne',sans-serif; font-size:15px; font-weight:800; cursor:pointer; transition:.2s; }
        .modal-btn-cancel { background:#f1f5f9; color:var(--slate-m); flex:.6; }
        .modal-btn-cancel:hover { background:#e2e8f0; }
        .modal-btn-confirm { background:linear-gradient(135deg,#22c55e,#16a34a); color:#fff; box-shadow:0 6px 16px rgba(22,163,74,.28); }
        .modal-btn-confirm:hover:not(:disabled) { filter:brightness(1.06); }
        .modal-btn-confirm:disabled { background:#e2e8f0; color:var(--slate-l); box-shadow:none; cursor:not-allowed; }

        /* ── Sukses ── */
        .success-body { padding:30px 24px 24px; text-align:center; }
        .success-icon { width:72px; height:72px; margin:0 auto 14px; border-radius:50%; background:var(--green-l); color:var(--green); font-size:36px; display:flex; align-items:center; justify-content:center; }
        .success-body h3 { font-family:'Syne',sans-serif; font-size:20px; margin-bottom:4px; }
        .success-code { font-size:13px; color:var(--slate-l); margin-bottom:20px; font-weight:600; letter-spacing:.5px; }
        .receipt { border:1.5px dashed var(--border); border-radius:14px; padding:6px 16px; text-align:left; margin-bottom:20px; }
        .receipt-row { display:flex; justify-content:space-between; align-items:center; padding:11px 0; font-size:14px; color:var(--slate-m); border-bottom:1px dashed var(--border); }
        .receipt-row:last-child { border-bottom:none; }
        .receipt-row .price { font-size:16px; color:var(--slate); }
        .receipt-row.big .price { font-size:20px; color:var(--green); }

        /* ── Toast ── */
        .toast-wrap { position:fixed; top:76px; left:50%; transform:translateX(-50%); z-index:300; display:flex; flex-direction:column; gap:8px; width:calc(100% - 32px); max-width:380px; pointer-events:none; }
        .toast { background:var(--slate); color:#fff; padding:12px 16px; border-radius:12px; font-size:13.5px; font-weight:600; box-shadow:0 10px 30px rgba(0,0,0,.25); animation:pop .2s ease; }
        .toast.error { background:var(--red); }

        /* ── Responsive ── */
        @media (max-width:1180px) { .main { grid-template-columns:minmax(0,1fr) 340px; } }

        @media (max-width:1024px) {
            body { padding-bottom:calc(84px + env(safe-area-inset-bottom)); }
            .main { display:block; }
            .toolbar, .products-grid { padding-left:16px; padding-right:16px; }

            .cart-panel {
                position:fixed; left:0; right:0; bottom:0; top:auto; height:auto; max-height:88dvh; max-height:88vh;
                z-index:100; border-left:none; border-radius:24px 24px 0 0; box-shadow:0 -12px 40px rgba(0,0,0,.2);
                transform:translateY(105%); transition:transform .3s cubic-bezier(.32,.72,0,1); visibility:hidden;
            }
            .cart-panel.open { transform:none; visibility:visible; }
            .cart-panel::before { content:''; display:block; width:42px; height:4px; background:#cbd5e1; border-radius:4px; margin:10px auto 0; }
            .cart-items { min-height:120px; }
            .sheet-close { display:block; }
            .backdrop.show { display:block; opacity:1; }

            .mobile-bar {
                display:flex; align-items:center; justify-content:space-between; gap:12px;
                position:fixed; left:12px; right:12px; bottom:calc(12px + env(safe-area-inset-bottom)); z-index:80;
                background:var(--slate); color:#fff; border:none; border-radius:18px; padding:12px 14px 12px 18px;
                box-shadow:0 12px 32px rgba(15,23,42,.4); cursor:pointer; text-align:left; width:calc(100% - 24px);
            }
            .mobile-bar.empty { opacity:.55; }
            .mb-left { display:flex; align-items:center; gap:12px; }
            .mb-icon { position:relative; font-size:24px; }
            .mb-count { position:absolute; top:-6px; right:-10px; background:var(--orange); color:#fff; font-size:11px; font-weight:700; min-width:20px; height:20px; border-radius:99px; display:flex; align-items:center; justify-content:center; padding:0 5px; }
            .mb-count.hidden { display:none; }
            .mb-text small { display:block; font-size:11px; opacity:.65; margin-bottom:3px; }
            .mb-text .price { font-size:19px; }
            .mb-cta { background:var(--orange); padding:11px 16px; border-radius:12px; font-weight:700; font-size:14px; white-space:nowrap; }
        }

        @media (max-width:640px) {
            :root { --hdr:58px; }
            .header { padding:0 14px; }
            .brand h1 { font-size:17px; }
            .user-pill { display:none; }
            .toolbar { padding-top:12px; }
            .products-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; padding-top:14px; }
            .product-card { padding:12px; border-radius:14px; }
            .emoji-wrap { width:60px; height:60px; font-size:32px; margin-bottom:8px; }
            .product-card .name { font-size:13px; min-height:34px; margin-bottom:8px; }
            .product-card .price { font-size:17px; }
            .gh-icon { width:32px; height:32px; font-size:17px; }
            .gh-name { font-size:15px; }
            .stock-pill { font-size:10px; padding:2px 7px; }
            .add-btn { padding:10px; font-size:13px; }
            .modal { padding:0; align-items:flex-end; }
            .modal.open { align-items:flex-end; }
            .modal-content { margin:0; max-width:none; border-radius:24px 24px 0 0; }
            .modal-body { padding:20px 18px calc(20px + env(safe-area-inset-bottom)); }
            .pay-total .price { font-size:32px; }
        }
        @media (max-width:360px) { .products-grid { gap:8px; } .product-card .price { font-size:15px; } }
        @media (prefers-reduced-motion:reduce) { * { animation:none !important; transition:none !important; } }
    </style>
</head>
<body>

<div class="header">
    <div class="brand">
        <span class="logo">🐾</span>
        <h1>Pet<span>Shop</span> POS</h1>
    </div>
    <div class="header-right">
        <span class="user-pill">👤 <?= e($_SESSION['username']) ?></span>
        <a href="logout.php" class="btn-logout">Keluar</a>
    </div>
</div>

<div class="main">
    <!-- PRODUK -->
    <div class="products-panel">
        <div class="toolbar">
            <div class="search-wrap">
                <span class="ico">🔍</span>
                <input type="search" id="searchInput" placeholder="Cari produk..." autocomplete="off">
                <button class="search-clear" id="searchClear" type="button" aria-label="Hapus pencarian">✕</button>
            </div>
            <div class="category-strip" id="categoryStrip"></div>
        </div>
        <div class="products-grid" id="productsGrid"></div>
    </div>

    <!-- KERANJANG (sidebar di desktop, bottom-sheet di mobile) -->
    <aside class="cart-panel" id="cartPanel">
        <div class="cart-head">
            <h2>🛒 Keranjang <span class="cart-badge hidden" id="cartBadge">0</span></h2>
            <div class="cart-actions">
                <button class="btn-clear hidden" id="clearBtn" onclick="clearCart()">Kosongkan</button>
                <button class="sheet-close" onclick="toggleCart(false)" aria-label="Tutup">✕</button>
            </div>
        </div>
        <div class="cart-items" id="cartItems"></div>
        <div class="cart-foot">
            <div class="sum-row"><span>Jumlah item</span><span id="itemCount">0</span></div>
            <div class="total-row">
                <span class="total-label">TOTAL</span>
                <span class="price total-value" id="totalDisplay"></span>
            </div>
            <button class="btn-checkout" id="checkoutBtn" onclick="openPayment()" disabled>Bayar Sekarang</button>
        </div>
    </aside>
</div>

<div class="backdrop" id="backdrop" onclick="toggleCart(false)"></div>

<button class="mobile-bar empty" id="mobileBar" onclick="toggleCart(true)">
    <span class="mb-left">
        <span class="mb-icon">🛒<span class="mb-count hidden" id="mbCount">0</span></span>
        <span class="mb-text"><small>Total belanja</small><span class="price" id="mbTotal"></span></span>
    </span>
    <span class="mb-cta">Lihat Keranjang</span>
</button>

<!-- MODAL PEMBAYARAN -->
<div id="payModal" class="modal">
    <div class="modal-content">
        <div class="modal-head">
            <h2>💳 Pembayaran</h2>
            <button class="modal-close" onclick="closePayment()" aria-label="Tutup">✕</button>
        </div>
        <div class="modal-body">
            <div class="pay-total">
                <div class="pay-total-label">Total Belanja</div>
                <div class="price" id="modalTotal"></div>
            </div>

            <label class="field-label" for="payInput">Jumlah Bayar</label>
            <div class="pay-input-wrap">
                <span>Rp</span>
                <input type="text" id="payInput" inputmode="numeric" autocomplete="off" oninput="onPayInput()">
            </div>
            <div class="quick-amounts" id="quickAmounts"></div>

            <div class="change-box" id="changeBox">
                <span class="change-label" id="changeLabel">Kembalian</span>
                <span class="price change-value" id="changeDisplay"></span>
            </div>

            <div class="modal-actions">
                <button class="modal-btn modal-btn-cancel" onclick="closePayment()">Batal</button>
                <button class="modal-btn modal-btn-confirm" id="confirmBtn" onclick="processTransaction()" disabled>Konfirmasi</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL SUKSES -->
<div id="successModal" class="modal">
    <div class="modal-content">
        <div class="success-body">
            <div class="success-icon">✓</div>
            <h3>Transaksi Berhasil!</h3>
            <div class="success-code" id="sCode"></div>
            <div class="receipt">
                <div class="receipt-row"><span>Total</span><span class="price" id="sTotal"></span></div>
                <div class="receipt-row"><span>Bayar</span><span class="price" id="sPay"></span></div>
                <div class="receipt-row big"><span>Kembalian</span><span class="price" id="sChange"></span></div>
            </div>
            <button class="modal-btn modal-btn-confirm" style="width:100%" onclick="closeSuccess()">Transaksi Baru</button>
        </div>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
const productsData = <?= json_encode($productsArray, $JSON_FLAGS) ?>;
const categories   = <?= json_encode($categories, $JSON_FLAGS) ?>;
const catIcons     = <?= json_encode($categoryIcons, $JSON_FLAGS) ?>;
const icon = c => catIcons[c] || '🏷️';

let products = productsData.map(p => ({...p}));
let cart     = [];
let selCat   = 'Semua';
let totalAmt = 0;
let busy     = false;

const $ = id => document.getElementById(id);
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const num = n => new Intl.NumberFormat('id-ID').format(n);
const priceHTML = n => `<span class="cur">Rp</span><span class="amt">${num(n)}</span>`;
const setPrice = (id, n) => { $(id).innerHTML = priceHTML(n); };

function toast(msg, type = '') {
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.textContent = msg;
    $('toastWrap').appendChild(t);
    setTimeout(() => t.remove(), 2600);
}

/* ── Produk ── */
function renderCats() {
    const strip = $('categoryStrip');
    strip.innerHTML = categories.map((c, i) =>
        `<button class="cat-btn ${c === selCat ? 'active' : ''}" data-i="${i}">${icon(c)} ${esc(c)}</button>`
    ).join('');
    strip.querySelectorAll('.cat-btn').forEach(b => b.onclick = () => {
        selCat = categories[b.dataset.i];
        renderCats(); renderProducts();
    });
}

function productCard(p) {
    const ci   = cart.find(i => i.id === p.id);
    const inC  = ci ? ci.quantity : 0;
    const full = p.stock <= 0 || inC >= p.stock;
    const stockCls = p.stock <= 0 ? 'zero' : (p.stock <= 5 ? 'low' : '');
    return `
    <div class="product-card ${inC ? 'in-cart' : ''}">
        ${inC ? `<span class="in-cart-badge">${inC} di keranjang</span>` : ''}
        <div class="emoji-wrap">${esc(p.image)}</div>
        <span class="cat-badge">${icon(p.category)} ${esc(p.category)}</span>
        <div class="name">${esc(p.name)}</div>
        <div class="price-row">
            <span class="price">${priceHTML(p.price)}</span>
            <span class="stock-pill ${stockCls}">Stok ${p.stock}</span>
        </div>
        <button class="add-btn" data-id="${p.id}" ${full ? 'disabled' : ''}>
            ${p.stock <= 0 ? 'Habis' : (full ? 'Stok Maks' : '+ Tambah')}
        </button>
    </div>`;
}

function renderProducts() {
    const q = $('searchInput').value.trim().toLowerCase();
    $('searchClear').classList.toggle('show', q.length > 0);

    let list = selCat === 'Semua' ? products : products.filter(p => p.category === selCat);
    list = list.filter(p => p.name.toLowerCase().includes(q));

    if (!list.length) {
        $('productsGrid').innerHTML = `<div class="empty-state"><div class="icon">🔎</div><p>Produk tidak ditemukan</p></div>`;
        return;
    }

    let html = '';
    if (selCat === 'Semua') {
        // kelompokkan per kategori dengan judul + emoji
        categories.slice(1).forEach(c => {
            const g = list.filter(p => p.category === c);
            if (!g.length) return;
            html += `<div class="group-head">
                        <span class="gh-icon">${icon(c)}</span>
                        <span class="gh-name">${esc(c)}</span>
                        <span class="gh-count">${g.length} produk</span>
                     </div>` + g.map(productCard).join('');
        });
    } else {
        html = list.map(productCard).join('');
    }
    $('productsGrid').innerHTML = html;
    $('productsGrid').querySelectorAll('.add-btn').forEach(b => b.onclick = () => addToCart(+b.dataset.id));
}

/* ── Keranjang ── */
function addToCart(id) {
    const prod = products.find(p => p.id === id);
    if (!prod) return;
    const ci = cart.find(i => i.id === id);
    if (ci) {
        if (ci.quantity < prod.stock) ci.quantity++;
        else toast(`Stok maksimal ${prod.stock}`, 'error');
    } else if (prod.stock > 0) {
        cart.push({id: prod.id, name: prod.name, price: prod.price, image: prod.image, quantity: 1});
    }
    renderCart(); renderProducts();
}

function updateQty(id, d) {
    const ci = cart.find(i => i.id === id), pr = products.find(p => p.id === id);
    if (!ci || !pr) return;
    const nq = ci.quantity + d;
    if (nq <= 0) return removeFromCart(id);
    if (nq > pr.stock) return toast(`Stok maksimal ${pr.stock}`, 'error');
    ci.quantity = nq;
    renderCart(); renderProducts();
}

function removeFromCart(id) { cart = cart.filter(i => i.id !== id); renderCart(); renderProducts(); }
function clearCart() { cart = []; renderCart(); renderProducts(); }

function renderCart() {
    totalAmt = cart.reduce((s, i) => s + i.price * i.quantity, 0);
    const items = cart.reduce((s, i) => s + i.quantity, 0);
    const has = cart.length > 0;

    setPrice('totalDisplay', totalAmt);
    setPrice('mbTotal', totalAmt);
    $('itemCount').textContent = items;
    $('checkoutBtn').disabled = !has;
    $('clearBtn').classList.toggle('hidden', !has);
    $('cartBadge').textContent = items;
    $('cartBadge').classList.toggle('hidden', !has);
    $('mbCount').textContent = items;
    $('mbCount').classList.toggle('hidden', !has);
    $('mobileBar').classList.toggle('empty', !has);

    const box = $('cartItems');
    if (!has) {
        box.innerHTML = `<div class="cart-empty"><div class="icon">🛒</div><p>Keranjang masih kosong.<br>Pilih produk untuk memulai.</p></div>`;
        return;
    }
    box.innerHTML = cart.map(i => {
        const pr = products.find(p => p.id === i.id);
        const max = pr ? pr.stock : i.quantity;
        return `
        <div class="cart-item">
            <div class="ci-emoji">${esc(i.image)}</div>
            <div class="ci-info">
                <div class="ci-name">${esc(i.name)}</div>
                <div class="ci-unit"><span class="price">${priceHTML(i.price)}</span> / item</div>
                <div class="qty">
                    <button data-act="dec" data-id="${i.id}" aria-label="Kurangi">−</button>
                    <span>${i.quantity}</span>
                    <button data-act="inc" data-id="${i.id}" aria-label="Tambah" ${i.quantity >= max ? 'disabled' : ''}>+</button>
                </div>
            </div>
            <div class="ci-right">
                <button class="ci-del" data-act="del" data-id="${i.id}" aria-label="Hapus">🗑</button>
                <span class="price">${priceHTML(i.price * i.quantity)}</span>
            </div>
        </div>`;
    }).join('');
}

$('cartItems').addEventListener('click', e => {
    const b = e.target.closest('button[data-act]');
    if (!b) return;
    const id = +b.dataset.id;
    if (b.dataset.act === 'inc') updateQty(id, 1);
    if (b.dataset.act === 'dec') updateQty(id, -1);
    if (b.dataset.act === 'del') removeFromCart(id);
});

function toggleCart(open) {
    $('cartPanel').classList.toggle('open', open);
    $('backdrop').classList.toggle('show', open);
}

/* ── Pembayaran ── */
function getPay() { return parseInt(String($('payInput').value).replace(/\D/g, '')) || 0; }
function setPay(n) { $('payInput').value = n ? num(n) : ''; calcChange(); }
function onPayInput() { const n = getPay(); $('payInput').value = n ? num(n) : ''; calcChange(); }

function quickAmounts(t) {
    const set = new Set();
    [5000, 10000, 50000, 100000].forEach(u => set.add(Math.ceil(t / u) * u));
    set.delete(t);
    return [...set].filter(v => v > t).sort((a, b) => a - b).slice(0, 4);
}

function openPayment() {
    if (!cart.length) return;
    toggleCart(false);
    setPrice('modalTotal', totalAmt);
    $('quickAmounts').innerHTML =
        `<button class="quick-btn exact" data-v="${totalAmt}">Uang Pas</button>` +
        quickAmounts(totalAmt).map(v => `<button class="quick-btn" data-v="${v}">Rp ${num(v)}</button>`).join('');
    $('quickAmounts').querySelectorAll('.quick-btn').forEach(b => b.onclick = () => setPay(+b.dataset.v));
    setPay(totalAmt);
    $('payModal').classList.add('open');
    setTimeout(() => { $('payInput').focus(); $('payInput').select(); }, 120);
}
function closePayment() { $('payModal').classList.remove('open'); }

function calcChange() {
    const chg = getPay() - totalAmt;
    const short = chg < 0;
    $('changeBox').classList.toggle('short', short);
    $('changeLabel').textContent = short ? 'Kurang' : 'Kembalian';
    setPrice('changeDisplay', Math.abs(chg));
    $('confirmBtn').disabled = short || busy;
}

async function processTransaction() {
    const pay = getPay();
    if (busy || pay < totalAmt) return;
    busy = true;
    $('confirmBtn').disabled = true;
    $('confirmBtn').textContent = 'Memproses...';
    try {
        const resp = await fetch('process_transaction.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': CSRF},
            body: JSON.stringify({cart: cart.map(i => ({id: i.id, quantity: i.quantity})), payment: pay})
        });
        const data = await resp.json();
        if (data.success) {
            // kurangi stok lokal
            cart.forEach(i => { const p = products.find(x => x.id === i.id); if (p) p.stock -= i.quantity; });
            $('sCode').textContent = data.transaction_code || '';
            setPrice('sTotal', data.total);
            setPrice('sPay', pay);
            setPrice('sChange', data.change);
            cart = [];
            renderCart(); renderProducts();
            closePayment();
            $('successModal').classList.add('open');
        } else {
            toast(data.message || 'Transaksi gagal', 'error');
        }
    } catch (err) {
        console.error(err);
        toast('Kesalahan koneksi ke server', 'error');
    } finally {
        busy = false;
        $('confirmBtn').textContent = 'Konfirmasi';
        calcChange();
    }
}

function closeSuccess() {
    $('successModal').classList.remove('open');
    location.reload(); // muat ulang data stok terbaru dari database
}

/* ── Event ── */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closePayment(); toggleCart(false); }
    if (e.key === 'Enter' && $('payModal').classList.contains('open') && !$('confirmBtn').disabled) processTransaction();
});
$('payModal').addEventListener('click', e => { if (e.target === $('payModal')) closePayment(); });
$('searchInput').addEventListener('input', renderProducts);
$('searchClear').addEventListener('click', () => { $('searchInput').value = ''; renderProducts(); $('searchInput').focus(); });

renderCats(); renderProducts(); renderCart();
</script>
</body>
</html>