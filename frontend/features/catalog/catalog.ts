import type { Product, ProductOption, ProductSort, ProductVariant } from "./types";

/** Pure catalog helpers shared by server and client components. */

export const sortOptions: { value: ProductSort; label: string }[] = [
  { value: "featured", label: "Featured" },
  { value: "newest", label: "Newest" },
  { value: "best_selling", label: "Best selling" },
  { value: "price_asc", label: "Price: low to high" },
  { value: "price_desc", label: "Price: high to low" },
  { value: "rating", label: "Top rated" },
];

/** Canonical storefront URL: digital products live under /digital (PRD §50). */
export function productHref(product: Pick<Product, "slug" | "product_type">): string {
  return `${product.product_type === "digital" ? "/digital" : "/product"}/${product.slug}`;
}

export function discountPercent(product: Pick<Product, "price" | "compare_at_price">): number | null {
  if (!product.compare_at_price || product.compare_at_price.amount <= product.price.amount) return null;
  return Math.round((1 - product.price.amount / product.compare_at_price.amount) * 100);
}

/** The variant matching every selected option value (or the only variant). */
export function findVariant(variants: ProductVariant[], options: ProductOption[], selected: Record<string, string>): ProductVariant | null {
  if (options.length === 0) return variants[0] ?? null;
  return variants.find((v) => options.every((o) => v.options[o.name] === selected[o.name])) ?? null;
}
