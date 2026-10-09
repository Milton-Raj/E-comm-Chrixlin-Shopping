"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { MailPlus, ShieldCheck, ShieldOff, UserPlus, Users } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { EmptyState } from "@/components/states/empty-state";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { ApiError } from "@/services/api-client";
import type { AdminMe } from "@/types/api";
import { staffApi, type StaffMember, type StaffRole } from "../api";
import { adminMeKey } from "../components/admin-gate";
import { roleLabel, RolesPanel, rolesKey } from "../components/roles-panel";
import { AdminPage, Field, FormActions, inputClass, Panel, RequiredNote, StatusBadge } from "../components/kit/admin-page";

const staffKey = ["admin", "staff"] as const;
const OWNER = "super-admin";

export { roleLabel };

function useFailToast(setErrors?: (e: Record<string, string[]>) => void) {
  return (e: unknown) => {
    if (e instanceof ApiError) {
      setErrors?.(e.fieldErrors);
      toast.error(Object.values(e.fieldErrors)[0]?.[0] ?? e.message);
    } else toast.error("Something went wrong. Please try again.");
  };
}

/** Staff accounts: invite people, change their role, deactivate them or reset their 2FA. */
export function StaffPage() {
  const me = useQueryClient().getQueryData<AdminMe>(adminMeKey);
  const canManageRoles = me?.permissions.includes("roles.manage") ?? false;
  const [tab, setTab] = useState<"staff" | "roles">("staff");

  return (
    <AdminPage title="Staff" description="People who can open the admin, and the roles that decide what each of them can do.">
      {canManageRoles ? (
        <div role="tablist" aria-label="Staff sections" className="flex gap-1 border-b border-border">
          {([["staff", "Staff members"], ["roles", "Roles & access"]] as const).map(([key, label]) => (
            <button key={key} type="button" role="tab" aria-selected={tab === key} onClick={() => setTab(key)}
              className={cn("-mb-px min-h-11 border-b-2 px-4 text-sm font-medium transition-colors", tab === key ? "border-primary text-foreground" : "border-transparent text-muted-foreground hover:text-foreground")}>
              {label}
            </button>
          ))}
        </div>
      ) : null}
      <div role={canManageRoles ? "tabpanel" : undefined}>{tab === "roles" && canManageRoles ? <RolesPanel /> : <StaffMembers />}</div>
    </AdminPage>
  );
}

function StaffMembers() {
  const [adding, setAdding] = useState(false);
  const staff = useQuery({ queryKey: staffKey, queryFn: staffApi.list });
  const roles = useQuery({ queryKey: [...staffKey, "roles"], queryFn: staffApi.roles });
  const me = useQueryClient().getQueryData<AdminMe>(adminMeKey);
  const viewerIsOwner = me?.roles.includes(OWNER) ?? false;

  return (
    <div className="grid gap-4">
      {!adding ? <div><Button onClick={() => setAdding(true)}><UserPlus className="size-4" aria-hidden />Add staff member</Button></div> : null}
      {adding && roles.data ? <AddStaffPanel roles={roles.data} onDone={() => setAdding(false)} /> : null}
      {staff.isPending || roles.isPending ? <LoadingState lines={6} /> : staff.isError || roles.isError ? (
        <ErrorState onRetry={() => { void staff.refetch(); void roles.refetch(); }} />
      ) : staff.data.length === 0 ? (
        <EmptyState icon={<Users className="size-6" aria-hidden />} title="No staff yet" description="Add the people who help run your store. Each one gets an email to set their own password." />
      ) : (
        <ul className="grid gap-3" aria-label="Staff members">
          {staff.data.map((member) => (
            <StaffRow key={member.uuid} member={member} roles={roles.data} manageable={!member.is_you && (member.role !== OWNER || viewerIsOwner)} />
          ))}
        </ul>
      )}
    </div>
  );
}

function RoleSummary({ role }: { role: StaffRole | undefined }) {
  if (!role) return null;
  return (
    <div className="rounded-sm border border-border bg-muted/40 p-3 text-sm">
      <p className="font-medium">{roleLabel(role.name)} can:</p>
      {role.everything ? (
        <p className="mt-1 text-muted-foreground">Do everything, including managing staff, roles and security settings.</p>
      ) : (
        <ul className="mt-1 grid gap-x-4 gap-y-0.5 text-muted-foreground sm:grid-cols-2">
          {role.permissions.map((p) => <li key={p}>• {p}</li>)}
        </ul>
      )}
    </div>
  );
}

