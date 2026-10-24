<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Modules\Accounting\ApController;
use Phai\Modules\Accounting\ApVoucherController;
use Phai\Modules\Accounting\ArInvoiceController;
use Phai\Modules\Accounting\BankRecController;
use Phai\Modules\Accounting\CashReceiptController;
use Phai\Modules\Accounting\EtaxController;
use Phai\Modules\Accounting\FixedAssetController;
use Phai\Modules\Accounting\PaymentController;
use Phai\Modules\Accounting\TrialBalanceController;
use Phai\Modules\Inventory\StockCountController;
use Phai\Modules\Production\WoController;
use Phai\Modules\Purchase\LandedCostController;
use Phai\Modules\Purchase\PoController;
use Phai\Modules\Purchase\PrController;
use Phai\Modules\Purchase\ReceiptController;
use Phai\Modules\Sales\BillingController;
use Phai\Modules\Sales\InvoiceController;
use Phai\Modules\Sales\ShipmentController;
use Phai\Modules\Sales\SoController;

/**
 * The story of a module on its dashboard: one card per "ทำรายการ" menu item of the dashboard's group, in menu order —
 * what the screen is (lang/th/flow.php) and the work waiting for it (the same queues the screen shows above its list).
 */
final class Flow
{
    /** Page path → the controller whose queues() are that page's work queues. */
    private const QUEUES = [
        '/purchase-requisitions' => PrController::class,
        '/purchase-orders'       => PoController::class,
        '/receipts'              => ReceiptController::class,
        '/landed-costs'          => LandedCostController::class,
        '/stock-counts'          => StockCountController::class,
        '/work-orders'           => WoController::class,
        '/sales-orders'          => SoController::class,
        '/shipments'             => ShipmentController::class,
        '/invoices'              => InvoiceController::class,
        '/billings'              => BillingController::class,
        '/payables'              => ApController::class,
        '/ap-vouchers'           => ApVoucherController::class,
        '/vouchers'              => PaymentController::class,
        '/ar-invoices'           => ArInvoiceController::class,
        '/etax'                  => EtaxController::class,
        '/cash-receipts'         => CashReceiptController::class,
        '/bank-rec'              => BankRecController::class,
        '/trial-balance'         => TrialBalanceController::class,
        '/fixed-assets'          => FixedAssetController::class,
    ];

    /**
     * Sections of the group whose menu holds $dashboard (e.g. '/purchase'), only items the user may view.
     * @return list<array{title: string, steps: list<array{name: string, icon: string, link: string, desc: string, queues: array}>}>
     */
    public static function of(string $dashboard): array
    {
        foreach (Menu::tree() as $group) {
            if (!in_array($dashboard, array_column($group['items'], 'mnu_link'), true)) {
                continue;
            }
            $sections = [];
            $doing = false; // past the "ตั้งค่า" (x.10) heading, inside a "ทำรายการ" one
            foreach ($group['items'] as $item) {
                if (Menu::isLabel($item)) {
                    $doing = !Menu::isFold($item);
                    if ($doing) {
                        $sections[] = ['title' => $item['mnu_name'], 'steps' => []];
                    }
                    continue;
                }
                if (!$doing || !$sections) {
                    continue;
                }
                $link = $item['mnu_link'];
                $class = self::QUEUES[strtok($link, '?')] ?? null;
                $sections[array_key_last($sections)]['steps'][] = [
                    'name'   => $item['mnu_name'],
                    'icon'   => $item['mnu_icon'] ?: 'ti ti-file',
                    'link'   => $link,
                    'desc'   => t('flow.' . $link),
                    'queues' => $class ? $class::queues() : [],
                ];
            }
            return array_values(array_filter($sections, fn ($s) => $s['steps']));
        }
        return [];
    }
}
