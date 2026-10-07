import type { FieldValues, Path, UseFormSetError } from "react-hook-form";
import { ApiError } from "@/services/api-client";

/**
 * Maps API validation errors onto form fields; anything else becomes a form-level message.
 * Returns the form-level message (or null when every error was mapped to a field).
 */
export function applyApiErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  fields: readonly Path<T>[],
): string | null {
  if (!(error instanceof ApiError)) {
    return "Something went wrong. Please try again.";
  }

  let unmapped = false;
  for (const [field, messages] of Object.entries(error.fieldErrors)) {
    if ((fields as readonly string[]).includes(field) && messages[0]) {
      setError(field as Path<T>, { type: "server", message: messages[0] });
    } else {
      unmapped = true;
    }
  }

  if (error.isValidation && !unmapped && Object.keys(error.fieldErrors).length > 0) {
    return null;
  }

  return Object.values(error.fieldErrors)[0]?.[0] ?? error.message;
}
