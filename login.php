<?php
require __DIR__ . '/config.php';

// Jika sudah login, arahkan sesuai role
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') { header('Location: admin.php'); exit; }
    if ($_SESSION['role'] === 'kasir') { header('Location: kasir.php'); exit; }
    session_destroy();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Shop - Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border: 4px solid #667eea;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .logo-section { text-align: center; margin-bottom: 30px; }
        .logo { font-size: 80px; margin-bottom: 10px; }
        .logo-text {
            font-family: Impact, sans-serif;
            font-size: 36px;
            color: #667eea;
            letter-spacing: 3px;
            margin-bottom: 10px;
        }
        .subtitle { color: #666; font-size: 14px; }

        .tab-container {
            display: flex;
            margin-bottom: 25px;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab {
            flex: 1;
            padding: 15px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 16px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: -2px;
        }

        .tab.active { color: #667eea; border-bottom-color: #667eea; }
        .tab:hover  { color: #667eea; }

        .form-content { display: none; }
        .form-content.active { display: block; }

        .form-group { margin-bottom: 20px; }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            font-size: 14px;
            border-left: 4px solid #f44336;
        }

        .error-message.show { display: block; }

        .submit-btn {
            width: 100%;
            padding: 15px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .admin-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .admin-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(102,126,234,0.4);
        }

        .kasir-btn {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        .kasir-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(79,172,254,0.4);
        }

        .icon { font-size: 24px; }

        .info-box {
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
            border-left: 4px solid #4caf50;
            border-radius: 8px;
            padding: 14px 16px;
            margin-top: 20px;
            font-size: 13px;
            color: #1b5e20;
        }
        .info-box strong { display: block; margin-bottom: 6px; font-size: 14px; }
        .info-box table  { width: 100%; border-collapse: collapse; }
        .info-box td     { padding: 3px 0; }
        .info-box td:first-child { font-weight: bold; width: 85px; }

        @media (max-width: 600px) {
            .login-container { padding: 30px 20px; }
            .logo { font-size: 60px; }
            .logo-text { font-size: 28px; }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo-section">
        <div class="logo">🐕</div>
        <div class="logo-text">PET SHOP</div>
        <div class="subtitle">Sistem Manajemen Toko</div>
    </div>

    <div class="tab-container">
        <button class="tab active" id="tabAdmin" onclick="switchTab('admin')">👨‍💼 Admin</button>
        <button class="tab"        id="tabKasir" onclick="switchTab('kasir')">💰 Kasir</button>
    </div>

    <div id="errorMessage" class="error-message"></div>

    <!-- Admin Form -->
    <div id="adminForm" class="form-content active">
        <form action="login_process.php" method="POST">
            <input type="hidden" name="role" value="admin">
            <div class="form-group">
                <label>📧 Email</label>
                <input type="email" name="email" placeholder="admin@petshop.com" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>🔒 Password</label>
                <input type="password" name="password" placeholder="Masukkan password" required autocomplete="off">
            </div>
            <button type="submit" class="submit-btn admin-btn">
                <span class="icon">👨‍💼</span>
                <span>LOGIN ADMIN</span>
            </button>
        </form>
    </div>

    <!-- Kasir Form -->
    <div id="kasirForm" class="form-content">
        <form action="login_process.php" method="POST">
            <input type="hidden" name="role" value="kasir">
            <div class="form-group">
                <label>👤 Username</label>
                <input type="text" name="username" placeholder="kasir" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>🔒 Password</label>
                <input type="password" name="password" placeholder="Masukkan password" required autocomplete="off">
            </div>
            <button type="submit" class="submit-btn kasir-btn">
                <span class="icon">💰</span>
                <span>LOGIN KASIR</span>
            </button>
        </form>
    </div>
</div>

<script>
    function switchTab(tab) {
        document.getElementById('tabAdmin').classList.toggle('active', tab === 'admin');
        document.getElementById('tabKasir').classList.toggle('active', tab === 'kasir');
        document.getElementById('adminForm').classList.toggle('active', tab === 'admin');
        document.getElementById('kasirForm').classList.toggle('active', tab === 'kasir');
        document.getElementById('errorMessage').classList.remove('show');
    }

    function showError(msg) {
        const el = document.getElementById('errorMessage');
        el.textContent = '⚠️ ' + msg;
        el.classList.add('show');
    }

    const p = new URLSearchParams(window.location.search);
    if (p.get('error') === 'invalid') {
        showError('Email/Username atau password salah!');
        if (p.get('role') === 'kasir') switchTab('kasir');
    }
</script>

</body>
</html>