import { env } from "@/lib/env";
import type { ApiEnvelope } from "@/types/api";

/**
 * The only way the frontend talks to the Laravel API (ARCHITECTURE §7).
 * - Sends cookies (Sanctum SPA session) and an X-Request-ID.
 * - Bootstraps the CSRF cookie before mutations and retries once on 419.
 * - Unwraps the API envelope and throws a typed ApiError on failure.
 */

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code: string | null = null,
    public readonly fieldErrors: Record<string, string[]> = {},
    public readonly requestId: string | null = null,
  ) {
    super(message);
    this.name = "ApiError";
  }

  get isValidation(): boolean {
    return this.status === 422;
  }
}

type Method = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

export type RequestOptions = {
  method?: Method;
  body?: unknown;
  signal?: AbortSignal;
  headers?: Record<string, string>;
  /** Extra fetch init, e.g. Next.js `next: { tags }` for cached public reads. */
  init?: RequestInit;
};

const XSRF_COOKIE = "XSRF-TOKEN";
const isBrowser = typeof window !== "undefined";

function readCookie(name: string): string | null {
  if (!isBrowser) return null;
  const match = document.cookie
    .split("; ")
    .find((part) => part.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

async function ensureCsrfCookie(force = false): Promise<void> {
  if (!isBrowser || (!force && readCookie(XSRF_COOKIE))) return;
  await fetch(`${env.apiOrigin}/sanctum/csrf-cookie`, {
    credentials: "include",
    headers: { Accept: "application/json" },
  });
}

export function newRequestId(): string {
  return crypto.randomUUID();
}

function toFieldErrors(errors: Record<string, unknown>): Record<string, string[]> {
  const fields: Record<string, string[]> = {};
  for (const [key, value] of Object.entries(errors)) {
    if (key === "code") continue;
    if (Array.isArray(value)) {
      fields[key] = value.filter((v): v is string => typeof v === "string");
    }
  }
  return fields;
}

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  return (await apiRequestWithMeta<T>(path, options)).data;
}

/** Like apiRequest, but also returns the envelope's `meta` (e.g. pagination). */
export async function apiRequestWithMeta<T>(
  path: string,
  { method = "GET", body, signal, headers, init }: RequestOptions = {},
  isRetry = false,
): Promise<{ data: T; meta: Record<string, unknown> }> {
  const isForm = typeof FormData !== "undefined" && body instanceof FormData;
  const mutating = method !== "GET";
  if (mutating) await ensureCsrfCookie();

  const requestId = newRequestId();
  const xsrf = mutating ? readCookie(XSRF_COOKIE) : null;

  let response: Response;
  try {
    response = await fetch(`${env.apiUrl}${path.startsWith("/") ? path : `/${path}`}`, {
      ...init,
      method,
      signal,
      credentials: "include",
      headers: {
        Accept: "application/json",
        "X-Request-ID": requestId,
        ...(body !== undefined && !isForm ? { "Content-Type": "application/json" } : {}),
        ...(xsrf ? { "X-XSRF-TOKEN": xsrf } : {}),
        ...headers,
      },
      body: body === undefined ? undefined : isForm ? (body as FormData) : JSON.stringify(body),
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") throw error;
    throw new ApiError("We couldn't reach the server. Check your connection and try again.", 0, "network_error", {}, requestId);
  }

  // Expired CSRF token: refresh the cookie and retry exactly once.
  if (response.status === 419 && mutating && !isRetry) {
    await ensureCsrfCookie(true);
    return apiRequestWithMeta<T>(path, { method, body, signal, headers, init }, true);
  }

  let payload: ApiEnvelope<T> | null = null;
  try {
    payload = (await response.json()) as ApiEnvelope<T>;
  } catch {
    payload = null;
  }

  if (!response.ok || !payload || payload.success !== true) {
    const failure = payload && payload.success === false ? payload : null;
    throw new ApiError(
      failure?.message ?? "Something went wrong. Please try again.",
      response.status,
      typeof failure?.errors?.code === "string" ? failure.errors.code : null,
      failure ? toFieldErrors(failure.errors) : {},
      failure?.meta?.request_id ?? response.headers.get("X-Request-ID") ?? requestId,
    );
  }

  return { data: payload.data, meta: payload.meta ?? {} };
}

/**
 * Downloads a file (e.g. an Excel export) with the session cookie and saves it in the
 * browser. Errors arrive as the usual JSON envelope and are thrown as ApiError.
 */
export async function apiDownload(path: string, fallbackName: string): Promise<string> {
  const requestId = newRequestId();
  let response: Response;
  try {
    response = await fetch(`${env.apiUrl}${path.startsWith("/") ? path : `/${path}`}`, {
      credentials: "include",
      headers: { Accept: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json", "X-Request-ID": requestId },
    });
  } catch {
    throw new ApiError("We couldn't reach the server. Check your connection and try again.", 0, "network_error", {}, requestId);
  }
  if (!response.ok) {
    const failure = (await response.json().catch(() => null)) as { message?: string; errors?: { code?: unknown } } | null;
    throw new ApiError(failure?.message ?? "The download failed. Please try again.", response.status, typeof failure?.errors?.code === "string" ? failure.errors.code : null, {}, requestId);
  }

  const disposition = response.headers.get("Content-Disposition") ?? "";
  const name = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition)?.[1];
  const filename = name ? decodeURIComponent(name) : fallbackName;
  const url = URL.createObjectURL(await response.blob());
  const link = Object.assign(document.createElement("a"), { href: url, download: filename });
  document.body.append(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1_000);
  return filename;
}

export type Pagination = { page: number; per_page: number; total: number; last_page: number };

export const api = {
  get: <T>(path: string, options?: Omit<RequestOptions, "method" | "body">) =>
    apiRequest<T>(path, { ...options, method: "GET" }),
  getPaged: async <T>(path: string): Promise<{ items: T[]; pagination: Pagination }> => {
    const { data, meta } = await apiRequestWithMeta<T[]>(path);
    const pagination = (meta.pagination as Pagination | undefined) ?? { page: 1, per_page: data.length, total: data.length, last_page: 1 };
    return { items: data, pagination };
  },
  post: <T>(path: string, body?: unknown, options?: Omit<RequestOptions, "method" | "body">) =>
    apiRequest<T>(path, { ...options, method: "POST", body }),
  put: <T>(path: string, body?: unknown, options?: Omit<RequestOptions, "method" | "body">) =>
    apiRequest<T>(path, { ...options, method: "PUT", body }),
  patch: <T>(path: string, body?: unknown, options?: Omit<RequestOptions, "method" | "body">) =>
    apiRequest<T>(path, { ...options, method: "PATCH", body }),
  delete: <T>(path: string, body?: unknown, options?: Omit<RequestOptions, "method" | "body">) =>
    apiRequest<T>(path, { ...options, method: "DELETE", body }),
};
