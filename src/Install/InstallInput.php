<?php

declare(strict_types=1);

namespace Phai\Install;

use Phai\Core\Validation\Email;
use Phai\Core\Validation\Length;
use Phai\Core\Validation\Pattern;
use Phai\Core\Validation\Range;

/** Database fields are always needed; company and admin fields only for the final step. */
final class InstallInput
{
    public function __construct(
        #[Length(max: 255)]
        public string $db_host = '127.0.0.1',
        #[Range(min: 1, max: 65535)]
        public int $db_port = 3306,
        #[Length(max: 64), Pattern('/^[A-Za-z0-9_]+$/', 'install.db_name_format')]
        public string $db_name = 'phaierp',
        #[Length(max: 255)]
        public string $db_user = 'root',
        public string $db_pass = '',
        #[Length(max: 255)]
        public ?string $co_name = null,
        #[Length(max: 50)]
        public ?string $co_tax_id = null,
        #[Length(max: 50)]
        public ?string $co_phone = null,
        #[Length(max: 255)]
        public ?string $ad_line1 = null,
        #[Length(max: 100)]
        public ?string $ad_sub_district = null,
        #[Length(max: 100)]
        public ?string $ad_city = null,
        #[Length(max: 100)]
        public ?string $ad_state = null,
        #[Length(max: 20), Pattern('/^[0-9A-Za-z -]*$/', 'validation.in')]
        public ?string $ad_zip = null,
        /** data URL of the logo image (checked by Logo::decode) */
        #[Length(max: 1500000)]
        public ?string $co_logo = null,
        #[Length(max: 50), Pattern('/^[A-Za-z0-9._-]+$/', 'validation.username')]
        public ?string $us_userid = null,
        #[Length(max: 255)]
        public ?string $us_name = null,
        #[Length(max: 255), Email]
        public ?string $us_email = null,
        #[Length(min: 8, max: 255)]
        public ?string $password = null,
        public bool $demo = false,
    ) {
    }

    /** Required fields of each wizard step. */
    public const STEPS = [
        'company' => ['co_name'],
        'admin'   => ['us_userid', 'us_name', 'us_email', 'password'],
    ];

    /** Fields needed by a step (or, without one, by the final install) that are still empty, as field => message. */
    public function missingForInstall(?string $step = null): array
    {
        $missing = [];
        $fields = $step === null ? array_merge(...array_values(self::STEPS)) : (self::STEPS[$step] ?? []);
        foreach ($fields as $field) {
            if ($this->$field === null) {
                $missing[$field] = t('validation.required');
            }
        }
        return $missing;
    }
}
