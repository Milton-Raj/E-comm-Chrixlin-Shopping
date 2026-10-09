"use client";

import { useMutation } from "@tanstack/react-query";
import { Check, ExternalLink, Truck } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { ordersAdminApi, type AdminOrderDetail, type CourierShipment } from "../api";
import { Panel, StatusBadge } from "./kit/admin-page";

const LABELS: Record<string, string> = {
  paid: "Paid", processing: "Processing", packed: "Packed", shipped: "Shipped", out_for_delivery: "Out for delivery", delivered: "Delivered",
};

export const stageLabel = (stage: string) => LABELS[stage] ?? stage.replaceAll("_", " ");

/** The stages an order moves through after payment (mirrors OrderStatus::fulfilmentPath on the API). */
export function stagesFor(requiresShipping: boolean): string[] {
  return requiresShipping ? ["paid", "processing", "packed", "shipped", "out_for_delivery", "delivered"] : ["paid", "delivered"];
}

/** Stages staff can move to by button. "Shipped" needs tracking (manual) or the courier, so it is never a plain button. */
function staffCanMoveTo(stage: string, current: number, path: string[]): boolean {
  const target = path.indexOf(stage);
  const shipped = path.indexOf("shipped");
  if (target <= current || stage === "shipped") return false;
  return shipped === -1 || current >= shipped || target < shipped;
}

/** The next stage staff can set from the order list, or null when it needs more than a click. */
export function nextStaffStage(status: string, requiresShipping: boolean): string | null {
  const path = stagesFor(requiresShipping);
  const current = path.indexOf(status);
  const next = path[current + 1];
  return current >= 0 && next && staffCanMoveTo(next, current, path) ? next : null;
}

export function StageTracker({ order, pending, onMove }: { order: AdminOrderDetail; pending: boolean; onMove: (stage: string) => void }) {
  const physical = order.items.some((i) => i.product_type === "physical");
  const path = stagesFor(physical);
  const current = path.indexOf(order.status);
  const reachedAt = (stage: string) => [...order.history].reverse().find((h) => h.to === stage)?.at;
  const next = current >= 0 ? path[current + 1] : undefined;
  const courierBooked = order.courier_shipments.some((s) => s.provider === "shiprocket" && s.status !== "failed");

  let hint: string | null = null;
  if (current < 0) hint = `This order is ${order.status.replaceAll("_", " ")}, so its delivery stages are closed.`;
  else if (next === "packed" && order.courier_enabled) hint = "Marking it packed books the Shiprocket pickup automatically.";
  else if (next === "shipped" && order.courier_shipments.some((s) => s.status === "test_created")) hint = "Shiprocket test mode: the order was created in Shiprocket only. No courier will collect it, so add tracking yourself below or cancel the test order in Shiprocket.";
  else if (next === "shipped" && order.courier_enabled && courierBooked) hint = "Waiting for the courier to collect it. Shiprocket moves it to Shipped, Out for delivery and Delivered on its own.";
  else if (next === "shipped") hint = order.courier_enabled ? "Book the Shiprocket pickup below, or add tracking details yourself." : "Add the courier and tracking number below to mark it shipped.";
  else if (next && physical && order.courier_enabled) hint = "Shiprocket updates this automatically. Use the buttons only if you need to correct it.";
  else if (!next && current >= 0) hint = "Completed.";

  return (
    <Panel title="Order stage">
      {current >= 0 ? (
        <div className="h-1 overflow-hidden rounded-full bg-muted" aria-hidden>
          <div className="animate-grow-x h-full rounded-full bg-primary transition-[width] duration-700 ease-out" style={{ width: `${(current / Math.max(1, path.length - 1)) * 100}%` }} />
        </div>
      ) : null}
      <ol className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6" aria-label="Delivery stages">
        {path.map((stage, i) => {
          const done = current >= 0 && i < current;
          const isCurrent = i === current;
          const at = reachedAt(stage);
          return (
            <li key={stage} aria-current={isCurrent ? "step" : undefined}
              className={cn("grid content-start gap-1 rounded-sm border p-3 transition-colors duration-500", isCurrent ? "border-primary bg-primary/5" : done ? "border-border bg-muted/40" : "border-dashed border-border")}>
              <span className="flex items-center gap-2">
                <span key={done ? "done" : isCurrent ? "now" : "next"} className={cn("flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-colors duration-500",
                  done ? "animate-pop bg-primary text-primary-foreground" : isCurrent ? "animate-pulse-ring bg-primary text-primary-foreground" : "bg-muted text-muted-foreground")}>
                  {done ? <Check className="size-3.5" aria-hidden /> : i + 1}
                </span>
                <span className={cn("text-sm font-semibold", !done && !isCurrent && "text-muted-foreground")}>{stageLabel(stage)}</span>
              </span>
              <span className="text-xs text-muted-foreground">
                {isCurrent ? "Current stage" : done ? "Done" : "Not yet"}
                {at && (done || isCurrent) ? ` · ${new Date(at).toLocaleString("en-IN", { day: "numeric", month: "short", hour: "numeric", minute: "2-digit" })}` : ""}
              </span>
            </li>
          );
        })}
      </ol>
      <div className="flex flex-wrap items-center gap-3 border-t border-border pt-4">
        {path.filter((s) => staffCanMoveTo(s, current, path) && current >= 0).map((stage, i) => (
          <Button key={stage} size="sm" variant={i === 0 ? "default" : "outline"} disabled={pending} onClick={() => onMove(stage)}>
            {stage === "packed" && order.courier_enabled ? "Mark packed & book pickup" : `Mark ${stageLabel(stage).toLowerCase()}`}
          </Button>
        ))}
        {hint ? <p className="text-sm text-muted-foreground">{hint}</p> : null}
      </div>
    </Panel>
  );
}

