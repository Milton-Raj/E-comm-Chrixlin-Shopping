import { BottomNav } from "@/components/layout/bottom-nav";
import { SiteFooter } from "@/components/layout/site-footer";
import { SiteHeader } from "@/components/layout/site-header";
import { CartDrawer } from "@/features/cart/components/cart-drawer";
import { Suspense } from "react";

/** Storefront chrome: header, footer and mobile bottom navigation. */
export default function SiteLayout({ children }: LayoutProps<"/">) {
  return (
    <>
      <SiteHeader />
      <main id="main" className="flex-1">
        {children}
      </main>
      <SiteFooter />
      {/* usePathname() is request data on dynamic routes, so the nav streams in behind Suspense. */}
      <Suspense fallback={null}>
        <BottomNav />
      </Suspense>
      <CartDrawer />
    </>
  );
}
