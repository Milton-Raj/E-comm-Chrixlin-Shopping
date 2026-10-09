<?php

use App\Models\Page;
use Database\Seeders\StorePagesSeeder;

it('publishes the footer pages and explains made-to-order sales only in terms and FAQ', function () {
    (new StorePagesSeeder)->run();

    expect(Page::where('status', 'published')->pluck('slug')->sort()->values()->all())->toBe(['contact', 'faq', 'privacy', 'shipping', 'terms']);

    foreach (['terms', 'faq'] as $slug) {
        expect(Page::where('slug', $slug)->value('body'))->toContain('made')->toContain('refund');
    }
    foreach (['privacy', 'shipping', 'contact'] as $slug) {
        expect(strtolower((string) Page::where('slug', $slug)->value('body')))->not->toContain('refund')->not->toContain('return');
    }

    $this->getJson('/api/v1/pages/terms')->assertOk()->assertJsonPath('data.title', 'Terms & conditions')
        ->assertJsonFragment(['## Why every order is final']);
});

it('never overwrites pages the owner has edited', function () {
    (new StorePagesSeeder)->run();
    Page::where('slug', 'faq')->update(['body' => 'Our own FAQ']);

    (new StorePagesSeeder)->run();

    expect(Page::where('slug', 'faq')->value('body'))->toBe('Our own FAQ');
});
