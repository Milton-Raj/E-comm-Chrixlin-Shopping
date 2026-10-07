/** Loads Razorpay Checkout on demand and resolves with the signed result (verified server-side). */
type RazorpayResult = { razorpay_payment_id: string; razorpay_order_id: string; razorpay_signature: string };

declare global {
  interface Window {
    Razorpay?: new (options: Record<string, unknown>) => { open: () => void; on: (event: string, cb: () => void) => void };
  }
}

function loadScript(): Promise<void> {
  if (window.Razorpay) return Promise.resolve();
  return new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = "https://checkout.razorpay.com/v1/checkout.js";
    script.onload = () => resolve();
    script.onerror = () => reject(new Error("Could not load the payment window."));
    document.body.appendChild(script);
  });
}

export async function openRazorpay(payload: Record<string, unknown>): Promise<RazorpayResult | null> {
  await loadScript();
  return new Promise((resolve) => {
    const instance = new window.Razorpay!({
      key: payload.key_id,
      order_id: payload.order_id,
      amount: payload.amount,
      currency: payload.currency,
      name: payload.name,
      description: payload.description,
      prefill: payload.prefill,
      theme: { color: "#49111c" },
      handler: (result: RazorpayResult) => resolve(result),
      modal: { ondismiss: () => resolve(null) },
    });
    instance.open();
  });
}
