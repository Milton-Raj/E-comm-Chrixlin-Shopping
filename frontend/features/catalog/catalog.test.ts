import { afterEach, describe, expect, it, vi } from "vitest";
import { fetchProduct, fetchProducts } from "./api";
import { discountPercent, findVariant, productHref } from "./catalog";
import type { ProductVariant } from "./types";

const money = (amount: number) => ({ amount, currency: "INR" });

describe("catalog helpers", () => {
  it("builds canonical URLs by type", () => {
    expect(productHref({ slug: "a", product_type: "digital" })).toBe("/digital/a");
    expect(productHref({ slug: "b", product_type: "physical" })).toBe("/product/b");
  });

  it("computes discounts only when compare-at is higher", () => {
    expect(discountPercent({ price: money(38500), compare_at_price: money(44000) })).toBe(13);
    expect(discountPercent({ price: money(100), compare_at_price: null })).toBeNull();
    expect(discountPercent({ price: money(100), compare_at_price: money(90) })).toBeNull();
  });

  it("finds the variant matching all selected options", () => {
    const variant = (uuid: string, options: Record<string, string>): ProductVariant => ({ uuid, sku: uuid, name: null, options, price: money(1), compare_at_price: null, available: true });
    const variants = [variant("s-oat", { Size: "S", Colour: "Oat" }), variant("m-oat", { Size: "M", Colour: "Oat" })];
    const options = [{ name: "Size", values: ["S", "M"] }, { name: "Colour", values: ["Oat"] }];

    expect(findVariant(variants, options, { Size: "M", Colour: "Oat" })?.uuid).toBe("m-oat");
    expect(findVariant(variants, options, { Size: "L", Colour: "Oat" })).toBeNull();
    expect(findVariant([variants[0]!], [], {})?.uuid).toBe("s-oat");
  });
});

describe("catalog api", () => {
  const fetchMock = vi.fn<typeof fetch>();
  afterEach(() => {
    fetchMock.mockReset();
    vi.unstubAllGlobals();
  });

  it("serialises only meaningful query params and unwraps pagination", async () => {
    vi.stubGlobal("fetch", fetchMock);
    fetchMock.mockResolvedValueOnce(new Response(JSON.stringify({ success: true, data: [], message: null, meta: { pagination: { page: 2, per_page: 24, total: 30, last_page: 2 } } })));

    const result = await fetchProducts({ category: "home", type: null, featured: false, new: true, page: 2 });

    expect(fetchMock.mock.calls[0]![0]).toBe("http://api.test/api/v1/products?category=home&new=1&page=2");
    expect(result.pagination.total).toBe(30);
  });

  it("degrades to null/empty when the API is unavailable", async () => {
    vi.stubGlobal("fetch", fetchMock);
    fetchMock.mockRejectedValue(new TypeError("offline"));

    await expect(fetchProduct("x")).resolves.toBeNull();
    await expect(fetchProducts()).resolves.toMatchObject({ items: [] });
  });
});
