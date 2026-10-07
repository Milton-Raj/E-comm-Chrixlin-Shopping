<?php

use App\Models\Coupon;
use App\Models\User;
use Database\Seeders\StoreSetupSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

beforeEach(function () {
    Notification::fake();
    (new StoreSetupSeeder)->run();
    $this->admin = User::factory()->staff('administrator')->create();
});

function workbook(TestResponse $response): Spreadsheet
{
    $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $response->streamedContent());
    $book = IOFactory::load($path);
    unlink($path);

    return $book;
}

it('exports every admin area as a formatted Excel workbook', function (string $type, array $sheets) {
    paidOrder($this, makeProduct(['name' => 'Export Scarf'], stock: 4, price: 250_000));
    paidOrder($this, makeProduct(['name' => 'Export Ebook'], type: 'digital'));
    Coupon::query()->create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 1000, 'is_active' => true]);
    $this->actingAs($this->admin);

    $book = workbook($this->get("/api/v1/admin/exports/{$type}"));

    expect($book->getSheetNames())->toBe($sheets);
    $sheet = $book->getSheet(0);
    expect($sheet->getCell('A1')->getValue())->toContain(config('commerce.store.name'))
        ->and($sheet->getFreezePane())->toBe('A5')
        ->and($sheet->getAutoFilter()->getRange())->toStartWith('A4:');
})->with([
    'products' => ['products', ['Products']],
    'inventory' => ['inventory', ['Inventory']],
    'orders' => ['orders', ['Orders', 'Order items']],
    'customers' => ['customers', ['Customers']],
    'digital' => ['digital', ['Digital access', 'Download log']],
    'coupons' => ['coupons', ['Coupons']],
]);

it('writes real numbers with currency formats and totals', function () {
    $order = paidOrder($this, makeProduct(price: 250_000), 2);
    $this->actingAs($this->admin);

    $sheet = workbook($this->get('/api/v1/admin/exports/orders'))->getSheetByName('Orders');
    $headers = collect($sheet->rangeToArray('A4:Z4')[0])->filter()->values();
    $col = fn (string $h) => chr(ord('A') + $headers->search($h));

    expect($sheet->getCell('A5')->getValue())->toBe($order->order_number)
        ->and($sheet->getCell($col('Order total').'5')->getValue())->toEqual(5000.0)
        ->and($sheet->getStyle($col('Order total').'5')->getNumberFormat()->getFormatCode())->toContain('₹')
        ->and($sheet->getCell('A6')->getValue())->toBe('Total')
        ->and($sheet->getCell($col('Order total').'6')->getValue())->toBe('=SUBTOTAL(9,'.$col('Order total').'5:'.$col('Order total').'5)');
});

it('builds the full report with a summary and every area the user can see', function () {
    paidOrder($this, makeProduct(price: 120_000));
    $this->actingAs($this->admin);

    $book = workbook($this->get('/api/v1/admin/exports/report?from='.now()->subDays(6)->toDateString().'&to='.now()->toDateString()));

    expect($book->getSheetNames())->toBe(['Summary', 'Daily sales', 'Sales by product', 'Sales by category', 'Products', 'Inventory', 'Orders', 'Order items', 'Customers', 'Digital access', 'Download log', 'Coupons']);
    $summary = $book->getSheetByName('Summary');
    $rows = collect($summary->rangeToArray('A5:B18', null, false, false))->mapWithKeys(fn ($r) => [$r[0] => $r[1]]);
    expect($rows['Paid orders'])->toBe(1)->and($rows['Gross sales (incl. GST & delivery)'])->toEqual(1450.0); // ₹1,200 + ₹250 delivery
    $this->assertDatabaseHas('audit_logs', ['action' => 'export.downloaded']);
});

it('limits the report to the areas a staff member may view, and refuses staff without export rights', function () {
    $finance = User::factory()->staff('finance-manager')->create();
    $this->actingAs($finance);
    $names = workbook($this->get('/api/v1/admin/exports/report'))->getSheetNames();
    expect($names)->toContain('Orders')->not->toContain('Customers')->not->toContain('Products');
    expect($this->get('/api/v1/admin/exports/customers')->status())->toBe(403);

    $support = User::factory()->staff('customer-support')->create();
    expect($this->actingAs($support)->get('/api/v1/admin/exports/orders')->status())->toBe(403);
    expect($this->actingAs($this->admin)->get('/api/v1/admin/exports/secrets')->status())->toBe(404);
});

it('stores text as text so spreadsheet formulas cannot be injected', function () {
    makeProduct(['name' => '=HYPERLINK("http://evil.test","click")']);
    $this->actingAs($this->admin);

    $cell = workbook($this->get('/api/v1/admin/exports/products'))->getSheet(0)->getCell('A5');
    expect($cell->getDataType())->toBe('s')->and($cell->getValue())->toStartWith('=HYPERLINK');
});
