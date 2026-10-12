"use client";

import { useMutation } from "@tanstack/react-query";
import { CircleCheck, ImagePlus, Star, X } from "lucide-react";
import { usePathname } from "next/navigation";
import { useEffect, useMemo, useState, type FormEvent } from "react";
import { ButtonLink } from "@/components/button-link";
import { FormMessage, TextField } from "@/components/form-field";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { useCurrentUser } from "@/features/auth/hooks";
import { t } from "@/lib/i18n";
import { cn } from "@/lib/utils";
import { ApiError } from "@/services/api-client";
import { reviewsApi } from "../api";

const MAX_PHOTOS = 3;

export function ReviewForm({ slug, productName, onClose }: { slug: string; productName: string; onClose: () => void }) {
  const pathname = usePathname();
  const user = useCurrentUser();
  const [rating, setRating] = useState(0);
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [photos, setPhotos] = useState<File[]>([]);
  const previews = useMemo(() => photos.map((p) => URL.createObjectURL(p)), [photos]);
  useEffect(() => () => previews.forEach((u) => URL.revokeObjectURL(u)), [previews]);

  const submit = useMutation({
    mutationFn: () => {
      const form = new FormData();
      form.append("rating", String(rating));
      if (title.trim()) form.append("title", title.trim());
      form.append("body", body.trim());
      photos.forEach((p) => form.append("photos[]", p));
      return reviewsApi.submit(slug, form);
    },
  });
  const fieldErrors = submit.error instanceof ApiError ? submit.error.fieldErrors : {};
  const photoError = fieldErrors.photos?.[0] ?? Object.entries(fieldErrors).find(([k]) => k.startsWith("photos."))?.[1]?.[0];
  const formError = submit.error && !Object.keys(fieldErrors).length ? (submit.error instanceof ApiError ? submit.error.message : t("reviews.error")) : null;

  if (user.isPending) return null;
  if (!user.data) {
    return (
      <div className="border border-border bg-card p-6">
        <p>{t("reviews.signIn")}</p>
        <div className="mt-4 flex gap-3">
          <ButtonLink href={`/login?next=${encodeURIComponent(pathname)}`}>{t("reviews.signInCta")}</ButtonLink>
          <Button variant="ghost" onClick={onClose}>{t("reviews.cancel")}</Button>
        </div>
      </div>
    );
  }

  if (submit.isSuccess) {
    return (
      <div className="grid justify-items-start gap-2 border border-border bg-card p-6" role="status">
        <CircleCheck className="size-6 text-primary" aria-hidden />
        <p className="font-medium">{t("reviews.thanksTitle")}</p>
        <p className="text-sm text-muted-foreground">{t("reviews.thanksBody")}</p>
      </div>
    );
  }

  return (
    <form className="grid gap-5 border border-border bg-card p-6" noValidate onSubmit={(e: FormEvent) => { e.preventDefault(); submit.mutate(); }}>
      <p className="font-medium">{t("reviews.formTitle", { name: productName })}</p>
      <fieldset>
        <legend className="mb-2 text-sm font-medium">{t("reviews.rating")}</legend>
        <div className="flex gap-1">
          {[1, 2, 3, 4, 5].map((n) => (
            <label key={n} className="cursor-pointer">
              <input type="radio" name="rating" value={n} checked={rating === n} onChange={() => setRating(n)} className="peer sr-only" />
              <span className="inline-flex size-11 items-center justify-center rounded-sm peer-focus-visible:ring-2 peer-focus-visible:ring-ring">
                <Star aria-hidden className={cn("size-7", n <= rating ? "fill-brand-grullo text-brand-grullo" : "text-border")} />
              </span>
              <span className="sr-only">{t("reviews.stars", { n })}</span>
            </label>
          ))}
        </div>
        {fieldErrors.rating?.[0] ? <p className="mt-1 text-sm text-destructive">{t("reviews.ratingRequired")}</p> : null}
      </fieldset>
      <TextField id="r-title" label={t("reviews.titleLabel")} maxLength={120} value={title} onChange={(e) => setTitle(e.target.value)} error={fieldErrors.title?.[0]} />
      <div className="grid gap-2">
        <Label htmlFor="r-body">{t("reviews.bodyLabel")}</Label>
        <Textarea id="r-body" className="min-h-32" maxLength={3000} required value={body} onChange={(e) => setBody(e.target.value)} aria-invalid={fieldErrors.body ? true : undefined} aria-describedby={fieldErrors.body ? "r-body-error" : undefined} />
        {fieldErrors.body?.[0] ? <p id="r-body-error" className="text-sm text-destructive">{fieldErrors.body[0]}</p> : null}
      </div>
      <div className="grid gap-2">
        <span className="text-sm font-medium">{t("reviews.photos")}</span>
        <div className="flex flex-wrap gap-2">
          {previews.map((url, i) => (
            <div key={url} className="relative size-20 overflow-hidden bg-muted">
              {/* eslint-disable-next-line @next/next/no-img-element -- local blob preview */}
              <img src={url} alt="" className="size-full object-cover" />
              <button type="button" onClick={() => setPhotos(photos.filter((_, j) => j !== i))} aria-label={t("reviews.removePhoto", { n: i + 1 })} className="absolute top-0 right-0 inline-flex size-8 items-center justify-center bg-background/90">
                <X className="size-4" aria-hidden />
              </button>
            </div>
          ))}
          {photos.length < MAX_PHOTOS ? (
            <label className="inline-flex size-20 cursor-pointer flex-col items-center justify-center gap-1 border border-dashed border-border text-xs text-muted-foreground hover:border-foreground">
              <ImagePlus className="size-5" aria-hidden />{t("reviews.addPhoto")}
              <input type="file" accept="image/jpeg,image/png,image/webp" multiple className="sr-only" onChange={(e) => { setPhotos([...photos, ...Array.from(e.target.files ?? [])].slice(0, MAX_PHOTOS)); e.target.value = ""; }} />
            </label>
          ) : null}
        </div>
        <p className="text-xs text-muted-foreground">{photoError ? <span className="text-destructive">{photoError}</span> : t("reviews.photosHint")}</p>
      </div>
      <FormMessage message={formError} />
      <div className="flex flex-wrap gap-3">
        <Button type="submit" disabled={submit.isPending || rating === 0 || body.trim().length < 10}>{submit.isPending ? t("reviews.sending") : t("reviews.submit")}</Button>
        <Button type="button" variant="ghost" onClick={onClose}>{t("reviews.cancel")}</Button>
      </div>
    </form>
  );
}
