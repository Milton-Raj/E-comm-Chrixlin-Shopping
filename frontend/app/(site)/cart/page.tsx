import type { Metadata } from "next";
import { CartPage } from "@/features/cart/components/cart-page";

export const metadata: Metadata = { title: "Your bag", robots: { index: false } };

export default function BagPage() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-12 md:px-6 md:py-16">
      <h1 className="font-display-tight mb-10 text-5xl md:text-6xl">Your bag</h1>
      <CartPage />
    </div>
  );
}
