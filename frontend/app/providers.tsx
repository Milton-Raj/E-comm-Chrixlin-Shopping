"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { Suspense, useEffect, useState, type ReactNode } from "react";
import { NavigationProgress } from "@/components/navigation-progress";
import { Toaster } from "@/components/ui/sonner";
import { CartProvider } from "@/features/cart/cart-context";
import { ApiError } from "@/services/api-client";

export function Providers({ children }: { children: ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            staleTime: 30_000,
            refetchOnWindowFocus: false,
            // Never retry auth/permission/validation failures.
            retry: (failureCount, error) =>
              !(error instanceof ApiError && error.status >= 400 && error.status < 500) && failureCount < 2,
          },
        },
      }),
  );

  // A page served stale by the CDN reloads itself with ?__fresh (deployment/hostinger/app.js); tidy the address afterwards.
  useEffect(() => {
    const url = new URL(window.location.href);
    if (!url.searchParams.has("__fresh")) return;
    url.searchParams.delete("__fresh");
    window.history.replaceState(window.history.state, "", url.pathname + url.search + url.hash);
  }, []);

  return (
    <QueryClientProvider client={queryClient}>
      <Suspense fallback={null}>
        <NavigationProgress />
      </Suspense>
      <CartProvider>{children}</CartProvider>
      <Toaster position="top-center" />
    </QueryClientProvider>
  );
}
