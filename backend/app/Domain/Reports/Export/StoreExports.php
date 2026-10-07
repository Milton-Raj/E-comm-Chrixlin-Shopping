<?php

namespace App\Domain\Reports\Export;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Reports\SalesReport;
use App\Models\Coupon;
use App\Models\DigitalEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use Generator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The sheets behind every admin export. Each method adds its sheet(s) to a workbook;
 * the full report combines them. Reads only — no business rules live here.
 */
class StoreExports
{
    /** area => [permission, sheet description] — also drives the report's contents list */
    public const AREAS = [
        'products' => ['products.view', 'Every product variant with price, cost, margin and stock'],
        'inventory' => ['inventory.view', 'Stock on hand, reserved and available per SKU, with stock value'],
        'orders' => ['orders.view', 'Every order with totals, payment, delivery and an itemised sheet'],
        'customers' => ['customers.view', 'Customer accounts with order count, spend and last order'],
        'digital' => ['downloads.view', 'Digital access per customer and the download log'],
        'coupons' => ['coupons.manage', 'Coupon rules, usage and the discount they have given'],
    ];

    public function __construct(private readonly SalesReport $sales) {}

    public function products(WorkbookBuilder $book): void
    {
        $rows = function (): Generator {
            $variants = ProductVariant::query()->with(['product.brand', 'product.category', 'inventory'])
                ->whereHas('product')->orderBy('product_id')->orderBy('sort_order');
            foreach ($variants->lazyById(500) as $v) {
                $p = $v->product;
                $available = $v->track_inventory && $v->inventory ? max(0, $v->inventory->on_hand - $v->inventory->reserved) : null;
                yield [
                    'product' => $p->name, 'variant' => $v->name ?: 'Default', 'sku' => $v->sku,
                    'type' => Str::headline($p->product_type->value), 'status' => Str::headline($p->status->value),
                    'category' => $p->category?->name, 'brand' => $p->brand?->name,
                    'price' => $v->price, 'compare' => $v->compare_at_price, 'cost' => $v->cost_price,
                    'margin' => $v->cost_price !== null && $v->price > 0 ? ($v->price - $v->cost_price) / $v->price : null,
                    'available' => $available, 'units_sold' => $p->units_sold, 'active' => $v->is_active,
                    'featured' => $p->is_featured, 'published' => $p->published_at, 'slug' => $p->slug,
                ];
            }
        };

        $book->table('Products', 'Products', [
            Column::text('Product', 'product', 34), Column::text('Variant', 'variant', 18), Column::code('SKU', 'sku'),
            Column::text('Type', 'type', 11), Column::text('Status', 'status', 11)->withTone(fn ($r) => $r['status'] === 'Active' ? 'good' : ($r['status'] === 'Archived' ? 'bad' : 'warn')),
            Column::text('Category', 'category', 18), Column::text('Brand', 'brand', 16),
            Column::money('Price', 'price', false), Column::money('Compare-at price', 'compare', false), Column::money('Cost price', 'cost', false),
            Column::percent('Margin', 'margin', 10),
            Column::int('Available stock', 'available', true, 12, fn ($r) => $r['available'] === null ? null : ($r['available'] <= 0 ? 'bad' : null)),
            Column::int('Units sold', 'units_sold', true), Column::bool('Variant active', 'active', 10), Column::bool('Featured', 'featured', 10),
            Column::date('Published', 'published'), Column::text('URL slug', 'slug', 28),
        ], $rows(), 'Prices include GST; blank stock = digital or untracked');
    }

