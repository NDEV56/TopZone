-- ════════════════════════════════════════════════════════════════════
--  TOPZONE — Sample Seed Data
-- ════════════════════════════════════════════════════════════════════
--  Akun default:
--    👑 Admin : username=admin       password=admin123
--    👤 User  : username=demo        password=demo123
-- ════════════════════════════════════════════════════════════════════

USE `topzone`;


-- ─── USERS ─────────────────────────────────────────────────────────
-- Password di-hash dengan password_hash(..., PASSWORD_DEFAULT)
INSERT INTO `users` (`nama_user`, `username`, `email`, `password`, `foto`, `role`) VALUES
('Administrator', 'admin', 'admin@topzone.com',
 '$2y$10$N9qo8uLOickgx2ZMRZoMye.IdL1tIOvT8d5JKxYg8VPAH2tPcwz0e', 'Default.jpg', 'admin'),
('Demo User', 'demo', 'demo@topzone.com',
 '$2y$10$YH9bJL5xqpXvZsPZGYxqvuQwKmF6Ot3FhcUH1kxkZN1xXX6SQPSPe', 'Default.jpg', 'user');


-- ─── GAMES ─────────────────────────────────────────────────────────
INSERT INTO `games` (`slug`, `nama_game`, `kategori`, `gambar`, `deskripsi`, `harga`, `terjual`) VALUES
('mobile-legends',  'Mobile Legends',     'MOBA',       'MLBB.jpeg',                  'Top up Diamond Mobile Legends instan & murah.',  10000, 1250),
('free-fire',       'Free Fire',          'FPS',        'FF.jpg',                     'Top up Diamond Free Fire 24 jam non-stop.',      15000,  980),
('pubg-mobile',     'PUBG Mobile',        'FPS',        'pubg-308.jpg',               'Top up UC PUBG Mobile resmi.',                   12000,  720),
('genshin-impact',  'Genshin Impact',     'Open World', 'Genshin.jpg',                'Top up Genesis Crystal Genshin Impact.',         16000,  640),
('honor-of-kings',  'Honor of Kings',     'MOBA',       'honor-of-kings-427.jpg',     'Top up Tokens Honor of Kings.',                  14000,  430),
('roblox',          'Roblox',             'Open World', 'Roblox.jpg',                 'Top up Robux via login atau 5-hari (no login).',  9000,  890),
('growtopia',       'Growtopia',          'Open World', 'growtopia-143.jpg',          'Top up World Lock & Diamond Lock.',               8000,  310);


-- ─── PRODUK_GAME (paket nominal) ───────────────────────────────────
-- Mobile Legends
INSERT INTO `produk_game` (`id_game`, `nama_produk`, `harga`, `tipe`) VALUES
(1, '86 Diamond',     20000, 'default'),
(1, '172 Diamond',    40000, 'default'),
(1, '257 Diamond',    60000, 'default'),
(1, '344 Diamond',    80000, 'default'),
(1, '706 Diamond',   150000, 'default'),
(1, '1412 Diamond',  300000, 'default');

-- Free Fire
INSERT INTO `produk_game` (`id_game`, `nama_produk`, `harga`, `tipe`) VALUES
(2, '70 Diamond',     10000, 'default'),
(2, '140 Diamond',    20000, 'default'),
(2, '355 Diamond',    50000, 'default'),
(2, '720 Diamond',   100000, 'default'),
(2, '1450 Diamond',  200000, 'default');

-- PUBG Mobile
INSERT INTO `produk_game` (`id_game`, `nama_produk`, `harga`, `tipe`) VALUES
(3, '60 UC',          15000, 'default'),
(3, '325 UC',         75000, 'default'),
(3, '660 UC',        150000, 'default'),
(3, '1800 UC',       400000, 'default');

-- Genshin Impact
INSERT INTO `produk_game` (`id_game`, `nama_produk`, `harga`, `tipe`) VALUES
(4, '60 Genesis Crystal',    16000, 'default'),
(4, '300 Genesis Crystal',   79000, 'default'),
(4, '980 Genesis Crystal',  249000, 'default'),
(4, '1980 Genesis Crystal', 499000, 'default');

-- Honor of Kings
INSERT INTO `produk_game` (`id_game`, `nama_produk`, `harga`, `tipe`) VALUES
(5, '78 Tokens',     14000, 'default'),
(5, '156 Tokens',    28000, 'default'),
(5, '390 Tokens',    70000, 'default'),
(5, '780 Tokens',   140000, 'default');

-- Roblox (login & 5-hari)
INSERT INTO `produk_game` (`id_game`, `nama_produk`, `harga`, `tipe`) VALUES
(6, '80 Robux',      14000, 'roblox_login'),
(6, '400 Robux',     65000, 'roblox_login'),
(6, '800 Robux',    130000, 'roblox_login'),
(6, '1700 Robux',   260000, 'roblox_login'),
(6, '80 Robux (5 Hari)',     16000, 'roblox_5hari'),
(6, '400 Robux (5 Hari)',    72000, 'roblox_5hari'),
(6, '800 Robux (5 Hari)',   140000, 'roblox_5hari');

-- Growtopia
INSERT INTO `produk_game` (`id_game`, `nama_produk`, `harga`, `tipe`) VALUES
(7, '5 Diamond Lock',    35000, 'default'),
(7, '10 Diamond Lock',   70000, 'default'),
(7, '25 Diamond Lock',  170000, 'default');


-- ─── REVIEWS (sample) ──────────────────────────────────────────────
INSERT INTO `reviews` (`id_game`, `id_user`, `user_name`, `rating`, `komentar`) VALUES
(1, 2, 'Demo User', 5, 'Mantap! Diamond ML masuk cepet banget, < 1 menit. Recomended.'),
(1, NULL, 'Anonim',  4, 'Bagus, cuma kadang chat admin lambat reply.'),
(2, NULL, 'FF Player', 5, 'FF top up disini langganan, harga termurah!'),
(6, 2, 'Demo User', 5, 'Robux 5 hari work, gak perlu kasih password.');


-- ════════════════════════════════════════════════════════════════════
--  ✅ Seed data berhasil di-insert!
--
--  Login:
--    Admin → http://localhost/TopZone/Login/tampilanlogin.php
--           username: admin / password: admin123
--    User  → username: demo  / password: demo123
-- ════════════════════════════════════════════════════════════════════
