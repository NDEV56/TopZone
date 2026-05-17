/**
 * lib/antiDdos.js — Multi-layer DDoS Protection
 * ─────────────────────────────────────────────
 *
 * Lapisan-lapisan pertahanan:
 *   1. Connection cap per-IP (prevent slow attacks)
 *   2. Token bucket (burst control, smooth rate)
 *   3. Sliding window (long-term abuse detection)
 *   4. Slowloris detector (header timeout, body timeout)
 *   5. Body size hard cap (memory-bomb prevention)
 *   6. Header count / size cap (HTTP request smuggling primitive)
 *   7. URL length cap
 *   8. Global concurrency cap (saturation safety)
 *   9. CIDR/Subnet abuse aggregation (per /24 IPv4)
 *  10. Suspicion score + auto graylist → blocklist promotion
 *  11. Adaptive throttling — di bawah serangan, ratchet limit lebih ketat
 *
 * Tidak butuh dependency eksternal. Pakai memory-store (cocok untuk
 * single-instance launcher seperti TopZone GUI).
 */

"use strict";

const { EventEmitter } = require("events");
const net = require("net");

const DEFAULTS = {
  // Token bucket per-IP
  perIpRatePerSec    : 8,         // request/detik per IP (steady state)
  perIpBurst         : 25,        // burst maksimum
  // Sliding window per-IP
  perIpWindowSec     : 60,
  perIpWindowMax     : 240,
  // Connection cap
  perIpMaxConn       : 30,
  // Subnet cap (IPv4 /24)
  perSubnetMaxConn   : 60,
  perSubnetRatePerSec: 24,
  perSubnetBurst     : 80,
  // Hard caps
  globalMaxConn      : 1000,
  maxBodyBytes       : 96 * 1024,    // 96 KB
  maxHeaderBytes     : 16 * 1024,    // 16 KB
  maxUrlLen          : 2048,
  maxHeaderCount     : 60,
  // Slowloris
  headerTimeoutMs    : 8000,         // header harus selesai dalam 8 detik
  bodyTimeoutMs      : 15000,        // body harus selesai dalam 15 detik
  idleSocketMs       : 30000,        // socket idle > 30s → kill
  // Suspicion
  suspicionGraylist  : 6,            // score → graylist (limit dipotong setengah)
  suspicionBlock     : 12,           // score → blocklist
  graylistDuration   : 5 * 60 * 1000,
  blockDuration      : 30 * 60 * 1000,
  // Adaptive
  globalRpsAttack    : 200,          // RPS global yang dianggap "under attack"
  adaptiveTighten    : 0.5,          // multiplier dipakai saat under attack
  // GC
  gcIntervalMs       : 30 * 1000,
};

// Denied response codes
const RESP = {
  RATE_LIMIT     : { code: 429, error: "Terlalu banyak request. Pelan-pelan." },
  CONN_CAP       : { code: 503, error: "Server sedang sibuk. Coba lagi sebentar." },
  GLOBAL_OVER    : { code: 503, error: "Server kelebihan beban global." },
  BLOCKED        : { code: 403, error: "Akses kamu sedang diblokir karena pola serangan." },
  GRAYLIST       : { code: 429, error: "Akses kamu dibatasi sementara." },
  TOO_LARGE_HDR  : { code: 431, error: "Header request terlalu besar." },
  TOO_LONG_URL   : { code: 414, error: "URL terlalu panjang." },
  TOO_LARGE_BODY : { code: 413, error: "Body request melebihi batas." },
  SLOW_HEADER    : { code: 408, error: "Timeout: header request terlalu lambat (slowloris?)." },
  SLOW_BODY      : { code: 408, error: "Timeout: body request terlalu lambat." },
  TOO_MANY_HDRS  : { code: 400, error: "Jumlah header melebihi batas." },
};

class AntiDdos extends EventEmitter {
  constructor(options = {}) {
    super();
    this.opt = { ...DEFAULTS, ...options };
    this.logger = options.logger || null;

    this.tokens     = new Map();   // ip -> { tokens, last }
    this.window     = new Map();   // ip -> [ts, ts, ...]
    this.subnetTok  = new Map();
    this.subnetWin  = new Map();
    this.conn       = new Map();   // ip -> activeCount
    this.subnetConn = new Map();
    this.suspicion  = new Map();   // ip -> score
    this.graylist   = new Map();   // ip -> until
    this.blocklist  = new Map();   // ip -> until + reason
    this.globalConn = 0;
    this.globalRpsCounter = { count: 0, since: Date.now() };
    this.underAttack = false;

    this._gcTimer = setInterval(() => this._gc(), this.opt.gcIntervalMs);
    if (this._gcTimer.unref) this._gcTimer.unref();
  }

