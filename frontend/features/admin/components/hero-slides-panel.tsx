"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowDown, ArrowUp, ImagePlus, Pencil, Trash2 } from "lucide-react";
import Image from "next/image";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { ApiError } from "@/services/api-client";
import { heroApi, type AdminHeroSlide } from "../api";
import { checkboxLabelClass, Field, FormActions, inputClass, Panel, RequiredNote, StatusBadge, textareaClass } from "./kit/admin-page";

const heroKey = ["admin", "hero-slides"] as const;
const MAX_SLIDES = 8;
const FOCAL_POINTS = [
  { value: "50% 50%", label: "Centre" }, { value: "50% 20%", label: "Top" }, { value: "50% 80%", label: "Bottom" },
  { value: "25% 50%", label: "Left" }, { value: "75% 50%", label: "Right" },
];

/** Admin → Content: the rotating photo slides at the top of the homepage. */
export function HeroSlidesPanel() {
  const queryClient = useQueryClient();
  const slides = useQuery({ queryKey: heroKey, queryFn: heroApi.list });
  const [editing, setEditing] = useState<AdminHeroSlide | "new" | null>(null);
  const [confirmDelete, setConfirmDelete] = useState<string | null>(null);
  const refresh = () => void queryClient.invalidateQueries({ queryKey: heroKey });
  const fail = (e: unknown) => toast.error(e instanceof ApiError ? e.message : "Something went wrong. Please try again.");

  const reorder = useMutation({ mutationFn: heroApi.reorder, onSuccess: (data) => { queryClient.setQueryData(heroKey, data); toast.success("Order saved."); }, onError: fail });
  const remove = useMutation({ mutationFn: heroApi.remove, onSuccess: () => { toast.success("Slide deleted."); setConfirmDelete(null); refresh(); }, onError: fail });

  const move = (list: AdminHeroSlide[], from: number, to: number) => {
    const order = list.map((s) => s.uuid);
    const [item] = order.splice(from, 1);
    order.splice(to, 0, item!);
    reorder.mutate(order);
  };

  return (
    <Panel title="Homepage hero" actions={slides.data && slides.data.length < MAX_SLIDES && editing === null
      ? <Button size="sm" onClick={() => setEditing("new")}><ImagePlus className="size-4" aria-hidden />Add slide</Button> : null}>
      <p className="text-sm text-muted-foreground">
        The large photos that rotate at the top of your homepage. Until you add your own, the built-in candle and Jesmonite slides are shown.
        Use wide, bright photos at least 1200 × 700 pixels (2400 px wide is ideal).
      </p>

      {editing ? <SlideEditor key={editing === "new" ? "new" : editing.uuid} slide={editing === "new" ? null : editing} onDone={() => { setEditing(null); refresh(); }} /> : null}

      {slides.isPending ? <LoadingState lines={3} /> : slides.isError ? <ErrorState onRetry={() => void slides.refetch()} /> : slides.data.length === 0 ? (
        <p className="border border-dashed border-border p-4 text-sm text-muted-foreground">No custom slides yet. Your homepage is showing the 4 built-in slides.</p>
      ) : (
        <ol className="grid gap-3">
          {slides.data.map((s, i, list) => (
            <li key={s.uuid} className="flex flex-wrap items-center gap-4 border border-border p-3">
              <span className="relative aspect-video w-32 shrink-0 overflow-hidden bg-muted">
                {s.image ? <Image src={s.image} alt="" fill sizes="128px" className="object-cover" style={{ objectPosition: s.focal_point }} /> : null}
              </span>
              <span className="grid min-w-0 flex-1 gap-0.5">
                {s.eyebrow ? <span className="eyebrow text-xs text-muted-foreground">{s.eyebrow}</span> : null}
                <span className="font-medium">{s.title}</span>
                {s.cta_label ? <span className="truncate text-xs text-muted-foreground">Button: {s.cta_label} → {s.cta_url}</span> : null}
              </span>
              <StatusBadge value={s.is_active ? "active" : "draft"} />
              <span className="flex items-center gap-1">
                <Button size="icon" variant="ghost" aria-label={`Move “${s.title}” up`} disabled={i === 0 || reorder.isPending} onClick={() => move(list, i, i - 1)}><ArrowUp className="size-4" aria-hidden /></Button>
                <Button size="icon" variant="ghost" aria-label={`Move “${s.title}” down`} disabled={i === list.length - 1 || reorder.isPending} onClick={() => move(list, i, i + 1)}><ArrowDown className="size-4" aria-hidden /></Button>
                <Button size="icon" variant="ghost" aria-label={`Edit “${s.title}”`} onClick={() => setEditing(s)}><Pencil className="size-4" aria-hidden /></Button>
                {confirmDelete === s.uuid ? (
                  <>
                    <Button size="sm" variant="destructive" disabled={remove.isPending} onClick={() => remove.mutate(s.uuid)}>Delete</Button>
                    <Button size="sm" variant="ghost" onClick={() => setConfirmDelete(null)}>Keep</Button>
                  </>
                ) : (
                  <Button size="icon" variant="ghost" aria-label={`Delete “${s.title}”`} onClick={() => setConfirmDelete(s.uuid)}><Trash2 className="size-4" aria-hidden /></Button>
                )}
              </span>
            </li>
          ))}
        </ol>
      )}
    </Panel>
  );
}

