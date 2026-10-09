<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use App\Support\Settings\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Puts Chrixlin's launch hero slides into Admin → Content so the owner can edit them.
 * Runs once: if the owner later deletes every slide, they are not recreated.
 */
class HeroSlidesSeeder extends Seeder
{
    private const DONE = 'content.hero_defaults_seeded';

    public function run(): void
    {
        $settings = app(Settings::class);
        if ($settings->get(self::DONE) || HeroSlide::query()->exists()) {
            return;
        }

        $slides = [
            ['berry-cake.jpg', '70% 50%', 'Dessert candles', 'Good enough to eat. Made to light.', 'Hand-sculpted berry cakes, glazed and dripping, that fill the room with a warm, sweet glow.', 'Shop dessert candles', '/search?q=candle', 'Lit candle shaped like a chocolate drip cake topped with wax berries'],
            ['berry-trio.jpg', '68% 50%', 'The berry collection', 'Indulgence, poured by hand.', 'Chocolate glaze, ruby drips and every berry placed by hand. Small-batch candles made to be gifted.', 'Shop the collection', '/shop', 'Three cake-shaped candles with chocolate glaze and wax berries on vintage book pages'],
            ['whipped-jar.jpg', '66% 50%', 'Whipped jar candles', 'Sweet scents, softly whipped.', 'Piped wax, pastel swirls and a macaron on top. A little dessert for your shelf that never melts away too soon.', 'Shop jar candles', '/search?q=jar', 'Whipped pastel wax candle in a glass jar topped with a wax macaron'],
            ['jesmonite-tray.jpg', '50% 45%', 'Cast in Jesmonite', 'Quiet objects, made to keep.', 'Trays, vessels and sculpted pieces cast by hand, so every swirl and speck is one of a kind.', 'Shop Jesmonite', '/search?q=jesmonite', 'Sculpted candles resting on an oval Jesmonite tray'],
        ];

        foreach ($slides as $i => [$file, $focal, $eyebrow, $title, $body, $cta, $url, $alt]) {
            $path = "hero/{$file}";
            Storage::disk('public')->put($path, (string) file_get_contents(__DIR__."/assets/hero/{$file}"));
            $slide = new HeroSlide([
                'image_alt' => $alt, 'focal_point' => $focal, 'eyebrow' => $eyebrow, 'title' => $title, 'body' => $body,
                'cta_label' => $cta, 'cta_url' => $url, 'sort_order' => $i + 1, 'is_active' => true,
            ]);
            $slide->image_path = $path;
            $slide->save();
        }

        $settings->set(self::DONE, true);
    }
}