  _now() { return Date.now(); }

  /** Ambil multiplier adaptif berdasarkan beban sistem. */
  _adaptive() {
    return this.underAttack ? this.opt.adaptiveTighten : 1;
  }

  /** Ekstraksi /24 untuk IPv4. IPv6 → /64 prefix. */
  _subnet(ip) {
    if (!ip) return "?";
    if (net.isIPv4(ip)) {
      const parts = ip.split(".");
      return parts[0] + "." + parts[1] + "." + parts[2] + ".0/24";
    }
    if (net.isIPv6(ip)) {
      // Take first 4 groups (64 bit prefix)
      const groups = ip.split(":").slice(0, 4);
      return groups.join(":") + "::/64";
    }
    return ip;
  }

  /** Token bucket check + decrement. */
  _bucket(map, key, ratePerSec, burst) {
    const now = this._now();
    let b = map.get(key);
    if (!b) {
      b = { tokens: burst, last: now };
      map.set(key, b);
    }
    // Refill berdasarkan elapsed
    const elapsedSec = (now - b.last) / 1000;
    b.tokens = Math.min(burst, b.tokens + elapsedSec * ratePerSec);
    b.last = now;
    if (b.tokens >= 1) {
      b.tokens -= 1;
      return { ok: true, remaining: Math.floor(b.tokens) };
    }
    return { ok: false, retryAfter: Math.ceil((1 - b.tokens) / ratePerSec) };
  }

  /** Sliding window check. */
  _windowCheck(map, key, windowSec, max) {
    const now = this._now();
    const cutoff = now - windowSec * 1000;
    let arr = map.get(key);
    if (!arr) { arr = []; map.set(key, arr); }
    // Buang yang sudah lewat (binary search style — array sudah terurut)
    let i = 0;
    while (i < arr.length && arr[i] < cutoff) i++;
    if (i > 0) arr.splice(0, i);
    if (arr.length >= max) {
      return { ok: false, count: arr.length };
    }
    arr.push(now);
    return { ok: true, count: arr.length };
  }

  /** Cek apakah IP ini loopback (localhost) — di-exempt dari blocking. */
  _isLoopback(ip) {
    if (!ip || typeof ip !== "string") return false;
    return ip === "127.0.0.1" || ip === "::1" || ip === "::ffff:127.0.0.1"
        || ip.startsWith("127.") || ip === "localhost";
  }

  /** Tambah skor curiga + auto promote ke graylist/block kalau melewati ambang. */
  bumpSuspicion(ip, points = 1, reason = "anomaly") {
    if (!ip) return;
    // EXEMPT: loopback (localhost) tidak pernah di-block — itu user/server kita sendiri
    if (this._isLoopback(ip)) return;
    const cur = (this.suspicion.get(ip) || 0) + points;
    this.suspicion.set(ip, cur);

    if (cur >= this.opt.suspicionBlock) {
      this.block(ip, this.opt.blockDuration, reason);
    } else if (cur >= this.opt.suspicionGraylist) {
      this.graylist.set(ip, this._now() + this.opt.graylistDuration);
      this.emit("graylist", { ip, score: cur, reason });
      this.logger?.security?.(`Graylist ${ip} (score ${cur}, alasan: ${reason})`, { ip, score: cur, reason });
    }
  }

  /** Decay suspicion seiring waktu (dipanggil dari _gc). */
  _decaySuspicion() {
    for (const [ip, score] of this.suspicion) {
      const next = Math.max(0, score - 1);
      if (next === 0) this.suspicion.delete(ip);
      else this.suspicion.set(ip, next);
    }
  }

  /** Manual block IP. */
  block(ip, durationMs = this.opt.blockDuration, reason = "manual") {
    if (!ip) return;
    const until = this._now() + durationMs;
    this.blocklist.set(ip, { until, reason });
    this.suspicion.delete(ip);
    this.graylist.delete(ip);
    this.emit("block", { ip, until, reason });
    this.logger?.security?.(`Block ${ip} (alasan: ${reason}, durasi: ${Math.round(durationMs/1000)}s)`, { ip, reason });
  }

  unblock(ip) {
    this.blocklist.delete(ip);
    this.graylist.delete(ip);
    this.suspicion.delete(ip);
  }

