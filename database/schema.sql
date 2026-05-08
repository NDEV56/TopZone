-- ════════════════════════════════════════════════════════════════════
--  TOPZONE — Database Schema
-- ════════════════════════════════════════════════════════════════════
--  Cara import:
--    1. Buat database baru: CREATE DATABASE topzone;
--    2. Import file ini   : mysql -u root -p topzone < schema.sql
--    3. (Opsional) Import seed.sql untuk data dummy
-- ════════════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS `topzone`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `topzone`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


-- ─── 👥 TABEL USERS ─────────────────────────────────────────────────
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `nama_user`  VARCHAR(100) NOT NULL,
  `username`   VARCHAR(50)  NOT NULL UNIQUE,
  `email`      VARCHAR(100) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `foto`       VARCHAR(150) DEFAULT 'Default.jpg',
  `role`       ENUM('user','admin') DEFAULT 'user',
  `is_active`  TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─── 🎮 TABEL GAMES ─────────────────────────────────────────────────
DROP TABLE IF EXISTS `games`;
CREATE TABLE `games` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(100) NOT NULL UNIQUE,
  `nama_game`  VARCHAR(150) NOT NULL,
  `kategori`   VARCHAR(50)  DEFAULT 'Lainnya',
  `gambar`     VARCHAR(255) DEFAULT 'Default.jpg',
  `deskripsi`  TEXT,
  `harga`      INT(11) DEFAULT 0,
  `terjual`    INT(11) DEFAULT 0,
  `is_active`  TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug`     (`slug`),
  KEY `idx_kategori` (`kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─── 💎 TABEL PRODUK_GAME (paket nominal) ──────────────────────────
DROP TABLE IF EXISTS `produk_game`;
CREATE TABLE `produk_game` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `id_game`      INT(11) NOT NULL,
  `nama_produk`  VARCHAR(150) NOT NULL,
  `harga`        INT(11) NOT NULL,
  `tipe`         VARCHAR(50) DEFAULT 'default',
  `is_active`    TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_id_game` (`id_game`),
  CONSTRAINT `fk_produk_game`
    FOREIGN KEY (`id_game`) REFERENCES `games`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─── 🛒 TABEL KERANJANG ────────────────────────────────────────────
DROP TABLE IF EXISTS `keranjang`;
CREATE TABLE `keranjang` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `id_user`      INT(11) NOT NULL,
  `id_game`      INT(11) DEFAULT NULL,
  `nama_produk`  VARCHAR(150),
  `harga`        INT(11) DEFAULT 0,
  `qty`          INT(11) DEFAULT 1,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user`    (`id_user`),
  KEY `idx_game`    (`id_game`),
  CONSTRAINT `fk_keranjang_user`
    FOREIGN KEY (`id_user`) REFERENCES `users`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─── 📦 TABEL ORDERS ───────────────────────────────────────────────
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id_order`       INT(11) NOT NULL AUTO_INCREMENT,
  `external_id`    VARCHAR(100) UNIQUE,
  `id_user`        INT(11) NOT NULL,
  `game_name`      VARCHAR(150),
  `paket`          VARCHAR(150),
  `total_price`    BIGINT(20) DEFAULT 0,
  `item_count`     INT(11) DEFAULT 1,
  `catatan`        TEXT,
  `payment_method` VARCHAR(100) DEFAULT 'Xendit',
  `status`         ENUM('pending','proses','dikirim','selesai','batal') DEFAULT 'pending',
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_order`),
  KEY `idx_user`    (`id_user`),
  KEY `idx_status`  (`status`),
  KEY `idx_ext`     (`external_id`),
  CONSTRAINT `fk_orders_user`
    FOREIGN KEY (`id_user`) REFERENCES `users`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─── ⭐ TABEL REVIEWS ──────────────────────────────────────────────
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `id_game`    INT(11) NOT NULL,
  `id_user`    INT(11) DEFAULT NULL,
  `user_name`  VARCHAR(100),
  `rating`     TINYINT(1) NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `komentar`   TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_game`   (`id_game`),
  KEY `idx_rating` (`rating`),
  CONSTRAINT `fk_reviews_game`
    FOREIGN KEY (`id_game`) REFERENCES `games`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ─── 💬 TABEL CHAT ─────────────────────────────────────────────────
DROP TABLE IF EXISTS `chat`;
CREATE TABLE `chat` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `id_user`    INT(11) NOT NULL,
  `pesan`      TEXT NOT NULL,
  `pengirim`   ENUM('user','admin') DEFAULT 'user',
  `is_read`    TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user`     (`id_user`),
  KEY `idx_pengirim` (`pengirim`),
  CONSTRAINT `fk_chat_user`
    FOREIGN KEY (`id_user`) REFERENCES `users`(`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


SET FOREIGN_KEY_CHECKS = 1;

-- ════════════════════════════════════════════════════════════════════
--  ✅ Schema berhasil dibuat!
--  Lanjut import seed.sql untuk data dummy:
--    mysql -u root -p topzone < seed.sql
-- ════════════════════════════════════════════════════════════════════
