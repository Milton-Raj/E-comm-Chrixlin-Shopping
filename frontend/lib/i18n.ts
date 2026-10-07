import { en, type MessageKey } from "@/messages/en";

/**
 * Minimal typed translator. v1 ships English only (ROADMAP V1); swapping in a
 * full i18n library later only changes this module.
 */
export function t(key: MessageKey, vars?: Record<string, string | number>): string {
  const template: string = en[key];
  if (!vars) return template;

  return template.replace(/\{(\w+)\}/g, (match, name: string) =>
    name in vars ? String(vars[name]) : match,
  );
}

export type { MessageKey };