function AddStaffPanel({ roles, onDone }: { roles: StaffRole[]; onDone: () => void }) {
  const queryClient = useQueryClient();
  const assignable = roles.filter((r) => r.assignable);
  const [form, setForm] = useState({ name: "", email: "", role: assignable.find((r) => r.name === "order-manager")?.name ?? assignable[0]?.name ?? "", password: "" });
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const invite = useMutation({
    mutationFn: () => staffApi.invite(form),
    onSuccess: (m) => { toast.success(`Invitation sent to ${m.email}.`); void queryClient.invalidateQueries({ queryKey: staffKey }); void queryClient.invalidateQueries({ queryKey: rolesKey }); onDone(); },
    onError: useFailToast(setErrors),
  });
  const set = (k: keyof typeof form, v: string) => setForm((f) => ({ ...f, [k]: v }));
  const err = (k: string) => errors[k]?.[0];

  return (
    <Panel title="Add staff member" actions={<RequiredNote />} className="animate-expand">
      <form className="grid gap-4" onSubmit={(e) => { e.preventDefault(); invite.mutate(); }}>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Full name" htmlFor="st-name" required error={err("name")}>
            <input id="st-name" className={inputClass} autoComplete="off" value={form.name} onChange={(e) => set("name", e.target.value)} required maxLength={120} />
          </Field>
          <Field label="Email" htmlFor="st-email" required error={err("email")} hint="They sign in with this address.">
            <input id="st-email" type="email" className={inputClass} autoComplete="off" value={form.email} onChange={(e) => set("email", e.target.value)} required />
          </Field>
          <Field label="Role" htmlFor="st-role" required error={err("role")}>
            <select id="st-role" className={inputClass} value={form.role} onChange={(e) => set("role", e.target.value)}>
              {assignable.map((r) => <option key={r.name} value={r.name}>{roleLabel(r.name)}</option>)}
            </select>
          </Field>
          <Field label="Your password" htmlFor="st-password" required error={err("password")} hint="Confirms it's really you.">
            <input id="st-password" type="password" className={inputClass} autoComplete="current-password" value={form.password} onChange={(e) => set("password", e.target.value)} required />
          </Field>
        </div>
        <RoleSummary role={roles.find((r) => r.name === form.role)} />
        <p className="text-sm text-muted-foreground">We email them a link to choose their own password. If the email already has a customer account, they keep their password and simply get staff access.</p>
        <FormActions>
          <Button type="submit" disabled={invite.isPending}><MailPlus className="size-4" aria-hidden />{invite.isPending ? "Sending…" : "Send invitation"}</Button>
          <Button type="button" variant="ghost" onClick={onDone}>Cancel</Button>
        </FormActions>
      </form>
    </Panel>
  );
}

function StaffRow({ member, roles, manageable }: { member: StaffMember; roles: StaffRole[]; manageable: boolean }) {
  const [open, setOpen] = useState(false);
  return (
    <li className={cn("grid gap-4 border border-border bg-card p-4 transition-colors", !member.is_active && "bg-muted/40")}>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="min-w-0">
          <p className="flex flex-wrap items-center gap-2 font-medium">
            {member.name}
            {member.is_you ? <span className="rounded-sm bg-primary/10 px-2 py-0.5 text-xs text-primary">You</span> : null}
          </p>
          <p className="truncate text-sm text-muted-foreground">{member.email}</p>
        </div>
        <dl className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
          <div><dt className="sr-only">Role</dt><dd className="font-medium">{roleLabel(member.role)}</dd></div>
          <div>
            <dt className="sr-only">Two-factor sign-in</dt>
            <dd className="flex items-center gap-1.5 text-muted-foreground">
              {member.two_factor_enabled ? <><ShieldCheck className="size-4 text-emerald-700" aria-hidden />2FA on</> : <><ShieldOff className="size-4 text-amber-700" aria-hidden />2FA not set up</>}
            </dd>
          </div>
          <div>
            <dt className="sr-only">Last sign-in</dt>
            <dd className="text-muted-foreground">{member.last_login_at ? `Signed in ${new Date(member.last_login_at).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" })}` : "Not signed in yet"}</dd>
          </div>
          <div><dt className="sr-only">Status</dt><dd><StatusBadge value={member.is_active ? "active" : "deactivated"} /></dd></div>
        </dl>
        {manageable ? (
          <Button variant="outline" size="sm" aria-expanded={open} onClick={() => setOpen((o) => !o)}>{open ? "Close" : "Manage"}</Button>
        ) : null}
      </div>
      {open ? <ManageStaff member={member} roles={roles} onClose={() => setOpen(false)} /> : null}
    </li>
  );
}

