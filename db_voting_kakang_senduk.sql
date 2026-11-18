-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 18, 2026 at 02:59 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_voting_kakang_senduk`
--

-- --------------------------------------------------------

--
-- Table structure for table `kontestan`
--

CREATE TABLE `kontestan` (
  `id` int(11) NOT NULL,
  `nomor_peserta` varchar(10) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kategori` enum('Kakang','Senduk') NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `asal_instansi` varchar(150) DEFAULT NULL,
  `visi` text DEFAULT NULL,
  `misi` text DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kontestan`
--

INSERT INTO `kontestan` (`id`, `nomor_peserta`, `nama`, `kategori`, `foto`, `asal_instansi`, `visi`, `misi`, `deskripsi`, `created_at`) VALUES
(1, '01', 'Fahriya', 'Senduk', '53a27c1d82784c510d5c5b513ce85f25.jpg', 'SMA PGRI 2 PONOROGO', 'Menjadi Senduk yang berkarakter, berwawasan luas, berbudaya, dan mampu menjadi representasi pariwisata daerah serta menginspirasi generasi muda.', 'Menjunjung tinggi sikap santun, percaya diri, disiplin, dan bertanggung jawab.\r\n\r\nMengembangkan wawasan serta kemampuan dalam bidang pariwisata, budaya, dan komunikasi.\r\n\r\nMelestarikan dan memperkenalkan budaya, destinasi wisata, serta potensi daerah kepada masyarakat luas.\r\n\r\nMendorong generasi muda untuk turut berperan aktif dalam memajukan pariwisata daerah.\r\n\r\nMenjadi teladan yang mampu memberikan kontribusi positif bagi masyarakat dan lingkungan.\r\n\r\nMembangun citra pariwisata daerah yang ramah, kreatif, menarik, dan berkelanjutan.', 'Saya adalah pribadi yang percaya diri, komunikatif, ramah, dan memiliki ketertarikan terhadap budaya serta pariwisata daerah. Melalui ajang Senduk, saya ingin mengembangkan potensi diri, memperluas wawasan, dan berkontribusi dalam memperkenalkan keindahan serta kekayaan budaya daerah kepada masyarakat luas.', '2026-11-07 13:41:10'),
(2, '02', 'Harjuno', 'Kakang', 'c6118bdb4d5b9650da04e456dcd499f4.jpg', 'SMA Adicara', 'Menjadikan Kakang bukan hanya sebagai gelar, tetapi sebagai teladan yang berintegritas, berprestasi, berbudaya, dan mampu menginspirasi serta memberi dampak positif bagi lingkungan.', 'Menjunjung tinggi nilai integritas, sopan santun, dan tanggung jawab dalam setiap tindakan.\r\n\r\nMengembangkan potensi diri melalui peningkatan wawasan, keterampilan, kreativitas, dan prestasi.\r\n\r\nMelestarikan serta memperkenalkan budaya dan potensi daerah dengan cara yang positif dan membanggakan.\r\n\r\nMenjadi teladan bagi generasi muda dalam sikap, perilaku, dan semangat berkontribusi.\r\n\r\nMembangun kepedulian sosial dan lingkungan melalui kegiatan yang bermanfaat bagi masyarakat.\r\n\r\nMenciptakan hubungan yang harmonis dan kolaboratif dengan sesama kontestan, masyarakat, serta berbagai pihak.', 'Kakang merupakan sosok pemuda yang berkarakter, berwawasan luas, percaya diri, dan memiliki kepedulian terhadap budaya serta lingkungan sekitar. Dengan menjunjung tinggi nilai kesopanan, integritas, dan tanggung jawab, Kakang berkomitmen untuk mengembangkan potensi diri, meraih prestasi, serta menjadi teladan dan inspirasi bagi generasi muda.', '2026-11-07 13:46:27'),
(6, '03', 'Kenang', 'Kakang', '4a693eaac7b6fd03127d5b718c643a6b.jpg', 'SMA Kenari 2', 'Menjadikan Kakang bukan hanya sebagai gelar, tetapi sebagai teladan yang berintegritas, berprestasi, berbudaya, dan mampu menginspirasi serta memberi dampak positif bagi lingkungan.', 'Menanamkan sikap santun, percaya diri, disiplin, dan bertanggung jawab.\r\n\r\nMengembangkan potensi diri dalam bidang akademik, nonakademik, seni, budaya, dan kepemimpinan.\r\n\r\nMemperkenalkan serta melestarikan budaya dan potensi daerah kepada masyarakat luas.\r\n\r\nMendorong generasi muda untuk aktif, kreatif, berprestasi, dan peduli terhadap sesama.\r\n\r\nBerkontribusi dalam kegiatan sosial dan lingkungan secara nyata dan berkelanjutan.', 'Saya adalah pribadi yang percaya diri, disiplin, komunikatif, dan memiliki semangat belajar yang tinggi. Saya tertarik pada bidang budaya, kepemudaan, dan kegiatan sosial. Melalui ajang Kakang, saya ingin mengembangkan potensi diri sekaligus berkontribusi dalam memperkenalkan budaya dan potensi daerah.', '2026-11-18 13:23:42'),
(7, '04', 'Aysar', 'Kakang', '8aa09b087d127d217e7fed355b47bcfb.jpg', 'SMK Pembangunan', 'Menjadikan Kakang bukan hanya sebagai gelar, tetapi sebagai teladan yang berintegritas, berprestasi, berbudaya, dan mampu menginspirasi serta memberi dampak positif bagi lingkungan.', 'Menanamkan sikap santun, percaya diri, disiplin, dan bertanggung jawab.\r\n\r\nMengembangkan potensi diri dalam bidang akademik, nonakademik, seni, budaya, dan kepemimpinan.\r\n\r\nMemperkenalkan serta melestarikan budaya dan potensi daerah kepada masyarakat luas.\r\n\r\nMendorong generasi muda untuk aktif, kreatif, berprestasi, dan peduli terhadap sesama.\r\n\r\nBerkontribusi dalam kegiatan sosial dan lingkungan secara nyata dan berkelanjutan.', 'Saya adalah pribadi yang percaya diri, disiplin, komunikatif, dan memiliki semangat belajar yang tinggi. Saya tertarik pada bidang budaya, kepemudaan, dan kegiatan sosial. Melalui ajang Kakang, saya ingin mengembangkan potensi diri sekaligus berkontribusi dalam memperkenalkan budaya dan potensi daerah.', '2026-11-18 13:24:50'),
(8, '05', 'Nadia', 'Senduk', '2767506bd462584388004d734dc35510.jpg', 'SMA Adicara', 'Menjadi Senduk yang berkarakter, berbudaya, berwawasan, dan mampu menjadi duta pariwisata yang inspiratif.', 'Mengembangkan potensi diri, melestarikan budaya, memperkenalkan destinasi wisata, serta mengajak generasi muda untuk berperan aktif dalam memajukan pariwisata daerah.', 'Senduk adalah sosok perempuan yang ramah, percaya diri, berwawasan luas, dan peduli terhadap budaya serta pariwisata. Ia hadir bukan hanya sebagai simbol keindahan, tetapi juga sebagai representasi karakter, keramahan, dan potensi daerah.', '2026-11-18 13:25:45');

-- --------------------------------------------------------

--
-- Table structure for table `pengaturan_vote`
--

CREATE TABLE `pengaturan_vote` (
  `id` int(11) NOT NULL,
  `harga_per_vote` decimal(12,2) NOT NULL DEFAULT 3500.00,
  `qris_image` varchar(255) DEFAULT NULL,
  `nama_penerima` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nominal_populer` varchar(255) NOT NULL DEFAULT '10,50,100,500'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengaturan_vote`
--

INSERT INTO `pengaturan_vote` (`id`, `harga_per_vote`, `qris_image`, `nama_penerima`, `updated_at`, `nominal_populer`) VALUES
(1, 3500.00, 'uploads/qris/qris_55b0f771b7d0ea99b49ccb74.png', NULL, '2026-11-07 16:33:05', '10,50,100,500');

-- --------------------------------------------------------

--
-- Table structure for table `periode_voting`
--

CREATE TABLE `periode_voting` (
  `id` int(11) NOT NULL,
  `nama_periode` varchar(100) NOT NULL,
  `tanggal_mulai` datetime NOT NULL,
  `tanggal_selesai` datetime NOT NULL,
  `status` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Nonaktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `periode_voting`
--

INSERT INTO `periode_voting` (`id`, `nama_periode`, `tanggal_mulai`, `tanggal_selesai`, `status`) VALUES
(1, 'periode gelombang 1', '2026-11-07 22:02:00', '2026-11-12 22:02:00', 'Nonaktif'),
(2, 'periode gelombang 2', '2026-11-18 19:42:00', '2026-11-20 19:42:00', 'Aktif');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan_vote`
--

