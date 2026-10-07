import "server-only";
import { cacheLife, cacheTag } from "next/cache";
import { fetchCategories, fetchCategory, fetchPage, fetchProduct, fetchProducts } from "./api";
import type { ProductQuery } from "./types";

/**
 * Cached server reads (ARCHITECTURE §6.17). Tagged so the backend can invalidate them
 * on change via /api/revalidate; otherwise they refresh within minutes.
 */

export async function getProducts(params: ProductQuery = {}) {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog");
  return fetchProducts(params);
}

export async function getProduct(slug: string) {
  "use cache";
  cacheLife("minutes");
  cacheTag("catalog", `product:${slug}`);
  return fetchProduct(slug);
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