    public function inventory(WorkbookBuilder $book): void
    {
        $rows = DB::table('product_variants')->where('product_variants.track_inventory', true)->whereNull('product_variants.deleted_at')
            ->join('inventory', 'inventory.variant_id', '=', 'product_variants.id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')->whereNull('products.deleted_at')
            ->orderByRaw('inventory.on_hand - inventory.reserved asc')->orderBy('products.name')
            ->select(['products.name as product', 'product_variants.name as variant', 'product_variants.sku', 'product_variants.cost_price', 'product_variants.price',
                'inventory.on_hand', 'inventory.reserved', 'inventory.low_stock_threshold', 'inventory.updated_at'])
            ->get()
            ->map(function ($r) {
                $available = max(0, (int) $r->on_hand - (int) $r->reserved);

                return [
                    'product' => $r->product, 'variant' => $r->variant ?: 'Default', 'sku' => $r->sku,
                    'on_hand' => (int) $r->on_hand, 'reserved' => (int) $r->reserved, 'available' => $available,
                    'threshold' => (int) $r->low_stock_threshold,
                    'state' => $available <= 0 ? 'Sold out' : ($available <= (int) $r->low_stock_threshold ? 'Low stock' : 'In stock'),
                    'cost_value' => $r->cost_price !== null ? (int) $r->cost_price * (int) $r->on_hand : null,
                    'retail_value' => (int) $r->price * (int) $r->on_hand, 'updated' => $r->updated_at,
                ];
            });

        $tone = fn ($r) => match ($r['state']) {
            'Sold out' => 'bad', 'Low stock' => 'warn', default => 'good'
        };
        $book->table('Inventory', 'Inventory', [
            Column::text('Product', 'product', 34), Column::text('Variant', 'variant', 18), Column::code('SKU', 'sku'),
            Column::int('On hand', 'on_hand', true), Column::int('Reserved', 'reserved', true), Column::int('Available', 'available', true, 12, fn ($r) => $tone($r) === 'good' ? null : $tone($r)),
            Column::int('Low-stock level', 'threshold', false, 13), Column::text('Stock state', 'state', 13)->withTone($tone),
            Column::money('Stock value (cost)', 'cost_value', true, 18), Column::money('Stock value (retail)', 'retail_value', true, 18),
            Column::datetime('Last movement', 'updated'),
        ], $rows, 'Sorted with the lowest available stock first');
    }

    public function orders(WorkbookBuilder $book, ?Carbon $from = null, ?Carbon $to = null): void
    {
        $query = fn () => Order::query()->whereNotNull('placed_at')
            ->when($from, fn ($q) => $q->where('placed_at', '>=', $from))->when($to, fn ($q) => $q->where('placed_at', '<=', $to));
        $note = $from && $to ? 'Placed '.$from->format('j M Y').' – '.$to->format('j M Y') : 'All orders';

        $orders = function () use ($query): Generator {
            foreach ($query()->with(['items', 'addresses', 'latestPayment', 'shipments', 'user'])->orderBy('placed_at')->lazyById(300) as $o) {
                $ship = $o->shippingAddress();
                $shipment = $o->shipments->sortByDesc('id')->first();
                yield [
                    'number' => $o->order_number, 'placed' => $o->placed_at, 'paid' => $o->paid_at,
                    'customer' => $o->user->name ?? $ship->name ?? 'Guest', 'email' => $o->email, 'phone' => $o->phone ?: $ship?->phone,
                    'account' => $o->user_id ? 'Registered' : 'Guest',
                    'status' => $o->status->label(), 'payment' => Str::headline($o->payment_status->value), 'fulfilment' => Str::headline($o->fulfillment_status->value),
                    'items' => (int) $o->items->sum('quantity'), 'subtotal' => $o->subtotal, 'discount' => $o->discount_total, 'coupon' => $o->coupon_code,
                    'shipping' => $o->shipping_total, 'tax' => $o->tax_total, 'total' => $o->grand_total, 'refunded' => $o->refunded_total,
                    'net' => $o->grand_total - $o->refunded_total,
                    'method' => $o->latestPayment ? trim(Str::headline($o->latestPayment->provider).($o->latestPayment->method ? ' · '.Str::upper($o->latestPayment->method) : '')) : null,
                    'city' => $ship?->city, 'state' => $ship?->state_code, 'pin' => $ship?->postal_code,
                    'courier' => $shipment?->carrier, 'awb' => $shipment?->tracking_number, 'attention' => $o->requires_attention,
                ];
            }
        };
        $statusTone = fn ($r) => match (true) {
            in_array($r['status'], ['Delivered'], true) => 'good',
            in_array($r['status'], ['Cancelled', 'Failed', 'Refunded'], true) => 'bad',
            in_array($r['status'], ['Pending', 'Payment Processing', 'Refund Requested'], true) => 'warn',
            default => null,
        };

        $book->table('Orders', 'Orders', [
            Column::code('Order', 'number', 18), Column::datetime('Placed', 'placed'), Column::datetime('Paid', 'paid'),
            Column::text('Customer', 'customer', 22), Column::text('Email', 'email', 28), Column::code('Phone', 'phone', 14), Column::text('Account', 'account', 11),
            Column::text('Order status', 'status', 16)->withTone($statusTone), Column::text('Payment', 'payment', 14), Column::text('Fulfilment', 'fulfilment', 13),
            Column::int('Items', 'items', true, 8), Column::money('Subtotal', 'subtotal'), Column::money('Discount', 'discount'), Column::code('Coupon', 'coupon', 12),
            Column::money('Delivery', 'shipping'), Column::money('GST (incl.)', 'tax'), Column::money('Order total', 'total'), Column::money('Refunded', 'refunded'),
            Column::money('Net', 'net'), Column::text('Paid with', 'method', 16), Column::text('City', 'city', 14), Column::text('State', 'state', 7),
            Column::code('PIN', 'pin', 9), Column::text('Courier', 'courier', 14), Column::code('AWB / tracking', 'awb', 18),
            Column::bool('Needs attention', 'attention', 11)->withTone(fn ($r) => $r['attention'] ? 'bad' : null),
        ], $orders(), $note.' · amounts include GST');

        $items = function () use ($query): Generator {
            $ids = $query()->select('id');
            foreach (OrderItem::query()->whereIn('order_id', $ids)->with('order')->orderBy('order_id')->lazyById(500) as $i) {
                yield [
                    'number' => $i->order->order_number, 'placed' => $i->order->placed_at, 'status' => $i->order->status->label(),
                    'product' => $i->name, 'variant' => $i->variant_name, 'sku' => $i->sku, 'type' => Str::headline($i->product_type),
                    'qty' => $i->quantity, 'unit' => $i->unit_price, 'discount' => $i->discount_total, 'tax' => $i->tax_total,
                    'line' => $i->line_total, 'refunded_qty' => $i->refunded_quantity,
                ];
            }
        };
        $book->table('Order items', 'Order items', [
            Column::code('Order', 'number', 18), Column::datetime('Placed', 'placed'), Column::text('Order status', 'status', 15),
            Column::text('Product', 'product', 32), Column::text('Variant', 'variant', 16), Column::code('SKU', 'sku'), Column::text('Type', 'type', 10),
            Column::int('Qty', 'qty', true, 7), Column::money('Unit price', 'unit', false), Column::money('Discount', 'discount'), Column::money('GST (incl.)', 'tax'),
            Column::money('Line total', 'line'), Column::int('Refunded qty', 'refunded_qty', true, 11),
        ], $items(), $note);
    }

