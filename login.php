<?php
session_start();
include 'koneksi.php';

// Jika sudah login, redirect
if(isset($_SESSION['id'])){
    header("Location: index.php");
    exit;
}

$error = "";

// Ambil cookie
$remember_email = "";
if(isset($_COOKIE['remember_user'])){
    $remember_email = $_COOKIE['remember_user'];
}

if(isset($_POST['submit'])){
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Validasi
    if(empty($email) || empty($password)){
        $error = "Email dan password wajib diisi!";
    } else {

        // Query aman
        $stmt = mysqli_prepare($koneksi, "SELECT * FROM users WHERE email=?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if(mysqli_num_rows($result) > 0){
            $user = mysqli_fetch_assoc($result);

            // Verifikasi password
            if(password_verify($password, $user['password'])){

                // Session
                $_SESSION['id'] = $user['id'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['role'] = $user['role'];

                // Cookie
                if(isset($_POST['remember'])){
                    setcookie("remember_user", $email, time() + (86400 * 7), "/");
                } else {
                    setcookie("remember_user", "", time() - 3600, "/");
                }

                // Redirect berdasarkan role
                if($user['role'] == 'admin'){
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: user/dashboard.php");
                }
                exit;

            } else {
                $error = "Password salah!";
            }

        } else {
            $error = "Email tidak ditemukan!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Smart Futsal</title>

<script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen flex items-center justify-center bg-gray-900 text-white">

<div class="w-full max-w-md bg-white/10 backdrop-blur-lg p-8 rounded-2xl border border-white/20">

<h2 class="text-2xl font-semibold mb-6 text-center">Login</h2>

<!-- ERROR -->
<?php if($error != ""): ?>
<div class="bg-red-500/20 text-red-300 text-sm p-3 rounded-lg mb-4">
    <?= $error ?>
</div>
<?php endif; ?>

<form method="POST" class="flex flex-col gap-4">

<!-- Email -->
<input type="email" name="email" placeholder="Email"
value="<?= htmlspecialchars($remember_email) ?>"
class="p-3 rounded-lg bg-white/10 border border-white/20 focus:outline-none">

<!-- Password -->
<input type="password" name="password" placeholder="Password"
class="p-3 rounded-lg bg-white/10 border border-white/20 focus:outline-none">

<!-- Remember -->
<label class="text-sm flex items-center gap-2">
<input type="checkbox" name="remember"> Ingat saya
</label>

<!-- Button -->
<button name="submit"
class="bg-red-600 hover:bg-red-700 p-3 rounded-lg">
Login
</button>

</form>

<p class="text-sm mt-4 text-center">
Belum punya akun?
<a href="register.php" class="text-red-400">Daftar</a>
</p>

</div>

</body>
</html>