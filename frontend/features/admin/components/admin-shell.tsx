"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { ReactNode } from "react";
import { env } from "@/lib/env";
import { t, type MessageKey } from "@/lib/i18n";
import { cn } from "@/lib/utils";
import type { AdminMe } from "@/types/api";
import { visibleAdminNav } from "../navigation";

const label = (key: string) => (key.includes(".") ? t(key as MessageKey) : key);

export function AdminShell({ me, children }: { me: AdminMe; children: ReactNode }) {
  const pathname = usePathname();
  const nav = visibleAdminNav(me.permissions);

  return (
    <div data-admin className="min-h-dvh lg:grid lg:grid-cols-[16rem_1fr]">
      <aside className="border-b bg-muted/30 lg:min-h-dvh lg:border-r lg:border-b-0">
        <div className="flex h-16 items-center px-4 font-semibold">
          <Link href="/admin">{env.storeName} · {t("admin.title")}</Link>
        </div>
        <nav data-admin-nav aria-label={t("admin.title")} className="overflow-x-auto px-2 pb-2 lg:pb-6">
          <ul className="flex gap-1 lg:flex-col">
            {nav.map((item) => {
              const active = item.href === "/admin" ? pathname === "/admin" : pathname.startsWith(item.href);
              const className = cn(
                "flex min-h-11 items-center whitespace-nowrap rounded-lg px-3 text-sm transition-all duration-300",
                active ? "bg-background font-semibold shadow-sm" : "text-muted-foreground",
                item.available ? "hover:bg-background hover:text-foreground" : "cursor-not-allowed opacity-50",
              );
              return (
                <li key={item.href}>
                  {item.available ? (
                    <Link href={item.href} aria-current={active ? "page" : undefined} className={className}><span>{label(item.label)}</span></Link>
                  ) : (
                    <span aria-disabled="true" className={className}>{label(item.label)}</span>
                  )}
                </li>
              );
            })}
          </ul>
        </nav>
      </aside>
      <div className="min-w-0">
        <header className="flex h-16 items-center justify-end gap-4 border-b px-4 text-sm text-muted-foreground md:px-6">
          <Link href="/" className="underline-offset-4 hover:underline">View store</Link>
          <span>{t("admin.welcome", { name: me.user.name })}</span>
        </header>
        <main id="main" className="p-4 md:p-6">{children}</main>
      </div>
    </div>
  );
}