  isBlocked(ip) {
    const b = this.blocklist.get(ip);
    if (!b) return false;
    if (b.until <= this._now()) {
      this.blocklist.delete(ip);
      return false;
    }
    return b;
  }

  isGraylisted(ip) {
    const until = this.graylist.get(ip);
    if (!until) return false;
    if (until <= this._now()) {
      this.graylist.delete(ip);
      return false;
    }
    return until;
  }

  /** Dipanggil saat koneksi baru (TCP). Return { ok, reason }. */
  onConnect(ip) {
    if (this.globalConn >= this.opt.globalMaxConn) {
      return { ok: false, ...RESP.GLOBAL_OVER };
    }
    if (this.isBlocked(ip)) {
      return { ok: false, ...RESP.BLOCKED };
    }
    const adaptive = this._adaptive();
    const ipMax = Math.max(2, Math.floor(this.opt.perIpMaxConn * adaptive));
    const subnetMax = Math.max(4, Math.floor(this.opt.perSubnetMaxConn * adaptive));

    if ((this.conn.get(ip) || 0) >= ipMax) {
      this.bumpSuspicion(ip, 2, "conn-cap-ip");
      return { ok: false, ...RESP.CONN_CAP };
    }
    const sub = this._subnet(ip);
    if ((this.subnetConn.get(sub) || 0) >= subnetMax) {
      this.bumpSuspicion(ip, 1, "conn-cap-subnet");
      return { ok: false, ...RESP.CONN_CAP };
    }
    // Increment
    this.conn.set(ip, (this.conn.get(ip) || 0) + 1);
    this.subnetConn.set(sub, (this.subnetConn.get(sub) || 0) + 1);
    this.globalConn++;
    return { ok: true };
  }

  onDisconnect(ip) {
    const cur = (this.conn.get(ip) || 0) - 1;
    if (cur <= 0) this.conn.delete(ip);
    else this.conn.set(ip, cur);

    const sub = this._subnet(ip);
    const subCur = (this.subnetConn.get(sub) || 0) - 1;
    if (subCur <= 0) this.subnetConn.delete(sub);
    else this.subnetConn.set(sub, subCur);

    if (this.globalConn > 0) this.globalConn--;
  }

  /** Dipanggil per-request (setelah headers ada). */
  onRequest(req, ip) {
    // Update global RPS
    const now = this._now();
    if (now - this.globalRpsCounter.since > 1000) {
      const rps = this.globalRpsCounter.count;
      this.globalRpsCounter = { count: 0, since: now };
      // Update under-attack flag
      const wasAttack = this.underAttack;
      this.underAttack = rps >= this.opt.globalRpsAttack;
      if (this.underAttack && !wasAttack) {
        this.emit("attack-start", { rps });
        this.logger?.critical?.(`Global RPS ${rps} ≥ ambang serangan ${this.opt.globalRpsAttack}, mode adaptif aktif.`, { rps });
      } else if (!this.underAttack && wasAttack) {
        this.emit("attack-end", { rps });
        this.logger?.uncommon?.(`RPS turun ke ${rps}, mode adaptif dinonaktifkan.`);
      }
    }
    this.globalRpsCounter.count++;

    // Header & URL caps
    if ((req.url || "").length > this.opt.maxUrlLen) {
      this.bumpSuspicion(ip, 2, "long-url");
      return { ok: false, ...RESP.TOO_LONG_URL };
    }
    const headerNames = Object.keys(req.headers || {});
    if (headerNames.length > this.opt.maxHeaderCount) {
      this.bumpSuspicion(ip, 3, "many-headers");
      return { ok: false, ...RESP.TOO_MANY_HDRS };
    }
    let hdrSize = 0;
    for (const k of headerNames) {
      hdrSize += k.length;
      const v = req.headers[k];
      hdrSize += String(Array.isArray(v) ? v.join(",") : v).length;
      if (hdrSize > this.opt.maxHeaderBytes) {
        this.bumpSuspicion(ip, 3, "huge-headers");
        return { ok: false, ...RESP.TOO_LARGE_HDR };
      }
    }

    // Block / graylist
    if (this.isBlocked(ip)) return { ok: false, ...RESP.BLOCKED };
    const gray = this.isGraylisted(ip);

    // Adaptive limits
    const adaptive = this._adaptive() * (gray ? 0.5 : 1);
    const ratePerSec = Math.max(1, this.opt.perIpRatePerSec * adaptive);
    const burst      = Math.max(2, this.opt.perIpBurst * adaptive);

    // Token bucket per-IP
    const ipB = this._bucket(this.tokens, ip, ratePerSec, burst);
    if (!ipB.ok) {
      this.bumpSuspicion(ip, 1, "token-ip");
      return { ok: false, ...RESP.RATE_LIMIT, retryAfter: ipB.retryAfter };
    }

    // Sliding window per-IP
    const ipW = this._windowCheck(this.window, ip,
        this.opt.perIpWindowSec, this.opt.perIpWindowMax);
    if (!ipW.ok) {
      this.bumpSuspicion(ip, 2, "window-ip");
      return { ok: false, ...RESP.RATE_LIMIT };
    }

    // Subnet token bucket
    const sub = this._subnet(ip);
    const subB = this._bucket(this.subnetTok, sub,
        this.opt.perSubnetRatePerSec * adaptive,
        this.opt.perSubnetBurst * adaptive);
    if (!subB.ok) {
      this.bumpSuspicion(ip, 1, "token-subnet");
      return { ok: false, ...RESP.RATE_LIMIT };
    }

    return { ok: true, gray };
  }

