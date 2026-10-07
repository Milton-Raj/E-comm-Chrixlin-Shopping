import Link from "next/link";
import { Reveal } from "@/components/motion/reveal";
import { t } from "@/lib/i18n";

export function SectionHeading({ eyebrow, title, href }: { eyebrow?: string; title: string; href?: string }) {
  return (
    <Reveal className="mb-8 flex items-end justify-between gap-4 md:mb-10">
      <div>
        {eyebrow ? <p className="eyebrow mb-3 text-muted-foreground">{eyebrow}</p> : null}
        <h2 className="font-display-tight text-3xl md:text-4xl">{title}</h2>
      </div>
      {href ? (
        <Link href={href} className="eyebrow group/link inline-flex min-h-11 items-center gap-2 hover:text-primary">
          <span className="link-draw">{t("catalog.viewAll")}</span>
          <span aria-hidden className="transition-transform duration-500 group-hover/link:translate-x-1">→</span>
        </Link>
      ) : null}
    </Reveal>
  );
}
