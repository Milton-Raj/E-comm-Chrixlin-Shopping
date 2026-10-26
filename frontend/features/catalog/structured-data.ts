import { env } from "@/lib/env";
import { moneyToDecimal } from "@/lib/money";
import { productHref } from "./catalog";
import type { Product, ProductDetail, StockStatus } from "./types";

/**
 * schema.org builders (Product, BreadcrumbList, Organization, WebSite) so Google can show
 * price, stock and ratings in results. Values mirror exactly what the page shows.
 */

export const absoluteUrl = (path: string) => new URL(path, `${env.siteUrl}/`).toString();

export function schemaAvailability(status: StockStatus): string {
  return status === "out_of_stock" ? "https://schema.org/OutOfStock" : "https://schema.org/InStock";
}

/** First paragraph of the long description, or the short one; never empty. */
export function productSummary(product: Product | ProductDetail): string {
  const long = "description" in product ? product.description.find((p) => p.trim() !== "") : undefined;
  return (product.short_description ?? long ?? product.name).trim();
}

export function productJsonLd(product: ProductDetail): Record<string, unknown> {
  const url = absoluteUrl(productHref(product));
  const availability = schemaAvailability(product.stock_status);
  const prices = product.variants.map((v) => v.price.amount);
  const lowest = prices.length ? Math.min(...prices) : product.price.amount;
  const highest = prices.length ? Math.max(...prices) : product.price.amount;
  const currency = product.price.currency;

  const offers =
    lowest !== highest
      ? {
          "@type": "AggregateOffer",
          priceCurrency: currency,
          lowPrice: moneyToDecimal({ amount: lowest, currency }),
          highPrice: moneyToDecimal({ amount: highest, currency }),
          offerCount: product.variants.length,
          availability,
          url,
        }
      : {
          "@type": "Offer",
          priceCurrency: currency,
          price: moneyToDecimal(product.price),
          availability,
          itemCondition: "https://schema.org/NewCondition",
          url,
          seller: { "@type": "Organization", name: env.storeName },
        };

  return {
    "@context": "https://schema.org",
    "@type": "Product",
    name: product.name,
    description: productSummary(product),
    url,
    image: product.images.map((i) => absoluteUrl(i.url)),
    ...(product.variants.length === 1 && product.variants[0] ? { sku: product.variants[0].sku } : {}),
    ...(product.brand ? { brand: { "@type": "Brand", name: product.brand.name } } : {}),
    ...(product.category ? { category: product.category.name } : {}),
    ...(product.rating.count > 0
      ? { aggregateRating: { "@type": "AggregateRating", ratingValue: product.rating.average, reviewCount: product.rating.count, bestRating: 5, worstRating: 1 } }
      : {}),
    offers,
  };
}

export function breadcrumbJsonLd(items: { name: string; path: string }[]): Record<string, unknown> {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({ "@type": "ListItem", position: index + 1, name: item.name, item: absoluteUrl(item.path) })),
  };
}

export function productBreadcrumbs(product: ProductDetail, homeLabel: string): Record<string, unknown> {
  return breadcrumbJsonLd([
    { name: homeLabel, path: "/" },
    ...(product.category ? [{ name: product.category.name, path: `/category/${product.category.slug}` }] : []),
    { name: product.name, path: productHref(product) },
  ]);
}

/** Store identity + site search box, rendered on the home page. */
export function storeJsonLd(description: string): Record<string, unknown> {
  return {
    "@context": "https://schema.org",
    "@graph": [
      { "@type": "Organization", "@id": absoluteUrl("/#organization"), name: env.storeName, url: absoluteUrl("/"), description },
      {
        "@type": "WebSite",
        "@id": absoluteUrl("/#website"),
        name: env.storeName,
        url: absoluteUrl("/"),
        publisher: { "@id": absoluteUrl("/#organization") },
        potentialAction: { "@type": "SearchAction", target: { "@type": "EntryPoint", urlTemplate: `${absoluteUrl("/search")}?q={search_term_string}` }, "query-input": "required name=search_term_string" },
      },
    ],
  };
}