    public function customers(WorkbookBuilder $book): void
    {
        $paid = [PaymentStatus::Captured->value, PaymentStatus::PartiallyRefunded->value];
        $rows = function () use ($paid): Generator {
            $users = User::query()->role('customer')
                ->withCount(['orders' => fn ($o) => $o->whereIn('payment_status', $paid)])
                ->withSum(['orders as total_spent' => fn ($o) => $o->whereIn('payment_status', $paid)], 'grand_total')
                ->withMax(['orders as last_order_at' => fn ($o) => $o->whereIn('payment_status', $paid)], 'placed_at')
                ->orderBy('id');
            foreach ($users->lazyById(500) as $u) {
                $count = (int) $u->orders_count;
                $spent = (int) $u->total_spent;
                yield [
                    'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'active' => $u->is_active,
                    'verified' => $u->email_verified_at !== null, 'marketing' => $u->marketing_opt_in,
                    'joined' => $u->created_at, 'last_login' => $u->last_login_at, 'orders' => $count, 'spent' => $spent,
                    'aov' => $count ? intdiv($spent, $count) : null, 'last_order' => $u->getAttribute('last_order_at'),
                ];
            }
        };
        $book->table('Customers', 'Customers', [
            Column::text('Name', 'name', 24), Column::text('Email', 'email', 30), Column::code('Phone', 'phone', 14),
            Column::bool('Active', 'active', 9)->withTone(fn ($r) => $r['active'] ? null : 'bad'), Column::bool('Email verified', 'verified', 10),
            Column::bool('Marketing opt-in', 'marketing', 11), Column::date('Joined', 'joined'), Column::datetime('Last sign-in', 'last_login'),
            Column::int('Paid orders', 'orders', true, 10), Column::money('Total spent', 'spent'), Column::money('Average order', 'aov', false),
            Column::date('Last order', 'last_order'),
        ], $rows(), 'Customer accounts only (staff excluded) · contains personal data, handle with care');
    }

