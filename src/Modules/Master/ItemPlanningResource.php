<?php
declare(strict_types=1);
namespace Phai\Modules\Master;

use Phai\Core\Crud\Resource;
use Phai\Core\Http\HttpException;

final class ItemPlanningResource extends Resource
{
    public const CODE = 'parts';
    public const PATH = '/item-planning';
    protected static string $table = 'pt_site_mstr';
    protected static array $key = ['pt_site', 'pt_part'];
    protected static array $list = ['pt_site', 'pt_part', 'pt_safe_stk', 'pt_rop', 'pt_roq'];
    protected static array $form = [
        'pt_site' => ['exists' => ['si_mstr', 'si_site'], 'upper' => true],
        'pt_part' => ['exists' => ['pt_mstr', 'pt_part'], 'upper' => true],
        'pt_status' => ['options' => ['ACTIVE', 'OBSOLETE', 'PHASE-OUT']],
        'pt_safe_stk' => [], 'pt_rop' => [], 'pt_roq' => [],
        'pt_buyer' => [], 'pt_sourcing' => [],
        'pt_mfg_lead_time' => [], 'pt_pur_lead_time' => [],
        'pt_iss_loc' => [], 'pt_rcv_loc' => [],
    ];
    public static function title(): string { return t('stock.item_planning_title'); }
    public static function validate(array $data, bool $creating): array
    {
        $row = parent::validate($data, $creating);
        $errors = [];
        foreach (['pt_safe_stk', 'pt_rop', 'pt_roq', 'pt_mfg_lead_time', 'pt_pur_lead_time'] as $key) {
            if (isset($row[$key]) && $row[$key] < 0) $errors[$key] = t('validation.positive');
        }
        if ($errors) throw HttpException::invalid($errors);
        return $row;
    }
}
