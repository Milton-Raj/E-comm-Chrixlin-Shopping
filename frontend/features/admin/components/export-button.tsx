"use client";

import { useQueryClient } from "@tanstack/react-query";
import { FileSpreadsheet, LoaderCircle } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { apiDownload, ApiError } from "@/services/api-client";
import type { AdminMe } from "@/types/api";
import { adminMeKey } from "./admin-gate";

export type ExportType = "products" | "inventory" | "orders" | "customers" | "digital" | "coupons" | "report";

/** Mirrors StoreExports::AREAS on the API: the view permission each export needs (plus exports.run). */
const NEEDS: Record<ExportType, string> = {
  products: "products.view", inventory: "inventory.view", orders: "orders.view", customers: "customers.view",
  digital: "downloads.view", coupons: "coupons.manage", report: "reports.view",
};

/** "Export to Excel" for an admin area; hidden for staff who may not export it. */
export function ExportButton({ type, from, to, label = "Export to Excel" }: { type: ExportType; from?: string; to?: string; label?: string }) {
  const me = useQueryClient().getQueryData<AdminMe>(adminMeKey);
  const [busy, setBusy] = useState(false);
  if (!me || !me.permissions.includes("exports.run") || !me.permissions.includes(NEEDS[type])) return null;

  const download = async () => {
    setBusy(true);
    const query = new URLSearchParams();
    if (from) query.set("from", from);
    if (to) query.set("to", to);
    try {
      const name = await apiDownload(`/admin/exports/${type}${query.size ? `?${query}` : ""}`, `${type}.xlsx`);
      toast.success(`Downloaded ${name}`);
    } catch (e) {
      toast.error(e instanceof ApiError ? e.message : "The export failed. Please try again.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Button type="button" variant="outline" onClick={() => void download()} disabled={busy} aria-busy={busy}>
      {busy ? <LoaderCircle className="size-4 animate-spin motion-reduce:animate-none" aria-hidden /> : <FileSpreadsheet className="size-4 text-emerald-700" aria-hidden />}
      {busy ? "Preparing…" : label}
    </Button>
  );
}
