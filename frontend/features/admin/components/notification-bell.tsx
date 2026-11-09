"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Bell, BellRing, MonitorSmartphone, ShoppingBag, Volume2, VolumeX } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useId, useRef, useState } from "react";
import { toast } from "sonner";
import { Skeleton } from "@/components/ui/skeleton";
import { playChime, unlockChime } from "@/lib/chime";
import { formatMoney } from "@/lib/money";
import { cn } from "@/lib/utils";
import { miscAdminApi, type AdminNotifications, type OrderAlert } from "../api";

const key = ["admin", "notifications"] as const;
const POLL_MS = 15_000;
const MUTE_KEY = "admin-alerts-muted";

function readMuted(): boolean {
  try {
    return window.localStorage.getItem(MUTE_KEY) === "1";
  } catch {
    return false;
  }
}

const timeAgo = (iso: string | null) => {
  if (!iso) return "";
  const minutes = Math.round((new Date(iso).getTime() - Date.now()) / 60_000);
  const rtf = new Intl.RelativeTimeFormat("en", { numeric: "auto" });
  if (Math.abs(minutes) < 60) return rtf.format(minutes, "minute");
  if (Math.abs(minutes) < 1440) return rtf.format(Math.round(minutes / 60), "hour");
  return rtf.format(Math.round(minutes / 1440), "day");
};

const amount = (a: OrderAlert) => formatMoney(a.data.charged ?? a.data.total);
const summary = (a: OrderAlert) => `${a.data.customer} · ${a.data.item_count} ${a.data.item_count === 1 ? "item" : "items"} · ${amount(a)}`;

/**
 * Admin bell: new paid orders, polled every 15 seconds (shared hosting has no websockets).
 * A new arrival chimes, shows a toast and, when the tab is in the background and the
 * person allowed it, a desktop notification. Polling never extends the idle timeout.
 */
