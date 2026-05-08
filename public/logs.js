/* ════════════════════════════════════════════════════════════
   TopZone GUI — logs.js
   ─────────────────────
   Live log stream dengan filter kategori, search, autoscroll,
   dan ekspor JSON.
   ════════════════════════════════════════════════════════════ */
"use strict";

(function () {

const TZ = window.TZ = window.TZ || {};
const $  = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

const State = {
  filter      : "all",
  search      : "",
  autoscroll  : true,
  buffer      : [],          // semua entri (terkumpul dari SSE + initial fetch)
  maxBuffer   : 1000,
  initialized : false,
};

function ts(s) {
  if (!s) return "";
  // s = "2026-05-08 14:23:01.456" → ambil "14:23:01"
  const m = String(s).match(/(\d{2}:\d{2}:\d{2})/);
  return m ? m[1] : String(s).slice(0, 8);
}

function renderEntry(entry) {
  const div = document.createElement("div");
  div.className = "log-entry " + (entry.level || "common");
  const time = ts(entry.ts);
  let metaText = "";
  if (entry.meta && Object.keys(entry.meta).length) {
    try {
      metaText = " " + Object.entries(entry.meta)
        .filter(([k, v]) => v !== "" && v != null)
        .map(([k, v]) => `${TZ.escapeHtml(k)}=${TZ.escapeHtml(String(v).slice(0, 60))}`)
        .join(" ");
    } catch (_) {}
  }
  div.innerHTML =
    `<span class="lt">${TZ.escapeHtml(time)}</span>` +
    `<span class="ll">${TZ.escapeHtml(entry.level || "common")}</span>` +
    `<span class="lm">${TZ.escapeHtml(entry.message || "")}<span class="meta">${metaText}</span></span>`;
  return div;
}

function passesFilter(entry) {
  if (State.filter !== "all" && entry.level !== State.filter) return false;
  if (State.search) {
    const hay = (entry.message + " " + JSON.stringify(entry.meta || {})).toLowerCase();
    if (!hay.includes(State.search)) return false;
  }
  return true;
}

function rerender() {
  const stream = $("#log-stream");
  if (!stream) return;
  // Kosongkan & render dari buffer (filter)
  stream.innerHTML = "";
  const filtered = State.buffer.filter(passesFilter);
  if (!filtered.length) {
    stream.innerHTML = `<div class="empty-state">
      Tidak ada log yang cocok dengan filter ini.</div>`;
    return;
  }
  for (const e of filtered) stream.appendChild(renderEntry(e));
  if (State.autoscroll) stream.scrollTop = stream.scrollHeight;
}

function append(entry) {
  if (!entry) return;
  State.buffer.push(entry);
  if (State.buffer.length > State.maxBuffer) {
    State.buffer.splice(0, State.buffer.length - State.maxBuffer);
  }

  // Update counter
  const k = entry.level || "common";
  const cntEl = $("#cnt-" + k);
  if (cntEl) cntEl.textContent = (parseInt(cntEl.textContent || "0", 10) || 0) + 1;
  const cntAll = $("#cnt-all");
  if (cntAll) cntAll.textContent = (parseInt(cntAll.textContent || "0", 10) || 0) + 1;

  // Update mini stream di dashboard
  appendDashboard(entry);

  // Tambah ke main log kalau lolos filter
  if (passesFilter(entry)) {
    const stream = $("#log-stream");
    if (stream) {
      const empty = stream.querySelector(".empty-state");
      if (empty) empty.remove();
      stream.appendChild(renderEntry(entry));
      if (State.autoscroll) stream.scrollTop = stream.scrollHeight;
      // Trim DOM
      while (stream.children.length > 600) stream.removeChild(stream.firstChild);
    }
  }
}

function appendDashboard(entry) {
  const mini = $("#dashboard-logs");
  if (!mini) return;
  const empty = mini.querySelector(".empty-state");
  if (empty) empty.remove();
  mini.appendChild(renderEntry(entry));
  while (mini.children.length > 30) mini.removeChild(mini.firstChild);
  mini.scrollTop = mini.scrollHeight;
}

// ─── Initial load ─────────────────────────────────
async function loadInitial() {
  if (State.initialized) return;
  State.initialized = true;
  try {
    const data = await TZ.api("/api/logs?count=200");
    State.buffer = data.entries || [];
    // Update counter dari stats
    if (data.stats) {
      for (const k of ["common","uncommon","warning","error","critical","security"]) {
        const el = $("#cnt-" + k);
        if (el) el.textContent = data.stats[k] ?? 0;
      }
      const all = $("#cnt-all");
      if (all) all.textContent = data.stats.total ?? 0;
    }
    rerender();
  } catch (_) {}
}

// ─── Bind UI ──────────────────────────────────────
function bind() {
  $$(".filter-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      $$(".filter-btn").forEach((b) => b.classList.toggle("active", b === btn));
      State.filter = btn.dataset.level || "all";
      rerender();
    });
  });

  $("#log-search")?.addEventListener("input", (e) => {
    State.search = (e.target.value || "").toLowerCase();
    rerender();
  });
  $("#log-autoscroll")?.addEventListener("change", (e) => {
    State.autoscroll = !!e.target.checked;
  });

  $("#logs-clear")?.addEventListener("click", async () => {
    const ok = await TZ.confirmDialog({
      title: "Bersihkan log?",
      message: "Buffer di memory akan dikosongkan. File log di disk tidak terhapus.",
      icon: "🧹", okText: "Ya, bersihkan",
    });
    if (!ok) return;
    try {
      await TZ.api("/api/logs/clear", { method: "POST" });
      State.buffer = [];
      ["common","uncommon","warning","error","critical","security"].forEach((k) => {
        const el = $("#cnt-" + k); if (el) el.textContent = "0";
      });
      const all = $("#cnt-all"); if (all) all.textContent = "0";
      rerender();
      TZ.toast("Buffer log dibersihkan", "", "success");
    } catch (e) {
      TZ.toast("Gagal bersihkan", e.message, "error");
    }
  });

  $("#logs-export")?.addEventListener("click", () => {
    const filtered = State.buffer.filter(passesFilter);
    const blob = new Blob([JSON.stringify(filtered, null, 2)],
                          { type: "application/json" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    const ts = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);
    a.download = `topzone-logs-${ts}.json`;
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    TZ.toast("File log diunduh", a.download, "success");
  });

  // Tab activation triggers initial load
  document.addEventListener("click", (e) => {
    const tab = e.target.closest(".tab");
    if (tab && tab.dataset.tab === "logs") loadInitial();
  });
}

document.addEventListener("DOMContentLoaded", () => {
  bind();
  // Kalau tab default = logs, load duluan
  if (window.location.hash === "#logs") {
    loadInitial();
  }
});

TZ.logs = { append, rerender, loadInitial, getBuffer: () => State.buffer.slice() };

})();