CREATE TABLE `pesanan_vote` (
  `id` int(11) NOT NULL,
  `kode_pesanan` varchar(40) NOT NULL,
  `user_id` int(11) NOT NULL,
  `kontestan_id` int(11) NOT NULL,
  `kategori` enum('Kakang','Senduk') NOT NULL,
  `jumlah_vote` int(11) NOT NULL,
  `harga_per_vote` decimal(12,2) NOT NULL,
  `total_bayar` decimal(12,2) NOT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `status` enum('Menunggu Pembayaran','Menunggu Verifikasi','Disetujui','Ditolak','Dibatalkan') NOT NULL DEFAULT 'Menunggu Pembayaran',
  `catatan_admin` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pesanan_vote`
--

INSERT INTO `pesanan_vote` (`id`, `kode_pesanan`, `user_id`, `kontestan_id`, `kategori`, `jumlah_vote`, `harga_per_vote`, `total_bayar`, `bukti_pembayaran`, `status`, `catatan_admin`, `created_at`, `verified_at`) VALUES
(2, 'KS-20261107225106-B7FBF8', 2, 1, 'Senduk', 13, 3500.00, 45500.00, 'uploads/bukti-pembayaran/bukti-2-4404245f4fe3fa49.png', 'Disetujui', NULL, '2026-11-07 15:51:06', '2026-11-07 22:56:53'),
(4, 'KS-20261107225450-C04AD2', 2, 2, 'Kakang', 100, 3500.00, 350000.00, 'uploads/bukti-pembayaran/bukti-4-62762a55b95811ba.png', 'Disetujui', NULL, '2026-11-07 17:26:00', '2026-11-08 00:30:22'),
(7, 'KS-20261118200310-4A5309', 4, 2, 'Kakang', 1, 3500.00, 3500.00, 'uploads/bukti-pembayaran/bukti-7-0fef364cd95d2036.png', 'Disetujui', NULL, '2026-11-18 13:03:10', '2026-11-18 20:05:02'),
(8, 'KS-20261118204307-2EDAB2', 4, 1, 'Senduk', 1, 3500.00, 3500.00, NULL, 'Dibatalkan', NULL, '2026-11-18 13:54:24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','pemilih') NOT NULL DEFAULT 'pemilih',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `no_hp`, `password`, `role`, `created_at`) VALUES
(1, 'Administrator', 'admin@voting.com', '081234567890', '$2y$10$31NkIwLgZ0zkRga060hUjOvR6KdPvPQNCtGhrYfLQTbnqS1BR1QQu', 'admin', '2026-11-07 13:07:25'),
(2, 'oca', 'oca@gmail.com', '087635465', '$2y$10$otq6ROjqliUozI0rw5d99eZfr7cneBU0nP8P.XRHFT6MbaVODT9Xi', 'pemilih', '2026-11-07 14:55:20'),
(3, 'alyn', 'all@gmail.com', '037487263', '$2y$10$r.FOxjZ73BLWpKyaO0X7Q.dnlbC0Gmjtdth.tSdOlw4Tsmsa4vH1K', 'admin', '2026-11-07 16:30:12'),
(4, 'budi', 'budi@gmail.com', '0867236547', '$2y$10$i3RZ6JDMF.qL2/OuXvzCju/iKapyXljMTae4yDUgPJ4ot5bmW3MGa', 'pemilih', '2026-11-18 12:35:12');

-- --------------------------------------------------------

--
-- Table structure for table `voting`
--

CREATE TABLE `voting` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `kontestan_id` int(11) NOT NULL,
  `kategori` enum('Kakang','Senduk') NOT NULL,
  `jumlah_vote` int(11) NOT NULL DEFAULT 1,
  `pesanan_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `voting`
--

INSERT INTO `voting` (`id`, `user_id`, `kontestan_id`, `kategori`, `jumlah_vote`, `pesanan_id`, `created_at`) VALUES
(1, 2, 1, 'Senduk', 13, 2, '2026-11-07 22:56:53'),
(2, 2, 2, 'Kakang', 100, 4, '2026-11-08 00:30:22'),
(3, 4, 2, 'Kakang', 1, 7, '2026-11-18 20:05:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `kontestan`
--
ALTER TABLE `kontestan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_peserta` (`nomor_peserta`);

--
-- Indexes for table `pengaturan_vote`
--
ALTER TABLE `pengaturan_vote`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `periode_voting`
--
ALTER TABLE `periode_voting`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pesanan_vote`
--
ALTER TABLE `pesanan_vote`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_pesanan` (`kode_pesanan`),
  ADD KEY `fk_pesanan_user` (`user_id`),
  ADD KEY `fk_pesanan_kontestan` (`kontestan_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `voting`
--
ALTER TABLE `voting`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_voting_user_kategori` (`user_id`,`kategori`),
  ADD KEY `fk_voting_kontestan` (`kontestan_id`),
  ADD KEY `fk_voting_pesanan` (`pesanan_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `kontestan`
--
ALTER TABLE `kontestan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `pengaturan_vote`
--
ALTER TABLE `pengaturan_vote`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `periode_voting`
--
ALTER TABLE `periode_voting`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pesanan_vote`
--
ALTER TABLE `pesanan_vote`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `voting`
--
ALTER TABLE `voting`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `pesanan_vote`
--
ALTER TABLE `pesanan_vote`
  ADD CONSTRAINT `fk_pesanan_kontestan` FOREIGN KEY (`kontestan_id`) REFERENCES `kontestan` (`id`),
  ADD CONSTRAINT `fk_pesanan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `voting`
--
ALTER TABLE `voting`
  ADD CONSTRAINT `fk_voting_kontestan` FOREIGN KEY (`kontestan_id`) REFERENCES `kontestan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_voting_pesanan` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan_vote` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_voting_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
