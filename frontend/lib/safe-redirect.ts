/**
 * Only same-origin relative paths may be used as post-login redirects,
 * preventing open redirects via `?next=https://evil.example`. Auth pages are never
 * a destination, so customers always land back where they were shopping.
 */
const AUTH_PAGES = ["/login", "/register", "/forgot-password", "/reset-password"];

export function safeRedirectPath(next: string | null | undefined, fallback = "/account"): string {
  if (!next || !next.startsWith("/") || next.startsWith("//") || next.startsWith("/\\")) {
    return fallback;
  }
  if (AUTH_PAGES.some((p) => next === p || next.startsWith(`${p}?`) || next.startsWith(`${p}/`))) {
    return fallback;
  }
  return next;
}

/** The page the visitor is on now, to come back to after signing in. */
export function currentPathForReturn(): string {
  return typeof window === "undefined" ? "/" : `${window.location.pathname}${window.location.search}`;
}
