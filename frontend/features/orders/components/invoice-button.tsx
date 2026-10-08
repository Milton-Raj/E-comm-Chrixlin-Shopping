"use client";

import { FileText, LoaderCircle } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { apiDownload, ApiError } from "@/services/api-client";

/**
 * Downloads the GST tax invoice PDF. `admin` uses the staff endpoint; otherwise the
 * customer endpoint, with the guest order token when there is no signed-in owner.
 */
export function InvoiceButton({ orderNumber, invoiceNumber, token, admin = false, className }: { orderNumber: string; invoiceNumber?: string | null; token?: string | null; admin?: boolean; className?: string }) {
  const [busy, setBusy] = useState(false);
  const path = `${admin ? "/admin" : ""}/orders/${encodeURIComponent(orderNumber)}/invoice`;
  const fallback = `Invoice-${(invoiceNumber ?? orderNumber).replaceAll("/", "-")}.pdf`;

  const download = async () => {
    setBusy(true);
    try {
      await apiDownload(path, fallback, { accept: "application/pdf", headers: token ? { "X-Order-Token": token } : {} });
    } catch (e) {
      toast.error(e instanceof ApiError ? e.message : "The invoice could not be downloaded. Please try again.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Button type="button" variant="outline" className={className} onClick={() => void download()} disabled={busy} aria-busy={busy}>
      {busy ? <LoaderCircle className="size-4 animate-spin motion-reduce:animate-none" aria-hidden /> : <FileText className="size-4" aria-hidden />}
      {busy ? "Preparing…" : "Download invoice"}
    </Button>
  );
}