    public function digital(WorkbookBuilder $book): void
    {
        $rows = function (): Generator {
            foreach (DigitalEntitlement::query()->with(['product', 'order'])->orderBy('id')->lazyById(500) as $e) {
                yield [
                    'email' => $e->email, 'product' => $e->product?->name, 'order' => $e->order->order_number, 'granted' => $e->created_at,
                    'status' => Str::headline($e->status), 'used' => $e->downloads_used, 'limit' => $e->download_limit,
                    'remaining' => $e->download_limit === null ? null : max(0, $e->download_limit - $e->downloads_used),
                    'expires' => $e->expires_at, 'revoked' => $e->revoked_at,
                ];
            }
        };
        $book->table('Digital access', 'Digital product access', [
            Column::text('Customer email', 'email', 30), Column::text('Product', 'product', 32), Column::code('Order', 'order', 18), Column::datetime('Granted', 'granted'),
            Column::text('Status', 'status', 13)->withTone(fn ($r) => in_array($r['status'], ['Revoked', 'Refunded', 'Expired'], true) ? 'bad' : ($r['status'] === 'Available' ? 'good' : null)),
            Column::int('Downloads used', 'used', true, 12), Column::int('Download limit', 'limit', false, 12), Column::int('Remaining', 'remaining', false, 11),
            Column::datetime('Access expires', 'expires'), Column::datetime('Revoked', 'revoked'),
        ], $rows(), 'Blank limit = unlimited · blank expiry = lifetime access');

        $log = DB::table('digital_downloads')->join('digital_entitlements', 'digital_entitlements.id', '=', 'digital_downloads.entitlement_id')
            ->join('digital_files', 'digital_files.id', '=', 'digital_downloads.digital_file_id')
            ->leftJoin('products', 'products.id', '=', 'digital_entitlements.product_id')
            ->select(['digital_downloads.created_at', 'digital_entitlements.email', 'products.name as product', 'digital_files.original_name', 'digital_downloads.status', 'digital_downloads.deny_reason', 'digital_downloads.ip_address'])
            ->orderByDesc('digital_downloads.id')->limit(50_000)->get()
            ->map(fn ($r) => [
                'at' => $r->created_at, 'email' => $r->email, 'product' => $r->product, 'file' => $r->original_name,
                'status' => Str::headline((string) $r->status), 'reason' => $r->deny_reason ? Str::headline((string) $r->deny_reason) : null, 'ip' => $r->ip_address,
            ]);
        $book->table('Download log', 'Download log', [
            Column::datetime('When', 'at'), Column::text('Customer email', 'email', 30), Column::text('Product', 'product', 28), Column::text('File', 'file', 28),
            Column::text('Result', 'status', 12)->withTone(fn ($r) => $r['status'] === 'Denied' ? 'bad' : null), Column::text('Reason', 'reason', 18), Column::code('IP address', 'ip', 16),
        ], $log, 'Newest first');
    }

    public function coupons(WorkbookBuilder $book): void
    {
        $use = DB::table('orders')->whereNotNull('coupon_code')->whereIn('payment_status', [PaymentStatus::Captured->value, PaymentStatus::PartiallyRefunded->value, PaymentStatus::Refunded->value])
            ->selectRaw('coupon_code, COUNT(*) as orders, SUM(discount_total) as discount, SUM(grand_total) as sales')->groupBy('coupon_code')->get()->keyBy('coupon_code');

        $rows = Coupon::query()->orderBy('code')->get()->map(function (Coupon $c) use ($use) {
            $state = match (true) {
                ! $c->is_active => 'Inactive',
                $c->starts_at && $c->starts_at->isFuture() => 'Scheduled',
                $c->ends_at && $c->ends_at->isPast() => 'Expired',
                $c->usage_limit !== null && $c->usage_count >= $c->usage_limit => 'Used up',
                default => 'Live',
            };
            $u = $use->get($c->code);

            return [
                'code' => $c->code, 'description' => $c->description, 'type' => Str::headline($c->type), 'state' => $state,
                'percent' => $c->type === 'percentage' ? $c->value / 10_000 : null, 'amount' => $c->type === 'fixed' ? $c->value : null,
                'max' => $c->max_discount, 'min' => $c->min_order_total, 'first' => $c->first_order_only,
                'limit' => $c->usage_limit, 'used' => $c->usage_count, 'remaining' => $c->usage_limit === null ? null : max(0, $c->usage_limit - $c->usage_count),
                'per_customer' => $c->per_customer_limit, 'starts' => $c->starts_at, 'ends' => $c->ends_at,
                'orders' => $u ? (int) $u->orders : 0, 'discount' => $u ? (int) $u->discount : 0, 'sales' => $u ? (int) $u->sales : 0,
            ];
        });

        $book->table('Coupons', 'Coupons', [
            Column::code('Code', 'code', 16), Column::text('Description', 'description', 30), Column::text('Type', 'type', 13),
            Column::text('State', 'state', 11)->withTone(fn ($r) => match ($r['state']) {
                'Live' => 'good', 'Scheduled' => 'warn', default => 'bad'
            }),
            Column::percent('Percent off', 'percent', 11), Column::money('Amount off', 'amount', false), Column::money('Max discount', 'max', false),
            Column::money('Min order', 'min', false), Column::bool('First order only', 'first', 11), Column::int('Usage limit', 'limit', false, 11),
            Column::int('Times used', 'used', true, 10), Column::int('Uses left', 'remaining', false, 10), Column::int('Per customer', 'per_customer', false, 11),
            Column::datetime('Starts', 'starts'), Column::datetime('Ends', 'ends'),
            Column::int('Paid orders', 'orders', true, 10), Column::money('Discount given', 'discount'), Column::money('Sales with coupon', 'sales'),
        ], $rows, 'Usage figures count paid orders only');
    }

