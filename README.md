# ⚽ Website Booking Lapangan Futsal

Aplikasi ini adalah sistem booking lapangan futsal berbasis web yang dibuat menggunakan **PHP dan MySQL**. Sistem ini memungkinkan pengguna untuk melakukan pemesanan lapangan secara online, melihat jadwal yang tersedia, serta menerima notifikasi booking.

---

## 🚀 Fitur Utama

### 👤 User / Pelanggan
- Registrasi dan login
- Melihat daftar lapangan
- Booking lapangan berdasarkan tanggal dan jam
- Melihat riwayat booking
- Notifikasi status booking
- Logout akun

### 🛠️ Admin (jika ada)
- Manajemen data lapangan
- Melihat semua booking
- Konfirmasi / batalkan booking
- Manajemen user

---

## 🧠 Fitur Sistem
- Validasi jadwal agar tidak terjadi double booking
- Sistem status booking (pending / approved / cancelled)
- Notifikasi otomatis setelah booking berhasil
- Session login user
- Proteksi halaman (harus login)

---

## 💻 Teknologi yang Digunakan
- PHP (Native)
- MySQL
- HTML, CSS
- Bootstrap (opsional)
- XAMPP (local server)

---

## ⚙️ Cara Instalasi

1. Clone repository ini
   https://github.com/riskyhelen05/goalkick-booking

2. Import database MySQL
- buka phpMyAdmin
- import file `.sql`

3. Jalankan XAMPP
- Start Apache & MySQL

4. Buka browser
   http://localhost/goalkick-booking

---

## 🔐 Validasi Sistem
- Jadwal yang sudah dibooking tidak bisa dipesan ulang
- User harus login sebelum booking
- Session otomatis mengamankan halaman

---

## 👨‍💻 Developer
- Nama Anggota Kelompok:
   1. Helen Risky Dwi Wahyuni (24082010054)
   2. Andrey Parinding        (24082010076)
   3. Muhammad Yahya Zahid    (24082010086)
- Project: FP Pemrograman Web

---

## 📌 Catatan
Project ini dibuat untuk pembelajaran dan pengembangan sistem booking sederhana berbasis web.
