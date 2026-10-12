<?php

use App\Models\AuditLog;
use App\Models\Enquiry;
use App\Models\User;
use App\Notifications\EnquiryReceivedNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function enquiry(array $overrides = []): array
{
    return array_merge([
        'name' => 'Priya', 'company' => 'Acme Pvt Ltd', 'email' => 'priya@acme.test', 'phone' => '+91 98765 43210',
        'quantity' => 120, 'budget' => '₹800–1,200', 'occasion' => 'Diwali', 'needed_by' => now()->addMonth()->toDateString(),
        'message' => 'Diwali gifts for our team with our logo on the box.',
    ], $overrides);
}

it('saves an enquiry and emails the store and the sender', function () {
    Notification::fake();

    $this->postJson('/api/v1/enquiries', enquiry())->assertCreated();

    expect(Enquiry::query()->where('status', 'new')->where('company', 'Acme Pvt Ltd')->exists())->toBeTrue();
    Notification::assertSentTo(new AnonymousNotifiable, EnquiryReceivedNotification::class, fn ($n, $c, $to) => $n->forStore && $to->routes['mail'] === config('mail.from.address'));
    Notification::assertSentTo(new AnonymousNotifiable, EnquiryReceivedNotification::class, fn ($n, $c, $to) => ! $n->forStore && $to->routes['mail'] === 'priya@acme.test');
});

it('rejects bots that fill the hidden field and invalid input', function () {
    Notification::fake();

    $this->postJson('/api/v1/enquiries', enquiry(['website' => 'http://spam.test']))->assertStatus(422);
    $this->postJson('/api/v1/enquiries', enquiry(['email' => 'nope', 'message' => 'hi', 'needed_by' => '2020-01-01']))
        ->assertStatus(422)->assertJsonValidationErrors(['email', 'message', 'needed_by']);
    expect(Enquiry::query()->count())->toBe(0);
});

it('rate limits the public form', function () {
    Notification::fake();
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/enquiries', enquiry())->assertCreated();
    }
    $this->postJson('/api/v1/enquiries', enquiry())->assertStatus(429);
});

it('lets support staff track enquiries, audited', function () {
    Notification::fake();
    $this->postJson('/api/v1/enquiries', enquiry());
    $uuid = Enquiry::query()->value('uuid');

    $this->actingAs(User::factory()->staff('customer-support')->create());
    $this->getJson('/api/v1/admin/enquiries')->assertOk()->assertJsonPath('data.0.company', 'Acme Pvt Ltd')->assertJsonPath('meta.counts.new', 1);
    $this->patchJson("/api/v1/admin/enquiries/{$uuid}", ['status' => 'quoted', 'notes' => 'Sent 3 box options'])->assertOk()->assertJsonPath('data.status', 'quoted');
    expect(AuditLog::query()->where('action', 'enquiry.updated')->exists())->toBeTrue();

    $this->actingAs(User::factory()->staff('inventory-manager')->create());
    $this->getJson('/api/v1/admin/enquiries')->assertForbidden();
});
