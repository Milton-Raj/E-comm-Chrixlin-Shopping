<?php

use App\Support\Logging\RedactSensitiveData;

it('redacts sensitive keys recursively and case-insensitively', function () {
    $redacted = RedactSensitiveData::redact([
        'email' => 'a@b.test',
        'password' => 'secret123',
        'Authorization' => 'Bearer x',
        'nested' => ['razorpay_signature' => 'sig', 'card_number' => '4111', 'company' => 'Acme'],
        'pan' => 'ABCDE1234F',
    ]);

    expect($redacted)->toBe([
        'email' => 'a@b.test',
        'password' => '[REDACTED]',
        'Authorization' => '[REDACTED]',
        'nested' => ['razorpay_signature' => '[REDACTED]', 'card_number' => '[REDACTED]', 'company' => 'Acme'],
        'pan' => '[REDACTED]',
    ]);
});
