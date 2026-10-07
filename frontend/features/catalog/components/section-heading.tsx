import Link from "next/link";
import { t } from "@/lib/i18n";

export function SectionHeading({ eyebrow, title, href }: { eyebrow?: string; title: string; href?: string }) {
  return (
    <div className="mb-8 flex items-end justify-between gap-4 md:mb-10">
      <div>
        {eyebrow ? <p className="eyebrow mb-3 text-muted-foreground">{eyebrow}</p> : null}
        <h2 className="font-display-tight text-3xl md:text-4xl">{title}</h2>
      </div>
      {href ? (
        <Link href={href} className="eyebrow inline-flex min-h-11 items-center border-b border-foreground/40 hover:border-primary hover:text-primary">
          {t("catalog.viewAll")}
        </Link>
      ) : null}
    </div>
  );
}
