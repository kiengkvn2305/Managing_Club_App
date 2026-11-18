import { spawnSync } from "node:child_process";
import { existsSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
export const root = dirname(dirname(fileURLToPath(import.meta.url)));
export function phpPath() {
  for (const candidate of [
    process.env.PHP_BIN,
    "php",
    ...(process.platform === "win32"
      ? ["C:/xampp/php/php.exe", "C:/php/php.exe"]
      : []),
  ].filter(Boolean)) {
    if (
      spawnSync(candidate, ["-v"], { stdio: "ignore", windowsHide: true })
        .status === 0
    )
      return candidate;
  }
  throw new Error(
    "PHP 8.2+ chưa có. Cài PHP hoặc đặt biến môi trường PHP_BIN tới php executable.",
  );
}
export function npmArgs(args) {
  const cli = [process.env.npm_execpath, join(dirname(process.execPath), "node_modules/npm/bin/npm-cli.js"), join(dirname(process.execPath), "../lib/node_modules/npm/bin/npm-cli.js"), "/usr/share/nodejs/npm/bin/npm-cli.js", "/usr/lib/node_modules/npm/bin/npm-cli.js"].filter(Boolean).find(existsSync);
  if (!cli) throw new Error("Không tìm thấy npm CLI. Hãy dùng npm run setup hoặc cài npm kèm Node.js.");
  return [cli, ...args];
}
export function run(command, args, cwd = root, env = process.env) {
  const result = spawnSync(command, args, {
    cwd,
    env,
    stdio: "inherit",
    windowsHide: true,
  });
  if (result.error) throw result.error;
  if (result.status !== 0)
    throw new Error(`Command failed (${result.status}): ${command}`);
}
