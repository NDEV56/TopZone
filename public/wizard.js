/* ════════════════════════════════════════════════════════════
   TopZone GUI — wizard.js
   ───────────────────────
   Setup wizard step-by-step yang sangat ramah pemula.
   Validasi tiap step + diagnostik real-time + ringkasan
   sebelum simpan. Memanggil POST /api/setup/save di akhir.
   ════════════════════════════════════════════════════════════ */
"use strict";

(function () {

const TZ = window.TZ = window.TZ || {};
const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

const W = {
  step: 1,
  data: {
    provider: "ngrok",
    ngrokToken: "",
    ngrokDomain: "",
    mode: "auto",
    localPort: 80,
    phpPort: 8080,
    phpRoot: "",
    guiPassword: "",
    logRequests: true,
    tunnelFallback: true,
    autoUpdate: "ask",
  },
};

function init() {
  bindStepNav();
  bindOptionCards();
  bindModeSelect();
  bindValidations();
  showStep(1);
  // Jalankan diagnostik di langkah 3
  scanForServers();
}

// ─── Step navigation ──────────────────────────────
function bindStepNav() {
  $$("[data-next]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const next = parseInt(btn.dataset.next, 10);
      if (!validateStep(W.step)) return;
      collectFromCurrentStep();
      showStep(next);
      if (next === 5) renderSummary();
    });
  });
  $$("[data-prev]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const prev = parseInt(btn.dataset.prev, 10);
      collectFromCurrentStep();
      showStep(prev);
    });
  });

  $("#wizard-submit")?.addEventListener("click", submitWizard);
}

function showStep(n) {
  W.step = n;
  $$(".wizard-step").forEach((s) => {
    s.hidden = parseInt(s.dataset.step, 10) !== n;
  });
  $$(".step-pill").forEach((p) => {
    const num = parseInt(p.dataset.step, 10);
    p.classList.toggle("active", num === n);
    p.classList.toggle("done",   num <  n);
  });
  // Step 2 hanya relevan kalau provider ngrok
  if (n === 2 && W.data.provider !== "ngrok") {
    showStep(3);
  }
}

// ─── Provider cards ───────────────────────────────
function bindOptionCards() {
  $$(".option-card").forEach((card) => {
    card.addEventListener("click", () => {
      const radio = card.querySelector("input[type='radio']");
      if (!radio) return;
      radio.checked = true;
      W.data.provider = radio.value;
    });
  });
}

// ─── Mode select & tip box ────────────────────────
function bindModeSelect() {
  const sel = $("#mode");
  const tip = $("#mode-tip");
  const customRow = $("#customPort-row");
  if (!sel) return;

  const tips = {
    auto:         "Sistem akan scan port-port umum. Cocok kalau kamu sudah pernah Start XAMPP/Laragon.",
    xampp:        "Pastikan klik tombol Start di baris Apache (warna hijau) di XAMPP Control Panel.",
    laragon:      "Buka Laragon → klik Start All. Tunggu icon Apache di baris kanan jadi hijau.",
    wamp:         "Klik kanan icon WampServer di taskbar → Start All Services.",
    mamp:         "Buka MAMP → tombol Start Servers di kanan atas.",
    ampps:        "Buka AMPPS Control → klik Start di Apache.",
    openserver:   "Klik kanan icon OpenServer di tray → Start (bendera hijau).",
    usbwebserver: "Buka USBWebserver.exe → tab General → klik kedua tombol Start.",
    easyphp:      "Buka EasyPHP Dashboard → klik Start All di pojok atas.",
    php:          "Tidak butuh app lain. Sistem akan otomatis jalankan: php -S 127.0.0.1:8080.",
    custom:       "Tentukan sendiri port server-mu (misal 3000 untuk Node.js, 8000 untuk Django).",
  };
  function update() {
    W.data.mode = sel.value;
    if (tip) {
      tip.textContent = "💡 " + (tips[sel.value] || "");
      tip.classList.add("visible");
    }
    if (customRow) customRow.hidden = sel.value !== "custom";
  }
  sel.addEventListener("change", update);
  update();
}

