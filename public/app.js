/* ════════════════════════════════════════════════════════════
   TopZone GUI — main app logic (app.js)
   ────────────────────────────────────
   • Tab switching, status polling, tunnel start/stop
   • Toast & confirm utilities
   • SSE log subscription (handed off to logs.js)
   • Settings load/save
   • Update check & pull
   • Diagnose render
   ════════════════════════════════════════════════════════════ */
"use strict";

(function () {

// ─── Globals ───────────────────────────────────────
const TZ = window.TZ = window.TZ || {};
TZ.csrf       = null;
TZ.snapshot   = null;
TZ.eventSrc   = null;
TZ.tabActive  = "dashboard";
TZ.uptimeTimer = null;

// ─── DOM helpers ───────────────────────────────────
const $  = (s, root = document) => root.querySelector(s);
const $$ = (s, root = document) => Array.from(root.querySelectorAll(s));

function escapeHtml(s) {
  if (s == null) return "";
  return String(s)
    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

// ─── Network ───────────────────────────────────────
async function api(path, options = {}) {
  const opts = {
    method: options.method || "GET",
    headers: { "Accept": "application/json" },
    credentials: "same-origin",
  };
  if (options.body !== undefined) {
    opts.headers["Content-Type"] = "application/json";
    opts.body = JSON.stringify(options.body);
  }
  if (TZ.csrf && /^(POST|PUT|DELETE|PATCH)$/.test(opts.method)) {
    opts.headers["X-CSRF-Token"] = TZ.csrf;
  }
  let res;
  try {
    res = await fetch(path, opts);
  } catch (e) {
    throw new Error("Tidak bisa konek ke panel. Server panel mungkin mati.");
  }
  let data = null;
  try { data = await res.json(); }
  catch (_) { data = null; }
  if (!res.ok) {
    const msg = (data && data.error) || `HTTP ${res.status}`;
    if (res.status === 401 && data && data.needLogin) {
      window.location.href = "/login";
    }
    throw new Error(msg);
  }
  return data;
}

// ─── Toast ─────────────────────────────────────────
function toast(title, msg = "", type = "info") {
  const cont = $("#toast-container");
  if (!cont) return;
  const el = document.createElement("div");
  el.className = "toast " + (type || "");
  el.innerHTML = `
    <div class="toast-title">${escapeHtml(title)}</div>
    ${msg ? `<div class="toast-msg">${escapeHtml(msg)}</div>` : ""}
  `;
  cont.appendChild(el);
  setTimeout(() => el.remove(), 5000);
}

// ─── Confirm ───────────────────────────────────────
function confirmDialog({ title, message, icon = "⚠️", okText = "Ya, lanjut", cancelText = "Batal" }) {
  return new Promise((resolve) => {
    const overlay = $("#confirm-overlay");
    $("#confirm-icon").textContent = icon;
    $("#confirm-title").textContent = title;
    $("#confirm-message").textContent = message;
    $("#confirm-ok").textContent = okText;
    $("#confirm-cancel").textContent = cancelText;
    overlay.hidden = false;

    const close = (val) => {
      overlay.hidden = true;
      $("#confirm-ok").onclick = null;
      $("#confirm-cancel").onclick = null;
      resolve(val);
    };
    $("#confirm-ok").onclick     = () => close(true);
    $("#confirm-cancel").onclick = () => close(false);
  });
}

// Expose helpers
TZ.api    = api;
TZ.toast  = toast;
TZ.confirmDialog = confirmDialog;
TZ.$ = $; TZ.$$ = $$; TZ.escapeHtml = escapeHtml;

// ─── Boot ──────────────────────────────────────────
async function boot() {
  // 1. Cek auth & config
  let who;
  try { who = await api("/api/whoami"); }
  catch (e) {
    toast("Server panel tidak merespons", e.message, "error");
    return;
  }

  if (who.needPassword && !who.authenticated) {
    window.location.href = "/login";
    return;
  }
  TZ.csrf = who.csrf || null;

  // 2. Wizard atau main?
  if (!who.isConfigured) {
    document.getElementById("wizard-screen").hidden = false;
    if (TZ.wizard) TZ.wizard.init();
    return;
  }

  document.getElementById("main-screen").hidden = false;

  // 3. Bind UI
  bindTabs();
  bindHeroButtons();
  bindControlButtons();
  bindCopyButtons();
  bindSettings();
  bindUpdate();
  bindDiagnose();
  bindSecurity();
  bindLogout();

  // 4. Initial state
  await refreshStatus();
  await loadSettings();

  // 5. Subscribe SSE
  startSse();

  // 6. Periodic refresh
  setInterval(refreshStatus, 5000);

  // 7. Uptime ticker
  TZ.uptimeTimer = setInterval(updateUptime, 1000);
}

function bindLogout() {
  const btn = $("#btn-logout");
  if (!btn) return;
  // Kalau ada password, tampilkan tombol logout
  api("/api/whoami").then((w) => {
    if (w.needPassword && w.authenticated) btn.hidden = false;
  }).catch(() => {});
  btn.addEventListener("click", async () => {
    await api("/api/logout", { method: "POST" });
    window.location.href = "/login";
  });
  $("#btn-help")?.addEventListener("click", () => switchTab("help"));
}

// ─── Tabs ──────────────────────────────────────────
function bindTabs() {
  $$(".tab").forEach((tab) => {
    tab.addEventListener("click", () => switchTab(tab.dataset.tab));
  });
  $$("[data-tab-link]").forEach((el) => {
    el.addEventListener("click", (ev) => {
      ev.preventDefault();
      switchTab(el.dataset.tabLink);
    });
  });
}

function switchTab(name) {
  TZ.tabActive = name;
  $$(".tab").forEach((t) => t.classList.toggle("active", t.dataset.tab === name));
  $$(".tab-panel").forEach((p) => {
    const isActive = p.dataset.tabPanel === name;
    p.hidden = !isActive;
    p.classList.toggle("active", isActive);
  });
  // Refresh data tab tertentu
  if (name === "diagnose") refreshDiagnose();
  if (name === "update")   refreshUpdateBackups();
  if (name === "security") refreshSecurity();
  if (name === "settings") loadSettings();
}

// ─── Hero buttons ──────────────────────────────────
function bindHeroButtons() {
  $("#hero-start")?.addEventListener("click", startServer);
  $("#hero-stop")?.addEventListener("click", () => confirmStop());
}
function bindControlButtons() {
  $("#ctrl-start")?.addEventListener("click", startServer);
  $("#ctrl-stop")?.addEventListener("click", () => confirmStop());
  $("#ctrl-restart")?.addEventListener("click", () => confirmRestart());
}

async function startServer() {
  try {
    setControlsBusy(true);
    toast("Mulai server…", "Sedang mendeteksi server lokal & buka tunnel", "info");
    await api("/api/server/start", { method: "POST" });
    // Status akan update via SSE; refresh sekarang juga
    setTimeout(refreshStatus, 500);
  } catch (e) {
    toast("Gagal mulai server", e.message, "error");
  } finally {
    setControlsBusy(false);
  }
}

async function confirmStop() {
  const ok = await confirmDialog({
    title: "Matikan Server?",
    message: "Tunnel publik akan ditutup. URL yang sedang dipakai (Midtrans dll) tidak bisa diakses lagi setelah ini.",
    icon: "🛑",
    okText: "Ya, matikan",
  });
  if (!ok) return;
  try {
    setControlsBusy(true);
    await api("/api/server/stop", { method: "POST" });
    toast("Server dimatikan", "", "warning");
    setTimeout(refreshStatus, 300);
  } catch (e) {
    toast("Gagal matikan", e.message, "error");
  } finally { setControlsBusy(false); }
}

async function confirmRestart() {
  const ok = await confirmDialog({
    title: "Restart Server?",
    message: "Server akan dimatikan dulu lalu dinyalakan lagi. URL publik kemungkinan berubah.",
    icon: "⟳", okText: "Ya, restart",
  });
  if (!ok) return;
  try {
    setControlsBusy(true);
    await api("/api/server/restart", { method: "POST" });
    toast("Restart dimulai", "Tunggu beberapa detik…", "info");
    setTimeout(refreshStatus, 1500);
  } catch (e) {
    toast("Gagal restart", e.message, "error");
  } finally { setControlsBusy(false); }
}

function setControlsBusy(busy) {
  ["#hero-start", "#hero-stop", "#ctrl-start", "#ctrl-stop", "#ctrl-restart"]
    .forEach((sel) => { const el = $(sel); if (el) el.disabled = busy; });
}

// ─── Status refresh ────────────────────────────────
async function refreshStatus() {
  try {
    const snap = await api("/api/status");
    TZ.snapshot = snap;
    renderStatus(snap);
  } catch (e) {
    // Diam — kalau auth gagal, redirect sudah di api()
  }
}

function renderStatus(snap) {
  const phase = snap.phase || "idle";

  // Phase indicator
  const ind = $("#phase-indicator");
  if (ind) {
    ind.className = "phase phase-" + phase;
    const labels = {
      idle: "Belum Dijalankan", booting: "Mempersiapkan…",
      preflight: "Cek Lingkungan…", detecting: "Mendeteksi Server…",
      tunneling: "Membuka Tunnel…", online: "ONLINE", stopping: "Mematikan…",
      error: "Error",
    };
    $(".phase-label", ind).textContent = labels[phase] || phase;
  }

  // Hero
  const heroStart = $("#hero-start");
  const heroStop  = $("#hero-stop");
  const heroIcon  = $(".hero-status-icon");
  const heroTitle = $(".hero-status-title");
  const heroSub   = $(".hero-status-sub");

  if (heroStart) heroStart.hidden = (phase === "online" || phase === "tunneling");
  if (heroStop)  heroStop.hidden  = !(phase === "online" || phase === "tunneling");

  const iconMap = { idle: "⚪", booting: "🟡", preflight: "🟡", detecting: "🟡",
                     tunneling: "🟡", online: "🟢", stopping: "🟠", error: "🔴" };
  if (heroIcon) heroIcon.textContent = iconMap[phase] || "⚪";

  const titleMap = {
    idle: "Server Belum Dijalankan",
    booting: "Mempersiapkan…",
    preflight: "Memeriksa Lingkungan…",
    detecting: "Mencari Server Lokal…",
    tunneling: "Membuka Tunnel Publik…",
    online: "Server ONLINE!",
    stopping: "Mematikan…",
    error: "Terjadi Error",
  };
  if (heroTitle) heroTitle.textContent = titleMap[phase] || phase;

  const subMap = {
    idle: "Klik tombol di samping untuk mulai",
    online: snap.tunnelUrl ? `Bisa diakses di ${snap.tunnelUrl}` : "Tunnel aktif",
    error: snap.lastError || "Lihat tab Diagnostik untuk detail",
  };
  if (heroSub) heroSub.textContent = subMap[phase] || "Tunggu sebentar…";

  // Stats
  const local  = snap.localPort ? `http://localhost:${snap.localPort}` : "—";
  $("#stat-local")    && ($("#stat-local").textContent    = local);
  $("#stat-public")   && ($("#stat-public").textContent   = snap.tunnelUrl || "—");
  $("#stat-server")   && ($("#stat-server").textContent   = snap.serverLabel || "—");
  $("#stat-port")     && ($("#stat-port").textContent     = "port: " + (snap.localPort || "—"));
  $("#stat-provider") && ($("#stat-provider").textContent = snap.tunnelProvider || "—");
  $("#stat-requests") && ($("#stat-requests").textContent = (snap.tunnelStat?.requestCount ?? 0));
  $("#stat-phase")    && ($("#stat-phase").textContent    = phase);

  // Webhooks
  const base = snap.tunnelUrl || "";
  $("#wh-callback") && ($("#wh-callback").textContent = base ? base + "/callback.php" : "—");
  $("#wh-token")    && ($("#wh-token").textContent    = base ? base + "/Checkout/ambil_token.php" : "—");

  // Log counters
  if (snap.logCount) {
    for (const k of ["common","uncommon","warning","error","critical","security","total"]) {
      const el = document.getElementById("cnt-" + (k === "total" ? "all" : k));
      if (el) el.textContent = snap.logCount[k] ?? 0;
    }
  }
}

function updateUptime() {
  if (!TZ.snapshot) return;
  const ms = TZ.snapshot.uptime || 0;
  const txt = formatDuration(Date.now() - (TZ.snapshot._t || Date.now()) + ms);
  if (TZ.snapshot.phase !== "online") return;
  const el = $("#stat-uptime");
  if (el) el.textContent = txt;
}
function formatDuration(ms) {
  if (ms < 1000) return Math.max(0, Math.round(ms)) + "ms";
  let s = Math.floor(ms / 1000);
  const h = Math.floor(s / 3600); s = s % 3600;
  const m = Math.floor(s / 60);
  s = s % 60;
  if (h) return `${h}j ${m}m ${s}d`;
  if (m) return `${m}m ${s}d`;
  return `${s}d`;
}

// ─── SSE ──────────────────────────────────────────
function startSse() {
  if (TZ.eventSrc) return;
  try {
    const src = new EventSource("/api/stream");
    TZ.eventSrc = src;
    src.addEventListener("hello", () => {});
    src.addEventListener("ping",  () => {});
    src.addEventListener("phase", (ev) => {
      try {
        const d = JSON.parse(ev.data);
        if (d.state) {
          TZ.snapshot = { ...TZ.snapshot, ...d.state };
          renderStatus(TZ.snapshot);
        }
      } catch (_) {}
    });
    src.addEventListener("log", (ev) => {
      try {
        const e = JSON.parse(ev.data);
        if (TZ.logs && TZ.logs.append) TZ.logs.append(e);
      } catch (_) {}
    });
    src.onerror = () => {
      // Re-connect dilakukan otomatis oleh browser (retry: 5000)
    };
  } catch (e) {
    console.warn("SSE gagal:", e);
  }
}

// ─── Copy buttons & open links ────────────────────
function bindCopyButtons() {
  document.addEventListener("click", (e) => {
    const t = e.target.closest("[data-copy]");
    if (t) {
      const sel = "#" + t.dataset.copy;
      const el = document.querySelector(sel);
      if (!el) return;
      const txt = el.textContent.trim();
      if (!txt || txt === "—") {
        toast("Belum ada URL untuk disalin", "Mulai server dulu", "warning");
        return;
      }
      navigator.clipboard.writeText(txt).then(() => {
        toast("Tersalin!", txt, "success");
      }).catch(() => toast("Gagal copy", "Browser memblokir clipboard", "error"));
      return;
    }
    const o = e.target.closest("[data-open]");
    if (o) {
      const sel = "#" + o.dataset.open;
      const el = document.querySelector(sel);
      if (!el) return;
      const txt = el.textContent.trim();
      if (!txt || txt === "—") return;
      window.open(txt, "_blank", "noopener");
    }
  });
}

// ─── Settings ─────────────────────────────────────
function bindSettings() {
  const form = $("#settings-form");
  if (!form) return;
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    // Konversi checkbox manual (FormData gak include unchecked)
    data.TUNNEL_FALLBACK = form.elements.TUNNEL_FALLBACK?.checked ? "true" : "false";

    if (data.guiPassword && data.guiPassword.length < 6) {
      toast("Password terlalu pendek", "Minimal 6 karakter", "warning");
      return;
    }
    try {
      await api("/api/settings", { method: "POST", body: data });
      toast("Tersimpan", "Restart server untuk pakai pengaturan baru", "success");
      // Reload UI
      loadSettings();
    } catch (e) {
      toast("Gagal simpan", e.message, "error");
    }
  });
  $("#settings-reload")?.addEventListener("click", loadSettings);
}

async function loadSettings() {
  try {
    const c = await api("/api/settings");
    const form = $("#settings-form");
    if (!form || !c) return;
    for (const [k, v] of Object.entries(c)) {
      const el = form.elements[k];
      if (!el) continue;
      if (el.type === "checkbox") {
        el.checked = (v === "true" || v === true);
      } else if (el.type !== "password") {
        el.value = v ?? "";
      }
    }
  } catch (_) {}
}

// ─── Update tab ───────────────────────────────────
function bindUpdate() {
  $("#update-check")?.addEventListener("click", checkUpdate);
}
async function checkUpdate() {
  const cont = $("#update-content");
  if (!cont) return;
  cont.innerHTML = `<div class="loading">Cek ke GitHub…</div>`;
  try {
    const res = await api("/api/update/check");
    if (!res.available) {
      cont.innerHTML = `
        <div class="info-banner success">✅ ${escapeHtml(res.reason || "Sudah versi terbaru.")}</div>
        <button class="btn btn-primary btn-large" id="update-check">🔎 Cek Lagi</button>
      `;
      $("#update-check")?.addEventListener("click", checkUpdate);
      return;
    }
    const summary = (res.summary || []).map((l) => escapeHtml(l)).join("<br>");
    cont.innerHTML = `
      <div class="info-banner warning">
        🆕 <strong>Update tersedia: ${res.behind} commit baru di branch ${escapeHtml(res.branch)}</strong>
      </div>
      <h3>Ringkasan perubahan:</h3>
      <div class="update-summary">${summary || "(tidak ada ringkasan)"}</div>
      <div style="display:flex; gap:10px;">
        <button class="btn btn-success btn-large" id="update-pull">⬇️  Tarik Update Sekarang</button>
        <button class="btn btn-ghost" id="update-recheck">🔎 Cek Lagi</button>
      </div>
    `;
    $("#update-pull")?.addEventListener("click", async () => {
      const ok = await confirmDialog({
        title: "Tarik Update?",
        message: `Akan menarik ${res.behind} commit baru. Backup otomatis dibuat.`,
        icon: "⬇️", okText: "Ya, tarik update",
      });
      if (!ok) return;
      cont.innerHTML = `<div class="loading">Mengupdate… (mungkin perlu 1-2 menit kalau ada npm install)</div>`;
      try {
        const r = await api("/api/update/pull", { method: "POST" });
        if (r.ok) {
          toast("Update sukses!", r.message, "success");
          cont.innerHTML = `
            <div class="info-banner success">✅ ${escapeHtml(r.message)}</div>
            ${r.depsChanged ? `<div class="info-banner">📦 Dependency berubah, npm install sudah dijalankan.</div>` : ""}
            <p>Restart server untuk pakai versi baru:</p>
            <button class="btn btn-warning" onclick="TZ.restartFromUpdate()">⟳ Restart Server</button>
            <button class="btn btn-primary" id="update-check">🔎 Cek Update Lagi</button>
          `;
          $("#update-check")?.addEventListener("click", checkUpdate);
          refreshUpdateBackups();
        } else {
          toast("Update gagal", r.message, "error");
          cont.innerHTML = `<div class="info-banner danger">❌ ${escapeHtml(r.message)}</div>
                            <button class="btn btn-primary" id="update-check">🔎 Coba Lagi</button>`;
          $("#update-check")?.addEventListener("click", checkUpdate);
        }
      } catch (e) {
        toast("Update error", e.message, "error");
        cont.innerHTML = `<div class="info-banner danger">❌ ${escapeHtml(e.message)}</div>
                          <button class="btn btn-primary" id="update-check">🔎 Coba Lagi</button>`;
        $("#update-check")?.addEventListener("click", checkUpdate);
      }
    });
    $("#update-recheck")?.addEventListener("click", checkUpdate);
  } catch (e) {
    cont.innerHTML = `<div class="info-banner danger">❌ ${escapeHtml(e.message)}</div>
                      <button class="btn btn-primary" id="update-check">🔎 Coba Lagi</button>`;
    $("#update-check")?.addEventListener("click", checkUpdate);
  }
}
TZ.restartFromUpdate = async function () {
  await api("/api/server/restart", { method: "POST" });
  toast("Restart…", "", "info");
};

async function refreshUpdateBackups() {
  const list = $("#backup-list"); if (!list) return;
  try {
    const res = await api("/api/update/backups");
    if (!res.backups || !res.backups.length) {
      list.innerHTML = `<div class="empty-state muted">Belum ada backup.</div>`;
      return;
    }
    list.innerHTML = res.backups.map((b) =>
      `<div class="backup-row">
         <code>${escapeHtml(b)}</code>
         <span class="muted">backups/${escapeHtml(b)}/</span>
       </div>`).join("");
  } catch (_) {}
}

// ─── Diagnose ─────────────────────────────────────
function bindDiagnose() {
  $("#diag-refresh")?.addEventListener("click", refreshDiagnose);
}

async function refreshDiagnose() {
  const cont = $("#diag-content"); if (!cont) return;
  cont.innerHTML = `<div class="loading">Memindai…</div>`;
  try {
    const d = await api("/api/diagnose");
    cont.innerHTML = renderDiagnose(d);
  } catch (e) {
    cont.innerHTML = `<div class="diag-row err"><span class="ico">❌</span>
      <div class="body"><div class="label">Gagal pindai</div>
      <div class="desc">${escapeHtml(e.message)}</div></div></div>`;
  }
}

function renderDiagnose(d) {
  const r = (cls, ico, label, desc) =>
    `<div class="diag-row ${cls}"><span class="ico">${ico}</span>
       <div class="body"><div class="label">${escapeHtml(label)}</div>
       ${desc ? `<div class="desc">${desc}</div>` : ""}</div></div>`;

  const out = [];

  out.push(`<div class="diag-section"><h4>🖥️  Sistem</h4>`);
  out.push(r("ok", "✓", `${d.platform} (${d.arch})`, "Platform OS"));
  out.push(r("ok", "✓", `Node.js ${d.nodeVersion}`, "Versi Node.js"));
  out.push(`</div>`);

  out.push(`<div class="diag-section"><h4>🔧 Tools Terinstall</h4>`);
  out.push(d.hasGit       ? r("ok",   "✓", "git", "Diperlukan untuk auto-update")
                          : r("warn", "⚠", "git tidak ada", "Auto-update tidak akan berfungsi. Install dari git-scm.com"));
  out.push(d.hasPhp       ? r("ok",   "✓", "PHP", "Tersedia di PATH")
                          : r("info", "ℹ", "PHP tidak ada di PATH",
                             "OK kalau pakai XAMPP/Laragon. Wajib kalau pakai mode 'php'."));
  out.push(d.hasNgrok     ? r("ok",   "✓", "ngrok binary", "Tersedia di PATH")
                          : r("info", "ℹ", "ngrok binary tidak ada", "OK — sistem akan pakai library @ngrok/ngrok via npm."));
  out.push(d.hasCloudflared ? r("ok", "✓", "cloudflared", "Bisa pakai Cloudflare Tunnel")
                          : r("info", "ℹ", "cloudflared tidak ada", "Opsional. Install dari developers.cloudflare.com kalau mau pakai."));
  out.push(d.hasLt        ? r("ok",   "✓", "localtunnel (lt)", "Bisa pakai LocalTunnel")
                          : r("info", "ℹ", "localtunnel tidak ada", "Opsional: npm i -g localtunnel"));
  out.push(`</div>`);

  out.push(`<div class="diag-section"><h4>📦 App Server Terinstall</h4>`);
  if (!d.installedServers.length) {
    out.push(r("warn", "⚠", "Tidak ada XAMPP/Laragon/WAMP/MAMP/AMPPS terdeteksi",
               "Install salah satu, atau pakai mode 'php' (PHP built-in)."));
  } else {
    for (const s of d.installedServers) {
      out.push(r("ok", "✓", s.name, escapeHtml(s.path)));
    }
  }
  out.push(`</div>`);

  out.push(`<div class="diag-section"><h4>🌐 Server HTTP yang Aktif</h4>`);
  if (!d.activeServers.length) {
    out.push(r("warn", "⚠", "Tidak ada server merespons",
               "Buka XAMPP/Laragon dan klik Start. Atau jalankan PHP built-in."));
  } else {
    for (const s of d.activeServers) {
      out.push(r("ok", "✓", `${s.label} (port ${s.port})`, `HTTP ${s.status}`));
    }
  }
  out.push(`</div>`);

  out.push(`<div class="diag-section"><h4>🗄️  Database</h4>`);
  out.push(d.mysql.running
    ? r("ok",   "✓", `MySQL/MariaDB di port ${d.mysql.port}`, "Running")
    : r("info", "ℹ", "MySQL tidak terdeteksi", "OK kalau project tidak butuh database."));
  out.push(`</div>`);

  return out.join("");
}

// ─── Security ─────────────────────────────────────
function bindSecurity() {}

async function refreshSecurity() {
  try {
    const s = await api("/api/security");
    $("#sec-sessions")  && ($("#sec-sessions").textContent  = s.activeSessions || 0);
    $("#sec-blocked")   && ($("#sec-blocked").textContent   = (s.blockedIps || []).length);
    $("#sec-suspicion") && ($("#sec-suspicion").textContent = s.suspicionCount || 0);

    const blocked = $("#blocked-list");
    if (blocked) {
      if (!s.blockedIps?.length) {
        blocked.innerHTML = `<div class="empty-state muted">Tidak ada IP terblokir.</div>`;
      } else {
        blocked.innerHTML = s.blockedIps.map((b) => {
          const until = new Date(b.until).toLocaleString("id-ID");
          return `<div class="diag-row warn">
            <span class="ico">🚫</span>
            <div class="body">
              <div class="label">${escapeHtml(b.ip)}</div>
              <div class="desc">Diblokir sampai ${escapeHtml(until)}</div>
            </div>
            <button class="btn btn-sm btn-ghost" data-unblock="${escapeHtml(b.ip)}">Unblock</button>
          </div>`;
        }).join("");
        $$("[data-unblock]", blocked).forEach((btn) => {
          btn.addEventListener("click", async () => {
            try {
              await api("/api/security/unblock", { method: "POST", body: { ip: btn.dataset.unblock } });
              toast("IP di-unblock", btn.dataset.unblock, "success");
              refreshSecurity();
            } catch (e) {
              toast("Gagal unblock", e.message, "error");
            }
          });
        });
      }
    }

    const susp = $("#suspicion-list");
    if (susp) {
      if (!s.lastSuspicions?.length) {
        susp.innerHTML = `<div class="empty-state muted">Belum ada aktivitas mencurigakan.</div>`;
      } else {
        susp.innerHTML = s.lastSuspicions.map((x) => {
          const t = new Date(x.at).toLocaleString("id-ID");
          return `<div class="diag-row err">
            <span class="ico">⚠</span>
            <div class="body">
              <div class="label">${escapeHtml(x.pattern)} dari ${escapeHtml(x.ip)}</div>
              <div class="desc">${escapeHtml(t)} — ${escapeHtml(x.url || "")}</div>
            </div>
          </div>`;
        }).join("");
      }
    }
  } catch (_) {}
}

// ─── Boot kick-off ────────────────────────────────
document.addEventListener("DOMContentLoaded", boot);

})();