  /** Pasang timer slowloris ke socket. Return cancel fn.
      EXEMPT: loopback tidak dipasang guard — keep-alive browser local wajar lambat. */
  attachSlowGuard(socket, ip) {
    if (!socket) return () => {};
    // Loopback (localhost): browser keep-alive idle ≠ slowloris attack.
    if (this._isLoopback(ip)) return () => {};

    const headerLimit = setTimeout(() => {
      this.bumpSuspicion(ip, 4, "slow-header");
      try { socket.destroy(new Error("slow-header timeout")); } catch (_) {}
    }, this.opt.headerTimeoutMs);

    let bodyLimit = null;
    const onHeaders = () => {
      clearTimeout(headerLimit);
      bodyLimit = setTimeout(() => {
        this.bumpSuspicion(ip, 4, "slow-body");
        try { socket.destroy(new Error("slow-body timeout")); } catch (_) {}
      }, this.opt.bodyTimeoutMs);
    };
    socket.once("headersComplete", onHeaders);

    // Idle timeout — loopback exempt sudah lewat early-return di atas
    socket.setTimeout(this.opt.idleSocketMs, () => {
      this.bumpSuspicion(ip, 1, "idle-socket");
      try { socket.destroy(); } catch (_) {}
    });

    return () => {
      clearTimeout(headerLimit);
      if (bodyLimit) clearTimeout(bodyLimit);
    };
  }

  /** Garbage collect peta lama. */
  _gc() {
    const now = this._now();

    // Buang block / graylist expired
    for (const [ip, b] of this.blocklist) if (b.until <= now) this.blocklist.delete(ip);
    for (const [ip, until] of this.graylist) if (until <= now) this.graylist.delete(ip);

    // Buang token buckets yang lama tidak dipakai (60s)
    for (const [k, b] of this.tokens) if (now - b.last > 60000) this.tokens.delete(k);
    for (const [k, b] of this.subnetTok) if (now - b.last > 60000) this.subnetTok.delete(k);

    // Trim sliding windows
    const cutoff = now - this.opt.perIpWindowSec * 1000;
    for (const [k, arr] of this.window) {
      while (arr.length && arr[0] < cutoff) arr.shift();
      if (!arr.length) this.window.delete(k);
    }
    for (const [k, arr] of this.subnetWin) {
      while (arr.length && arr[0] < cutoff) arr.shift();
      if (!arr.length) this.subnetWin.delete(k);
    }

    // Decay suspicion
    this._decaySuspicion();
  }

  stats() {
    const now = this._now();
    return {
      globalConn   : this.globalConn,
      activeIps    : this.conn.size,
      activeSubnets: this.subnetConn.size,
      blocked      : [...this.blocklist.entries()].map(([ip, b]) => ({
                       ip, until: b.until, reason: b.reason,
                       remainingSec: Math.max(0, Math.round((b.until - now)/1000)),
                     })),
      graylisted   : [...this.graylist.entries()].map(([ip, until]) => ({
                       ip, until, remainingSec: Math.max(0, Math.round((until - now)/1000)),
                     })),
      suspicion    : [...this.suspicion.entries()]
                       .sort((a, b) => b[1] - a[1]).slice(0, 20)
                       .map(([ip, score]) => ({ ip, score })),
      underAttack  : this.underAttack,
      lastGlobalRps: this.globalRpsCounter.count,
    };
  }

  shutdown() {
    if (this._gcTimer) clearInterval(this._gcTimer);
  }
}

module.exports = { AntiDdos, RESP, DEFAULTS };
