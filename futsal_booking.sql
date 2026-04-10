-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Waktu pembuatan: 10 Apr 2026 pada 07.57
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `futsal_booking`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `booking`
--

CREATE TABLE `booking` (
  `id` int(11) NOT NULL,
  `kode_booking` varchar(50) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `lapangan_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `durasi_jam` int(11) NOT NULL,
  `harga_saat_booking` int(11) NOT NULL,
  `total_harga` int(11) NOT NULL,
  `status` enum('pending','dikonfirmasi','selesai','dibatalkan') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Trigger `booking`
--
DELIMITER $$
CREATE TRIGGER `before_insert_booking` BEFORE INSERT ON `booking` FOR EACH ROW BEGIN
    DECLARE jumlah INT;

    -- Cek double booking
    SELECT COUNT(*) INTO jumlah
    FROM booking
    WHERE lapangan_id = NEW.lapangan_id
    AND tanggal = NEW.tanggal
    AND status IN ('pending','dikonfirmasi')
    AND (NEW.jam_mulai < jam_selesai AND NEW.jam_selesai > jam_mulai);

    IF jumlah > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Jadwal sudah dibooking';
    END IF;

    -- Cek jadwal blokir
    SELECT COUNT(*) INTO jumlah
    FROM jadwal_blokir
    WHERE lapangan_id = NEW.lapangan_id
    AND tanggal = NEW.tanggal
    AND (NEW.jam_mulai < jam_selesai AND NEW.jam_selesai > jam_mulai);

    IF jumlah > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Lapangan sedang diblokir';
    END IF;

END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_update_booking` BEFORE UPDATE ON `booking` FOR EACH ROW BEGIN
    DECLARE jumlah INT;

    -- Cek double booking (kecuali dirinya sendiri)
    SELECT COUNT(*) INTO jumlah
    FROM booking
    WHERE lapangan_id = NEW.lapangan_id
    AND tanggal = NEW.tanggal
    AND id != OLD.id
    AND status IN ('pending','dikonfirmasi')
    AND (NEW.jam_mulai < jam_selesai AND NEW.jam_selesai > jam_mulai);

    IF jumlah > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Jadwal bentrok saat update';
    END IF;

    -- Cek jadwal blokir
    SELECT COUNT(*) INTO jumlah
    FROM jadwal_blokir
    WHERE lapangan_id = NEW.lapangan_id
    AND tanggal = NEW.tanggal
    AND (NEW.jam_mulai < jam_selesai AND NEW.jam_selesai > jam_mulai);

    IF jumlah > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Lapangan sedang diblokir';
    END IF;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_blokir`
--

CREATE TABLE `jadwal_blokir` (
  `id` int(11) NOT NULL,
  `lapangan_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `lapangan`
--

CREATE TABLE `lapangan` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jenis` enum('sintetis','vinyl','rumput') NOT NULL,
  `harga_per_jam` int(11) NOT NULL,
  `fasilitas` text DEFAULT NULL,
  `status` enum('tersedia','maintenance','tidak_tersedia') DEFAULT 'tersedia'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `judul` varchar(100) DEFAULT NULL,
  `pesan` text DEFAULT NULL,
  `tipe` enum('info','warning','success') DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `metode` enum('transfer','cash','e-wallet') NOT NULL,
  `jumlah` int(11) NOT NULL,
  `status` enum('pending','berhasil','gagal') DEFAULT 'pending',
  `bukti_url` varchar(255) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `no_whatsapp` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','pelanggan') DEFAULT 'pelanggan',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_booking` (`kode_booking`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_booking_jadwal` (`lapangan_id`,`tanggal`,`jam_mulai`,`jam_selesai`);

--
-- Indeks untuk tabel `jadwal_blokir`
--
ALTER TABLE `jadwal_blokir`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lapangan_id` (`lapangan_id`);

--
-- Indeks untuk tabel `lapangan`
--
ALTER TABLE `lapangan`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `booking`
--
ALTER TABLE `booking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `jadwal_blokir`
--
ALTER TABLE `jadwal_blokir`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `lapangan`
--
ALTER TABLE `lapangan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `booking`
--
ALTER TABLE `booking`
  ADD CONSTRAINT `booking_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_ibfk_2` FOREIGN KEY (`lapangan_id`) REFERENCES `lapangan` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal_blokir`
--
ALTER TABLE `jadwal_blokir`
  ADD CONSTRAINT `jadwal_blokir_ibfk_1` FOREIGN KEY (`lapangan_id`) REFERENCES `lapangan` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `notifikasi_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifikasi_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `booking` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
