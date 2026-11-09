import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { miscAdminApi, type AdminNotifications, type OrderAlert } from "../api";
import { NotificationBell } from "./notification-bell";

const chime = vi.hoisted(() => ({ playChime: vi.fn(), unlockChime: vi.fn() }));
vi.mock("@/lib/chime", () => chime);
vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }) }));
vi.mock("sonner", () => ({ toast: { success: vi.fn() } }));

const alert = (id: string, read = false): OrderAlert => ({
  id, type: "order_paid", read, created_at: new Date().toISOString(),
  data: { order_number: `ORD-2026-00000${id}`, customer: "Asha", item_count: 2, total: { amount: 261_000, currency: "INR" }, charged: null, country_code: "IN" },
});

function renderBell(client = new QueryClient({ defaultOptions: { queries: { retry: false } } })) {
  render(<QueryClientProvider client={client}><NotificationBell /></QueryClientProvider>);
  return client;
}

describe("NotificationBell", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.localStorage.clear();
  });

  it("shows the unread count and lists orders", async () => {
    vi.spyOn(miscAdminApi, "notifications").mockResolvedValue({ items: [alert("1"), alert("2", true)], unread_count: 1 });
    renderBell();

    const button = await screen.findByRole("button", { name: "Notifications, 1 unread" });
    fireEvent.click(button);
    expect(await screen.findByText(/Order ORD-2026-000001/)).toBeInTheDocument();
    expect(screen.getAllByText(/Asha · 2 items · ₹2,610.00/)).toHaveLength(2);
  });

  it("chimes only for orders that arrive after the page loaded", async () => {
    const spy = vi.spyOn(miscAdminApi, "notifications").mockResolvedValue({ items: [alert("1")], unread_count: 1 });
    const client = renderBell();
    await screen.findByRole("button", { name: "Notifications, 1 unread" });
    expect(chime.playChime).not.toHaveBeenCalled(); // existing alerts are the baseline

    spy.mockResolvedValue({ items: [alert("2"), alert("1")], unread_count: 2 } satisfies AdminNotifications);
    await client.refetchQueries({ queryKey: ["admin", "notifications"] });
    await waitFor(() => expect(chime.playChime).toHaveBeenCalledTimes(1));

    await client.refetchQueries({ queryKey: ["admin", "notifications"] });
    expect(chime.playChime).toHaveBeenCalledTimes(1); // not again for the same order
  });

  it("stays quiet when the sound is turned off", async () => {
    window.localStorage.setItem("admin-alerts-muted", "1");
    const spy = vi.spyOn(miscAdminApi, "notifications").mockResolvedValue({ items: [], unread_count: 0 });
    const client = renderBell();
    await screen.findByRole("button", { name: "Notifications" });

    spy.mockResolvedValue({ items: [alert("3")], unread_count: 1 });
    await client.refetchQueries({ queryKey: ["admin", "notifications"] });
    await screen.findByRole("button", { name: "Notifications, 1 unread" });
    expect(chime.playChime).not.toHaveBeenCalled();
  });

  it("shows an empty state", async () => {
    vi.spyOn(miscAdminApi, "notifications").mockResolvedValue({ items: [], unread_count: 0 });
    renderBell();
    fireEvent.click(await screen.findByRole("button", { name: "Notifications" }));
    expect(await screen.findByText(/hear a chime the moment one is paid/)).toBeInTheDocument();
  });
});
