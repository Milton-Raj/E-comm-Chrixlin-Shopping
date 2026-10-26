import { describe, expect, it } from "vitest";
import { productBreadcrumbs, productJsonLd, storeJsonLd } from "./structured-data";
import type { ProductDetail, ProductVariant } from "./types";

const money = (amount: number) => ({ amount, currency: "INR" });
const variant = (sku: string, amount: number): ProductVariant => ({ uuid: sku, sku, name: null, options: {}, price: money(amount), compare_at_price: null, available: true });

const product = (overrides: Partial<ProductDetail> = {}): ProductDetail => ({
  uuid: "p-1", slug: "rose-candle", name: "Rose Candle", product_type: "physical",
  brand: { slug: "chrixlin", name: "Chrixlin" }, category: { slug: "candles", name: "Candles" },
  short_description: "Hand-poured rose dessert candle.", price: money(149900), compare_at_price: null,
  rating: { average: 4.8, count: 12 }, stock_status: "in_stock", low_stock_count: null, quick_add_variant: null,
  is_new: false, is_best_seller: false, free_shipping: false, is_featured: false,
  images: [{ url: "http://api.test/storage/rose.jpg", alt: "Rose candle" }],
  description: ["Long text."], options: [], specifications: [], variants: [variant("ROSE-1", 149900)], digital: null,
  seo: { title: "Rose Candle", description: null },
  ...overrides,
});

describe("product structured data", () => {
  it("describes a single-price product with an Offer Google can show", () => {
    const data = productJsonLd(product());

    expect(data).toMatchObject({
      "@type": "Product", name: "Rose Candle", sku: "ROSE-1", url: "http://shop.test/product/rose-candle",
      image: ["http://api.test/storage/rose.jpg"], brand: { name: "Chrixlin" },
      aggregateRating: { ratingValue: 4.8, reviewCount: 12 },
      offers: { "@type": "Offer", price: "1499.00", priceCurrency: "INR", availability: "https://schema.org/InStock" },
    });
  });

  it("uses a price range when variants cost different amounts", () => {
    const data = productJsonLd(product({ variants: [variant("S", 99900), variant("L", 199900)] }));

    expect(data.offers).toMatchObject({ "@type": "AggregateOffer", lowPrice: "999.00", highPrice: "1999.00", offerCount: 2 });
    expect(data).not.toHaveProperty("sku");
  });

  it("marks sold-out products and omits ratings nobody has given", () => {
    const data = productJsonLd(product({ stock_status: "out_of_stock", rating: { average: 0, count: 0 } }));

    expect(data.offers).toMatchObject({ availability: "https://schema.org/OutOfStock" });
    expect(data).not.toHaveProperty("aggregateRating");
  });

  it("builds breadcrumbs through the category", () => {
    const crumbs = productBreadcrumbs(product(), "Home") as { itemListElement: { name: string; item: string }[] };

    expect(crumbs.itemListElement.map((c) => [c.name, c.item])).toEqual([
      ["Home", "http://shop.test/"],
      ["Candles", "http://shop.test/category/candles"],
      ["Rose Candle", "http://shop.test/product/rose-candle"],
    ]);
  });

  it("points the site search box at the search page", () => {
    expect(JSON.stringify(storeJsonLd("About us"))).toContain("http://shop.test/search?q={search_term_string}");
  });
});