export function NotificationBell() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const panelId = useId();
  const [open, setOpen] = useState(false);
  // The admin renders only in the browser (after the staff check), so these can read browser state up front.
  const [muted, setMuted] = useState(readMuted);
  const [desktop, setDesktop] = useState<NotificationPermission | "unsupported">(() => (typeof window !== "undefined" && typeof window.Notification === "function" ? window.Notification.permission : "unsupported"));
  const seen = useRef<Set<string> | null>(null);
  const wrapper = useRef<HTMLDivElement>(null);
  const button = useRef<HTMLButtonElement>(null);

  const alerts = useQuery({
    queryKey: key,
    queryFn: miscAdminApi.notifications,
    // Stop asking once the session has ended (the admin gate sends them to sign in).
    refetchInterval: (query) => (query.state.error ? false : POLL_MS),
    refetchIntervalInBackground: true,
    refetchOnWindowFocus: true,
    staleTime: 0,
  });

  useEffect(() => {
    // Browsers only allow sound after an interaction with the page.
    const unlock = () => unlockChime();
    window.addEventListener("pointerdown", unlock, { once: true });
    window.addEventListener("keydown", unlock, { once: true });
    return () => {
      window.removeEventListener("pointerdown", unlock);
      window.removeEventListener("keydown", unlock);
    };
  }, []);

  // Announce alerts that arrived since the last poll (the first load only sets the baseline).
  useEffect(() => {
    const items = alerts.data?.items;
    if (!items) return;
    if (seen.current === null) {
      seen.current = new Set(items.map((a) => a.id));
      return;
    }
    const fresh = items.filter((a) => !a.read && !seen.current!.has(a.id));
    items.forEach((a) => seen.current!.add(a.id));
    if (!fresh.length) return;

    if (!readMuted()) playChime();
    const newest = fresh[0]!;
    const title = fresh.length === 1 ? `New order ${newest.data.order_number}` : `${fresh.length} new orders`;
    toast.success(title, {
      description: summary(newest),
      action: { label: "View", onClick: () => router.push(`/admin/orders/${newest.data.order_number}`) },
    });
    if (document.hidden && typeof window.Notification === "function" && window.Notification.permission === "granted") {
      const note = new window.Notification(title, { body: summary(newest), tag: newest.id });
      note.onclick = () => { window.focus(); router.push(`/admin/orders/${newest.data.order_number}`); note.close(); };
    }
  }, [alerts.data, router]);

  // Close on outside click or Escape.
  useEffect(() => {
    if (!open) return;
    const onPointer = (e: PointerEvent) => { if (!wrapper.current?.contains(e.target as Node)) setOpen(false); };
    const onKey = (e: KeyboardEvent) => { if (e.key === "Escape") { setOpen(false); button.current?.focus(); } };
    document.addEventListener("pointerdown", onPointer);
    document.addEventListener("keydown", onKey);
    return () => { document.removeEventListener("pointerdown", onPointer); document.removeEventListener("keydown", onKey); };
  }, [open]);

  const setUnread = (unread_count: number, read: (a: OrderAlert) => boolean) =>
    queryClient.setQueryData<AdminNotifications>(key, (old) => (old ? { unread_count, items: old.items.map((a) => (read(a) ? { ...a, read: true } : a)) } : old));
  const markRead = useMutation({ mutationFn: (id: string) => miscAdminApi.readNotification(id), onSuccess: (r, id) => setUnread(r.unread_count, (a) => a.id === id) });
  const markAll = useMutation({ mutationFn: miscAdminApi.readAllNotifications, onSuccess: () => setUnread(0, () => true) });

  const toggleMute = () => {
    const next = !muted;
    setMuted(next);
    try { window.localStorage.setItem(MUTE_KEY, next ? "1" : "0"); } catch { /* per-visit only */ }
    if (!next) { unlockChime(); playChime(); }
  };
  const enableDesktop = async () => setDesktop(await window.Notification.requestPermission());

  const unread = alerts.data?.unread_count ?? 0;
  const Icon = unread > 0 ? BellRing : Bell;

  return (
    <div ref={wrapper} className="relative">
      <button
        ref={button}
        type="button"
        aria-label={unread > 0 ? `Notifications, ${unread} unread` : "Notifications"}
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((o) => !o)}
        className="relative inline-flex size-11 items-center justify-center rounded-full text-foreground transition-colors hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none"
      >
        <Icon className="size-5" aria-hidden />
        {unread > 0 ? (
          <span aria-hidden className="absolute top-1 right-1 flex min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs leading-5 font-semibold text-primary-foreground">
            {unread > 99 ? "99+" : unread}
          </span>
        ) : null}
      </button>

      {open ? (
        <div id={panelId} role="region" aria-label="Notifications" className="fixed inset-x-4 top-16 z-50 flex max-h-120 flex-col overflow-hidden rounded-lg border border-border bg-background shadow-lg sm:absolute sm:inset-x-auto sm:top-full sm:right-0 sm:mt-2 sm:w-96">
          <div className="flex items-center justify-between gap-2 border-b border-border px-4 py-3">
            <p className="text-sm font-semibold text-foreground">New orders</p>
            <div className="flex items-center gap-1">
              <button type="button" onClick={toggleMute} aria-pressed={!muted} aria-label={muted ? "Turn alert sound on" : "Turn alert sound off"} title={muted ? "Sound off" : "Sound on"} className="inline-flex size-11 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground">
                {muted ? <VolumeX className="size-4" aria-hidden /> : <Volume2 className="size-4" aria-hidden />}
              </button>
              <button type="button" disabled={unread === 0 || markAll.isPending} onClick={() => markAll.mutate()} className="inline-flex min-h-11 items-center px-2 text-xs font-medium text-foreground underline-offset-4 hover:underline disabled:opacity-40">
                Mark all read
              </button>
            </div>
          </div>

          <div className="min-h-0 flex-1 overflow-y-auto">
            {alerts.isPending ? (
              <div className="grid gap-3 p-4">{[0, 1, 2].map((i) => <Skeleton key={i} className="h-12 w-full" />)}</div>
            ) : alerts.isError ? (
              <div className="grid gap-2 p-4 text-sm text-muted-foreground">
                <p>Alerts could not be loaded. You may have been signed out.</p>
                <button type="button" onClick={() => void alerts.refetch()} className="justify-self-start font-medium text-foreground underline underline-offset-4">Try again</button>
              </div>
            ) : alerts.data.items.length === 0 ? (
              <div className="grid justify-items-center gap-2 px-6 py-10 text-center text-sm text-muted-foreground">
                <ShoppingBag className="size-6" aria-hidden />
                <p>No orders yet. You&apos;ll hear a chime the moment one is paid.</p>
              </div>
            ) : (
              <ul className="divide-y divide-border">
                {alerts.data.items.map((a) => (
                  <li key={a.id}>
                    <Link
                      href={`/admin/orders/${a.data.order_number}`}
                      onClick={() => { setOpen(false); if (!a.read) markRead.mutate(a.id); }}
                      className={cn("flex gap-3 px-4 py-3 text-sm transition-colors hover:bg-muted/60", !a.read && "bg-muted/30")}
                    >
                      <span className={cn("mt-1.5 size-2 shrink-0 rounded-full", a.read ? "bg-transparent" : "bg-primary")} aria-hidden />
                      <span className="grid min-w-0 flex-1 gap-0.5">
                        <span className="flex justify-between gap-2">
                          <span className="font-medium text-foreground">Order {a.data.order_number}{a.read ? "" : <span className="sr-only"> (unread)</span>}</span>
                          <span className="shrink-0 text-xs text-muted-foreground">{timeAgo(a.created_at)}</span>
                        </span>
                        <span className="truncate text-muted-foreground">{summary(a)}</span>
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </div>

          {desktop === "default" ? (
            <button type="button" onClick={() => void enableDesktop()} className="flex min-h-11 items-center justify-center gap-2 border-t border-border px-4 text-xs font-medium text-foreground hover:bg-muted">
              <MonitorSmartphone className="size-4" aria-hidden /> Also alert me when this tab is in the background
            </button>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
