<?php

namespace App\Domain\Catalog\Enums;

/**
 * PRD §12. Only physical and digital are sellable in v1; the rest are reserved
 * so the schema and code paths never assume "physical only".
 */
enum ProductType: string
{
    case Physical = 'physical';
    case Digital = 'digital';
    case Service = 'service';
    case Subscription = 'subscription';
    case Bundle = 'bundle';

    /** @return list<self> */
    public static function enabled(): array
    {
        return [self::Physical, self::Digital];
    }

    public function requiresShipping(): bool
    {
        return $this === self::Physical;
    }

    public function tracksInventory(): bool
    {
        return $this === self::Physical;
    }
}
