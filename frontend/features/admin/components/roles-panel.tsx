"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Lock, Plus, ShieldCheck } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { ApiError } from "@/services/api-client";
import { rolesApi, type AdminRole, type PermissionGroup } from "../api";
import { Field, FormActions, inputClass, RequiredNote } from "./kit/admin-page";

export const rolesKey = ["admin", "roles"] as const;

export const roleLabel = (role: string | null) =>
  role === "super-admin" ? "Owner (super admin)" : role ? role.split("-").map((w) => (w[0]?.toUpperCase() ?? "") + w.slice(1)).join(" ") : "—";

/** Admin → Staff → Roles & access: create roles and tick which admin areas each one can use. */
export function RolesPanel() {
  const roles = useQuery({ queryKey: rolesKey, queryFn: rolesApi.list });
  const [editing, setEditing] = useState<AdminRole | "new" | null>(null);

  if (roles.isPending) return <LoadingState lines={6} />;
  if (roles.isError) return <ErrorState onRetry={() => void roles.refetch()} />;
  const { groups } = roles.data;

  return (
    <div className="grid gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="max-w-2xl text-sm text-muted-foreground">
          A role is a set of admin areas. Create one, tick what it can open, then give it to as many staff as you like. Changing a role updates everyone who has it straight away.
        </p>
        {editing === null ? <Button onClick={() => setEditing("new")}><Plus className="size-4" aria-hidden />Create role</Button> : null}
      </div>

      {editing === "new" ? <RoleEditor role={null} groups={groups} onDone={() => setEditing(null)} /> : null}

      <ul className="grid gap-3" aria-label="Roles">
        {roles.data.roles.map((role) => (
          <li key={role.name} className="grid gap-4 border border-border bg-card p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="grid gap-1">
                <p className="flex flex-wrap items-center gap-2 font-medium">
                  {roleLabel(role.name)}
                  <span className="rounded-sm bg-muted px-2 py-0.5 text-xs text-muted-foreground">{role.locked ? "Fixed" : role.is_default ? "Default" : "Custom"}</span>
                  {role.yours ? <span className="rounded-sm bg-primary/10 px-2 py-0.5 text-xs text-primary">Your role</span> : null}
                </p>
                <p className="text-sm text-muted-foreground">
                  {role.staff_count === 0 ? "Not given to anyone yet" : `Used by ${role.staff_count} staff ${role.staff_count === 1 ? "member" : "members"}`}
                  {" · "}
                  {role.name === "super-admin" ? "Everything" : areaSummary(role, groups)}
                </p>
              </div>
              {role.locked || role.yours ? (
                <span className="flex items-center gap-1.5 text-xs text-muted-foreground"><Lock className="size-3.5" aria-hidden />{role.locked ? "Can’t be changed" : "Ask another owner to change"}</span>
              ) : (
                <Button variant="outline" size="sm" aria-expanded={editing !== "new" && editing?.name === role.name}
                  onClick={() => setEditing(editing !== "new" && editing?.name === role.name ? null : role)}>
                  {editing !== "new" && editing?.name === role.name ? "Close" : "Edit access"}
                </Button>
              )}
            </div>
            {editing !== "new" && editing?.name === role.name ? <RoleEditor role={role} groups={groups} onDone={() => setEditing(null)} /> : null}
          </li>
        ))}
      </ul>
    </div>
  );
}

function areaSummary(role: AdminRole, groups: PermissionGroup[]) {
  const areas = groups.filter((g) => g.permissions.some((p) => role.permissions.includes(p.name))).map((g) => g.label);
  return areas.length ? areas.join(", ") : "No access";
}

