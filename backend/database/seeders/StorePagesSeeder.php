<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Support\Settings\Settings;
use Illuminate\Database\Seeder;

/**
 * Chrixlin's footer pages (terms, privacy, shipping, FAQ, contact). Runs in every environment.
 * Missing pages are created; a page is only updated when the owner has never edited it,
 * so changes made in Admin → Content are always kept.
 * Body format: blank-line paragraphs; a paragraph starting with "## " is a heading.
 */
class StorePagesSeeder extends Seeder
{
    private const SEEDED = 'content.seeded_page_hashes';

    public function run(): void
    {
        $store = (string) config('commerce.store.name', 'Chrixlin');
        $email = (string) config('mail.from.address');
        $settings = app(Settings::class);
        $seeded = (array) $settings->get(self::SEEDED, []);

        foreach ($this->pages($store, $email) as $slug => [$title, $description, $body]) {
            $page = Page::query()->where('slug', $slug)->first();

            if (! $page) {
                Page::query()->create([
                    'slug' => $slug, 'title' => $title, 'seo_description' => $description, 'body' => $body,
                    'status' => 'published', 'published_at' => now(),
                ]);
            } elseif ($page->body !== $body && $this->untouched($page, $seeded[$slug] ?? null)) {
                // Our wording changed and the owner never edited this page: roll the update out.
                $page->forceFill(['title' => $title, 'seo_description' => $description, 'body' => $body])->save();
            } elseif ($page->body !== $body) {
                continue;
            }
            $seeded[$slug] = sha1($body);
        }

        $settings->set(self::SEEDED, $seeded);
    }

