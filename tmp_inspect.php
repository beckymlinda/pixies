<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$stockEntryId = 14;
$s = App\Models\DailyStockEntry::find($stockEntryId);
if (!$s) { echo "stockEntry not found\n"; exit; }

echo "stock_entry_id={$s->id} bar_id={$s->bar_id} date={$s->date}\n";

$totalSales = $s->stockEntryItems()->sum('sales_amount');
$stockPayments = $s->payments()->sum('amount');
$dr = App\Models\DailyReport::where('bar_id', $s->bar_id)->where('date', $s->date)->first();
$drPayments = $dr ? $dr->payments()->sum('amount') : 0;

$creditSales = App\Models\CustomerTab::where('date', $s->date)
    ->where('bar_id', $s->bar_id)
    ->where('status', '!=', 'paid')
    ->sum('balance');

$directExpenses = App\Models\Expense::where('stock_entry_id', $s->id)->get();
$fallbackExpenses = App\Models\Expense::where('date', $s->date)->where(function($q) use ($s) {
    $q->where('stock_entry_id', $s->id)->orWhere('user_id', $s->user_id);
})->get();

echo "totalSales={$totalSales}\n";
echo "stockPayments={$stockPayments} drPayments={$drPayments}\n";
echo "creditSales={$creditSales}\n";
echo "directExpenses:\n";
foreach ($directExpenses as $e) echo " id={$e->id} amount={$e->amount} desc={$e->description}\n";
echo "fallbackExpenses:\n";
foreach ($fallbackExpenses as $e) echo " id={$e->id} amount={$e->amount} desc={$e->description} user_id={$e->user_id} stock_entry_id={$e->stock_entry_id}\n";
echo "sumFallback=" . $fallbackExpenses->sum('amount') . "\n";

$recon = App\Models\CashReconciliation::where('stock_entry_id', $s->id)->first();
if ($recon) echo "recon: id={$recon->id} expected_cash={$recon->expected_cash} cash_counted={$recon->cash_counted} electronic_counted={$recon->electronic_counted} difference={$recon->difference}\n";
