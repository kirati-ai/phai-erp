<?php
namespace Phai\Modules\Master;

/** Separate raw-material view; permissions and stock ledger remain shared with parts. */
final class MaterialResource extends PartResource
{
    public const PATH = '/materials';
    protected const INVENTORY_TYPE = 'RM';
    protected const FIXED_TYPE = true;
    public static function title(): string { return t('production.materials'); }
    public static function filters(): array { return [parent::filters()[1]]; }
}
