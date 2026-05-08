# 🤝 Panduan Kontribusi TopZone

Terima kasih sudah mau ikut bantu development TopZone! Berikut panduan singkat:

---

## 🐛 Lapor Bug

1. Cek dulu di [Issues](https://github.com/<your-username>/TopZone/issues) — siapa tahu sudah dilaporkan
2. Buat issue baru dengan format:
   - **Judul jelas**: contoh "Login error setelah upload foto profil"
   - **Steps to reproduce**: langkah persis yang menyebabkan bug
   - **Expected**: harusnya gimana
   - **Actual**: kenyataannya gimana
   - **Environment**: OS, browser, PHP version

---

## ✨ Usul Fitur Baru

1. Buka issue dengan label `enhancement`
2. Jelaskan:
   - **Problem**: apa yang masalah / kurang
   - **Solusi**: ide kamu
   - **Alternatif**: opsi lain yang kamu pertimbangkan

---

## 💻 Submit Pull Request

```bash
# 1. Fork repo di GitHub
# 2. Clone fork-an kamu
git clone https://github.com/<username-kamu>/TopZone.git
cd TopZone

# 3. Buat branch fitur
git checkout -b fitur-keren

# 4. Edit code, commit
git add .
git commit -m "feat: tambah filter game by harga"

# 5. Push & buka PR
git push origin fitur-keren
```

### Format Commit Message

Pakai [Conventional Commits](https://www.conventionalcommits.org/):

| Prefix | Untuk |
|---|---|
| `feat:` | Fitur baru |
| `fix:` | Bug fix |
| `docs:` | Update dokumentasi |
| `style:` | Format/whitespace (tidak ubah logika) |
| `refactor:` | Refactor tanpa ubah behavior |
| `perf:` | Optimisasi performance |
| `test:` | Tambah/edit test |
| `chore:` | Maintenance (config, deps) |

Contoh:
```
feat: tambah filter game by harga
fix: callback Xendit gagal update status
docs: update SETUP.md untuk macOS
refactor: migrasi login ke prepared statement
```

---

## 🎨 Coding Standards

### PHP

- ✅ **Gunakan prepared statement** untuk SEMUA query (lihat [`includes/db.php`](includes/db.php))
- ✅ **Escape output** dengan `e()` atau `htmlspecialchars()`
- ✅ **CSRF token** di semua form POST (`csrf_field()`)
- ✅ Indentasi 4 spasi
- ❌ Jangan pakai `mysqli_query()` dengan string concat — pakai helper `db_run()`/`db_one()`/`db_all()`

```php
// ❌ JANGAN
$id = $_GET['id'];
mysqli_query($conn, "SELECT * FROM users WHERE id = '$id'");

// ✅ BOLEH
$user = db_one("SELECT * FROM users WHERE id = ?", [$_GET['id']]);
```

### JavaScript

- ✅ Pakai `const`/`let`, bukan `var`
- ✅ Indentasi 2 spasi
- ✅ Pakai fetch API atau jQuery (yang sudah ada)
- ❌ Jangan inline event handler kalau bisa di-attach via `addEventListener`

### CSS

- ✅ Pakai variabel CSS (`:root { --primary: ... }`)
- ✅ Mobile-first responsive
- ❌ Hindari `!important` kecuali emergency

---

## 📁 Struktur Folder yang Disarankan

```
Home/
├── index.php           # Entry point user
├── admin*.php          # Halaman admin
├── Chat/               # Modul chat
├── Checkout/           # Modul pembayaran
└── uploads/            # User content (jangan commit)

includes/               # Helper PHP (config, db, security)
database/               # SQL files (schema, seed, migration)
Login/                  # Auth pages
```

**Aturan main:**
- File baru yang reusable → masuk `includes/`
- Migration baru → buat file `database/migration_YYYYMMDD_xxx.sql`
- Asset (gambar produk) → masuk `Home/assets/img/` (BUKAN root `Home/`)

---

## ✅ Checklist Sebelum Submit PR

- [ ] Code jalan di lokal tanpa error
- [ ] Semua query baru pakai prepared statement
- [ ] Form baru ada CSRF token
- [ ] Tidak ada `var_dump()`, `console.log()`, atau debug code yang ketinggalan
- [ ] Tidak commit file `.env`, `node_modules/`, atau `*.log`
- [ ] Update `CHANGELOG.md` di section `[Unreleased]`
- [ ] PR description menjelaskan apa yang berubah & kenapa

---

Makasih kontribusinya! 🎉
