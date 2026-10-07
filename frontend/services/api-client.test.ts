import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { api, ApiError } from "./api-client";

function jsonResponse(status: number, body: unknown, headers: Record<string, string> = {}) {
  return new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json", ...headers } });
}

describe("api client", () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    vi.stubGlobal("fetch", fetchMock);
    document.cookie = "XSRF-TOKEN=token%3D123; path=/";
  });

  afterEach(() => {
    fetchMock.mockReset();
    vi.unstubAllGlobals();
    document.cookie = "XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
  });

  it("unwraps the success envelope and sends credentials + request id", async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { success: true, data: { ok: 1 }, message: null, meta: {} }));

    await expect(api.get<{ ok: number }>("/health")).resolves.toEqual({ ok: 1 });

    const [url, init] = fetchMock.mock.calls[0]!;
    expect(url).toBe("http://api.test/api/v1/health");
    expect(init?.credentials).toBe("include");
    expect((init?.headers as Record<string, string>)["X-Request-ID"]).toMatch(/^[0-9a-f-]{36}$/);
  });

  it("sends the decoded XSRF token on mutations", async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { success: true, data: {}, message: null, meta: {} }));

    await api.post("/auth/logout");

    const headers = fetchMock.mock.calls[0]![1]?.headers as Record<string, string>;
    expect(headers["X-XSRF-TOKEN"]).toBe("token=123");
  });

  it("throws a typed ApiError with field errors, code and request id", async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse(422, {
        success: false,
        message: "Validation failed",
        errors: { email: ["Taken."], code: "x_code" },
        meta: { request_id: "req-123" },
      }),
    );

    const error = await api.post("/auth/register", {}).catch((e: unknown) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({ status: 422, code: "x_code", requestId: "req-123", fieldErrors: { email: ["Taken."] } });
    expect((error as ApiError).isValidation).toBe(true);
  });

  it("refreshes the CSRF cookie and retries once on 419", async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(419, { success: false, message: "Session expired.", errors: {} }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(jsonResponse(200, { success: true, data: { done: true }, message: null, meta: {} }));

    await expect(api.post("/auth/login", { a: 1 })).resolves.toEqual({ done: true });
    expect(fetchMock.mock.calls[1]![0]).toBe("http://api.test/sanctum/csrf-cookie");
    expect(fetchMock).toHaveBeenCalledTimes(3);
  });

  it("maps network failures to a friendly error", async () => {
    fetchMock.mockRejectedValueOnce(new TypeError("Failed to fetch"));

    await expect(api.get("/me")).rejects.toMatchObject({ status: 0, code: "network_error" });
  });

  it("handles non-JSON error bodies", async () => {
    fetchMock.mockResolvedValueOnce(new Response("<html>502</html>", { status: 502 }));

    await expect(api.get("/me")).rejects.toMatchObject({ status: 502, message: "Something went wrong. Please try again." });
  });
});
