"use client";

import { Download } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { ApiError } from "@/services/api-client";
import { ordersApi } from "../api";

/** Requests a fresh single-use link (5-minute expiry) and starts the download. */
export function DownloadButton({ entitlement, file, name, disabled, token, onDownloaded }: {
  entitlement: string; file: string; name: string; disabled?: boolean; token?: string | null; onDownloaded?: () => void;
}) {
  const [busy, setBusy] = useState(false);

  return (
    <Button
      variant="outline"
      disabled={disabled || busy}
      onClick={async () => {
        setBusy(true);
        try {
          const { url } = await ordersApi.downloadLink(entitlement, file, token);
          window.location.assign(url);
          onDownloaded?.();
        } catch (error) {
          toast.error(error instanceof ApiError ? error.message : "Download failed. Please try again.");
        } finally {
          setBusy(false);
        }
      }}
    >
      <Download className="size-4" aria-hidden /> {name}
    </Button>
  );
}
