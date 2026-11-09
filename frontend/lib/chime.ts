/**
 * A soft two-note bell for new-order alerts, synthesised with the Web Audio API
 * (no audio file to load). Browsers only allow sound after the person has interacted
 * with the page, so call unlockChime() from a click or key press first.
 */
let context: AudioContext | null = null;

function audio(): AudioContext | null {
  if (typeof window === "undefined" || typeof window.AudioContext !== "function") return null;
  context ??= new window.AudioContext();
  return context;
}

export function unlockChime(): void {
  const ctx = audio();
  if (ctx?.state === "suspended") void ctx.resume();
}

/** One bell strike: a sine fundamental with a quieter, faster-fading overtone. */
function strike(ctx: AudioContext, frequency: number, at: number, volume: number): void {
  for (const [ratio, gainScale, decay] of [[1, 1, 1.6], [2.76, 0.18, 0.5], [5.4, 0.05, 0.25]] as const) {
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = "sine";
    osc.frequency.setValueAtTime(frequency * ratio, at);
    gain.gain.setValueAtTime(0.0001, at);
    gain.gain.exponentialRampToValueAtTime(volume * gainScale, at + 0.012);
    gain.gain.exponentialRampToValueAtTime(0.0001, at + decay);
    osc.connect(gain).connect(ctx.destination);
    osc.start(at);
    osc.stop(at + decay + 0.05);
  }
}

export function playChime(volume = 0.22): void {
  const ctx = audio();
  if (!ctx || ctx.state !== "running") return;
  const now = ctx.currentTime + 0.02;
  strike(ctx, 1318.51, now, volume);        // E6
  strike(ctx, 1760, now + 0.16, volume * 0.85); // A6
}
