<?php

namespace Database\Seeders;

use App\Domain\Cart\CartService;
use App\Domain\Checkout\PlaceOrder;
use App\Domain\Orders\Actions\CancelOrder;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\OrderStateMachine;
use App\Domain\Payments\Actions\ProcessGatewayEvent;
use App\Domain\Payments\Gateways\TestGateway;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo orders created through the real checkout + payment code paths (development only),
 * so the admin dashboard, reports and order screens have realistic data.
 */
class DemoOrdersSeeder extends Seeder
{
    public function run(CartService $carts, PlaceOrder $placeOrder, ProcessGatewayEvent $process, TestGateway $gateway, OrderStateMachine $states): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoOrdersSeeder must not run in production.');
        }

        $customers = User::query()->role('customer')->orderBy('id')->get();
        $products = Product::query()->with('variants.inventory')->where('status', 'active')->orderBy('id')->get();
        $states_plan = ['delivered', 'delivered', 'shipped', 'processing', 'delivered', 'processing', 'pending', 'delivered', 'shipped', 'delivered', 'cancelled', 'delivered'];

        foreach ($states_plan as $i => $target) {
            $customer = $customers[$i % max(1, $customers->count())] ?? null;
            if (! $customer) {
                return;
            }

            $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '127.0.0.1']);
            $request->setUserResolver(fn () => $customer);

            $cart = Cart::create(['token' => (string) Str::uuid(), 'user_id' => $customer->id, 'status' => 'active', 'currency' => 'INR', 'email' => $customer->email]);
            foreach ($products->slice(($i * 3) % $products->count(), 2) as $product) {
                $variant = $product->variants->first();
                if ($variant) {
                    $carts->addItem($cart, $variant->uuid, 1);
                }
            }
            $cart->forceFill([
                'shipping_address' => ['name' => $customer->name, 'phone' => '9876543210', 'line1' => (10 + $i).' Marina Road', 'line2' => null, 'city' => $i % 2 ? 'Bengaluru' : 'Chennai', 'state_code' => $i % 2 ? 'KA' : 'TN', 'postal_code' => $i % 2 ? '560001' : '600001', 'country_code' => 'IN'],
                'shipping_method_code' => 'standard',
            ])->save();

            $result = $placeOrder->handle($cart->fresh() ?? $cart, 'test', null, $request);
            /** @var Order $order */
            $order = $result['order'];
            $daysAgo = 26 - $i * 2;
            $order->forceFill(['placed_at' => now()->subDays($daysAgo), 'created_at' => now()->subDays($daysAgo)])->save();

            if ($target === 'pending') {
                continue;
            }
            if ($target === 'cancelled') {
                app(CancelOrder::class)->handle($order, 'system', null, 'Demo: payment not completed');

                continue;
            }

            $event = $gateway->confirm($result['payment'], ['outcome' => 'success']);
            $process->handle('test', $event);
            $order->refresh()->forceFill(['paid_at' => now()->subDays($daysAgo)])->save();

            if ($order->requiresShipping() && in_array($target, ['shipped', 'delivered'], true)) {
                $order->shipments()->create(['carrier' => 'Blue Dart', 'tracking_number' => 'BD'.random_int(100000000, 999999999), 'shipped_at' => now()->subDays(max(0, $daysAgo - 1))]);
                $states->transition($order, OrderStatus::Shipped, 'staff', null, 'Demo shipment');
                if ($target === 'delivered') {
                    $states->advance($order, OrderStatus::Delivered, 'staff', null, 'Demo delivery'); // also marks it fulfilled
                }
            }
        }
    }
}
