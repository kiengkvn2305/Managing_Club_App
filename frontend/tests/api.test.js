import test from "node:test";
import assert from "node:assert/strict";
import { createApiClient, ApiError, cookieValue } from "../src/api.js";
test("mutations obtain CSRF, decode token and include session credentials", async () => {
  const calls = [];
  const api = createApiClient({
    cookie: () => "XSRF-TOKEN=abc%3Dxyz",
    fetcher: async (url, options) => {
      calls.push({ url, options });
      return url.includes("csrf-cookie")
        ? new Response(null, { status: 204 })
        : Response.json({ data: { id: 7 } });
    },
  });
  assert.deepEqual(await api.post("/clubs", { name: "UIT FC" }), {
    data: { id: 7 },
  });
  assert.deepEqual(
    calls.map((x) => x.url),
    ["/sanctum/csrf-cookie", "/api/v1/clubs"],
  );
  assert.equal(calls[1].options.headers["X-XSRF-TOKEN"], "abc=xyz");
  assert.equal(calls[1].options.credentials, "include");
  assert.equal(calls[1].options.headers.Authorization, undefined);
});
test("validation detail survives the API boundary", async () => {
  const api = createApiClient({
    fetcher: async () =>
      Response.json(
        { message: "Invalid", errors: { email: ["Duplicate"] } },
        { status: 422 },
      ),
  });
  await assert.rejects(
    api.get("/auth/user"),
    (error) =>
      error instanceof ApiError && error.errors.email[0] === "Duplicate",
  );
});
test("expired session resets app, anonymous bootstrap remains silent", async () => {
  let resets = 0;
  const api = createApiClient({
    fetcher: async () => Response.json({}, { status: 401 }),
    onUnauthorized: () => resets++,
  });
  await assert.rejects(api.get("/clubs"));
  await assert.rejects(api.get("/auth/user", { silentUnauthorized: true }));
  assert.equal(resets, 1);
});
test("failed CSRF prevents any mutation or replay", async () => {
  const calls = [];
  const api = createApiClient({
    fetcher: async (url) => {
      calls.push(url);
      return Response.json({}, { status: 503 });
    },
  });
  await assert.rejects(api.delete("/clubs/1/members/2"));
  assert.deepEqual(calls, ["/sanctum/csrf-cookie"]);
});
test("204, invalid JSON and offline errors are handled", async () => {
  const api = createApiClient({
    fetcher: async () => new Response(null, { status: 204 }),
  });
  assert.deepEqual(await api.delete("/x"), {});
  await assert.rejects(
    createApiClient({
      fetcher: async () => new Response("oops", { status: 502 }),
    }).get("/x"),
    (e) => e.status === 502,
  );
  await assert.rejects(
    createApiClient({
      fetcher: async () => {
        throw new TypeError("offline");
      },
    }).get("/x"),
    (e) => e.status === 0,
  );
  assert.equal(cookieValue("XSRF-TOKEN=%oops", "XSRF-TOKEN"), "");
});
