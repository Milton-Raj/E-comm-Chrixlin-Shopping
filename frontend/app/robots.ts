import type { MetadataRoute } from "next";
import { env } from "@/lib/env";

/** Crawl the shop; keep private, per-shopper and admin pages out of search (ARCHITECTURE §8 SEO). */
export default function robots(): MetadataRoute.Robots {
  return {
    rules: {
      userAgent: "*",
      allow: "/",
      disallow: ["/admin", "/cart", "/checkout", "/account", "/orders", "/downloads", "/wishlist", "/login", "/register", "/forgot-password", "/reset-password", "/revalidate", "/*?*sort="],
    },
    sitemap: `${env.siteUrl}/sitemap.xml`,
  };
}
