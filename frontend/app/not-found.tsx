import { ButtonLink } from "@/components/button-link";
import { t } from "@/lib/i18n";

export default function NotFound() {
  return (
    <main id="main" className="mx-auto flex min-h-[70vh] max-w-md flex-col items-center justify-center gap-3 px-4 text-center">
      <p className="text-sm font-medium text-muted-foreground">404</p>
      <h1 className="text-2xl font-semibold tracking-tight">{t("common.notFoundTitle")}</h1>
      <p className="text-muted-foreground">{t("common.notFoundBody")}</p>
      <ButtonLink href="/" className="mt-2">{t("common.backHome")}</ButtonLink>
    </main>
  );
}
