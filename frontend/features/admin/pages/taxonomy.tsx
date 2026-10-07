"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { ApiError } from "@/services/api-client";
import { taxonomyApi, type AdminCategory } from "../api";
import { AdminPage, checkboxLabelClass, Field, FormActions, inputClass, Panel, StatusBadge, textareaClass } from "../components/kit/admin-page";

export function TaxonomyPage() {
  return (
    <AdminPage title="Categories & brands" description="Organise the catalogue. Changes appear on the storefront immediately.">
      <div className="grid gap-4 lg:grid-cols-2">
        <TaxonomyList kind="category" />
        <TaxonomyList kind="brand" />
      </div>
    </AdminPage>
  );
}

function TaxonomyList({ kind }: { kind: "category" | "brand" }) {
  const queryClient = useQueryClient();
  const key = ["admin", kind];
  const list = useQuery({ queryKey: key, queryFn: kind === "category" ? taxonomyApi.categories : taxonomyApi.brands });
  const [editing, setEditing] = useState<AdminCategory | "new" | null>(null);
  const remove = useMutation({
    mutationFn: (uuid: string) => (kind === "category" ? taxonomyApi.deleteCategory(uuid) : taxonomyApi.deleteBrand(uuid)),
    onSuccess: () => { toast.success("Deleted."); void queryClient.invalidateQueries({ queryKey: key }); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not delete."),
  });

  return (
    <Panel title={kind === "category" ? "Categories" : "Brands"} actions={<Button size="sm" variant="outline" onClick={() => setEditing("new")}>Add {kind}</Button>}>
      {editing ? <TaxonomyForm kind={kind} item={editing === "new" ? null : editing} onDone={() => { setEditing(null); void queryClient.invalidateQueries({ queryKey: key }); }} /> : null}
      {list.isPending ? <LoadingState lines={4} /> : list.isError ? <ErrorState onRetry={() => void list.refetch()} /> : (
        <ul className="divide-y divide-border text-sm">
          {list.data.map((item) => (
            <li key={item.uuid} className="flex items-center justify-between gap-3 py-2">
              <span>
                <span className="font-medium">{item.name}</span> <span className="text-muted-foreground">/{item.slug} · {item.products_count} products</span>
                {!item.is_active ? <span className="ml-2"><StatusBadge value="draft" /></span> : null}
              </span>
              <span className="flex gap-1">
                <Button size="sm" variant="ghost" onClick={() => setEditing(item)}>Edit</Button>
                <Button size="sm" variant="ghost" disabled={item.products_count > 0} title={item.products_count > 0 ? "Move its products first" : undefined} onClick={() => remove.mutate(item.uuid)}>Delete</Button>
              </span>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}

function TaxonomyForm({ kind, item, onDone }: { kind: "category" | "brand"; item: AdminCategory | null; onDone: () => void }) {
  const [name, setName] = useState(item?.name ?? "");
  const [slug, setSlug] = useState(item?.slug ?? "");
  const [description, setDescription] = useState(item?.description ?? "");
  const [active, setActive] = useState(item?.is_active ?? true);
  const save = useMutation({
    mutationFn: () => {
      const body = { name, slug: slug || null, description: description || null, is_active: active };
      return kind === "category" ? taxonomyApi.saveCategory(item?.uuid ?? null, body) : taxonomyApi.saveBrand(item?.uuid ?? null, body);
    },
    onSuccess: () => { toast.success("Saved."); onDone(); },
    onError: (e) => toast.error(e instanceof ApiError ? (Object.values(e.fieldErrors)[0]?.[0] ?? e.message) : "Could not save."),
  });

  return (
    <form className="grid gap-3 rounded-sm border border-dashed border-border p-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
      <div className="grid items-start gap-3 sm:grid-cols-2">
        <Field label="Name" htmlFor={`${kind}-name`} required><input id={`${kind}-name`} className={inputClass} value={name} onChange={(e) => setName(e.target.value)} required /></Field>
        <Field label="URL slug" htmlFor={`${kind}-slug`} hint="Leave empty to generate from the name."><input id={`${kind}-slug`} className={inputClass} value={slug} onChange={(e) => setSlug(e.target.value.toLowerCase())} /></Field>
      </div>
      <Field label="Description" htmlFor={`${kind}-desc`}><textarea id={`${kind}-desc`} className={textareaClass} value={description} onChange={(e) => setDescription(e.target.value)} /></Field>
      <label className={checkboxLabelClass}><input type="checkbox" className="size-4 accent-primary" checked={active} onChange={(e) => setActive(e.target.checked)} /> Visible on the storefront</label>
      <FormActions><Button type="submit" disabled={save.isPending || !name.trim()}>Save</Button><Button type="button" variant="ghost" onClick={onDone}>Cancel</Button></FormActions>
    </form>
  );
}
