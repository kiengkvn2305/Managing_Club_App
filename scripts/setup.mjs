import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { createHash } from "node:crypto";
import { join } from "node:path";
import { root, phpPath, npmArgs, run } from "./runtime.mjs";
const php = phpPath(),
  backend = join(root, "backend"),
  tools = join(root, ".tools");
mkdirSync(tools, { recursive: true });
const composer = join(tools, "composer.phar");
if (!existsSync(composer)) {
  const response = await fetch("https://getcomposer.org/composer-stable.phar");
  if (!response.ok) throw new Error("Không tải được Composer.");
  const data = Buffer.from(await response.arrayBuffer());
  const checksum = await fetch(
    "https://getcomposer.org/composer-stable.phar.sha256sum",
  );
  if (!checksum.ok) throw new Error("Không lấy được checksum Composer.");
  const expected = (await checksum.text()).trim().split(/\s+/)[0];
  if (createHash("sha256").update(data).digest("hex") !== expected)
    throw new Error("Composer checksum mismatch.");
  writeFileSync(composer, data);
}
const envPath = join(backend, ".env");
if (!existsSync(envPath)) {
  let text = readFileSync(join(backend, ".env.example"), "utf8");
  if (process.argv.includes("--sqlite"))
    text = text
      .replace(/^DB_CONNECTION=.*$/m, "DB_CONNECTION=sqlite")
      .replace(
        /^DB_DATABASE=.*$/m,
        `DB_DATABASE=${join(backend, "database/database.sqlite").replaceAll("\\", "/")}`,
      );
  writeFileSync(envPath, text);
}
const env = readFileSync(envPath, "utf8");
const envValue = (name) => (env.match(new RegExp(`^${name}=(.*)$`, "m"))?.[1] || "").trim().replace(/^["']|["']$/g, "");
if (envValue("APP_ENV") === "production")
  throw new Error("Setup dành cho development; không chạy trên production.");
if (envValue("DB_CONNECTION") === "sqlite") {
  const file = join(backend, "database/database.sqlite");
  if (!existsSync(file)) writeFileSync(file, "");
}
// zip is already enabled in most PHP installs; XAMPP ships the DLL but disables it.
const extensions = spawnExtensions(php);
run(
  php,
  [...extensions, composer, "install", "--prefer-dist", "--no-interaction"],
  backend,
  { ...process.env, COMPOSER_HOME: join(tools, "composer-home"), COMPOSER_CACHE_DIR: join(tools, "composer-cache") },
);
if (!envValue("APP_KEY"))
  run(php, ["artisan", "key:generate", "--no-interaction"], backend);
run(php, ["artisan", "migrate", "--no-interaction"], backend);
run(
  process.execPath,
  npmArgs(["ci", "--no-fund", "--no-audit", "--cache", join(root, ".npm-cache")]),
  join(root, "frontend"),
);
run(process.execPath, npmArgs(["run", "build"]), join(root, "frontend"));
console.log(
  "Đã cài đặt. Chạy: npm run dev. MySQL 8 integration vẫn là gate nếu dùng SQLite.",
);
function spawnExtensions(binary) {
  return process.platform === "win32" && binary.toLowerCase().includes("xampp")
    ? ["-d", "extension=zip"]
    : [];
}
