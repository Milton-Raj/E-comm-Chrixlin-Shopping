"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Heart } from "lucide-react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { toast } from "sonner";
import { useCurrentUser } from "@/features/auth/hooks";
import type { Product } from "@/features/catalog/types";
import { t } from "@/lib/i18n";
import { cn } from "@/lib/utils";
import { api, ApiError } from "@/services/api-client";

export const wishlistKey = ["wishlist"] as const;

type WishlistEntry = { uuid: string; product: Product };

/** The signed-in customer's wishlist (empty for guests); shared by hearts, badges and the wishlist page. */
export function useWishlist() {
  const { data: user } = useCurrentUser();
  const query = useQuery({
    queryKey: [...wishlistKey, user?.uuid ?? "guest"],
    queryFn: () => api.get<WishlistEntry[]>("/wishlist"),
    enabled: Boolean(user),
    staleTime: 60_000,
  });
  const items = user ? (query.data ?? []) : [];
  return { ...query, items, has: (productUuid: string) => items.some((i) => i.product.uuid === productUuid), signedIn: Boolean(user) };
}

/** Heart toggle: filled red when saved. Guests are asked to sign in first. */
export function WishlistButton({ productUuid, name, className, withLabel = false }: { productUuid: string; name: string; className?: string; withLabel?: boolean }) {
  const router = useRouter();
  const pathname = usePathname();
  const queryClient = useQueryClient();
  const wishlist = useWishlist();
  const saved = wishlist.has(productUuid);

  const toggle = useMutation({
    mutationFn: (save: boolean) => (save ? api.post("/wishlist/items", { product_uuid: productUuid }) : api.delete(`/wishlist/items/${productUuid}`)),
    onSuccess: (_data, save) => toast.success(save ? `${name} saved to your wishlist.` : `${name} removed from your wishlist.`),
    onError: (error) => toast.error(error instanceof ApiError ? error.message : "Could not update your wishlist. Please try again."),
    onSettled: () => queryClient.invalidateQueries({ queryKey: wishlistKey }),
  });
  // Show the new state immediately while the request is in flight.
  const showSaved = toggle.isPending ? toggle.variables : saved;

  return (
    <button
      type="button"
      disabled={toggle.isPending}
      aria-pressed={showSaved}
      onClick={() => {
        if (!wishlist.signedIn) {
          toast("Sign in to save items to your wishlist.");
          router.push(`/login?next=${encodeURIComponent(pathname)}`);
          return;
        }
        toggle.mutate(!saved);
      }}
      aria-label={withLabel ? undefined : t(showSaved ? "catalog.removeFromWishlist" : "catalog.addToWishlist", { name })}
      className={cn(
        "inline-flex items-center justify-center gap-2 transition-colors",
        withLabel
          ? "min-h-12 border border-border px-5 text-sm font-semibold tracking-wide uppercase hover:border-foreground"
          : "size-11 rounded-full bg-background/85 backdrop-blur hover:bg-background",
        showSaved ? "text-red-600" : "text-foreground hover:text-red-600",
        className,
      )}
    >
      <Heart className={cn("size-5", showSaved && "fill-current")} aria-hidden />
      {withLabel ? (showSaved ? "Saved to wishlist" : "Add to wishlist") : null}
    </button>
  );
}

/** Header/bottom-nav wishlist link with a red count badge once anything is saved. */
export function WishlistLink({ className, iconClassName, children }: { className?: string; iconClassName?: string; children?: React.ReactNode }) {
  const { items } = useWishlist();
  const count = items.length;
  return (
    <Link href="/wishlist" className={className} aria-label={count ? t("nav.wishlistCount", { count }) : t("nav.wishlist")}>
      <span className="relative">
        <Heart className={cn("size-5", count > 0 && "fill-red-600 text-red-600", iconClassName)} aria-hidden />
        {count > 0 ? (
          <span className="absolute -top-2 -right-2.5 flex size-5 items-center justify-center rounded-full bg-red-600 text-xs leading-none font-semibold text-white ring-2 ring-background" aria-hidden>
            {count > 9 ? "9+" : count}
          </span>
        ) : null}
      </span>
      {children}
    </Link>
  );
}
