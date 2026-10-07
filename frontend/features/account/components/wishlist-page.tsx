"use client";

import { Reveal } from "@/components/motion/reveal";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Heart } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { ButtonLink } from "@/components/button-link";
import { EmptyState } from "@/components/states/empty-state";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { useCurrentUser } from "@/features/auth/hooks";
import { ProductCard } from "@/features/catalog/components/product-card";
import { useWishlist, wishlistKey } from "@/features/catalog/components/wishlist-button";
import { api } from "@/services/api-client";

export function WishlistPage() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const { data: user, isPending: userPending } = useCurrentUser();
  const wishlist = useWishlist();
  const remove = useMutation({
    mutationFn: (productUuid: string) => api.delete(`/wishlist/items/${productUuid}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: wishlistKey }),
  });

  useEffect(() => {
    if (!userPending && !user) router.replace("/login?next=/wishlist");
  }, [user, userPending, router]);

  if (userPending || !user || wishlist.isPending) return <LoadingState lines={4} />;
  if (wishlist.isError) return <ErrorState onRetry={() => void wishlist.refetch()} />;
  if (!wishlist.data.length) {
    return <EmptyState icon={<Heart />} title="Your wishlist is empty" description="Save products you love and come back to them any time." action={<ButtonLink href="/shop">Discover the collection</ButtonLink>} />;
  }

  return (
    <Reveal stagger>
    <ul className="grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 xl:grid-cols-4">
      {wishlist.data.map(({ uuid, product }) => (
        <li key={uuid} className="grid gap-3">
          <ProductCard product={product} />
          <button type="button" className="eyebrow inline-flex min-h-11 items-center justify-center border border-border transition-colors duration-300 hover:border-foreground hover:bg-foreground hover:text-background" onClick={() => remove.mutate(product.uuid)}>
            Remove
          </button>
        </li>
      ))}
    </ul>
    </Reveal>
  );
}
