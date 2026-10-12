import type { Metadata } from "next";
import { Building2, Gift, PackageCheck } from "lucide-react";
import { Suspense } from "react";
import { getSiteContent, siteText } from "@/features/catalog/site-content";
import { EnquiryForm } from "@/features/marketing/components/enquiry-form";
import { t } from "@/lib/i18n";

export const metadata: Metadata = { title: t("gifting.metaTitle"), description: t("gifting.intro") };

export default function CorporateGiftingPage() {
  return (
    <div className="mx-auto grid max-w-6xl gap-10 px-4 py-12 md:px-6 md:py-16 lg:grid-cols-5 lg:gap-14">
      <Suspense fallback={<div className="lg:col-span-2" />}>
        <Intro />
      </Suspense>
      <div className="lg:col-span-3">
        <EnquiryForm />
      </div>
    </div>
  );
}

async function Intro() {
  const content = await getSiteContent();
  const points = [
    { icon: Gift, text: siteText(content, "gifting.point1") },
    { icon: Building2, text: siteText(content, "gifting.point2") },
    { icon: PackageCheck, text: siteText(content, "gifting.point3") },
  ];

  return (
    <div className="grid content-start gap-6 lg:col-span-2">
      <p className="eyebrow text-primary">{t("gifting.eyebrow")}</p>
      <h1 className="font-display-tight text-4xl md:text-5xl">{siteText(content, "gifting.title")}</h1>
      <p className="text-muted-foreground">{siteText(content, "gifting.intro")}</p>
      <ul className="grid gap-3">
        {points.map(({ icon: Icon, text }) => (
          <li key={text} className="flex items-start gap-3 text-sm"><Icon className="mt-0.5 size-5 shrink-0 text-primary" aria-hidden />{text}</li>
        ))}
      </ul>
    </div>
  );
}
