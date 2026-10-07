<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'is_staff' => $this->isStaff(),
            'marketing_opt_in' => $this->marketing_opt_in,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
