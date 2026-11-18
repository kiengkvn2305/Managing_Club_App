import assert from "node:assert/strict";
const origin = process.env.FCMS_TEST_BASE_URL || "http://127.0.0.1:5173";
if (!["127.0.0.1", "localhost"].includes(new URL(origin).hostname))
  throw new Error("Smoke test chỉ được chạy trên localhost.");
class Session {
  cookies = new Map();
  async request(method, path, body, sendCsrf = true) {
    const headers = {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      Cookie: [...this.cookies].map(([k, v]) => `${k}=${v}`).join("; "),
    };
    if (body !== undefined) headers["Content-Type"] = "application/json";
    if (sendCsrf && this.cookies.has("XSRF-TOKEN"))
      headers["X-XSRF-TOKEN"] = decodeURIComponent(
        this.cookies.get("XSRF-TOKEN"),
      );
    const response = await fetch(origin + path, {
      method,
      headers,
      ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    for (const cookie of response.headers.getSetCookie()) {
      const pair = cookie.split(";")[0],
        index = pair.indexOf("=");
      this.cookies.set(pair.slice(0, index), pair.slice(index + 1));
    }
    const text = await response.text();
    let data;
    try {
      data = text ? JSON.parse(text) : {};
    } catch {
      throw new Error(`Non-JSON ${path}: ${text.slice(0, 100)}`);
    }
    return { status: response.status, data, headers: response.headers };
  }
}
const suffix = Date.now(),
  owner = new Session(),
  outsider = new Session();
assert.equal((await owner.request("GET", "/api/v1/clubs")).status, 401);
assert.equal(
  (
    await owner.request(
      "POST",
      "/api/v1/auth/register",
      { name: "CSRF Test" },
      false,
    )
  ).status,
  419,
);
assert.equal((await owner.request("GET", "/sanctum/csrf-cookie")).status, 204);
const account = {
  name: "QA Owner",
  email: `qa${suffix}@example.test`,
  password: "SmokePassword123!",
  password_confirmation: "SmokePassword123!",
};
assert.equal(
  (await owner.request("POST", "/api/v1/auth/register", account)).status,
  201,
);
const created = await owner.request("POST", "/api/v1/clubs", {
  name: `QA Smoke ${suffix}`,
  format: "7",
});
assert.equal(created.status, 201);
const club = created.data.data.id,
  path = `/api/v1/clubs/${club}`;
const team = await owner.request("POST", path + "/teams", {
  name: "QA First Team",
  format: "7",
});
assert.equal(team.status, 201);
const player = await owner.request("POST", path + "/members", {
  full_name: "Nguyễn Văn QA",
  team_id: team.data.data.id,
  jersey_number: 9,
  status: "active",
  club_id: 99999,
});
assert.equal(player.status, 201);
assert.equal(player.data.data.club_id, club);
const read = await owner.request("GET", path + "/members?q=QA");
assert.equal(read.data.meta.total, 1);
assert.match(read.headers.get("cache-control"), /no-store/);
const dashboard = await owner.request("GET", path + "/dashboard");
assert.equal(dashboard.data.data.members_count, 1);
await outsider.request("GET", "/sanctum/csrf-cookie");
assert.equal(
  (
    await outsider.request("POST", "/api/v1/auth/register", {
      ...account,
      name: "QA Outsider",
      email: `outsider${suffix}@example.test`,
    })
  ).status,
  201,
);
assert.equal((await outsider.request("GET", path + "/members")).status, 404);
assert.equal(
  (await outsider.request("DELETE", path + `/members/${player.data.data.id}`))
    .status,
  404,
);
assert.equal(
  (await owner.request("DELETE", path + `/members/${player.data.data.id}`))
    .status,
  204,
);
assert.equal(
  (await owner.request("POST", "/api/v1/auth/logout", {})).status,
  204,
);
assert.equal((await owner.request("GET", "/api/v1/clubs")).status, 401);
console.log(
  "PASS: real HTTP session, CSRF 419, registration, club/team/member CRUD, counts, no-store, tenant IDOR and logout. Test records are confined to the configured localhost database.",
);
