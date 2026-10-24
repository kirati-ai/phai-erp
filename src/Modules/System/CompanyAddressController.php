<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Program;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Put;
use Phai\Core\Validation\Check;

/** The company's own address (ad_mstr COMPANY), edited on the company screen. */
#[Program('company')]
final class CompanyAddressController
{
    #[Get('/api/company/address')]
    public function show(): array
    {
        return Address::companyParts() + ['text' => Address::format(Address::company())];
    }

    /** Body: the parts (ad_building … ad_road), ad_line2 (anything else), ad_sub_district, ad_city, ad_state, ad_zip */
    #[Put('/api/company/address')]
    public function update(Request $request): array
    {
        $c = new Check();
        $fields = Address::check($c, $request->body);
        $c->done();
        $old = Address::company() ?? [];
        App::db()->transaction(fn () => Address::saveCompany($fields));
        Audit::updated('ad_mstr', 'ad_addr=' . Address::COMPANY, array_intersect_key($old, $fields), $fields);
        return $this->show();
    }
}