    /** The full report: period summary and sales breakdowns, then every area the user may see. */
    public function report(WorkbookBuilder $book, Carbon $from, Carbon $to, User $user): void
    {
        $r = $this->sales->build($from, $to, null);
        $s = $r['summary'];
        $period = $from->format('j M Y').' – '.$to->format('j M Y');
        $newCustomers = User::query()->role('customer')->whereBetween('created_at', [$from, $to])->count();
        $placed = Order::query()->whereNotNull('placed_at')->whereBetween('placed_at', [$from, $to])->count();

        $areas = array_filter(self::AREAS, fn ($area) => $user->can($area[0]));
        $contents = [
            ['Daily sales', 'Paid orders and net revenue per day'],
            ['Sales by product', 'Units and revenue for every product sold in the period'],
            ['Sales by category', 'Units and revenue per category'],
        ];
        foreach ($areas as $key => [, $description]) {
            $contents[] = [match ($key) {
                'digital' => 'Digital access', default => Str::headline($key)
            }, $description];
            if ($key === 'orders') {
                $contents[] = ['Order items', 'Every line of every order in the period'];
            }
            if ($key === 'digital') {
                $contents[] = ['Download log', 'Each download attempt, allowed or denied'];
            }
        }

        $book->summary('Summary', "Store report · {$period}", [
            ['Report period start', $from, Column::DATE], ['Report period end', $to, Column::DATE],
            ['Orders placed', $placed, Column::INT], ['Paid orders', $s['orders'], Column::INT],
            ['Checkout conversion (paid ÷ placed)', $placed ? $s['orders'] / $placed : 0, Column::PERCENT],
            ['Units sold', $s['units'], Column::INT],
            ['Gross sales (incl. GST & delivery)', $s['gross'], Column::MONEY], ['Discounts given', $s['discounts'], Column::MONEY],
            ['Delivery charges collected', $s['shipping'], Column::MONEY], ['GST collected (included above)', $s['tax'], Column::MONEY],
            ['Refunds', $s['refunds'], Column::MONEY], ['Net sales (gross − refunds)', $s['net'], Column::MONEY],
            ['Average order value', $s['aov'], Column::MONEY], ['New customer accounts', $newCustomers, Column::INT],
        ], $contents, 'Sales figures use paid orders by payment date');

        $book->table('Daily sales', "Daily sales · {$period}", [
            Column::date('Date', 'date'), Column::int('Paid orders', 'orders', true), Column::money('Net revenue', 'revenue'),
        ], $r['daily'], 'Net of refunds');
        $share = fn (int $v, int $total) => $total > 0 ? $v / $total : 0;
        $productTotal = (int) $r['by_product']->sum('revenue');
        $book->table('Sales by product', "Sales by product · {$period}", [
            Column::text('Product', 'name', 36), Column::text('Type', 'type', 11), Column::int('Units', 'units', true), Column::money('Revenue', 'revenue'), Column::percent('Share of revenue', 'share', 14),
        ], $r['by_product']->map(fn ($p) => [...$p, 'type' => Str::headline($p['type']), 'share' => $share($p['revenue'], $productTotal)]));
        $categoryTotal = (int) $r['by_category']->sum('revenue');
        $book->table('Sales by category', "Sales by category · {$period}", [
            Column::text('Category', 'category', 28), Column::int('Units', 'units', true), Column::money('Revenue', 'revenue'), Column::percent('Share of revenue', 'share', 14),
        ], $r['by_category']->map(fn ($c) => [...$c, 'share' => $share($c['revenue'], $categoryTotal)]));

        foreach (array_keys($areas) as $key) {
            match ($key) {
                'orders' => $this->orders($book, $from, $to),
                default => $this->{$key}($book),
            };
        }
    }
}
