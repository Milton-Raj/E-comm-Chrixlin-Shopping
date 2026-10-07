<?php

namespace App\Domain\Identity\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) with replay protection and one-time recovery codes
 * (SECURITY.md §3). Recovery codes are stored as SHA-256 hashes inside an
 * encrypted column; they are high-entropy, so a fast hash is appropriate.
 */
class TwoFactorAuthenticator
{
    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $engine) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->engine->getQRCodeUrl((string) config('commerce.store.name'), $user->email, $secret);
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($this->otpauthUrl($user, $secret));
    }

    /**
     * Verifies a TOTP code for the user's stored secret, rejecting codes from an
     * already-used time step. Persists the new time step on success.
     */
    public function verify(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        $timestep = $this->engine->verifyKeyNewer(
            $user->two_factor_secret,
            preg_replace('/\s+/', '', $code) ?? '',
            $user->two_factor_last_used_timestep ?? 0, // non-null => returns the matched timestep
            1,
        );

        if ($timestep === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_used_timestep' => (int) $timestep])->save();

        return true;
    }

    /**
     * Consumes a recovery code if it matches one of the stored hashes.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $hashes = $user->two_factor_recovery_codes ?? [];
        $candidate = $this->hash($code);

        foreach ($hashes as $index => $hash) {
            if (hash_equals($hash, $candidate)) {
                unset($hashes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Generates fresh plaintext codes (shown to the user once) and stores their hashes.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = array_map(
            fn () => Str::lower(Str::random(5).'-'.Str::random(5)),
            range(1, self::RECOVERY_CODE_COUNT),
        );

        $user->forceFill([
            'two_factor_recovery_codes' => array_map($this->hash(...), $codes),
        ])->save();

        return $codes;
    }

    private function hash(string $code): string
    {
        return hash('sha256', Str::lower(trim($code)));
    }
}
