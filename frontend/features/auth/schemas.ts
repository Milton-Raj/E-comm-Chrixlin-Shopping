import { z } from "zod";
import { t } from "@/lib/i18n";

/** Mirrors backend FormRequests; the API stays authoritative. */

const email = z.email(t("validation.email")).max(255);

export const loginSchema = z.object({
  email,
  password: z.string().min(1, t("validation.required")).max(255),
  remember: z.boolean(),
});

export const registerSchema = z
  .object({
    name: z.string().trim().min(1, t("validation.required")).max(120),
    email,
    password: z.string().min(6, t("validation.passwordMin")).max(255),
    password_confirmation: z.string(),
    marketing_opt_in: z.boolean(),
  })
  .refine((v) => v.password === v.password_confirmation, {
    path: ["password_confirmation"],
    message: t("validation.passwordMatch"),
  });

export const twoFactorCodeSchema = z.object({
  code: z.string().regex(/^\d{6}$/, t("validation.code")),
});

/** Sign-in challenge accepts either a TOTP code or a recovery code, depending on the chosen mode. */
export function twoFactorChallengeSchema(useRecoveryCode: boolean) {
  return z
    .object({ code: z.string(), recovery_code: z.string().max(32) })
    .superRefine((value, ctx) => {
      if (useRecoveryCode && value.recovery_code.trim() === "") {
        ctx.addIssue({ code: "custom", path: ["recovery_code"], message: t("validation.required") });
      }
      if (!useRecoveryCode && !/^\d{6}$/.test(value.code)) {
        ctx.addIssue({ code: "custom", path: ["code"], message: t("validation.code") });
      }
    });
}

export const forgotPasswordSchema = z.object({ email });

export const resetPasswordSchema = z
  .object({
    password: z.string().min(6, t("validation.passwordMin")).max(255),
    password_confirmation: z.string(),
  })
  .refine((v) => v.password === v.password_confirmation, {
    path: ["password_confirmation"],
    message: t("validation.passwordMatch"),
  });

export type LoginInput = z.infer<typeof loginSchema>;
export type RegisterInput = z.infer<typeof registerSchema>;