function RoleEditor({ role, groups, onDone }: { role: AdminRole | null; groups: PermissionGroup[]; onDone: () => void }) {
  const queryClient = useQueryClient();
  const [name, setName] = useState(role ? roleLabel(role.name) : "");
  const [selected, setSelected] = useState<Set<string>>(new Set(role?.permissions ?? []));
  const [password, setPassword] = useState("");
  const [confirmDelete, setConfirmDelete] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const done = (message: string) => {
    toast.success(message);
    void queryClient.invalidateQueries({ queryKey: rolesKey });
    void queryClient.invalidateQueries({ queryKey: ["admin", "staff"] });
    onDone();
  };
  const fail = (e: unknown) => {
    if (e instanceof ApiError) { setErrors(e.fieldErrors); toast.error(Object.values(e.fieldErrors)[0]?.[0] ?? e.message); }
    else toast.error("Something went wrong. Please try again.");
  };

  const save = useMutation({
    mutationFn: () => {
      const body = { name, permissions: [...selected], password };
      return role ? rolesApi.update(role.name, body) : rolesApi.create(body);
    },
    onSuccess: () => done(role ? "Role updated. Staff with this role have the new access now." : `Role “${name}” created. You can now give it to staff.`),
    onError: fail,
  });
  const remove = useMutation({ mutationFn: () => rolesApi.remove(role!.name, password), onSuccess: () => done("Role deleted."), onError: fail });

  const toggle = (perm: string, on: boolean) => setSelected((s) => { const next = new Set(s); if (on) next.add(perm); else next.delete(perm); return next; });
  const toggleGroup = (group: PermissionGroup, on: boolean) => setSelected((s) => {
    const next = new Set(s);
    group.permissions.forEach((p) => (on ? next.add(p.name) : next.delete(p.name)));
    return next;
  });
  const busy = save.isPending || remove.isPending;

  return (
    <form className="animate-expand grid gap-5 rounded-sm border border-dashed border-border p-4" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
      <div className="flex items-center justify-between gap-3">
        <h3 className="font-semibold">{role ? `Edit “${roleLabel(role.name)}”` : "New role"}</h3>
        <RequiredNote />
      </div>
      <Field label="Role name" htmlFor="role-name" required error={errors.name?.[0]} hint="For example “Packing staff” or “Shop assistant”." className="max-w-sm">
        <input id="role-name" className={inputClass} maxLength={50} value={name} onChange={(e) => setName(e.target.value)} required />
      </Field>

      <fieldset className="grid gap-3">
        <legend className="mb-2 text-sm font-medium">What can this role open? <span className="font-normal text-muted-foreground">({selected.size} selected)</span></legend>
        {errors.permissions?.[0] ? <p className="text-sm text-destructive">{errors.permissions[0]}</p> : null}
        <div className="grid gap-3 md:grid-cols-2">
          {groups.map((group) => {
            const count = group.permissions.filter((p) => selected.has(p.name)).length;
            const all = count === group.permissions.length;
            const id = `grp-${group.label.replace(/\W+/g, "-")}`;
            return (
              <div key={group.label} className={cn("grid content-start gap-1 border p-3 transition-colors", count ? "border-primary/40 bg-primary/5" : "border-border")}>
                <label htmlFor={id} className="flex min-h-11 cursor-pointer items-center gap-3 font-medium">
                  <input id={id} type="checkbox" className="size-4 accent-primary" checked={all}
                    ref={(el) => { if (el) el.indeterminate = count > 0 && !all; }}
                    onChange={(e) => toggleGroup(group, e.target.checked)} />
                  {group.label}
                  <span className="ml-auto text-xs font-normal text-muted-foreground">{count}/{group.permissions.length}</span>
                </label>
                <ul className="grid gap-0.5 pl-7">
                  {group.permissions.map((p) => (
                    <li key={p.name}>
                      <label className="flex min-h-9 cursor-pointer items-center gap-3 text-sm">
                        <input type="checkbox" className="size-4 accent-primary" checked={selected.has(p.name)} onChange={(e) => toggle(p.name, e.target.checked)} />
                        {p.description}
                      </label>
                    </li>
                  ))}
                </ul>
              </div>
            );
          })}
        </div>
      </fieldset>

      <Field label="Your password" htmlFor="role-password" required error={errors.password?.[0]} hint="Confirms it’s really you." className="max-w-sm">
        <input id="role-password" type="password" className={inputClass} autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
      </Field>

      <FormActions>
        <Button type="submit" disabled={busy || selected.size === 0 || !name.trim()}><ShieldCheck className="size-4" aria-hidden />{save.isPending ? "Saving…" : role ? "Save access" : "Create role"}</Button>
        <Button type="button" variant="ghost" onClick={onDone}>Cancel</Button>
        {role ? (
          confirmDelete ? (
            <span className="ml-auto flex flex-wrap items-center gap-2 text-sm">
              Delete this role?
              <Button type="button" size="sm" variant="destructive" disabled={busy || !password} onClick={() => remove.mutate()}>Delete role</Button>
              <Button type="button" size="sm" variant="ghost" onClick={() => setConfirmDelete(false)}>Keep</Button>
            </span>
          ) : (
            <Button type="button" variant="ghost" className="ml-auto text-destructive" disabled={role.staff_count > 0}
              title={role.staff_count > 0 ? "Give its staff another role first" : undefined} onClick={() => setConfirmDelete(true)}>
              Delete role
            </Button>
          )
        ) : null}
      </FormActions>
      {role && role.staff_count > 0 ? <p className="text-xs text-muted-foreground">This role is in use, so it can’t be deleted. Move its staff to another role first.</p> : null}
    </form>
  );
}