function SlideEditor({ slide, onDone }: { slide: AdminHeroSlide | null; onDone: () => void }) {
  const [form, setForm] = useState({
    eyebrow: slide?.eyebrow ?? "", title: slide?.title ?? "", body: slide?.body ?? "", cta_label: slide?.cta_label ?? "", cta_url: slide?.cta_url ?? "",
    image_alt: slide?.image_alt ?? "", focal_point: slide?.focal_point ?? "50% 50%", is_active: slide?.is_active ?? true,
  });
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(slide?.image ?? null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  // Release the local preview of a picked photo when it is replaced or the editor closes.
  useEffect(() => () => { if (preview?.startsWith("blob:")) URL.revokeObjectURL(preview); }, [preview]);
  const pick = (picked: File | null) => {
    setFile(picked);
    setPreview(picked ? URL.createObjectURL(picked) : slide?.image ?? null);
  };

  const save = useMutation({
    mutationFn: () => {
      const data = new FormData();
      Object.entries(form).forEach(([k, v]) => data.append(k, typeof v === "boolean" ? (v ? "1" : "0") : v));
      if (file) data.append("image", file);
      return heroApi.save(slide?.uuid ?? null, data);
    },
    onSuccess: () => { toast.success("Slide saved. The homepage updates within a minute."); onDone(); },
    onError: (e) => {
      if (e instanceof ApiError) { setErrors(e.fieldErrors); toast.error(Object.values(e.fieldErrors)[0]?.[0] ?? e.message); }
      else toast.error("Something went wrong. Please try again.");
    },
  });
  const set = <K extends keyof typeof form>(k: K, v: (typeof form)[K]) => setForm((f) => ({ ...f, [k]: v }));
  const err = (k: string) => errors[k]?.[0];

  return (
    <form className="animate-expand grid gap-4 rounded-sm border border-dashed border-border p-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
      <div className="flex items-center justify-between gap-3">
        <h3 className="font-semibold">{slide ? "Edit slide" : "New slide"}</h3>
        <RequiredNote />
      </div>
      <div className="grid gap-4 lg:grid-cols-2">
        <div className="grid content-start gap-3">
          <div className="relative aspect-video overflow-hidden bg-brand-black">
            {preview ? <Image src={preview} alt="" fill sizes="(min-width: 1024px) 40vw, 90vw" className="object-cover opacity-80" style={{ objectPosition: form.focal_point }} unoptimized={preview.startsWith("blob:")} /> : null}
            <div className="absolute inset-x-0 bottom-0 grid gap-1 bg-linear-to-t from-brand-black/90 to-transparent p-4 text-brand-cultured">
              {form.eyebrow ? <span className="eyebrow text-xs text-brand-grullo">{form.eyebrow}</span> : null}
              <span className="font-display-tight text-2xl">{form.title || "Your headline"}</span>
            </div>
          </div>
          <Field label="Photo" htmlFor="hs-image" required={!slide} error={err("image")} hint="JPG, PNG or WebP, at least 1200 × 700 px, up to 10 MB.">
            <input id="hs-image" type="file" accept="image/jpeg,image/png,image/webp" className={`${inputClass} py-1.5`} onChange={(e) => pick(e.target.files?.[0] ?? null)} required={!slide} />
          </Field>
          <Field label="Keep in view" htmlFor="hs-focal" hint="Which part of the photo stays visible on phones and wide screens.">
            <select id="hs-focal" className={inputClass} value={form.focal_point} onChange={(e) => set("focal_point", e.target.value)}>
              {FOCAL_POINTS.map((f) => <option key={f.value} value={f.value}>{f.label}</option>)}
            </select>
          </Field>
          <Field label="Photo description" htmlFor="hs-alt" error={err("image_alt")} hint="For screen readers, e.g. “Amber candle on a stone tray”.">
            <input id="hs-alt" className={inputClass} maxLength={200} value={form.image_alt} onChange={(e) => set("image_alt", e.target.value)} />
          </Field>
        </div>
        <div className="grid content-start gap-3">
          <Field label="Small heading" htmlFor="hs-eyebrow" error={err("eyebrow")} hint="Short line above the headline, e.g. “Hand-poured in small batches”.">
            <input id="hs-eyebrow" className={inputClass} maxLength={80} value={form.eyebrow} onChange={(e) => set("eyebrow", e.target.value)} />
          </Field>
          <Field label="Headline" htmlFor="hs-title" required error={err("title")}>
            <input id="hs-title" className={inputClass} maxLength={120} value={form.title} onChange={(e) => set("title", e.target.value)} required />
          </Field>
          <Field label="Text" htmlFor="hs-body" error={err("body")}>
            <textarea id="hs-body" className={textareaClass} maxLength={300} value={form.body} onChange={(e) => set("body", e.target.value)} />
          </Field>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Button text" htmlFor="hs-cta" error={err("cta_label")}>
              <input id="hs-cta" className={inputClass} maxLength={40} placeholder="Shop candles" value={form.cta_label} onChange={(e) => set("cta_label", e.target.value)} />
            </Field>
            <Field label="Button link" htmlFor="hs-url" error={err("cta_url")} hint="A store page like /shop or /category/candles.">
              <input id="hs-url" className={inputClass} maxLength={255} placeholder="/shop" value={form.cta_url} onChange={(e) => set("cta_url", e.target.value)} />
            </Field>
          </div>
          <label className={checkboxLabelClass}>
            <input type="checkbox" className="size-4" checked={form.is_active} onChange={(e) => set("is_active", e.target.checked)} />
            Show this slide on the homepage
          </label>
        </div>
      </div>
      <FormActions>
        <Button type="submit" disabled={save.isPending}>{save.isPending ? "Saving…" : "Save slide"}</Button>
        <Button type="button" variant="ghost" onClick={onDone}>Cancel</Button>
      </FormActions>
    </form>
  );
}
