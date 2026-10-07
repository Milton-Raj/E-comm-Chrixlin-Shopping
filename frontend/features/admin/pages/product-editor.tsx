"use client";

import { cn } from "@/lib/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FileText, Trash2, Upload } from "lucide-react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { toast } from "sonner";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { fromMinorUnits, toMinorUnits } from "@/lib/money-input";
import { ApiError } from "@/services/api-client";
import { adminApi, type AdminProductDetail, type AdminVariant } from "../api";
import { AdminPage, Field, inputClass, Panel, RequiredNote, StatusBadge, checkboxLabelClass, textareaClass } from "../components/kit/admin-page";

type VariantRow = { uuid?: string; sku: string; name: string; options: Record<string, string>; price: string; compare: string; cost: string; stock: string; is_active: boolean; on_hand?: number | null; reserved?: number | null };

type FormState = {
  name: string; slug: string; product_type: "physical" | "digital"; status: "draft" | "active" | "archived";
  short_description: string; description: string; brand: string; category: string; tax_class: string;
  is_featured: boolean; is_new: boolean; is_best_seller: boolean; seo_title: string; seo_description: string;
  options: { name: string; values: string }[]; specifications: { label: string; value: string }[];
  variants: VariantRow[]; download_limit: string; access_days: string; format: string;
};

const toRow = (v: AdminVariant): VariantRow => ({
  uuid: v.uuid, sku: v.sku, name: v.name ?? "", options: v.options, price: fromMinorUnits(v.price), compare: fromMinorUnits(v.compare_at_price),
  cost: fromMinorUnits(v.cost_price), stock: "", is_active: v.is_active, on_hand: v.on_hand, reserved: v.reserved,
});

function initial(p?: AdminProductDetail): FormState {
  return {
    name: p?.name ?? "", slug: p?.slug ?? "", product_type: p?.product_type ?? "physical", status: p?.status ?? "draft",
    short_description: p?.short_description ?? "", description: p?.description ?? "", brand: p?.brand?.uuid ?? "", category: p?.category?.uuid ?? "",
    tax_class: p?.tax_class?.uuid ?? "", is_featured: p?.is_featured ?? false, is_new: p?.is_new ?? false, is_best_seller: p?.is_best_seller ?? false,
    seo_title: p?.seo_title ?? "", seo_description: p?.seo_description ?? "",
    options: (p?.options ?? []).map((o) => ({ name: o.name, values: o.values.join(", ") })),
    specifications: p?.specifications ?? [],
    variants: p?.variants.length ? p.variants.map(toRow) : [{ sku: "", name: "", options: {}, price: "", compare: "", cost: "", stock: "0", is_active: true }],
    download_limit: String(p?.digital?.download_limit ?? 5), access_days: p?.digital?.access_days ? String(p.digital.access_days) : "", format: p?.digital?.format ?? "",
  };
}

export function ProductEditorPage({ uuid }: { uuid: string | null }) {
  const product = useQuery({ queryKey: ["admin", "product", uuid], queryFn: () => adminApi.product(uuid!), enabled: Boolean(uuid) });
  const lookups = useQuery({ queryKey: ["admin", "lookups"], queryFn: adminApi.lookups, staleTime: 60_000 });

  if ((uuid && product.isPending) || lookups.isPending) return <LoadingState lines={10} />;
  if (product.isError || lookups.isError) return <ErrorState onRetry={() => { void product.refetch(); void lookups.refetch(); }} />;

  return <Editor key={product.data?.uuid ?? "new"} product={product.data} lookups={lookups.data} />;
}

