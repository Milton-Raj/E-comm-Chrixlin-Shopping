import { DashboardPage } from "@/features/admin/pages/dashboard";

/** Rendered after the client-side staff check (see admin/layout.tsx). */
export const instant = false;

export default function AdminHomePage() {
  return <DashboardPage />;
}