    /** True when the page still holds exactly what this seeder last wrote. */
    private function untouched(Page $page, ?string $seededHash): bool
    {
        if ($seededHash !== null) {
            return sha1((string) $page->body) === $seededHash;
        }

        // Pages seeded before hashes were recorded: unchanged since creation.
        return $page->created_at !== null && $page->updated_at->equalTo($page->created_at);
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    private function pages(string $store, string $email): array
    {
        $madeForYou = "## Why every order is final\n\n"
            ."Each {$store} piece is made by hand, just for you, after your order is confirmed. We do not keep ready-made stock on a shelf: once your payment comes through, we begin pouring, sculpting and decorating your candle or casting your Jesmonite piece.\n\n"
            ."Because your piece is made especially for your order, and because candles and decorative pieces cannot be resold once they have left our studio, we are not able to accept returns or exchanges or offer refunds once an order is placed.\n\n"
            ."We know this matters when you shop online, so we put real care into every step: we check each piece before it is packed, wrap it securely for its journey, and are always happy to answer questions about size, colour or fragrance before you order. If you are unsure about anything, please write to us first at {$email}. We would much rather help you choose than have you feel uncertain.";

        return [
            'terms' => ['Terms & conditions', "The terms for shopping with {$store}, including how our made-to-order pieces work.", implode("\n\n", [
                "Welcome to {$store}. These terms apply whenever you browse our store or place an order with us. By placing an order you agree to them, so please read them carefully. If you have any questions, write to us at {$email}.",
                '## Our products',
                "{$store} makes handmade dessert candles and Jesmonite décor. Every piece is made by hand in small batches, so small variations in colour, swirl, texture and finish are a natural part of handmade work and make each piece one of a kind. Photographs on our website show the style of each design; your piece will be made to the same design but will not be an identical copy of the photo.",
                'Our dessert candles look good enough to eat, but they are not food. Please never eat or taste any part of them, and keep them away from children and pets.',
                '## Orders',
                'When you place an order, you will receive an email confirming the details. Your order is accepted once your payment has been confirmed by our payment partner. We may decline or cancel an order if a product is unavailable or a payment cannot be verified; if that happens after payment, the full amount will be returned to your original payment method.',
                'Each piece is made for you after your order is confirmed, so please check your order, delivery address and contact details carefully before you pay. If you notice a mistake, write to us straight away with your order number and we will do our best to help before work on your piece begins.',
                $madeForYou,
                '## If your order arrives damaged',
                "We pack every piece with great care, but parcels sometimes have a rough journey. If your order arrives damaged, or you receive a different item from the one you ordered, please email {$email} within 24 hours of delivery with your order number and clear photos of the item and its packaging (an unboxing video helps too). We will look into it straight away and arrange a replacement piece for you.",
                '## Prices and payment',
                'All prices are shown in Indian Rupees and include GST where applicable. A GST tax invoice is emailed to you once your payment is confirmed. We accept secure online payments only (UPI, cards, net banking and wallets through our payment partner). We do not offer cash on delivery, and we never see or store your full card details.',
                '## Delivery',
                'We ship across India through trusted courier partners. The delivery charge and estimate for your address are shown at checkout. As each piece is made after you order, please allow time for making before dispatch. You will receive tracking details by email once your order is on its way. See our Shipping policy for more detail.',
                '## Candle care and safety',
                'Never leave a burning candle unattended. Burn candles on a heat-resistant surface, away from anything flammable, draughts, children and pets. Trim the wick to about 5 mm before each use and do not burn a candle for more than 3–4 hours at a time. Stop using a candle when about 1 cm of wax remains. Decorative toppers may soften or change shape as the candle burns.',
                'Jesmonite pieces are decorative. Wipe them with a soft, slightly damp cloth; they are not dishwasher safe and are not intended for direct contact with food or open flames unless a product page says otherwise.',
                '## Your account',
                'You are responsible for keeping your account password private and for activity on your account. Please tell us straight away if you think someone else has used it.',
                '## Intellectual property',
                "All designs, photographs, text and the {$store} name and logo belong to {$store} and may not be copied or used without our written permission.",
                '## Liability',
                'We take care to describe our products accurately and to make them safely. To the extent permitted by law, we are not responsible for loss or damage caused by using a product in a way that goes against the care and safety guidance above. Nothing in these terms limits any rights you have under Indian consumer law.',
                '## Governing law',
                "These terms are governed by the laws of India. Any dispute will be handled by the courts that have jurisdiction where {$store} is registered.",
                '## Complaints and grievances',
                "If you are unhappy with anything about your order or our service, please write to our Grievance Officer at {$email} with your order number. We acknowledge every complaint within 48 hours and aim to resolve it within one month, in line with the Consumer Protection (E-Commerce) Rules, 2020.",
                '## Changes to these terms',
                'We may update these terms from time to time. The version shown on this page when you place your order is the one that applies to that order.',
                '## Contact',
                "Questions about these terms or an order? Write to us at {$email} and include your order number if you have one.",
            ])],

            'faq' => ['Frequently asked questions', "Answers to common questions about {$store} dessert candles, Jesmonite pieces, orders and delivery.", implode("\n\n", [
                "## How long will my order take?\nEvery piece is made by hand after your order is confirmed. Once it is ready, we pack it carefully and send it by courier. The delivery estimate for your address is shown at checkout, and you will receive tracking details by email when your order is dispatched.",
                "## Can I return or exchange my order?\nWe are sorry, but we are not able to accept returns or exchanges or offer refunds. Each {$store} piece is made specially for you once your order is confirmed. We don't sell from ready-made stock, and a handmade candle or Jesmonite piece cannot be resold once it has left our studio. We hope you understand, and we are always happy to help you choose the right piece before you order. Just write to us at {$email}.",
                "## What if my order arrives damaged?\nWe pack every piece with great care, but if your order arrives damaged, or you receive a different item, please email {$email} within 24 hours of delivery with your order number and photos of the item and its packaging (an unboxing video helps too). We will look into it straight away and arrange a replacement piece for you.",
                "## Can I change my order after placing it?\nPlease write to {$email} as soon as possible with your order number. Because we start making your piece soon after your order is confirmed, we can only make changes before work on it begins.",
                "## Will my candle look exactly like the photo?\nYour piece is made to the same design, by hand. Small differences in colour, glaze drips, swirls and texture are part of what makes handmade pieces special, so no two are exactly alike.",
                "## Are the dessert candles edible?\nNo. They are made to look like desserts, but they are candles. Please never eat or taste them, and keep them away from children and pets.",
                "## How should I burn my candle safely?\nNever leave it unattended. Place it on a heat-resistant surface away from draughts and anything flammable, trim the wick to about 5 mm before lighting, and burn for no more than 3–4 hours at a time.",
                "## How do I look after Jesmonite pieces?\nWipe gently with a soft, slightly damp cloth. Jesmonite pieces are decorative and not dishwasher safe.",
                "## How can I pay?\nWe accept secure online payments: UPI, debit and credit cards, net banking and wallets. We do not offer cash on delivery. A GST tax invoice is emailed to you as soon as your payment is confirmed.",
                "## Do you ship across India?\nYes. We deliver across India with trusted courier partners. You can see the delivery charge for your address at checkout.",
                "## Can I order a candle as a gift?\nOf course. Enter the recipient's address at checkout. If you would like a note included, write to us with your order number.",
                "## How do I contact you?\nEmail us at {$email}. Please include your order number if your question is about an order.",
            ])],

            'shipping' => ['Shipping policy', "How {$store} makes, packs and delivers your order across India.", implode("\n\n", [
                "Every {$store} piece is made by hand after your order is confirmed. Once it is ready, we check it, wrap it carefully and hand it to one of our courier partners.",
                "## Where we deliver\nWe deliver across India. The delivery options and charges for your address are shown at checkout before you pay.",
                "## How long it takes\nPlease allow time for your piece to be made, plus the courier's delivery time. The estimate for your address is shown at checkout.",
                "## Tracking\nAs soon as your order is dispatched, we email you the courier name and tracking number. You can also follow your order from your account.",
                "## Receiving your order\nPlease make sure the delivery address and phone number are correct, and that someone is available to receive the parcel. Candles are best kept away from direct sun and heat, so please bring your parcel indoors as soon as it arrives.",
                "## Questions\nWrite to us at {$email} with your order number and we will be glad to help.",
            ])],

            'privacy' => ['Privacy policy', "How {$store} collects, uses and protects your personal information.", implode("\n\n", [
                "Your privacy matters to us. This policy explains what information {$store} collects when you use our store, why we need it and how we keep it safe.",
                "## What we collect\nWhen you create an account or place an order, we collect your name, email address, phone number and delivery and billing addresses. We also keep a record of your orders and invoices. When you browse, we use essential cookies to keep you signed in, remember your bag and keep the store secure.",
                "## How we use it\nWe use your information to make and deliver your order, send order updates and your GST invoice, answer your questions, keep your account secure and meet our legal and tax obligations. We only send marketing emails if you have chosen to receive them, and you can stop them at any time.",
                "## Who we share it with\nWe share only what is needed with the partners who help us run the store: our payment partner (to process your payment securely; we never see or store your full card details), our courier partners (to deliver your order) and our email and hosting providers. We never sell your personal information.",
                "## How long we keep it\nWe keep your account information while your account is open. Order and invoice records are kept for as long as Indian tax law requires.",
                "## Keeping it safe\nYour data is sent over encrypted connections, passwords are stored securely hashed, and access to customer information is limited to staff who need it.",
                "## Your choices\nYou can view and update your details in your account at any time. To ask for a copy of your information or for your account to be closed, write to us at {$email}.",
                "## Changes\nWe may update this policy from time to time. The latest version will always be on this page.",
                "## Contact\nQuestions about your privacy? Write to us at {$email}.",
            ])],

            'contact' => ['Contact us', "Get in touch with {$store}.", implode("\n\n", [
                'We would love to hear from you, whether you have a question about a design, need help choosing a gift, or want to know where your order is.',
                "## Email\n{$email}",
                "## About an order?\nPlease include your order number so we can help you quickly.",
            ])],
        ];
    }
}
