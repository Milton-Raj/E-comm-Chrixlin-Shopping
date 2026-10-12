"use client";

import { useMutation } from "@tanstack/react-query";
import { BellRing, Check } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useCurrentUser } from "@/features/auth/hooks";
import { t } from "@/lib/i18n";
import { api, ApiError } from "@/services/api-client";

/** "Email me when it's back" for a sold-out variant; one email when stock returns. */
export function NotifyMe({ slug, variantUuid }: { slug: string; variantUuid: string }) {
  const { data: user } = useCurrentUser();
  const [typed, setTyped] = useState<string | null>(null);
  const email = typed ?? user?.email ?? "";
  const request = useMutation({ mutationFn: () => api.post<null>(`/products/${encodeURIComponent(slug)}/stock-alerts`, { variant_uuid: variantUuid, email: email.trim() }) });
  const error = request.error instanceof ApiError ? (request.error.fieldErrors.email?.[0] ?? request.error.message) : request.error ? t("catalog.notifyError") : null;

  if (request.isSuccess) {
    return <p className="flex items-center gap-2 border border-border p-4 text-sm" role="status"><Check className="size-4 text-primary" aria-hidden />{t("catalog.notifyDone")}</p>;
  }

  return (
    <form className="grid gap-2 border border-border p-4" onSubmit={(e) => { e.preventDefault(); request.mutate(); }}>
      <label htmlFor={`notify-${variantUuid}`} className="flex items-center gap-2 text-sm font-medium"><BellRing className="size-4 text-primary" aria-hidden />{t("catalog.notifyTitle")}</label>
      <div className="flex gap-2">
        <Input id={`notify-${variantUuid}`} type="email" required autoComplete="email" placeholder={t("catalog.notifyPlaceholder")} value={email} onChange={(e) => setTyped(e.target.value)} aria-invalid={error ? true : undefined} aria-describedby={error ? `notify-${variantUuid}-error` : undefined} />
        <Button type="submit" variant="outline" disabled={request.isPending || !email.trim()}>{request.isPending ? "…" : t("catalog.notifySubmit")}</Button>
      </div>
      {error ? <p id={`notify-${variantUuid}-error`} className="text-sm text-destructive">{error}</p> : <p className="text-xs text-muted-foreground">{t("catalog.notifyHint")}</p>}
    </form>
  );
}
