import assert from "node:assert/strict";
import { createServer } from "node:net";
import { join } from "node:path";
import { test } from "node:test";
import { envValue, localEnvironment, requireFreePort } from "./start.mjs";

test("quick mode isolates database/session/cache/queue from configured MySQL", () => {
  const inherited = {
    DB_CONNECTION: "mysql", DB_DATABASE: "existing_database", DB_URL: "mysql://private",
    SESSION_CONNECTION: "mysql", DB_CACHE_CONNECTION: "mysql", DB_QUEUE_CONNECTION: "mysql",
    SESSION_SECURE_COOKIE: "true", MAIL_MAILER: "smtp", API_PROXY_TARGET: "http://different-api",
    APP_KEY: "unchanged-existing-key",
  };
  const original = { ...inherited };
  const directory = join(process.cwd(), ".tools/local-test");
  const env = localEnvironment(inherited, directory);
  assert.deepEqual(inherited, original);
  assert.equal(env.APP_KEY, inherited.APP_KEY);
  assert.equal(env.DB_DATABASE, join(directory, "fcms.sqlite").replaceAll("\\", "/"));
  assert.equal(env.APP_CONFIG_CACHE, join(directory, "config.php"));
  for (const name of ["DB_CONNECTION", "SESSION_CONNECTION", "DB_CACHE_CONNECTION", "DB_CACHE_LOCK_CONNECTION", "DB_QUEUE_CONNECTION"]) {
    assert.equal(env[name], "sqlite");
  }
  assert.equal(env.DB_URL, "");
  assert.equal(env.MAIL_MAILER, "log");
  assert.equal(env.SESSION_SECURE_COOKIE, "false");
  assert.equal(env.API_PROXY_TARGET, "http://127.0.0.1:8000");
});

test("env parsing supports quoted keys and production guard values", () => {
  assert.equal(envValue('APP_ENV="production"\r\n', "APP_ENV"), "production");
  assert.equal(envValue("APP_ENV = production # deployed\n", "APP_ENV"), "production");
  assert.equal(envValue('APP_KEY="base64:existing=key"\n', "APP_KEY"), "base64:existing=key");
  assert.equal(envValue("", "APP_KEY"), "");
});

test("occupied ports are reported without stopping the existing listener", async () => {
  const server = createServer();
  await new Promise((resolve) => server.listen(0, "127.0.0.1", resolve));
  const port = server.address().port;
  try {
    await assert.rejects(requireFreePort(port), /đang được dùng/);
    assert.equal(server.listening, true);
  } finally {
    await new Promise((resolve) => server.close(resolve));
  }
  await requireFreePort(port);
});
