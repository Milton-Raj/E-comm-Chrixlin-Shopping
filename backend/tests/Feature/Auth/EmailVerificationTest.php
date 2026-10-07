<?php

use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Support\Facades\URL;

it('verifies email from a signed link using the public uuid', function () {
    $user = User::factory()->customer()->unverified()->create();

    $url = URL::temporarySignedRoute('v1.verification.verify', now()->addHour(), [
        'id' => $user->uuid, 'hash' => sha1($user->email),
    ]);

    expect($url)->not->toContain('/'.$user->id.'/');

    $this->get($url)->assertRedirect('http://localhost:3000/account?verified=1');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects tampered verification links', function () {
    $user = User::factory()->customer()->unverified()->create();
    $url = URL::temporarySignedRoute('v1.verification.verify', now()->addHour(), ['id' => $user->uuid, 'hash' => sha1($user->email)]);

    $this->getJson($url.'x')->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('builds verification emails with the uuid route', function () {
    $user = User::factory()->customer()->unverified()->create();
    $mail = (new VerifyEmailNotification)->toMail($user);

    expect($mail->actionUrl)->toContain('/api/v1/auth/email/verify/'.$user->uuid.'/');
});
