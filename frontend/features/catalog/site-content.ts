import "server-only";
import { cacheLife, cacheTag } from "next/cache";
import { env } from "@/lib/env";
import { t, type MessageKey } from "@/lib/i18n";
import type { ApiEnvelope } from "@/types/api";

/** Owner-edited storefront text (Admin → Content → Site text) and the homepage story photo. */
export type SiteContent = { texts: Partial<Record<MessageKey, string>>; editorial_image: string | null };

const EMPTY: SiteContent = { texts: {}, editorial_image: null };

export async function getSiteContent(): Promise<SiteContent> {
  "use cache";
  cacheLife("minutes");
  cacheTag("content", "site-content");
  try {
    const response = await fetch(`${env.apiUrl}/site-content`, { headers: { Accept: "application/json" } });
    if (!response.ok) return EMPTY;
    const body = (await response.json()) as ApiEnvelope<SiteContent>;
    return body.success ? body.data : EMPTY;
  } catch {
    return EMPTY;
  }
}

/** The owner's wording for a key when set, otherwise the storefront default. */
export function siteText(content: SiteContent, key: MessageKey): string {
  return content.texts[key] || t(key);
}
