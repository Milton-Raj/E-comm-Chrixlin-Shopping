import type { ReactNode } from "react";
import { ButtonLink } from "@/components/button-link";
import { EmptyState } from "@/components/states/empty-state";
import { t } from "@/lib/i18n";

/**
 * Interim page for routes whose features ship in later phases (ROADMAP.md),
 * so navigation never lands on a 404. Replaced by the real page in its phase.
 */
export function PlaceholderPage({
  heading,
  title,
  description,
  icon,
  actions,
}: {
  heading: string;
  title: string;
  description: string;
  icon?: ReactNode;
  actions?: ReactNode;
}) {
  return (
    <div className="mx-auto max-w-7xl px-4 py-10 md:px-6 md:py-14">
      <h1 className="mb-6 text-2xl font-semibold tracking-tight md:text-3xl">{heading}</h1>
      <EmptyState
        icon={icon}
        title={title}
        description={description}
        action={
          actions ?? (
            <div className="flex flex-wrap justify-center gap-2">
              <ButtonLink href="/register">{t("placeholder.createAccount")}</ButtonLink>
              <ButtonLink href="/" variant="outline">{t("common.backHome")}</ButtonLink>
            </div>
          )
        }
      />
    </div>
  );
}
