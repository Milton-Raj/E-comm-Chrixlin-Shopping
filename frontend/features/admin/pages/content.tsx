"use client";

import { cn } from "@/lib/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { toast } from "sonner";
import { ButtonLink } from "@/components/button-link";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { ApiError } from "@/services/api-client";
import { miscAdminApi, type AdminPageItem } from "../api";
import { AdminPage, Field, inputClass, Panel, RequiredNote, StatusBadge, textareaClass } from "../components/kit/admin-page";

export function ContentPage() {
  const pages = useQuery({ queryKey: ["admin", "pages"], queryFn: miscAdminApi.pages });
  return (
    <AdminPage title="Content" description="Policy, help and information pages linked from the storefront footer." actions={<ButtonLink href="/admin/content/new">New page</ButtonLink>}>
      {pages.isPending ? <LoadingState lines={6} /> : pages.isError ? <ErrorState onRetry={() => void pages.refetch()} /> : (
        <ul className="divide-y divide-border border border-border bg-card text-sm">
          {pages.data.map((p) => (
            <li key={p.uuid}>
              <Link href={`/admin/content/${p.uuid}`} className="flex items-center justify-between gap-3 p-3 hover:bg-muted/40">
                <span><span className="font-medium">{p.title}</span> <span className="text-muted-foreground">/{p.slug === "faq" ? "faq" : `pages/${p.slug}`}</span></span>
                <span className="flex items-center gap-3"><StatusBadge value={p.status} /><span className="text-xs text-muted-foreground">{new Date(p.updated_at).toLocaleDateString("en-IN")}</span></span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </AdminPage>
  );
}

export function PageEditor({ uuid }: { uuid: string | null }) {
  const page = useQuery({ queryKey: ["admin", "page", uuid], queryFn: () => miscAdminApi.page(uuid!), enabled: Boolean(uuid) });
  if (uuid && page.isPending) return <LoadingState lines={8} />;
  if (page.isError) return <ErrorState onRetry={() => void page.refetch()} />;
  return <Editor key={page.data?.uuid ?? "new"} page={page.data ?? null} />;
}

function Editor({ page }: { page: AdminPageItem | null }) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const [f, setF] = useState({ title: page?.title ?? "", slug: page?.slug ?? "", body: page?.body ?? "", status: page?.status ?? "draft", seo_description: page?.seo_description ?? "" });
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const save = useMutation({
    mutationFn: () => miscAdminApi.savePage(page?.uuid ?? null, { ...f, seo_description: f.seo_description || null }),
    onSuccess: (saved) => { toast.success("Page saved."); void queryClient.invalidateQueries({ queryKey: ["admin", "pages"] }); if (!page) router.replace(`/admin/content/${saved.uuid}`); },
    onError: (e) => { if (e instanceof ApiError) setErrors(e.fieldErrors); toast.error(e instanceof ApiError ? e.message : "Could not save."); },
  });
  const remove = useMutation({ mutationFn: () => miscAdminApi.deletePage(page!.uuid), onSuccess: () => { toast.success("Page deleted."); router.push("/admin/content"); } });

  return (
    <AdminPage title={page ? page.title : "New page"} actions={<>{page ? <Button variant="ghost" onClick={() => remove.mutate()}>Delete</Button> : null}<Button onClick={() => save.mutate()} disabled={save.isPending}>Save</Button></>}>
      <div className="grid gap-4 lg:grid-cols-3">
        <Panel className="lg:col-span-2" actions={<RequiredNote />}>
          <Field label="Title" htmlFor="pg-title" required error={errors.title?.[0]}><input id="pg-title" className={inputClass} value={f.title} onChange={(e) => setF({ ...f, title: e.target.value })} /></Field>
          <Field label="Content" htmlFor="pg-body" hint="Plain text. Leave a blank line between paragraphs."><textarea id="pg-body" className={cn(textareaClass, "min-h-96 font-mono")} value={f.body} onChange={(e) => setF({ ...f, body: e.target.value })} /></Field>
        </Panel>
        <Panel>
          <Field label="Status" htmlFor="pg-status" required><select id="pg-status" className={inputClass} value={f.status} onChange={(e) => setF({ ...f, status: e.target.value as "draft" | "published" })}><option value="draft">Draft</option><option value="published">Published</option></select></Field>
          <Field label="URL slug" htmlFor="pg-slug" required error={errors.slug?.[0]} hint={f.slug === "faq" ? "Shown at /faq" : `Shown at /pages/${f.slug || "…"}`}><input id="pg-slug" className={inputClass} value={f.slug} onChange={(e) => setF({ ...f, slug: e.target.value.toLowerCase() })} /></Field>
          <Field label="SEO description" htmlFor="pg-seo"><textarea id="pg-seo" className={cn(textareaClass, "min-h-20")} maxLength={320} value={f.seo_description} onChange={(e) => setF({ ...f, seo_description: e.target.value })} /></Field>
          {page && f.status === "published" ? <Link className="text-sm underline underline-offset-4" href={f.slug === "faq" ? "/faq" : `/pages/${f.slug}`} target="_blank">View on storefront</Link> : null}
        </Panel>
      </div>
    </AdminPage>
  );
}
