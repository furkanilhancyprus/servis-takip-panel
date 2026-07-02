<?php
session_start();
define('ROOT', __DIR__);
require_once ROOT . '/config/database.php';
require_once ROOT . '/config/remember.php';

$db = Database::getInstance();
remember_try_restore($db);

if (isset($_SESSION['firma_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap — Servis Takip Panel</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .bg-pattern {
            background-color: #f0f9ff;
            background-image: radial-gradient(#bae6fd 1px, transparent 1px);
            background-size: 24px 24px;
        }
    </style>
</head>
<body class="bg-pattern min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">

        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="landing.php" class="inline-flex items-center gap-3">
                <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-clipboard-check text-white text-xl"></i>
                </div>
                <div class="text-left">
                    <div class="text-xl font-bold text-slate-800">Servis Takip Panel</div>
                    <div class="text-xs text-slate-500">Su Arıtma Servis Yönetimi</div>
                </div>
            </a>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-8">

            <h1 class="text-2xl font-bold text-slate-800 mb-1">Hoş geldiniz</h1>
            <p class="text-slate-500 text-sm mb-7">Hesabınıza giriş yapın</p>

            <!-- Hata mesajı -->
            <div id="errorBox" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i>
                <span id="errorMsg"></span>
            </div>

            <form id="girisForm" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">E-posta Adresi</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
                        <input type="email" name="email" id="email" required
                            placeholder="ornek@firma.com"
                            class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Şifre</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3.5 top-3.5 text-slate-400 text-sm"></i>
                        <input type="password" name="sifre" id="sifre" required
                            placeholder="••••••••"
                            class="w-full pl-10 pr-12 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition">
                        <button type="button" onclick="toggleSifre('sifre', 'eyeIcon1')"
                            class="absolute right-3.5 top-3 text-slate-400 hover:text-slate-600 transition p-0.5">
                            <i id="eyeIcon1" class="fas fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <label class="flex items-center justify-between gap-3 cursor-pointer select-none">
                    <span class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" id="beniHatirla" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" checked>
                        Beni hatırla
                    </span>
                    <span class="text-xs text-slate-400">30 gün açık kalsın</span>
                </label>

                <button type="submit" id="submitBtn"
                    class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition flex items-center justify-center gap-2 text-sm">
                    <i class="fas fa-right-to-bracket"></i>
                    Giriş Yap
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-slate-100 text-center">
                <span class="text-slate-500 text-sm">Hesabınız yok mu? </span>
                <a href="kayit.php" class="text-blue-600 font-semibold text-sm hover:underline">Ücretsiz Kaydolun</a>
            </div>
        </div>

        <div class="text-center mt-6">
            <a href="landing.php" class="text-slate-400 text-sm hover:text-slate-600 transition">
                <i class="fas fa-arrow-left mr-1"></i>Ana sayfaya dön
            </a>
        </div>
    </div>

    <script>
    function toggleSifre(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash text-sm';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye text-sm';
        }
    }

    document.getElementById('girisForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn     = document.getElementById('submitBtn');
        const errorBox = document.getElementById('errorBox');
        const errorMsg = document.getElementById('errorMsg');

        btn.disabled = true;
        btn.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span> Giriş yapılıyor...';
        errorBox.classList.add('hidden');

        try {
            const res = await fetch('api/auth.php?action=giris', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: document.getElementById('email').value,
                    sifre: document.getElementById('sifre').value,
                    beni_hatirla: document.getElementById('beniHatirla').checked,
                })
            });
            const data = await res.json();
            if (data.success) {
                window.location.href = 'index.php';
            } else {
                errorMsg.textContent = data.message;
                errorBox.classList.remove('hidden');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-right-to-bracket"></i> Giriş Yap';
            }
        } catch(err) {
            errorMsg.textContent = 'Bağlantı hatası. Lütfen tekrar deneyin.';
            errorBox.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-right-to-bracket"></i> Giriş Yap';
        }
    });
    </script>
</body>
</html>
