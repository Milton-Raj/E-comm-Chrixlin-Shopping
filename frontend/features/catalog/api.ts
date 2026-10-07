import { env } from "@/lib/env";
import type { ApiEnvelope } from "@/types/api";
import type { Category, ContentPage, Paginated, Product, ProductDetail, ProductQuery } from "./types";

/**
 * Public, cookie-less catalog reads. Safe on the server (cached) and in the browser.
 * Failures degrade to empty results so a temporarily unavailable API never breaks the page shell.
 */

function query(params: ProductQuery): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === "" || value === false) continue;
    search.set(key, value === true ? "1" : String(value));
  }
  const s = search.toString();
  return s ? `?${s}` : "";
}

async function get<T>(path: string): Promise<{ data: T; meta: Record<string, unknown> } | null> {
  try {
    const response = await fetch(`${env.apiUrl}${path}`, { headers: { Accept: "application/json" } });
    if (!response.ok) return null;
    const body = (await response.json()) as ApiEnvelope<T>;
    return body.success ? { data: body.data, meta: body.meta } : null;
  } catch {
    return null;
  }
}

export async function fetchProducts(params: ProductQuery = {}): Promise<Paginated<Product>> {
  const result = await get<Product[]>(`/products${query(params)}`);
  const pagination = (result?.meta.pagination as Paginated<Product>["pagination"] | undefined) ?? { page: 1, per_page: 24, total: 0, last_page: 1 };
  return { items: result?.data ?? [], pagination };
}

export async function fetchProduct(slug: string): Promise<ProductDetail | null> {
  return (await get<ProductDetail>(`/products/${encodeURIComponent(slug)}`))?.data ?? null;
}

export async function fetchCategories(): Promise<Category[]> {
  return (await get<Category[]>("/categories"))?.data ?? [];
}

export async function fetchCategory(slug: string): Promise<Category | null> {
  return (await get<Category>(`/categories/${encodeURIComponent(slug)}`))?.data ?? null;
}

export async function fetchPage(slug: string): Promise<ContentPage | null> {
  return (await get<ContentPage>(`/pages/${encodeURIComponent(slug)}`))?.data ?? null;
}
