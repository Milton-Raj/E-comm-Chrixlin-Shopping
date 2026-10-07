import { z } from "zod";

/**
 * Public runtime configuration, validated once (DEPLOYMENT.md §3).
 * NEXT_PUBLIC_* values are inlined at build time, so they must be referenced
 * statically here — and must never contain secrets.
 */
const publicEnvSchema = z.object({
  NEXT_PUBLIC_API_URL: z.url(),
  NEXT_PUBLIC_SITE_URL: z.url(),
  NEXT_PUBLIC_STORE_NAME: z.string().min(1).default("Commerce"),
});

const parsed = publicEnvSchema.safeParse({
  NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL,
  NEXT_PUBLIC_SITE_URL: process.env.NEXT_PUBLIC_SITE_URL,
  NEXT_PUBLIC_STORE_NAME: process.env.NEXT_PUBLIC_STORE_NAME,
});

if (!parsed.success) {
  throw new Error(
    `Invalid public environment configuration: ${z.prettifyError(parsed.error)}`,
  );
}

export const env = {
  apiUrl: parsed.data.NEXT_PUBLIC_API_URL.replace(/\/+$/, ""),
  apiOrigin: new URL(parsed.data.NEXT_PUBLIC_API_URL).origin,
  siteUrl: parsed.data.NEXT_PUBLIC_SITE_URL.replace(/\/+$/, ""),
  storeName: parsed.data.NEXT_PUBLIC_STORE_NAME,
} as const;
