/**
 * The shopper's own currency choice, shared by every price on the page and kept in
 * localStorage. Null until they choose; the API's default for their country applies then.
 */
const KEY = "currency";
const listeners = new Set<() => void>();
let chosen: string | null | undefined;

function read(): string | null {
  if (chosen === undefined) {
    try {
      chosen = window.localStorage.getItem(KEY);
    } catch {
      chosen = null;
    }
  }
  return chosen;
}

export const currencyStore = {
  subscribe(listener: () => void) {
    listeners.add(listener);
    return () => listeners.delete(listener);
  },
  getSnapshot: read,
  getServerSnapshot: (): string | null => null,
  set(code: string) {
    chosen = code;
    try {
      window.localStorage.setItem(KEY, code);
    } catch {
      // Private mode: the choice lasts for this page view.
    }
    listeners.forEach((listener) => listener());
  },
};
