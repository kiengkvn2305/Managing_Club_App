export class ApiError extends Error {
  constructor(message, status = 0, errors = {}) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
}
export function cookieValue(cookie, name) {
  const part = cookie
    .split(";")
    .map((value) => value.trim())
    .find((value) => value.startsWith(`${name}=`));
  if (!part) return "";
  try {
    return decodeURIComponent(part.slice(name.length + 1));
  } catch {
    return "";
  }
}
export function createApiClient({
  fetcher = (...args) => globalThis.fetch(...args),
  cookie = () => globalThis.document?.cookie || "",
  onUnauthorized = () => {},
} = {}) {
  let csrfPending;
  async function csrf() {
    if (!csrfPending)
      csrfPending = fetcher("/sanctum/csrf-cookie", {
        credentials: "include",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      })
        .then((response) => {
          if (!response.ok)
            throw new ApiError(
              "Không thể khởi tạo phiên bảo mật. Vui lòng thử lại.",
              response.status,
            );
        })
        .finally(() => {
          csrfPending = undefined;
        });
    await csrfPending;
  }
  async function request(
    path,
    { method = "GET", body, signal, silentUnauthorized = false } = {},
  ) {
    const headers = {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    };
    try {
      if (!["GET", "HEAD", "OPTIONS"].includes(method)) {
        await csrf();
        const token = cookieValue(cookie(), "XSRF-TOKEN");
        if (token) headers["X-XSRF-TOKEN"] = token;
      }
      const multipart = typeof FormData !== "undefined" && body instanceof FormData;
      if (body !== undefined && !multipart) headers["Content-Type"] = "application/json";
      const response = await fetcher(`/api/v1${path}`, {
        method,
        credentials: "include",
        headers,
        signal,
        ...(body !== undefined ? { body: multipart ? body : JSON.stringify(body) } : {}),
      });
      const raw = response.status === 204 ? "" : await response.text();
      let payload = {};
      if (raw) {
        try {
          payload = JSON.parse(raw);
        } catch {
          throw new ApiError(
            "Máy chủ trả về dữ liệu không hợp lệ. Vui lòng thử lại.",
            response.status,
          );
        }
      }
      if (!response.ok) {
        if (response.status === 401 && !silentUnauthorized) onUnauthorized();
        const fallback =
          response.status === 419
            ? "Phiên bảo mật đã hết hạn. Vui lòng thử lại."
            : response.status === 403
              ? "Bạn không có quyền thực hiện thao tác này."
              : "Không thể hoàn tất yêu cầu. Vui lòng thử lại.";
        throw new ApiError(
          payload.message || fallback,
          response.status,
          payload.errors || {},
        );
      }
      return payload;
    } catch (error) {
      if (error.name === "AbortError" || error instanceof ApiError) throw error;
      throw new ApiError(
        "Không kết nối được máy chủ. Kiểm tra kết nối mạng và thử lại.",
      );
    }
  }
  return {
    request,
    get: (path, options) => request(path, options),
    post: (path, body, options) =>
      request(path, { ...options, method: "POST", body }),
    patch: (path, body, options) =>
      request(path, { ...options, method: "PATCH", body }),
    delete: (path, options) => request(path, { ...options, method: "DELETE" }),
  };
}
