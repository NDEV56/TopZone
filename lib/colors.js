/**
 * lib/colors.js — ANSI color helpers + console UI primitives
 * ──────────────────────────────────────────────────────────
 * Tidak butuh dependency eksternal (chalk, picocolors, dll).
 * Dipakai oleh seluruh modul TopZone.
 */

"use strict";

const supportsColor = (() => {
  if (process.env.NO_COLOR) return false;
  if (process.env.FORCE_COLOR) return true;
  if (process.platform === "win32") return true; // Windows 10+ ANSI dukung
  return process.stdout.isTTY === true;
})();

const wrap = (open, close) => (s) =>
  supportsColor ? `\x1b[${open}m${s}\x1b[${close}m` : String(s);

const c = {
  reset   : "\x1b[0m",
  bold    : wrap(1, 22),
  dim     : wrap(2, 22),
  italic  : wrap(3, 23),
  underline: wrap(4, 24),
  inverse : wrap(7, 27),
  black   : wrap(30, 39),
  red     : wrap(31, 39),
  green   : wrap(32, 39),
  yellow  : wrap(33, 39),
  blue    : wrap(34, 39),
  magenta : wrap(35, 39),
  cyan    : wrap(36, 39),
  white   : wrap(37, 39),
  gray    : wrap(90, 39),
  bgRed   : wrap(41, 49),
  bgGreen : wrap(42, 49),
  bgYellow: wrap(43, 49),
  bgBlue  : wrap(44, 49),
  bgCyan  : wrap(46, 49),
};

// Status badges
const badge = {
  ok      : (s) => `${c.green("✔")}  ${s}`,
  fail    : (s) => `${c.red("✖")}  ${s}`,
  warn    : (s) => `${c.yellow("⚠")}  ${s}`,
  info    : (s) => `${c.cyan("ℹ")}  ${s}`,
  step    : (s) => `${c.blue("→")}  ${s}`,
  spark   : (s) => `${c.magenta("✦")}  ${s}`,
  bullet  : (s) => `${c.gray("•")}  ${s}`,
};

// Heading helpers
function header(label, color = "cyan") {
  const fn = c[color] || c.cyan;
  return `\n${c.bold(fn(`[ ${label} ]`))}`;
}

function divider(char = "─", len = 62, color = "gray") {
  const fn = c[color] || c.gray;
  return fn(char.repeat(len));
}

function box(title, lines, color = "cyan") {
  const fn = c[color] || c.cyan;
  const width = Math.max(title.length + 4, ...lines.map(stripAnsi).map((l) => l.length + 4), 50);
  const top = "╔" + "═".repeat(width - 2) + "╗";
  const bot = "╚" + "═".repeat(width - 2) + "╝";
  const mid = lines.map((l) => {
    const padLen = width - 4 - stripAnsi(l).length;
    return "║ " + l + " ".repeat(Math.max(0, padLen)) + " ║";
  });
  const titleLine = "║ " + c.bold(title) +
    " ".repeat(Math.max(0, width - 4 - title.length)) + " ║";
  return [fn(top), fn(titleLine), fn("╠" + "═".repeat(width - 2) + "╣"),
          ...mid.map((l) => fn(l)), fn(bot)].join("\n");
}

function stripAnsi(s) {
  return String(s).replace(/\x1b\[[0-9;]*m/g, "");
}

function padAnsi(s, len, side = "right") {
  const visible = stripAnsi(s);
  const pad = Math.max(0, len - visible.length);
  return side === "right" ? s + " ".repeat(pad) : " ".repeat(pad) + s;
}

module.exports = {
  c,
  badge,
  header,
  divider,
  box,
  stripAnsi,
  padAnsi,
  supportsColor,
};