// ─── Field validation real-time ───────────────────
function bindValidations() {
  const tk = $("#ngrokToken");
  if (tk) tk.addEventListener("input", () => validateNgrokTokenField());
  const pwd = $("#guiPassword");
  if (pwd) pwd.addEventListener("input", () => validatePasswordField());
}

function validateNgrokTokenField() {
  const tk = $("#ngrokToken");
  const out = $("#ngrokToken-validation");
  if (!tk || !out) return false;
  const v = tk.value.trim();
  if (!v) { out.className = "validation"; out.textContent = ""; return false; }
  if (v.length < 30 || !/^[A-Za-z0-9_]+$/.test(v)) {
    out.className = "validation error";
    out.textContent = "❌ Token tidak valid. Pastikan kamu copy SELURUH token (panjang ~40-50 karakter).";
    return false;
  }
  out.className = "validation ok";
  out.textContent = "✅ Format token terlihat OK.";
  return true;
}

function validatePasswordField() {
  const pwd = $("#guiPassword");
  const out = $("#guiPassword-validation");
  if (!pwd || !out) return true;
  const v = pwd.value;
  if (!v) { out.className = "validation"; out.textContent = ""; return true; }
  if (v.length < 6) {
    out.className = "validation error";
    out.textContent = "❌ Minimal 6 karakter.";
    return false;
  }
  // Strength check
  let strength = 0;
  if (v.length >= 8) strength++;
  if (/[a-z]/.test(v)) strength++;
  if (/[A-Z]/.test(v)) strength++;
  if (/[0-9]/.test(v)) strength++;
  if (/[^A-Za-z0-9]/.test(v)) strength++;
  const labels = ["Sangat lemah", "Lemah", "Sedang", "Bagus", "Kuat", "Sangat kuat"];
  out.className = "validation " + (strength >= 4 ? "ok" : strength >= 2 ? "" : "error");
  out.textContent = `🔒 Kekuatan: ${labels[strength]}`;
  return true;
}

// ─── Per-step validation ──────────────────────────
function validateStep(n) {
  if (n === 1) {
    const checked = document.querySelector("input[name='provider']:checked");
    if (!checked) { TZ.toast("Pilih satu provider tunnel", "", "warning"); return false; }
    W.data.provider = checked.value;
    return true;
  }
  if (n === 2) {
    if (W.data.provider !== "ngrok") return true;
    const ok = validateNgrokTokenField();
    if (!ok || !$("#ngrokToken").value.trim()) {
      TZ.toast("Token ngrok belum diisi", "Lihat petunjuk di atas untuk dapat token gratis.", "warning");
      return false;
    }
    return true;
  }
  if (n === 3) {
    const mode = $("#mode")?.value || "auto";
    if (mode === "custom") {
      const p = parseInt($("#localPort")?.value || "0", 10);
      if (!p || p < 1 || p > 65535) {
        TZ.toast("Port tidak valid", "Masukkan angka 1-65535 (contoh: 8080)", "warning");
        return false;
      }
    }
    return true;
  }
  if (n === 4) {
    const pwd = $("#guiPassword")?.value || "";
    if (pwd && pwd.length < 6) {
      TZ.toast("Password terlalu pendek", "Minimal 6 karakter, atau kosongkan saja.", "warning");
      return false;
    }
    return true;
  }
  return true;
}

function collectFromCurrentStep() {
  if (W.step === 1) {
    const v = document.querySelector("input[name='provider']:checked");
    if (v) W.data.provider = v.value;
  }
  if (W.step === 2) {
    W.data.ngrokToken  = $("#ngrokToken")?.value.trim() || "";
    W.data.ngrokDomain = $("#ngrokDomain")?.value.trim() || "";
  }
  if (W.step === 3) {
    W.data.mode      = $("#mode")?.value || "auto";
    W.data.localPort = parseInt($("#localPort")?.value || "80", 10) || 80;
    W.data.phpPort   = parseInt($("#phpPort")?.value   || "8080", 10) || 8080;
    W.data.phpRoot   = $("#phpRoot")?.value.trim() || "";
  }
  if (W.step === 4) {
    W.data.guiPassword    = $("#guiPassword")?.value || "";
    W.data.logRequests    = !!$("#logRequests")?.checked;
    W.data.tunnelFallback = !!$("#tunnelFallback")?.checked;
    W.data.autoUpdate     = $("#autoUpdate")?.value || "ask";
  }
}

