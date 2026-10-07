"use client";

import { useQuery } from "@tanstack/react-query";
import { Package } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { ButtonLink } from "@/components/button-link";
import { EmptyState } from "@/components/states/empty-state";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { useCurrentUser } from "@/features/auth/hooks";
import { formatMoney } from "@/lib/money";
import { ordersApi } from "../api";

export function OrdersList() {
  const router = useRouter();
  const { data: user, isPending: userPending } = useCurrentUser();
  const orders = useQuery({ queryKey: ["orders"], queryFn: ordersApi.list, enabled: Boolean(user) });

  useEffect(() => {
    if (!userPending && !user) router.replace("/login?next=/orders");
  }, [user, userPending, router]);

  if (userPending || !user || orders.isPending) return <LoadingState lines={5} />;
  if (orders.isError) return <ErrorState onRetry={() => void orders.refetch()} />;
  if (!orders.data.length) return <EmptyState icon={<Package />} title="No orders yet" description="When you place an order it will appear here." action={<ButtonLink href="/shop">Start shopping</ButtonLink>} />;

  return (
    <ul className="divide-y divide-border border-y border-border">
      {orders.data.map((o) => (
        <li key={o.uuid}>
          <Link href={`/orders/${o.order_number}`} className="grid gap-1 py-5 hover:bg-muted/40 sm:grid-cols-4 sm:items-center sm:gap-4 sm:px-3">
            <span className="font-medium">{o.order_number}</span>
            <span className="text-sm text-muted-foreground">{o.placed_at ? new Date(o.placed_at).toLocaleDateString("en-IN", { dateStyle: "medium" }) : ""} · {o.item_count} items</span>
            <span className="eyebrow">{o.status_label}</span>
            <span className="text-right font-medium">{formatMoney(o.totals.total)}</span>
          </Link>
        </li>
      ))}
    </ul>
  );
}
