import { spawn, execFileSync } from "node:child_process";
import { existsSync, mkdirSync, readFileSync, closeSync, openSync } from "node:fs";
import { createServer } from "node:net";
import { join } from "node:path";
import { pathToFileURL } from "node:url";
import { root, phpPath, run } from "./runtime.mjs";

export function envValue(text, name) {
  const value = text.match(new RegExp(`^\\s*${name}\\s*=\\s*(.*)$`, "m"))?.[1]?.trim() || "";
  return /^["']/.test(value) ? value.slice(1, value.lastIndexOf(value[0])) : value.replace(/\s+#.*$/, "").trim();
}

export function localEnvironment(inherited, directory) {
  return {
    ...inherited,
    APP_ENV: "local",
    APP_DEBUG: "false",
    APP_URL: "http://127.0.0.1:8000",
    APP_CONFIG_CACHE: join(directory, "config.php"),
    DB_CONNECTION: "sqlite",
    DB_DATABASE: join(directory, "fcms.sqlite").replaceAll("\\", "/"),
    DB_URL: "",
    DB_FOREIGN_KEYS: "true",
    SESSION_DRIVER: "database",
    SESSION_CONNECTION: "sqlite",
    SESSION_COOKIE: "fcms_quick_session",
    SESSION_DOMAIN: "null",
    SESSION_PATH: "/",
    SESSION_SECURE_COOKIE: "false",
    SESSION_HTTP_ONLY: "true",
    SESSION_SAME_SITE: "lax",
    SESSION_ENCRYPT: "true",
    CACHE_STORE: "database",
    DB_CACHE_CONNECTION: "sqlite",
    DB_CACHE_LOCK_CONNECTION: "sqlite",
    QUEUE_CONNECTION: "database",
    DB_QUEUE_CONNECTION: "sqlite",
    MAIL_MAILER: "log",
    LOG_CHANNEL: "single",
    API_PROXY_TARGET: "http://127.0.0.1:8000",
  };
}

export async function requireFreePort(port) {
  await new Promise((resolve, reject) => {
    const server = createServer();
    server.once("error", (error) => reject(new Error(
      error.code === "EADDRINUSE"
        ? `Cổng ${port} đang được dùng. Hãy dừng cửa sổ chạy ứng dụng cũ rồi mở lại CHAY-NHANH.cmd.`
        : `Không mở được cổng ${port}: ${error.message}`,
    )));
    server.listen(port, "127.0.0.1", () => server.close(resolve));
  });
}

async function waitUntilReady(child) {
  const deadline = Date.now() + 30_000;
  while (Date.now() < deadline) {
    if (child.exitCode !== null || child.signalCode !== null || !child.connected) {
      throw new Error("Server đã dừng trước khi sẵn sàng. Xem thông báo bên trên.");
    }
    try {
      const responses = await Promise.all([
        fetch("http://127.0.0.1:8000/up", { signal: AbortSignal.timeout(1500) }),
        fetch("http://127.0.0.1:5173/api/v1/auth/user", { headers: { Accept: "application/json" }, signal: AbortSignal.timeout(1500) }),
      ]);
      const ready = responses[0].ok && responses[1].status === 401;
      await Promise.all(responses.map((response) => response.body?.cancel()));
      if (ready) return;
    } catch {
      // Servers need a moment to start; never open the browser before API proxy works.
    }
    await new Promise((resolve) => setTimeout(resolve, 250));
  }
  throw new Error("Ứng dụng chưa sẵn sàng sau 30 giây. Xem lỗi server bên trên.");
}

function openBrowser() {
  const url = "http://127.0.0.1:5173";
  const command = process.platform === "win32" ? "rundll32.exe" : process.platform === "darwin" ? "open" : "xdg-open";
  const args = process.platform === "win32" ? ["url.dll,FileProtocolHandler", url] : [url];
  const browser = spawn(command, args, { stdio: "ignore", windowsHide: true, detached: true });
  browser.once("error", () => console.log(`Hãy mở trình duyệt tại ${url}`));
  browser.unref();
}

export async function start({ open = true, directory = join(root, ".tools", "local") } = {}) {
  const [major, minor] = process.versions.node.split(".").map(Number);
  if (major < 22 || (major === 22 && minor < 12)) {
    throw new Error("Cần Node.js 22.12 trở lên; có thể cài Node.js 24 LTS.");
  }
  const backend = join(root, "backend");
  const envPath = join(backend, ".env");
  const existingEnv = existsSync(envPath) ? readFileSync(envPath, "utf8") : "";
  if ((process.env.APP_ENV || envValue(existingEnv, "APP_ENV")) === "production") {
    throw new Error("Chạy nhanh chỉ dành cho local; không dùng với APP_ENV=production.");
  }
  await Promise.all([requireFreePort(8000), requireFreePort(5173)]);
  const php = phpPath();
  const runtime = JSON.parse(execFileSync(php, ["-r", 'echo json_encode(["version" => PHP_VERSION_ID, "sqlite" => extension_loaded("pdo_sqlite")]);'], { encoding: "utf8", windowsHide: true }));
  if (runtime.version < 80200 || !runtime.sqlite) {
    throw new Error("Cần PHP 8.2+ có extension pdo_sqlite. PHP của XAMPP trên máy này có thể dùng trực tiếp.");
  }

  mkdirSync(directory, { recursive: true });
  const env = { ...localEnvironment(process.env, directory), PHP_BIN: php };
  // Append mode creates a new file, but never truncates a previous local database.
  closeSync(openSync(env.DB_DATABASE, "a"));
  console.log("FCMS PRO · CHẠY NHANH — SQLite riêng, không cần bật XAMPP/MySQL.");
  console.log("Dữ liệu MySQL không tự chuyển sang chế độ này. Cấu hình backend/.env được giữ nguyên.");

  const needsSetup = !existsSync(join(backend, "vendor/autoload.php"))
    || !existsSync(join(root, "frontend/node_modules/vite/bin/vite.js"))
    || !envValue(existingEnv, "APP_KEY");
  if (needsSetup) {
    console.log("Chuẩn bị dependencies lần đầu; cần Internet. Những lần sau không cài lại.");
    run(process.execPath, [join(root, "scripts/setup.mjs"), "--sqlite"], root, env);
  }
  run(php, ["artisan", "config:clear", "--no-ansi"], backend, env);
  run(php, ["artisan", "migrate", "--no-interaction", "--no-ansi"], backend, env);

  const child = spawn(process.execPath, [join(root, "scripts/dev.mjs")], {
    cwd: root, env, stdio: ["inherit", "inherit", "inherit", "ipc"], windowsHide: true,
  });
  let stopping = false;
  const stop = () => {
    if (stopping) return;
    stopping = true;
    if (child.connected) child.send("stop", () => {});
    else child.kill();
  };
  process.on("SIGINT", stop);
  process.on("SIGTERM", stop);
  process.on("message", (message) => { if (message === "stop") stop(); });
  process.on("disconnect", stop);
  child.on("error", (error) => { console.error(error.message); process.exitCode = 1; });
  child.on("exit", (code) => {
    if (!stopping) process.exitCode = code || 1;
    if (process.connected) process.disconnect();
  });
  try {
    await waitUntilReady(child);
    if (!stopping) {
      console.log("\nĐÃ SẴN SÀNG: http://127.0.0.1:5173");
      console.log("Giữ cửa sổ này mở. Nhấn Ctrl+C để dừng. Lần sau bấm đúp CHAY-NHANH.cmd.");
      console.log("Email xác minh local: backend/storage/logs/laravel.log (không gửi email thật).\n");
      if (open) openBrowser();
    }
  } catch (error) {
    if (stopping) return;
    stop();
    throw error;
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  start({ open: !process.argv.includes("--no-open") }).catch((error) => {
    console.error(`\nKhông khởi động được: ${error.message}`);
    process.exitCode = 1;
  });
}
