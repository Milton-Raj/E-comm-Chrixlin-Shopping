<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\StaffAccess;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Notifications\Auth\StaffInvitationNotification;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Adds a staff member. A new person gets an account with an unusable random password
 * and an email link to choose their own; an existing customer keeps their account and
 * password and gains the staff role. Passwords never pass through the admin.
 */
class InviteStaffMember
{
    public function handle(User $actor, string $name, string $email, string $role): User
    {
        StaffAccess::ensureCanGrant($actor, $role);
        $email = Str::lower(trim($email));

        [$user, $isNew] = DB::transaction(function () use ($actor, $name, $email, $role) {
            /** @var User|null $existing */
            $existing = User::withTrashed()->where('email', $email)->lockForUpdate()->first();

            if ($existing?->trashed()) {
                throw new ApiException('This email belongs to a closed account and can’t be reused.', 409, 'account_closed');
            }
            if ($existing?->isStaff()) {
                throw new ApiException('This person is already a staff member.', 409, 'already_staff');
            }

            $user = $existing ?? User::create(['name' => $name, 'email' => $email, 'password' => Str::password(48)]);
            if (! $existing) {
                // The invitation link proves the address; the admin vouches for it meanwhile.
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            $user->assignRole($role);

            Audit::record($existing ? 'staff.promoted' : 'staff.created', $user, null, ['email' => $email, 'role' => $role], $actor);

            return [$user, ! $existing];
        });

        $this->sendInvitation($user, $actor, $isNew);

        return $user;
    }

    public function resend(User $actor, User $user): void
    {
        StaffAccess::ensureCanManage($actor, $user);
        if (! $user->isStaff()) {
            throw new ApiException('This person is not a staff member.', 404, 'not_staff');
        }

        $this->sendInvitation($user, $actor, true);
        Audit::record('staff.invite_resent', $user, null, null, $actor);
    }

    private function sendInvitation(User $user, User $actor, bool $withPasswordLink): void
    {
        $token = $withPasswordLink ? Password::broker()->createToken($user) : null;
        $user->notify(new StaffInvitationNotification($actor->name, (string) StaffAccess::staffRoleOf($user), $token));
    }
}
