import { revalidateTag } from "next/cache";
import { timingSafeEqual } from "node:crypto";

/**
 * On-demand cache invalidation called by the Laravel API after catalog/content changes
 * (ARCHITECTURE §6.17). Authenticated with a shared secret; never exposed to browsers.
 */
export async function POST(request: Request) {
  const secret = process.env.REVALIDATE_SECRET ?? "";
  const provided = request.headers.get("x-revalidate-secret") ?? "";
  const valid = secret.length > 0 && provided.length === secret.length && timingSafeEqual(Buffer.from(secret), Buffer.from(provided));
  if (!valid) {
    return Response.json({ success: false, message: "Unauthorized." }, { status: 401 });
  }

  const body = (await request.json().catch(() => ({}))) as { tags?: unknown };
  const tags = Array.isArray(body.tags) ? body.tags.filter((t): t is string => typeof t === "string" && /^[a-z0-9:_-]{1,80}$/.test(t)).slice(0, 20) : [];
  // expire: 0 — prices and stock must be correct on the very next request, never served stale.
  for (const tag of tags) revalidateTag(tag, { expire: 0 });

  return Response.json({ success: true, data: { revalidated: tags } });
}
