"use client";

/** Last-resort boundary when the root layout itself fails; must render its own <html>. */
export default function GlobalError({ error, retry }: { error: Error & { digest?: string }; retry: () => void }) {
  return (
    <html lang="en">
      <body style={{ fontFamily: "system-ui, sans-serif", display: "grid", placeItems: "center", minHeight: "100vh", margin: 0 }}>
        <div role="alert" style={{ textAlign: "center", padding: 24 }}>
          <h1>Something went wrong</h1>
          <p>Please try again.</p>
          {error.digest ? <p style={{ fontFamily: "monospace", fontSize: 12 }}>Reference: {error.digest}</p> : null}
          <button type="button" onClick={retry} style={{ minHeight: 44, padding: "0 16px" }}>
            Try again
          </button>
        </div>
      </body>
    </html>
  );
}
