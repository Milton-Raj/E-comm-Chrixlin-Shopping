/**
 * Only same-origin relative paths may be used as post-login redirects,
 * preventing open redirects via `?next=https://evil.example`.
 */
export function safeRedirectPath(next: string | null | undefined, fallback = "/account"): string {
  if (!next || !next.startsWith("/") || next.startsWith("//") || next.startsWith("/\\")) {
    return fallback;
  }
  return next;
}
