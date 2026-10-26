import "server-only";
import { cacheLife, cacheTag } from "next/cache";
import { fetchCategories, fetchCategory, fetchHeroSlides, fetchPage, fetchProduct, fetchProducts, fetchRelated } from "./api";
import type { Product, ProductQuery } from "./types";

/**
 * Cached server reads (ARCHITECTURE §6.17). Tagged so the backend can invalidate them
 * on change via /revalidate; otherwise they refresh within minutes.
 */

export async function getProducts(params: ProductQuery = {}) {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog");
  return fetchProducts(params);
}

/** Every listed product, page by page (sitemap, product feed). Capped so a huge catalog cannot stall a request. */
export async function getAllProducts(params: ProductQuery = {}): Promise<Product[]> {
  "use cache";
  cacheLife("hours");
  cacheTag("catalog");
  const perPage = 60; // API maximum
  const first = await fetchProducts({ ...params, per_page: perPage, page: 1 });
  const lastPage = Math.min(first.pagination.last_page, 200);
  const rest = await Promise.all(Array.from({ length: lastPage - 1 }, (_, i) => fetchProducts({ ...params, per_page: perPage, page: i + 2 })));

  return [first, ...rest].flatMap((page) => page.items);
}

export async function getProduct(slug: string) {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog", `product:${slug}`);
  return fetchProduct(slug);
}

export async function getRelated(slug: string) {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog", `product:${slug}`);
  return fetchRelated(slug);
}

export async function getCategories() {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog");
  return fetchCategories();
}

export async function getCategory(slug: string) {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog");
  return fetchCategory(slug);
}

export async function getPage(slug: string) {
  "use cache";
  cacheLife("minutes");
  cacheTag("content", `page:${slug}`);
  return fetchPage(slug);
}

export async function getHeroSlides() {
  "use cache";
  cacheLife("minutes");
  cacheTag("content", "hero");
  return fetchHeroSlides();
}
