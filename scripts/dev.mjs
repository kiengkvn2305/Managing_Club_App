import { spawn } from "node:child_process";
import { join } from "node:path";
import { root, phpPath } from "./runtime.mjs";
const backend = join(root, "backend");
const processes = [
  spawn(
    phpPath(),
    [
      "-S",
      "127.0.0.1:8000",
      "-t",
      ".",
      join(
        backend,
        "vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php",
      ),
    ],
    { cwd: join(backend, "public"), stdio: "inherit", windowsHide: true },
  ),
  spawn(
    process.execPath,
    ["node_modules/vite/bin/vite.js", "--host", "127.0.0.1"],
    { cwd: join(root, "frontend"), stdio: "inherit", windowsHide: true },
  ),
  spawn(
    phpPath(),
    ["artisan", "queue:work", "--sleep=1", "--tries=3", "--timeout=30"],
    { cwd: backend, stdio: "inherit", windowsHide: true },
  ),
];
let stopping = false;
function stop(code = 0) {
  if (stopping) return;
  stopping = true;
  for (const child of processes) child.kill();
  process.exitCode = code;
  if (process.connected) process.disconnect();
}
for (const child of processes) {
  child.on("error", (error) => {
    console.error(error.message);
    stop(1);
  });
  child.on("exit", (code) => stop(code || 0));
}
process.on("SIGINT", () => stop());
process.on("SIGTERM", () => stop());
process.on("message", (message) => { if (message === "stop") stop(); });
process.on("disconnect", () => stop());
console.log(
  "FCMS PRO: http://127.0.0.1:5173 — Ctrl+C để dừng server và queue worker.",
);
