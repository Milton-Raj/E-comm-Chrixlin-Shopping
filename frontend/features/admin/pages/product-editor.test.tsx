import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { adminApi, type AdminProductDetail } from "../api";
import { ProductEditorPage } from "./product-editor";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn(), replace: vi.fn() }) }));

const product: AdminProductDetail = {
  uuid: "p-1", name: "Macaron Candle", slug: "macaron-candle", product_type: "physical", status: "active",
  brand: null, category: null, price: { amount: 89900, currency: "INR" }, max_price: { amount: 89900, currency: "INR" }, image: null,
  variant_count: 1, stock_on_hand: 5, stock_available: 5, units_sold: 0, updated_at: null,
  short_description: null, description: null, tax_class: null, options: [], specifications: [],
  is_featured: false, is_new: false, is_best_seller: false, free_shipping: true, seo_title: null, seo_description: null, published_at: null,
  media: [], videos: [], digital: null, files: [], pairs_with: [],
  variants: [{ uuid: "v-1", sku: "MAC-1", name: null, options: {}, price: 89900, compare_at_price: null, cost_price: null, is_active: true, on_hand: 5, reserved: 0 }],
};

function renderEditor() {
  vi.spyOn(adminApi, "product").mockResolvedValue(product);
  vi.spyOn(adminApi, "lookups").mockResolvedValue({ categories: [], brands: [], tax_classes: [] } as never);
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={client}><ProductEditorPage slug="macaron-candle" /></QueryClientProvider>);
}

describe("product editor pricing", () => {
  it("derives the selling price from MRP and discount %", async () => {
    renderEditor();
    const mrp = await screen.findByLabelText("MRP");
    expect(mrp).toHaveValue("899.00");
    await userEvent.clear(mrp);
    await userEvent.type(mrp, "1000");
    await userEvent.type(screen.getByLabelText("Discount percent"), "20");
    expect(screen.getByText("800.00")).toBeInTheDocument();
  });
});