function ManageStaff({ member, roles, onClose }: { member: StaffMember; roles: StaffRole[]; onClose: () => void }) {
  const queryClient = useQueryClient();
  const [role, setRole] = useState(member.role ?? "");
  const [password, setPassword] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const fail = useFailToast(setErrors);
  const done = (message: string) => { toast.success(message); setPassword(""); setErrors({}); void queryClient.invalidateQueries({ queryKey: staffKey }); void queryClient.invalidateQueries({ queryKey: rolesKey }); };
  const assignable = roles.filter((r) => r.assignable || r.name === member.role);

  const update = useMutation({
    mutationFn: (body: { role?: string; is_active?: boolean }) => staffApi.update(member.uuid, { ...body, password }),
    onSuccess: (_, body) => { done(body.is_active === false ? `${member.name} was deactivated and signed out.` : body.is_active ? `${member.name} can sign in again.` : "Role updated."); if (body.is_active !== undefined) onClose(); },
    onError: fail,
  });
  const reset2fa = useMutation({
    mutationFn: () => staffApi.resetTwoFactor(member.uuid, password),
    onSuccess: () => done(`Two-factor sign-in reset for ${member.name}. They'll set it up again next time they sign in.`),
    onError: fail,
  });
  const resend = useMutation({
    mutationFn: () => staffApi.resendInvite(member.uuid),
    onSuccess: () => toast.success(`A new invitation was sent to ${member.email}.`),
    onError: useFailToast(),
  });
  const busy = update.isPending || reset2fa.isPending;
  const needPassword = password.length === 0;

  return (
    <div className="animate-expand grid gap-4 border-t border-border pt-4">
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Role" htmlFor={`role-${member.uuid}`}>
          <select id={`role-${member.uuid}`} className={inputClass} value={role} onChange={(e) => setRole(e.target.value)}>
            {assignable.map((r) => <option key={r.name} value={r.name}>{roleLabel(r.name)}</option>)}
          </select>
        </Field>
        <Field label="Your password" htmlFor={`pw-${member.uuid}`} required error={errors.password?.[0]} hint="Needed to save any change below.">
          <input id={`pw-${member.uuid}`} type="password" className={inputClass} autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />
        </Field>
      </div>
      {role !== member.role ? <RoleSummary role={roles.find((r) => r.name === role)} /> : null}
      <FormActions>
        <Button size="sm" disabled={busy || needPassword || role === member.role} onClick={() => update.mutate({ role })}>Save role</Button>
        {member.is_active ? (
          <Button size="sm" variant="outline" className="text-destructive" disabled={busy || needPassword} onClick={() => update.mutate({ is_active: false })}>Deactivate</Button>
        ) : (
          <Button size="sm" variant="outline" disabled={busy || needPassword} onClick={() => update.mutate({ is_active: true })}>Reactivate</Button>
        )}
        {member.two_factor_enabled ? (
          <Button size="sm" variant="outline" disabled={busy || needPassword} onClick={() => reset2fa.mutate()}>Reset two-factor</Button>
        ) : null}
        <Button size="sm" variant="ghost" disabled={resend.isPending || !member.is_active} onClick={() => resend.mutate()}>{resend.isPending ? "Sending…" : "Resend invitation"}</Button>
      </FormActions>
      <p className="text-xs text-muted-foreground">Deactivating signs them out everywhere and blocks sign-in; their past actions stay in the audit log. Resetting two-factor helps someone who lost their phone.</p>
    </div>
  );
}