function Editor({ product, lookups }: { product?: AdminProductDetail; lookups: NonNullable<Awaited<ReturnType<typeof adminApi.lookups>>> }) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [form, setForm] = useState<FormState>(() => initial(product));
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const set = <K extends keyof FormState>(key: K, value: FormState[K]) => setForm((f) => ({ ...f, [key]: value }));
  const err = (key: string) => errors[key]?.[0];

  const save = useMutation({
    mutationFn: () => {
      const options = form.options.filter((o) => o.name.trim()).map((o) => ({ name: o.name.trim(), values: o.values.split(",").map((v) => v.trim()).filter(Boolean) }));
      const variants = form.variants.map((v) => ({
        uuid: v.uuid, sku: v.sku.trim(), name: v.name.trim() || null, options: Object.keys(v.options).length ? v.options : null,
        price: toMinorUnits(v.price) ?? -1, compare_at_price: toMinorUnits(v.compare), cost_price: toMinorUnits(v.cost),
        is_active: v.is_active, opening_stock: v.uuid ? undefined : Number(v.stock || 0),
      }));
      return adminApi.saveProduct(product?.uuid ?? null, {
        name: form.name, slug: form.slug || null, product_type: form.product_type, status: form.status,
        short_description: form.short_description || null, description: form.description || null,
        brand: form.brand || null, category: form.category || null, tax_class: form.tax_class || null,
        is_featured: form.is_featured, is_new: form.is_new, is_best_seller: form.is_best_seller,
        seo_title: form.seo_title || null, seo_description: form.seo_description || null,
        options, specifications: form.specifications.filter((s) => s.label && s.value), variants,
        digital: form.product_type === "digital" ? { download_limit: Number(form.download_limit) || null, access_days: Number(form.access_days) || null, format: form.format || null } : null,
      });
    },
    onSuccess: (saved) => {
      setErrors({});
      toast.success(product ? "Product saved." : "Product created.");
      queryClient.setQueryData(["admin", "product", saved.uuid], saved);
      void queryClient.invalidateQueries({ queryKey: ["admin", "products"] });
      if (!product) router.replace(`/admin/products/${saved.uuid}`);
      else setForm(initial(saved));
    },
    onError: (e) => {
      if (e instanceof ApiError) setErrors(e.fieldErrors);
      toast.error(e instanceof ApiError ? e.message : "Could not save.");
    },
  });

  const generateVariants = () => {
    const options = form.options.filter((o) => o.name.trim()).map((o) => ({ name: o.name.trim(), values: o.values.split(",").map((v) => v.trim()).filter(Boolean) }));
    let combos: Record<string, string>[] = [{}];
    for (const o of options) combos = combos.flatMap((c) => o.values.map((v) => ({ ...c, [o.name]: v })));
    const base = form.variants[0];
    const prefix = (base?.sku || form.name.slice(0, 3) || "SKU").toUpperCase().replace(/[^A-Z0-9]+/g, "-");
    set("variants", combos.map((combo) => {
      const existing = form.variants.find((v) => JSON.stringify(v.options) === JSON.stringify(combo));
      const label = Object.values(combo).join(" / ");
      return existing ?? { sku: `${prefix}-${Object.values(combo).map((v) => v.slice(0, 4).toUpperCase()).join("-")}`, name: label, options: combo, price: base?.price ?? "", compare: base?.compare ?? "", cost: base?.cost ?? "", stock: "0", is_active: true };
    }));
  };

  const updateVariant = (i: number, patch: Partial<VariantRow>) => set("variants", form.variants.map((v, j) => (j === i ? { ...v, ...patch } : v)));
  const physical = form.product_type === "physical";

  return (
    <AdminPage
      title={product ? product.name : "New product"}
      description={product ? `/${product.product_type === "digital" ? "digital" : "product"}/${product.slug}` : "Create a physical or digital product."}
      actions={
        <>
          {product ? <StatusBadge value={product.status} /> : null}
          {product && product.status !== "archived" ? (
            <Button variant="outline" onClick={async () => { await adminApi.archiveProduct(product.uuid); toast.success("Archived."); router.push("/admin/products"); }}>Archive</Button>
          ) : null}
          <Button onClick={() => save.mutate()} disabled={save.isPending}>{save.isPending ? "Saving…" : "Save product"}</Button>
        </>
      }
    >
      <form className="grid gap-4 lg:grid-cols-3" onSubmit={(e: FormEvent) => { e.preventDefault(); save.mutate(); }}>
        <div className="grid content-start gap-4 lg:col-span-2">
          <Panel title="Basics" actions={<RequiredNote />}>
            <Field label="Name" htmlFor="p-name" required error={err("name")}><input id="p-name" className={inputClass} value={form.name} onChange={(e) => set("name", e.target.value)} required /></Field>
            <Field label="Short description" htmlFor="p-short" error={err("short_description")}><input id="p-short" className={inputClass} value={form.short_description} onChange={(e) => set("short_description", e.target.value)} maxLength={500} /></Field>
            <Field label="Description" htmlFor="p-desc" hint="Separate paragraphs with a blank line." error={err("description")}><textarea id="p-desc" className={cn(textareaClass, "min-h-40")} value={form.description} onChange={(e) => set("description", e.target.value)} /></Field>
          </Panel>

          <Panel title="Options & variants" actions={<Button type="button" variant="outline" size="sm" onClick={generateVariants}>Generate variants</Button>}>
            <p className="text-sm text-muted-foreground">Add options like Size or Colour (comma-separated values), then generate one variant per combination. Prices are in rupees.</p>
            {form.options.map((o, i) => (
              <div key={i} className="grid grid-cols-5 items-center gap-2">
                <input aria-label="Option name" className={cn(inputClass, "col-span-2")} placeholder="Size" value={o.name} onChange={(e) => set("options", form.options.map((x, j) => (j === i ? { ...x, name: e.target.value } : x)))} />
                <input aria-label="Option values" className={cn(inputClass, "col-span-2")} placeholder="S, M, L" value={o.values} onChange={(e) => set("options", form.options.map((x, j) => (j === i ? { ...x, values: e.target.value } : x)))} />
                <Button type="button" variant="ghost" onClick={() => set("options", form.options.filter((_, j) => j !== i))}>Remove</Button>
              </div>
            ))}
            {form.options.length < 3 ? <div><Button type="button" variant="ghost" size="sm" onClick={() => set("options", [...form.options, { name: "", values: "" }])}>+ Add option</Button></div> : null}

            <div className="overflow-x-auto">
              <table className="w-full min-w-[640px] text-sm">
                <thead className="text-left text-xs text-muted-foreground uppercase">
                  <tr><th className="p-2">Variant</th><th className="p-2">SKU<Req /></th><th className="p-2">Price ₹<Req /></th><th className="p-2">Compare ₹</th><th className="p-2">Cost ₹</th>{physical ? <th className="p-2">Stock</th> : null}<th className="p-2">Active</th></tr>
                </thead>
                <tbody>
                  {form.variants.map((v, i) => (
                    <tr key={v.uuid ?? `new-${i}`} className="border-t border-border align-middle">
                      <td className="p-2 whitespace-nowrap">{v.name || "Default"}</td>
                      <td className="p-2"><input aria-label="SKU" className={inputClass} value={v.sku} onChange={(e) => updateVariant(i, { sku: e.target.value })} /><Err msg={err(`variants.${i}.sku`)} /></td>
                      <td className="p-2"><input aria-label="Price" inputMode="decimal" className={inputClass} value={v.price} onChange={(e) => updateVariant(i, { price: e.target.value })} /><Err msg={err(`variants.${i}.price`)} /></td>
                      <td className="p-2"><input aria-label="Compare-at price" inputMode="decimal" className={inputClass} value={v.compare} onChange={(e) => updateVariant(i, { compare: e.target.value })} /><Err msg={err(`variants.${i}.compare_at_price`)} /></td>
                      <td className="p-2"><input aria-label="Cost price" inputMode="decimal" className={inputClass} value={v.cost} onChange={(e) => updateVariant(i, { cost: e.target.value })} /></td>
                      {physical ? (
                        <td className="p-2">
                          {v.uuid ? <span className="whitespace-nowrap text-muted-foreground">{v.on_hand ?? 0} on hand{v.reserved ? ` · ${v.reserved} held` : ""}</span>
                            : <input aria-label="Opening stock" inputMode="numeric" className={inputClass} value={v.stock} onChange={(e) => updateVariant(i, { stock: e.target.value.replace(/\D/g, "") })} />}
                        </td>
                      ) : null}
                      <td className="p-2 text-center"><input aria-label="Active" type="checkbox" className="size-4 accent-primary" checked={v.is_active} onChange={(e) => updateVariant(i, { is_active: e.target.checked })} /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {physical && product ? <p className="text-xs text-muted-foreground">Change stock for existing variants in Inventory — every change is recorded in the ledger.</p> : null}
          </Panel>

          <Panel title="Specifications">
            {form.specifications.map((s, i) => (
              <div key={i} className="grid grid-cols-5 items-center gap-2">
                <input aria-label="Label" className={cn(inputClass, "col-span-2")} placeholder="Material" value={s.label} onChange={(e) => set("specifications", form.specifications.map((x, j) => (j === i ? { ...x, label: e.target.value } : x)))} />
                <input aria-label="Value" className={cn(inputClass, "col-span-2")} placeholder="Full-grain leather" value={s.value} onChange={(e) => set("specifications", form.specifications.map((x, j) => (j === i ? { ...x, value: e.target.value } : x)))} />
                <Button type="button" variant="ghost" onClick={() => set("specifications", form.specifications.filter((_, j) => j !== i))}>Remove</Button>
              </div>
            ))}
            <div><Button type="button" variant="ghost" size="sm" onClick={() => set("specifications", [...form.specifications, { label: "", value: "" }])}>+ Add specification</Button></div>
          </Panel>

          {product ? <MediaPanel product={product} /> : <Panel title="Images"><p className="text-sm text-muted-foreground">Save the product first, then upload images.</p></Panel>}
          {product && product.product_type === "digital" ? <FilesPanel product={product} /> : null}
        </div>

        <div className="grid content-start gap-4">
          <Panel title="Status">
            <Field label="Status" htmlFor="p-status" required><select id="p-status" className={inputClass} value={form.status} onChange={(e) => set("status", e.target.value as FormState["status"])}><option value="draft">Draft (hidden)</option><option value="active">Active (visible)</option><option value="archived">Archived</option></select></Field>
            {!product ? (
              <Field label="Product type" htmlFor="p-type" required error={err("product_type")}><select id="p-type" className={inputClass} value={form.product_type} onChange={(e) => set("product_type", e.target.value as FormState["product_type"])}><option value="physical">Physical (shipped)</option><option value="digital">Digital (download)</option></select></Field>
            ) : null}
            {(["is_featured", "is_new", "is_best_seller"] as const).map((key) => (
              <label key={key} className={checkboxLabelClass}><input type="checkbox" className="size-4 accent-primary" checked={form[key]} onChange={(e) => set(key, e.target.checked)} />{key === "is_featured" ? "Featured" : key === "is_new" ? "New arrival" : "Best seller"}</label>
            ))}
          </Panel>
          <Panel title="Organisation">
            <Field label="Category" htmlFor="p-cat"><select id="p-cat" className={inputClass} value={form.category} onChange={(e) => set("category", e.target.value)}><option value="">None</option>{lookups.categories.map((c) => <option key={c.uuid} value={c.uuid}>{c.name}</option>)}</select></Field>
            <Field label="Brand" htmlFor="p-brand"><select id="p-brand" className={inputClass} value={form.brand} onChange={(e) => set("brand", e.target.value)}><option value="">None</option>{lookups.brands.map((b) => <option key={b.uuid} value={b.uuid}>{b.name}</option>)}</select></Field>
            <Field label="Tax class" htmlFor="p-tax" hint="Prices include GST."><select id="p-tax" className={inputClass} value={form.tax_class} onChange={(e) => set("tax_class", e.target.value)}><option value="">Default</option>{lookups.tax_classes.map((t) => <option key={t.uuid} value={t.uuid}>{t.name}</option>)}</select></Field>
          </Panel>
          {form.product_type === "digital" ? (
            <Panel title="Digital delivery">
              <Field label="Download limit" htmlFor="p-dl" required><input id="p-dl" inputMode="numeric" className={inputClass} value={form.download_limit} onChange={(e) => set("download_limit", e.target.value.replace(/\D/g, ""))} /></Field>
              <Field label="Access days" htmlFor="p-days" hint="Leave empty for lifetime access."><input id="p-days" inputMode="numeric" className={inputClass} value={form.access_days} onChange={(e) => set("access_days", e.target.value.replace(/\D/g, ""))} /></Field>
              <Field label="Format" htmlFor="p-format"><input id="p-format" className={inputClass} placeholder="EPUB + PDF" value={form.format} onChange={(e) => set("format", e.target.value)} /></Field>
            </Panel>
          ) : null}
          <Panel title="Search engines">
            <Field label="URL slug" htmlFor="p-slug" error={err("slug")} hint="Leave empty to generate from the name."><input id="p-slug" className={inputClass} value={form.slug} onChange={(e) => set("slug", e.target.value.toLowerCase())} /></Field>
            <Field label="SEO title" htmlFor="p-seo-title"><input id="p-seo-title" className={inputClass} value={form.seo_title} onChange={(e) => set("seo_title", e.target.value)} /></Field>
            <Field label="SEO description" htmlFor="p-seo-desc"><textarea id="p-seo-desc" className={cn(textareaClass, "min-h-20")} maxLength={320} value={form.seo_description} onChange={(e) => set("seo_description", e.target.value)} /></Field>
          </Panel>
        </div>
      </form>
    </AdminPage>
  );
}

function Req() {
  return (
    <>
      <span className="ml-0.5 text-red-600 normal-case" aria-hidden="true">*</span>
      <span className="sr-only"> (required)</span>
    </>
  );
}

function Err({ msg }: { msg?: string }) {
  return msg ? <p className="mt-1 text-xs text-destructive">{msg}</p> : null;
}

function MediaPanel({ product }: { product: AdminProductDetail }) {
  const queryClient = useQueryClient();
  const store = (p: AdminProductDetail) => queryClient.setQueryData(["admin", "product", p.uuid], p);
  const upload = useMutation({
    mutationFn: (file: File) => { const form = new FormData(); form.append("image", file); form.append("alt_text", product.name); return adminApi.uploadMedia(product.uuid, form); },
    onSuccess: (p) => { store(p); toast.success("Image uploaded."); },
    onError: (e) => toast.error(e instanceof Error ? e.message : "Upload failed."),
  });
  const remove = useMutation({ mutationFn: (media: string) => adminApi.deleteMedia(product.uuid, media), onSuccess: store });

  return (
    <Panel title="Images" actions={<UploadButton label="Upload image" accept="image/jpeg,image/png,image/webp" busy={upload.isPending} onFile={(f) => upload.mutate(f)} />}>
      {product.media.length ? (
        <ul className="grid grid-cols-3 gap-3 md:grid-cols-4">
          {product.media.map((m, i) => (
            <li key={m.uuid} className="group relative aspect-4/5 overflow-hidden bg-muted">
              <Image src={m.url} alt={m.alt_text ?? ""} fill sizes="160px" className="object-cover" />
              {i === 0 ? <span className="eyebrow absolute top-1 left-1 bg-background/90 px-1.5 py-0.5">Main</span> : null}
              <button type="button" aria-label="Delete image" onClick={() => remove.mutate(m.uuid)} className="absolute top-1 right-1 inline-flex size-9 items-center justify-center bg-background/90 hover:text-destructive"><Trash2 className="size-4" aria-hidden /></button>
            </li>
          ))}
        </ul>
      ) : <p className="text-sm text-muted-foreground">No images yet. JPG, PNG or WebP, at least 300×300.</p>}
    </Panel>
  );
}

function FilesPanel({ product }: { product: AdminProductDetail }) {
  const queryClient = useQueryClient();
  const store = (p: AdminProductDetail) => queryClient.setQueryData(["admin", "product", p.uuid], p);
  const upload = useMutation({
    mutationFn: (file: File) => { const form = new FormData(); form.append("file", file); return adminApi.uploadFile(product.uuid, form); },
    onSuccess: (p) => { store(p); toast.success("File uploaded to private storage."); },
    onError: (e) => toast.error(e instanceof Error ? e.message : "Upload failed."),
  });
  const remove = useMutation({ mutationFn: (file: string) => adminApi.deleteFile(product.uuid, file), onSuccess: store });

  return (
    <Panel title="Downloadable files" actions={<UploadButton label="Upload file" accept=".pdf,.zip,.epub,.mp3,.mp4,.docx,.xlsx,.pptx,.png,.jpg,.jpeg" busy={upload.isPending} onFile={(f) => upload.mutate(f)} />}>
      <p className="text-xs text-muted-foreground">Files are stored privately and only delivered to paying customers through expiring, single-use links.</p>
      {product.files.length ? (
        <ul className="divide-y divide-border text-sm">
          {product.files.map((f) => (
            <li key={f.uuid} className="flex items-center justify-between gap-3 py-2">
              <span className="flex items-center gap-2"><FileText className="size-4 text-muted-foreground" aria-hidden />{f.name} <span className="text-muted-foreground">({Math.max(1, Math.round(f.size_bytes / 1024))} KB)</span></span>
              <Button type="button" variant="ghost" size="sm" onClick={() => remove.mutate(f.uuid)}>Remove</Button>
            </li>
          ))}
        </ul>
      ) : <p className="text-sm text-primary">No files yet — customers will have nothing to download.</p>}
    </Panel>
  );
}

function UploadButton({ label, accept, busy, onFile }: { label: string; accept: string; busy: boolean; onFile: (file: File) => void }) {
  return (
    <label className={`inline-flex min-h-10 cursor-pointer items-center gap-2 border border-border px-3 text-xs font-medium tracking-wider uppercase hover:bg-muted ${busy ? "opacity-50" : ""}`}>
      <Upload className="size-4" aria-hidden /> {busy ? "Uploading…" : label}
      <input type="file" accept={accept} className="sr-only" disabled={busy} onChange={(e) => { const f = e.target.files?.[0]; if (f) onFile(f); e.target.value = ""; }} />
    </label>
  );
}
