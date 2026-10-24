<?php
declare(strict_types=1);
// Only operates on its own randomly named database, never the installed ledger.
require dirname(__DIR__) . '/src/autoload.php';
use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Router;
use Phai\Install\Installer;
use Phai\Modules\Master\PartResource;
use Phai\Modules\Master\MaterialResource;
use Phai\Modules\Master\ProductLineResource;
$config = require PHAI_ROOT . '/config.php';
$installed = $config['db']['name'];
$source = new Db($config['db']);
$name = 'phaierp_pl_test_' . bin2hex(random_bytes(5));
$source->run('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$config['db']['name'] = $name;
App::boot($config); $db = App::db(); $checks = 0;
function verifyLine(bool $yes, string $label): void {
    if (!$yes) throw new RuntimeException($label);
    $GLOBALS['checks']++; echo "ok $label\n";
}
function invalidLine(callable $fn, string $label): void {
    try { $fn(); } catch (HttpException $e) { verifyLine($e->status === 422, $label); return; }
    throw new RuntimeException($label . ' accepted');
}
try {
    foreach (['database/schema/phaierp_schema.sql', 'database/seeds/20_reference.sql', 'database/seeds/30_system.sql', 'database/seeds/35_programs.sql', 'database/seeds/40_menu.sql'] as $file) {
        foreach (Installer::statements(file_get_contents(PHAI_ROOT . '/' . $file)) as $sql) $db->run($sql);
    }
    $db->insert('us_mstr', ['us_userid' => 'pl-test', 'us_name' => 'Test', 'us_email' => 'pl-test@example.invalid', 'us_password_hash' => 'login-disabled', 'us_role' => 'ADMIN']);
    Auth::actAs('pl-test');
    verifyLine($db->value("SELECT COUNT(*) FROM pl_mstr WHERE pl_inventory_type IN ('FG','RM')") == 2, 'fresh installation seeds FG and RM lines');
    $fg = PartResource::create(PartResource::validate(['pt_part' => 'FG1', 'pt_desc1' => 'Finished', 'pt_um' => 'EA', 'pt_prod_line' => 'FG', 'pt_part_type' => 'P'], true));
    MaterialResource::create(MaterialResource::validate(['pt_part' => 'RM1', 'pt_desc1' => 'Material', 'pt_um' => 'KG', 'pt_prod_line' => 'RM', 'pt_part_type' => 'M'], true));
    $db->insert('pl_mstr', ['pl_prod_line' => 'GEN', 'pl_desc' => 'Legacy unclassified line', 'pl_active' => 1]); // older installs
    $db->insert('pt_mstr', ['pt_part' => 'OLD', 'pt_desc1' => 'Legacy', 'pt_um' => 'EA', 'pt_prod_line' => 'GEN']);
    verifyLine(array_column(PartResource::list(['type' => 'FG'])['rows'], 'pt_part') === ['FG1', 'OLD'], 'finished list keeps unclassified legacy and purchased FG');
    verifyLine(array_column(MaterialResource::list()['rows'], 'pt_part') === ['RM1'], 'material list includes manufactured RM based only on Product Line');
    verifyLine(MaterialResource::list(['q' => 'Finished'])['total'] === 0, 'search cannot escape material classification');
    verifyLine(PartResource::list()['total'] === 3 && array_column(PartResource::list(['type' => 'RM'])['rows'], 'pt_part') === ['RM1'], 'one item screen shows all, filtered by stock type');
    verifyLine(array_column(PartResource::list(['pl' => 'RM'])['rows'], 'pt_part') === ['RM1'], 'item screen filters by product line');
    verifyLine(PartResource::list(['q' => 'Legacy'])['total'] === 1, 'search includes legacy records');
    verifyLine(!array_key_exists('pt_inventory_type', $fg), 'obsolete item classification hidden from row');
    verifyLine(!in_array('pt_inventory_type', array_column(PartResource::gridFields(), 'name'), true), 'obsolete classification absent from column chooser');
    verifyLine(!in_array('pt_inventory_type', array_column(MaterialResource::fields(), 'name'), true), 'no duplicate classification in material form');
    verifyLine(array_column(PartResource::fields(), 'default', 'name')['pt_prod_line'] === 'FG'
        && array_column(MaterialResource::fields(), 'default', 'name')['pt_prod_line'] === 'RM', 'each screen defaults its Product Line');
    invalidLine(fn () => ProductLineResource::validate(['pl_prod_line' => 'BAD', 'pl_desc' => 'Missing type'], true), 'new Product Line requires classification');
    invalidLine(fn () => ProductLineResource::validate(['pl_prod_line' => 'BAD', 'pl_desc' => 'Bad type', 'pl_inventory_type' => 'XX'], true), 'invalid line classification rejected');
    invalidLine(fn () => PartResource::validate(['pt_part' => 'BAD', 'pt_desc1' => 'Bad', 'pt_um' => 'EA', 'pt_prod_line' => 'GEN'], true), 'new item requires classified active line');
    ProductLineResource::update('GEN', ProductLineResource::validate(['pl_inventory_type' => 'RM'], false));
    verifyLine(MaterialResource::list()['total'] === 2 && PartResource::list(['type' => 'FG'])['total'] === 1, 'changing existing line reclassifies all linked items');
    PartResource::update('FG1', PartResource::validate(['pt_prod_line' => 'RM'], false));
    verifyLine(PartResource::list(['type' => 'FG'])['total'] === 0 && MaterialResource::list()['total'] === 3, 'changing item Product Line moves item between screens');
    $db->update('pl_mstr', ['pl_active' => 0], ['pl_prod_line' => 'RM']);
    verifyLine(MaterialResource::list()['total'] === 3, 'inactive line does not hide existing materials');
    invalidLine(fn () => MaterialResource::validate(['pt_part' => 'BAD', 'pt_desc1' => 'Bad', 'pt_um' => 'EA', 'pt_prod_line' => 'RM'], true), 'new item cannot use inactive line');
    $db->update('pl_mstr', ['pl_active' => 1], ['pl_prod_line' => 'RM']);
    verifyLine(MaterialResource::list(['per_page' => 1, 'page' => 2, 'sort' => 'pt_part'])['rows'][0]['pt_part'] === 'OLD', 'sorting and paging preserve scope');
    $router = new Router(); $router->load(PHAI_ROOT . '/src/Modules');
    foreach (['/parts', '/materials', '/product-lines', '/api/parts', '/api/materials', '/api/product-lines'] as $path) {
        $response = $router->dispatch(new Request('GET', $path, '', [], [], [], ''));
        verifyLine(strlen($response->body) > 0, 'route ' . $path);
    }
    verifyLine($db->value('SELECT COUNT(*) FROM gl_trhist') == 0, 'classification creates no GL postings');
    echo "$checks Product Line integration checks passed.\n";
} finally {
    if (preg_match('/^phaierp_pl_test_[a-f0-9]{10}$/', $name) && $name !== $installed) $source->run('DROP DATABASE `' . $name . '`');
}
