import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { LoginForm } from "./login-form";

const push = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({ push, replace: vi.fn() }),
  useSearchParams: () => new URLSearchParams("next=/orders"),
}));

function renderForm() {
  const client = new QueryClient({ defaultOptions: { mutations: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <LoginForm />
    </QueryClientProvider>,
  );
}

describe("LoginForm", () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    vi.stubGlobal("fetch", fetchMock);
    document.cookie = "XSRF-TOKEN=abc; path=/";
  });

  afterEach(() => {
    fetchMock.mockReset();
    push.mockReset();
    vi.unstubAllGlobals();
  });

  it("validates input before calling the API", async () => {
    renderForm();
    await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findByText("Enter a valid email address.")).toBeInTheDocument();
    expect(screen.getByLabelText("Email")).toHaveAttribute("aria-invalid", "true");
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("shows server errors on the matching field", async () => {
    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ success: false, message: "Validation failed", errors: { email: ["These credentials do not match our records."] } }), { status: 422 }),
    );
    renderForm();

    await userEvent.type(screen.getByLabelText("Email"), "asha@example.test");
    await userEvent.type(screen.getByLabelText("Password"), "wrong-password");
    await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findByText("These credentials do not match our records.")).toBeInTheDocument();
    expect(push).not.toHaveBeenCalled();
  });

  it("redirects to the two-factor step when required", async () => {
    fetchMock.mockResolvedValueOnce(
      new Response(JSON.stringify({ success: true, data: { two_factor_required: true }, message: null, meta: {} }), { status: 200 }),
    );
    renderForm();

    await userEvent.type(screen.getByLabelText("Email"), "asha@example.test");
    await userEvent.type(screen.getByLabelText("Password"), "correct-horse-battery");
    await userEvent.click(screen.getByRole("button", { name: "Sign in" }));

    await vi.waitFor(() => expect(push).toHaveBeenCalledWith("/login/two-factor?next=%2Forders"));
  });
});
