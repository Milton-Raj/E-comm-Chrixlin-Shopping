"use client";

import Image from "next/image";
import { useState, type PointerEvent } from "react";
import { cn } from "@/lib/utils";
import type { ProductImage } from "../types";

/** Main image with hover zoom on fine pointers (PRD §59); thumbnails when there is more than one image. */
export function ProductGallery({ images, name }: { images: ProductImage[]; name: string }) {
  const [active, setActive] = useState(0);
  const [origin, setOrigin] = useState<string | null>(null);
  const image = images[active] ?? images[0]!;

  const onMove = (event: PointerEvent<HTMLDivElement>) => {
    if (event.pointerType !== "mouse") return;
    const rect = event.currentTarget.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width) * 100;
    const y = ((event.clientY - rect.top) / rect.height) * 100;
    setOrigin(`${x}% ${y}%`);
  };

  return (
    <div className="grid gap-3">
      <div
        className="relative aspect-4/5 overflow-hidden bg-muted md:cursor-zoom-in"
        onPointerMove={onMove}
        onPointerLeave={() => setOrigin(null)}
      >
        <Image
          key={image.url}
          src={image.url}
          alt={image.alt}
          fill
          preload
          sizes="(min-width: 1024px) 55vw, 100vw"
          className={cn("animate-fade object-cover transition-transform duration-500 ease-out motion-reduce:transition-none", origin && "scale-175")}
          style={origin ? { transformOrigin: origin } : undefined}
        />
      </div>
      {images.length > 1 ? (
        <ul className="grid grid-cols-5 gap-2" aria-label={`${name} images`}>
          {images.map((img, i) => (
            <li key={img.url}>
              <button
                type="button"
                onClick={() => setActive(i)}
                aria-current={i === active}
                className={cn("relative block aspect-square w-full overflow-hidden border", i === active ? "border-foreground" : "border-transparent")}
              >
                <Image src={img.url} alt="" fill sizes="96px" className="object-cover" />
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