// ─── Diagnose call ────────────────────────────────
async function scanForServers() {
  const panel = $("#diagnose-panel");
  if (!panel) return;
  try {
    const d = await TZ.api("/api/diagnose");
    const found = d.installedServers || [];
    const active = d.activeServers || [];
    const lines = [];
    if (active.length) {
      lines.push(`<div class="diag-row ok"><span class="ico">✓</span>
        <div class="body"><div class="label">Server aktif terdeteksi:</div>
        <div class="desc">${active.map((a) =>
          `${TZ.escapeHtml(a.label)} di port ${a.port}`).join(", ")}</div></div></div>`);
    } else {
      lines.push(`<div class="diag-row warn"><span class="ico">⚠</span>
        <div class="body"><div class="label">Tidak ada server yang sedang berjalan.</div>
        <div class="desc">Buka XAMPP/Laragon-mu dan klik Start sebelum melanjutkan.</div></div></div>`);
    }
    if (found.length) {
      lines.push(`<div class="diag-row info"><span class="ico">📦</span>
        <div class="body"><div class="label">Terinstall:</div>
        <div class="desc">${found.map((f) => TZ.escapeHtml(f.name)).join(", ")}</div></div></div>`);
    }
    panel.innerHTML = lines.join("");
  } catch (_) {
    panel.innerHTML = `<div class="diag-row info"><span class="ico">ℹ</span>
      <div class="body"><div class="label">Pindai gagal</div></div></div>`;
  }
}

// ─── Summary (step 5) ─────────────────────────────
function renderSummary() {
  collectFromCurrentStep(); // pastikan terbaru
  const card = $("#summary-card");
  if (!card) return;
  const tokenMask = W.data.ngrokToken
    ? W.data.ngrokToken.slice(0, 4) + "…" + W.data.ngrokToken.slice(-4)
    : "—";
  const rows = [
    ["Provider Tunnel", W.data.provider],
    W.data.provider === "ngrok" ? ["Token Ngrok", tokenMask] : null,
    W.data.ngrokDomain ? ["Custom Domain", W.data.ngrokDomain] : null,
    ["Mode Server", W.data.mode],
    W.data.mode === "custom" ? ["Port", String(W.data.localPort)] : null,
    W.data.mode === "php" ? ["Port PHP", String(W.data.phpPort)] : null,
    ["Password Panel", W.data.guiPassword ? "(diisi, hash scrypt)" : "tanpa password"],
    ["Auto-update", W.data.autoUpdate],
    ["Log requests", W.data.logRequests ? "ya" : "tidak"],
    ["Fallback tunnel", W.data.tunnelFallback ? "aktif" : "non-aktif"],
  ].filter(Boolean);
  card.innerHTML = rows.map(([k, v]) =>
    `<div class="summary-row"><span class="summary-key">${TZ.escapeHtml(k)}</span>
       <span class="summary-val">${TZ.escapeHtml(v)}</span></div>`).join("");
}

// ─── Submit ───────────────────────────────────────
async function submitWizard() {
  collectFromCurrentStep();
  const btn = $("#wizard-submit");
  if (btn) { btn.disabled = true; btn.textContent = "⏳ Menyimpan…"; }
  try {
    const res = await fetch("/api/setup/save", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(W.data),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || ("HTTP " + res.status));
    TZ.toast("Tersimpan!", "Setup selesai. Memulai server…", "success");
    // Reload halaman supaya main app muncul
    setTimeout(() => location.reload(), 1500);
  } catch (e) {
    TZ.toast("Gagal simpan", e.message, "error");
    if (btn) { btn.disabled = false; btn.textContent = "✅ Simpan & Mulai Server"; }
  }
}

TZ.wizard = { init };

})();
