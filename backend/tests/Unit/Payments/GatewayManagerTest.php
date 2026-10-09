<?php

use App\Domain\Payments\GatewayManager;

it('labels Razorpay as test mode when test keys are configured', function () {
    config(['payments.razorpay.key_id' => 'rzp_test_abc', 'payments.razorpay.key_secret' => 'secret', 'payments.test.enabled' => false]);
    expect((new GatewayManager)->available())->toBe([['key' => 'razorpay', 'name' => 'Razorpay (test mode)', 'description' => 'Test payments only, no real money is charged']]);

    config(['payments.razorpay.key_id' => 'rzp_live_abc']);
    expect((new GatewayManager)->available()[0]['name'])->toBe('Razorpay');
});