const COURIER_STATE: Record<NonNullable<CourierShipment["status"]>, string> = {
  booking: "Booking…", pickup_scheduled: "Pickup booked", test_created: "Test order created (no courier)", failed: "Booking failed", in_transit: "In transit", delivered: "Delivered", exception: "Needs attention",
};

/** Shiprocket booking and live tracking for one order. */
export function CourierPanel({ order, onSaved, onError }: { order: AdminOrderDetail; onSaved: (o: AdminOrderDetail) => void; onError: (e: unknown) => void }) {
  const book = useMutation({
    mutationFn: () => ordersAdminApi.bookCourier(order.order_number),
    onSuccess: (o) => { onSaved(o); const s = o.courier_shipments.at(-1); toast.success(`Pickup booked with ${s?.carrier ?? "the courier"}${s?.tracking_number ? ` (AWB ${s.tracking_number})` : ""}.`); },
    onError,
  });
  const shipments = order.courier_shipments.filter((s) => s.provider === "shiprocket");
  const canBook = order.courier_enabled && order.status === "packed" && !shipments.some((s) => s.status !== "failed");
  if (!shipments.length && !canBook) return null;

  return (
    <Panel title="Shiprocket delivery" actions={<Truck className="size-5 text-muted-foreground" aria-hidden />}>
      {shipments.map((s) => (
        <div key={s.uuid} className="grid gap-2 text-sm">
          <div className="flex flex-wrap items-center gap-2">
            <span className={cn("inline-flex rounded-sm px-2 py-0.5 text-xs font-medium",
              s.status === "failed" || s.status === "exception" ? "bg-red-100 text-red-900" : s.status === "delivered" ? "bg-emerald-100 text-emerald-900" : s.status === "test_created" ? "bg-amber-100 text-amber-900" : "bg-sky-100 text-sky-900")}>
              {s.status ? COURIER_STATE[s.status] : "—"}
            </span>
            {s.courier_status ? <StatusBadge value={s.courier_status.toLowerCase()} /> : null}
          </div>
          <dl className="grid grid-cols-3 gap-x-3 gap-y-1">
            <dt className="text-muted-foreground">Courier</dt><dd className="col-span-2">{s.carrier ?? "Not assigned yet"}</dd>
            <dt className="text-muted-foreground">AWB</dt>
            <dd className="col-span-2 font-mono">
              {s.tracking_number ? (s.tracking_url ? <a className="inline-flex items-center gap-1 underline underline-offset-4" href={s.tracking_url} target="_blank" rel="noreferrer">{s.tracking_number}<ExternalLink className="size-3.5" aria-hidden /></a> : s.tracking_number) : "—"}
            </dd>
            {s.pickup_scheduled_at ? <><dt className="text-muted-foreground">Pickup</dt><dd className="col-span-2">{new Date(s.pickup_scheduled_at).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" })}</dd></> : null}
            {s.last_event_at ? <><dt className="text-muted-foreground">Last update</dt><dd className="col-span-2">{new Date(s.last_event_at).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" })}</dd></> : null}
          </dl>
          {s.last_error ? <p className="border-l-4 border-destructive bg-destructive/5 px-3 py-2 text-sm">{s.last_error}</p> : null}
        </div>
      ))}
      {canBook ? (
        <div className="grid gap-2">
          {!shipments.length ? <p className="text-sm text-muted-foreground">This order is packed but has no pickup booked yet.</p> : null}
          <div><Button size="sm" disabled={book.isPending} onClick={() => book.mutate()}>{shipments.length ? "Retry booking" : "Book pickup"}</Button></div>
        </div>
      ) : null}
    </Panel>
  );
}
