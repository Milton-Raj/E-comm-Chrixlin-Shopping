import type { MetadataRoute } from "next";
import { productHref } from "@/features/catalog/catalog";
import { getAllProducts, getCategories, getPage } from "@/features/catalog/server";
import { absoluteUrl } from "@/features/catalog/structured-data";

/**
 * Every public page Google should index, built from the API (ARCHITECTURE §8 SEO).
 * Product images are listed too, so they can appear in Google Images.
 */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [categories, products, contentPages] = await Promise.all([
    getCategories(),
    getAllProducts(),
    // Only pages the owner has published; a missing one would send Google to a 404.
    Promise.all(["contact", "shipping", "returns", "refund-policy", "terms", "privacy"].map(getPage)),
  ]);

  const pages: MetadataRoute.Sitemap = [
    { url: absoluteUrl("/"), changeFrequency: "daily", priority: 1 },
    { url: absoluteUrl("/shop"), changeFrequency: "daily", priority: 0.9 },
    { url: absoluteUrl("/categories"), changeFrequency: "weekly", priority: 0.7 },
    { url: absoluteUrl("/corporate-gifting"), changeFrequency: "monthly", priority: 0.6 },
    { url: absoluteUrl("/faq"), changeFrequency: "monthly", priority: 0.4 },
    ...contentPages.flatMap((page) => (page ? [{ url: absoluteUrl(`/pages/${page.slug}`), lastModified: page.updated_at, changeFrequency: "yearly" as const, priority: 0.3 }] : [])),
  ];

  return [
    ...pages,
    ...categories.map((category) => ({ url: absoluteUrl(`/category/${category.slug}`), changeFrequency: "weekly" as const, priority: 0.8 })),
    ...products.map((product) => ({
      url: absoluteUrl(productHref(product)),
      ...(product.updated_at ? { lastModified: product.updated_at } : {}),
      changeFrequency: "weekly" as const,
      priority: 0.8,
      images: product.images.map((image) => absoluteUrl(image.url)),
    })),
  ];
}
