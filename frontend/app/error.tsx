"use client";

import { useEffect } from "react";
import { ErrorState } from "@/components/states/error-state";

export default function RouteError({ error, retry }: { error: Error & { digest?: string }; retry: () => void }) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <div className="mx-auto max-w-xl px-4 py-16">
      <ErrorState requestId={error.digest ?? null} onRetry={retry} />
    </div>
  );
}
