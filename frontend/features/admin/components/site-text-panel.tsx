"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ImagePlus, RotateCcw } from "lucide-react";
import Image from "next/image";
import { useState } from "react";
import { toast } from "sonner";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { t, type MessageKey } from "@/lib/i18n";
import { ApiError } from "@/services/api-client";
import { siteContentApi, type AdminSiteContent, type SiteTextField } from "../api";
import { Field, FormActions, inputClass, Panel, textareaClass } from "./kit/admin-page";

const siteContentKey = ["admin", "site-content"] as const;
const DEFAULT_STORY_IMAGE = "/hero/arch-candle.jpg";

/** The storefront's built-in wording for a field (shown when the owner hasn't changed it). */
const defaultText = (key: string) => t(key as MessageKey);

/** Admin → Content → Site text: every editable sentence on the storefront, grouped by where it appears. */
export function SiteTextPanel() {
  const content = useQuery({ queryKey: siteContentKey, queryFn: siteContentApi.get });
  if (content.isPending) return <LoadingState lines={8} />;
  if (content.isError) return <ErrorState onRetry={() => void content.refetch()} />;
  return <SiteTextForm data={content.data} />;
}

function SiteTextForm({ data }: { data: AdminSiteContent }) {
  const queryClient = useQueryClient();
  const initial = Object.fromEntries(data.fields.map((f) => [f.key, data.texts[f.key] ?? defaultText(f.key)]));
  const [values, setValues] = useState<Record<string, string>>(initial);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const changed = data.fields.filter((f) => values[f.key] !== initial[f.key]);

  const save = useMutation({
    // Text equal to the built-in default is stored as "not customised", so future copy updates still reach it.
    mutationFn: () => siteContentApi.save(Object.fromEntries(changed.map((f) => {
      const v = values[f.key]?.trim() ?? "";
      return [f.key, v === "" || v === defaultText(f.key) ? null : v];
    }))),
    onSuccess: (d) => { queryClient.setQueryData(siteContentKey, d); setErrors({}); toast.success("Site text saved. The store updates within a minute."); },
    onError: (e) => {
      if (e instanceof ApiError) { setErrors(e.fieldErrors); toast.error(Object.values(e.fieldErrors)[0]?.[0] ?? e.message); }
      else toast.error("Something went wrong. Please try again.");
    },
  });

  const groups = [...new Set(data.fields.map((f) => f.group))];

  return (
    <form className="grid gap-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
      <p className="text-sm text-muted-foreground">
        Change any wording on your store. Clear a box, or press “Use default”, to go back to the original text. Changes appear on the store within a minute.
      </p>
      {groups.map((group) => (
        <Panel key={group} title={group}>
          {group === "Homepage story" ? <StoryPhoto image={data.editorial_image} /> : null}
          <div className="grid gap-4 md:grid-cols-2">
            {data.fields.filter((f) => f.group === group).map((f) => (
              <TextField key={f.key} field={f} value={values[f.key] ?? ""} error={errors[`texts.${f.key}`]?.[0]}
                onChange={(v) => setValues((s) => ({ ...s, [f.key]: v }))} />
            ))}
          </div>
        </Panel>
      ))}
      <div className="sticky bottom-0 z-10 -mx-1 border-t border-border bg-background/95 px-1 py-3 backdrop-blur">
        <FormActions>
          <Button type="submit" disabled={save.isPending || changed.length === 0}>{save.isPending ? "Saving…" : changed.length ? `Save ${changed.length} change${changed.length === 1 ? "" : "s"}` : "No changes"}</Button>
          {changed.length ? <Button type="button" variant="ghost" onClick={() => setValues(initial)}>Discard changes</Button> : null}
        </FormActions>
      </div>
    </form>
  );
}

function TextField({ field, value, error, onChange }: { field: SiteTextField; value: string; error?: string; onChange: (v: string) => void }) {
  const id = `st-${field.key.replace(/\W/g, "-")}`;
  const isDefault = value.trim() === defaultText(field.key);
  return (
    <Field label={field.label} htmlFor={id} error={error} hint={`${value.length}/${field.max}${isDefault ? " · default text" : " · your text"}`}
      className={field.multiline ? "md:col-span-2" : undefined}>
      <div className="grid gap-1">
        {field.multiline
          ? <textarea id={id} className={textareaClass} maxLength={field.max} value={value} onChange={(e) => onChange(e.target.value)} />
          : <input id={id} className={inputClass} maxLength={field.max} value={value} onChange={(e) => onChange(e.target.value)} />}
        {!isDefault ? (
          <button type="button" className="inline-flex min-h-8 items-center gap-1 justify-self-start text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline" onClick={() => onChange(defaultText(field.key))}>
            <RotateCcw className="size-3" aria-hidden />Use default
          </button>
        ) : null}
      </div>
    </Field>
  );
}

function StoryPhoto({ image }: { image: string | null }) {
  const queryClient = useQueryClient();
  const upload = useMutation({
    mutationFn: (file: File | null) => {
      const form = new FormData();
      if (file) form.append("image", file);
      return siteContentApi.editorialImage(form);
    },
    onSuccess: (d, file) => { queryClient.setQueryData(siteContentKey, d); toast.success(file ? "Photo updated." : "Photo reset to the default."); },
    onError: (e) => toast.error(e instanceof ApiError ? (Object.values(e.fieldErrors)[0]?.[0] ?? e.message) : "Something went wrong. Please try again."),
  });

  return (
    <div className="flex flex-wrap items-center gap-4">
      <span className="relative aspect-4/3 w-40 shrink-0 overflow-hidden bg-muted">
        <Image src={image ?? DEFAULT_STORY_IMAGE} alt="" fill sizes="160px" className="object-cover" />
      </span>
      <div className="grid gap-2">
        <p className="text-sm font-medium">Story photo</p>
        <p className="text-xs text-muted-foreground">Shown beside the story text. At least 800 × 600 px; JPG, PNG or WebP.</p>
        <div className="flex flex-wrap gap-2">
          <label className="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-sm border border-border px-3 text-sm hover:bg-muted">
            <ImagePlus className="size-4" aria-hidden />{upload.isPending ? "Uploading…" : "Change photo"}
            <input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" disabled={upload.isPending}
              onChange={(e) => { const f = e.target.files?.[0]; if (f) upload.mutate(f); e.target.value = ""; }} />
          </label>
          {image ? <Button type="button" variant="ghost" size="sm" disabled={upload.isPending} onClick={() => upload.mutate(null)}>Use default photo</Button> : null}
        </div>
      </div>
    </div>
  );
}
