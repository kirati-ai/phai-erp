<?php
use Phai\Modules\Accounting\AssetDepreciation;
function assetFixture(string $method = 'SL'): array
{
    return ['fa_start' => '2026-01-31', 'fa_last_month' => null, 'fa_cost' => 12000, 'fa_salvage' => 1200, 'fa_accum' => 0, 'fa_life_months' => 12, 'fa_method' => $method, 'fa_rate' => 40];
}
test('Asset straight line reaches salvage with full-month convention', function () {
    $a = assetFixture();
    check(AssetDepreciation::amount($a, '2026-01') === 900.0);
    $s = AssetDepreciation::schedule($a);
    check(count($s) === 12);
    check(end($s)['book_value'] === 1200.0);
    check(array_sum(array_column($s, 'depreciation')) === 10800.0);
});
test('Asset declining balance decreases and settles at end of life', function () {
    $a = assetFixture('DB');
    $s = AssetDepreciation::schedule($a);
    check($s[0]['depreciation'] === 400.0);
    check($s[1]['depreciation'] === 386.67);
    check(count($s) === 12 && end($s)['book_value'] === 1200.0);
});
test('Asset opening balances, residual cap and year rollover', function () {
    $a = assetFixture(); $a['fa_accum'] = 10799.95; $a['fa_last_month'] = '2026-12';
    check(AssetDepreciation::next($a) === '2027-01');
    check(AssetDepreciation::amount($a, '2027-01') === .05);
    $a['fa_accum'] = 10800;
    check(AssetDepreciation::schedule($a) === []);
    check(AssetDepreciation::schedule(assetFixture('NONE')) === []);
    check(AssetDepreciation::amount(assetFixture(), '2025-12') === 0.0);
});
test('Asset depreciation rejects malformed periods', function () {
    foreach (['2026-13', '2026-1', 'hello', '2026-00'] as $m) {
        try { AssetDepreciation::month($m); throw new RuntimeException('Invalid month accepted'); }
        catch (InvalidArgumentException) {}
    }
});
