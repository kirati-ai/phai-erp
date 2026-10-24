<?php
use Phai\Modules\Production\OrderSizing;
test('MRP respects minimum, multiple and maximum lots', function () {
    $p = ['pt_min_ord' => 10, 'pt_max_ord' => 25, 'pt_mult_ord' => 5];
    check(OrderSizing::lots(3, $p) === [10.0]);
    check(OrderSizing::lots(26, $p) === [15.0, 15.0]);
    check(OrderSizing::lots(51, $p) === [20.0, 20.0, 15.0]);
    check(OrderSizing::lots(0, $p) === []);
});
