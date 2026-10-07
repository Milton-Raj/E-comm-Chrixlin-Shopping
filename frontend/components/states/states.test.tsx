import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { EmptyState } from "./empty-state";
import { ErrorState } from "./error-state";
import { LoadingState } from "./loading-state";

describe("state components", () => {
  it("renders an error with a reference id and retry", async () => {
    const onRetry = vi.fn();
    render(<ErrorState requestId="req-1" onRetry={onRetry} />);

    expect(screen.getByRole("alert")).toHaveTextContent("Reference: req-1");
    await userEvent.click(screen.getByRole("button", { name: "Try again" }));
    expect(onRetry).toHaveBeenCalledOnce();
  });

  it("announces loading to screen readers", () => {
    render(<LoadingState />);
    expect(screen.getByRole("status")).toHaveTextContent("Loading…");
  });

  it("renders empty state copy", () => {
    render(<EmptyState title="No orders yet" description="Your orders will appear here." />);
    expect(screen.getByRole("heading", { name: "No orders yet" })).toBeInTheDocument();
  });
});
