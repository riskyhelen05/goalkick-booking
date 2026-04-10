<?php
session_start();

$error = '';
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama'] ?? '');
    $no_wa    = trim($_POST['no_wa'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';
    $role     = $_POST['role'] ?? 'customer';
    $setuju   = isset($_POST['setuju']);

    if (empty($nama))        $errors[] = 'Nama lengkap wajib diisi.';
    if (empty($no_wa))       $errors[] = 'Nomor WhatsApp wajib diisi.';
    if (empty($email))       $errors[] = 'Email wajib diisi.';
    if (strlen($password) < 8) $errors[] = 'Password minimal 8 karakter.';
    if ($password !== $konfirmasi) $errors[] = 'Konfirmasi password tidak cocok.';
    if (!$setuju)            $errors[] = 'Kamu harus menyetujui syarat & ketentuan.';

    if (empty($errors)) {
        // TODO: Simpan ke database
        // $hashed = password_hash($password, PASSWORD_BCRYPT);
        // INSERT INTO users (nama, no_whatsapp, email, password, role) VALUES (...)
        $success = 'Akun berhasil dibuat! Silakan masuk.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar — Smart Futsal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-display { font-family: 'Bebas Neue', sans-serif; }

        .bg-futsal {
            background-image:
                linear-gradient(to right, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.15) 50%, rgba(120,0,0,0.5) 100%),
                url('https://images.unsplash.com/photo-1529900748604-07564a03e7a6?w=1600&q=80&fit=crop');
            background-size: cover;
            background-position: center top;
        }

        .glass-form {
            background: rgba(255, 255, 255, 0.07);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.13);
        }

        .input-field {
            background: rgba(255,255,255,0.09);
            border: 1px solid rgba(255,255,255,0.18);
            color: #fff;
            transition: all 0.25s ease;
        }
        .input-field::placeholder { color: rgba(255,255,255,0.38); }
        .input-field:focus {
            outline: none;
            border-color: #ef4444;
            background: rgba(255,255,255,0.13);
            box-shadow: 0 0 0 3px rgba(239,68,68,0.2);
        }

        .btn-daftar {
            background: #dc2626;
            transition: all 0.2s ease;
            letter-spacing: 0.05em;
        }
        .btn-daftar:hover {
            background: #b91c1c;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(220,38,38,0.4);
        }
        .btn-daftar:active { transform: scale(0.98); }

        .role-card {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.15);
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .role-card:hover { background: rgba(255,255,255,0.12); }
        .role-card.selected {
            background: rgba(220,38,38,0.2);
            border-color: #ef4444;
            box-shadow: 0 0 0 2px rgba(239,68,68,0.2);
        }

        .navbar-blur {
            background: rgba(0,0,0,0.35);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeup { animation: fadeUp 0.55s ease both; }
        .delay-1 { animation-delay: 0.08s; }
        .delay-2 { animation-delay: 0.16s; }
        .delay-3 { animation-delay: 0.24s; }
        .delay-4 { animation-delay: 0.32s; }

        .hero-badge {
            background: rgba(220,38,38,0.2);
            border: 1px solid rgba(220,38,38,0.45);
            color: #fca5a5;
        }

        input[type="checkbox"] { accent-color: #dc2626; }
        .show-pass { cursor: pointer; color: rgba(255,255,255,0.4); transition: color 0.2s; }
        .show-pass:hover { color: #ef4444; }

        .strength-bar { height: 3px; border-radius: 2px; transition: all 0.3s; }

        /* Success overlay */
        .success-card {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.15);
        }
    </style>
</head>
<body class="min-h-screen bg-futsal flex flex-col">

    <!-- Navbar -->
    <nav class="navbar-blur fixed top-0 left-0 right-0 z-50 flex items-center justify-between px-8 py-4">
        <a href="login.php" class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-red-600 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/>
                    <path stroke-linecap="round" stroke-width="1.5" d="M12 6v12M6 12h12M8.5 8.5l7 7M15.5 8.5l-7 7"/>
                </svg>
            </div>
            <span class="text-white font-semibold text-[15px] tracking-wide">Smart<span class="text-red-400">Futsal</span></span>
        </a>
        <div class="hidden md:flex items-center gap-8 text-sm text-white/70">
            <a href="#" class="hover:text-white transition-colors">Beranda</a>
            <a href="#" class="hover:text-white transition-colors">Lapangan</a>
            <a href="#" class="hover:text-white transition-colors">Harga</a>
            <a href="#" class="hover:text-white transition-colors">Kontak</a>
        </div>
        <a href="login.php" class="text-sm text-white/70 border border-white/20 rounded-full px-4 py-1.5 hover:bg-white/10 hover:text-white transition-all">
            Masuk
        </a>
    </nav>

    <!-- Main Content -->
    <div class="flex-1 flex items-center justify-center min-h-screen px-6 pt-24 pb-10">
        <div class="w-full max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

            <!-- Hero Left -->
            <div class="hidden lg:flex flex-col gap-6 animate-fadeup">
                <span class="hero-badge self-start text-xs font-medium px-3 py-1.5 rounded-full uppercase tracking-widest">
                    🏟 Gabung Sekarang
                </span>
                <h1 class="font-display text-white leading-none">
                    <span class="text-7xl block">Mulai</span>
                    <span class="text-7xl block text-red-400">Booking</span>
                    <span class="text-7xl block">Hari Ini.</span>
                </h1>
                <p class="text-white/50 text-[15px] leading-relaxed max-w-sm">
                    Buat akun gratis dan nikmati kemudahan booking lapangan futsal secara digital. Jadwal real-time, bayar digital, tanpa antri.
                </p>

                <!-- Stats -->
                <div class="flex gap-6 mt-2">
                    <div>
                        <div class="text-white text-2xl font-semibold">4+</div>
                        <div class="text-white/45 text-xs">Lapangan tersedia</div>
                    </div>
                    <div class="w-px bg-white/15"></div>
                    <div>
                        <div class="text-white text-2xl font-semibold">100%</div>
                        <div class="text-white/45 text-xs">Konfirmasi otomatis</div>
                    </div>
                    <div class="w-px bg-white/15"></div>
                    <div>
                        <div class="text-white text-2xl font-semibold">3</div>
                        <div class="text-white/45 text-xs">Metode bayar digital</div>
                    </div>
                </div>
            </div>

            <!-- Form Right -->
            <div class="w-full max-w-md mx-auto lg:mx-0 lg:ml-auto">

                <?php if ($success): ?>
                <!-- Success State -->
                <div class="success-card rounded-2xl p-10 text-center animate-fadeup">
                    <div class="w-16 h-16 rounded-full bg-red-600/30 border border-red-500/40 flex items-center justify-center mx-auto mb-5">
                        <svg class="w-8 h-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-white text-xl font-semibold mb-2">Akun Berhasil Dibuat!</h3>
                    <p class="text-white/50 text-sm mb-6">Selamat bergabung di Smart Futsal. Silakan masuk dengan akun barumu.</p>
                    <a href="login.php" class="btn-daftar inline-block text-white text-sm font-semibold rounded-xl px-8 py-3">
                        Masuk Sekarang
                    </a>
                </div>

                <?php else: ?>
                <!-- Form Card -->
                <div class="glass-form rounded-2xl p-8 animate-fadeup delay-1">

                    <div class="mb-6">
                        <h2 class="text-white text-3xl font-semibold mb-1">Daftar</h2>
                        <p class="text-white/50 text-sm">
                            Sudah punya akun?
                            <a href="login.php" class="text-red-400 hover:text-red-300 font-medium transition-colors ml-1">Masuk di sini</a>
                        </p>
                    </div>

                    <!-- Errors -->
                    <?php if (!empty($errors)): ?>
                    <div class="mb-5 bg-red-900/35 border border-red-500/40 rounded-xl px-4 py-3">
                        <?php foreach ($errors as $e): ?>
                        <div class="flex items-center gap-2 text-red-300 text-xs mb-1 last:mb-0">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <?= htmlspecialchars($e) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="flex flex-col gap-4">

                        <!-- Nama + WA (2 col) -->
                        <div class="grid grid-cols-2 gap-3 animate-fadeup delay-2">
                            <div>
                                <label class="block text-white/55 text-xs font-medium mb-1.5 uppercase tracking-wider">Nama</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white/30">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </span>
                                    <input type="text" name="nama" placeholder="Nama lengkap" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" class="input-field w-full rounded-xl pl-9 pr-3 py-3 text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-white/55 text-xs font-medium mb-1.5 uppercase tracking-wider">WhatsApp</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white/30">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                        </svg>
                                    </span>
                                    <input type="tel" name="no_wa" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($_POST['no_wa'] ?? '') ?>" class="input-field w-full rounded-xl pl-9 pr-3 py-3 text-sm">
                                </div>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="animate-fadeup delay-3">
                            <label class="block text-white/55 text-xs font-medium mb-1.5 uppercase tracking-wider">Email</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-white/30">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </span>
                                <input type="email" name="email" placeholder="contoh@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" class="input-field w-full rounded-xl pl-10 pr-4 py-3 text-sm">
                            </div>
                        </div>

                        <!-- Password + Konfirmasi (2 col) -->
                        <div class="grid grid-cols-2 gap-3 animate-fadeup delay-3">
                            <div>
                                <label class="block text-white/55 text-xs font-medium mb-1.5 uppercase tracking-wider">Password</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white/30">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                    </span>
                                    <input type="password" name="password" id="reg-pass" placeholder="Min. 8 karakter" class="input-field w-full rounded-xl pl-9 pr-9 py-3 text-sm" oninput="checkStrength(this.value)">
                                    <button type="button" class="show-pass absolute right-3 top-1/2 -translate-y-1/2" onclick="togglePass('reg-pass', this)">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </div>
                                <!-- Strength indicator -->
                                <div class="flex gap-1 mt-1.5">
                                    <div class="strength-bar flex-1 bg-white/10" id="s1"></div>
                                    <div class="strength-bar flex-1 bg-white/10" id="s2"></div>
                                    <div class="strength-bar flex-1 bg-white/10" id="s3"></div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-white/55 text-xs font-medium mb-1.5 uppercase tracking-wider">Konfirmasi</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-white/30">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                    </span>
                                    <input type="password" name="konfirmasi" id="reg-pass2" placeholder="Ulangi password" class="input-field w-full rounded-xl pl-9 pr-9 py-3 text-sm">
                                    <button type="button" class="show-pass absolute right-3 top-1/2 -translate-y-1/2" onclick="togglePass('reg-pass2', this)">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Checkboxes -->
                        <div class="flex flex-col gap-2.5 animate-fadeup delay-4">
                            <label class="flex items-center gap-2.5 cursor-pointer group">
                                <input type="checkbox" name="setuju" <?= isset($_POST['setuju']) ? 'checked' : '' ?>>
                                <span class="text-white/50 text-xs leading-relaxed">
                                    Saya menyetujui
                                    <a href="#" class="text-red-400 hover:text-red-300">Syarat & Ketentuan</a>
                                    dan
                                    <a href="#" class="text-red-400 hover:text-red-300">Kebijakan Privasi</a>
                                </span>
                            </label>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="btn-daftar w-full text-white font-semibold rounded-xl py-3 text-sm mt-1 animate-fadeup delay-4">
                            Buat Akun Gratis
                        </button>

                    </form>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script>
    function selectRole(role) {
        document.getElementById('card-customer').classList.toggle('selected', role === 'customer');
        document.getElementById('card-admin').classList.toggle('selected', role === 'admin');
        document.querySelector('input[value="customer"]').checked = role === 'customer';
        document.querySelector('input[value="admin"]').checked = role === 'admin';
    }
    function togglePass(id, btn) {
        const inp = document.getElementById(id);
        const isPass = inp.type === 'password';
        inp.type = isPass ? 'text' : 'password';
        btn.style.color = isPass ? '#ef4444' : '';
    }
    function checkStrength(val) {
        const bars = [document.getElementById('s1'), document.getElementById('s2'), document.getElementById('s3')];
        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        const colors = ['#ef4444', '#f97316', '#22c55e'];
        bars.forEach((b, i) => {
            b.style.background = i < score ? colors[score - 1] : 'rgba(255,255,255,0.1)';
        });
    }
    </script>

</body>
</html>