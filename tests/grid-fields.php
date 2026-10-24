<?php
// Read-only integration audit against an installed database; never runs the installer.
require dirname(__DIR__).'/src/bootstrap.php';
use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Routing\Router;
use Phai\Core\Http\Request;
Auth::actAs(App::db()->value("SELECT us_userid FROM us_mstr WHERE us_role='ADMIN' AND us_active=1 LIMIT 1"));
$r=new Router();$r->load(PHAI_ROOT.'/src/Modules');
foreach (['purchase-requisitions','purchase-orders','receipts','landed-costs','quotations','sales-orders','shipments','invoices','billings','work-orders','stock-counts','journals','payables','cash-receipts','vouchers','stock/history','stock','users'] as $p) {
 try {
 $res=$r->dispatch(new Request('GET','/api/'.$p,'',[],[],[],''));$d=json_decode($res->body,true)['data'];
 echo $p.' '.count($d['fields']??[]).' fields '.count($d['rows']??[]).' rows'."\n";
 } catch (Throwable $e) {echo $p.' ERROR '.$e->getMessage()."\n";}
}

foreach (['shipments'=>'sh_mstr','sales-orders'=>'so_mstr','purchase-orders'=>'po_mstr','stock/history'=>'tr_hist','stock'=>'ld_det'] as $path=>$table) {
 $response=$r->dispatch(new Request('GET','/api/'.$path,'',[],[],[],''));
 $data=json_decode($response->body,true)['data'];
 $missing=array_diff(App::db()->columns($table),array_column($data['fields'],'key'));
 if ($missing) throw new RuntimeException($path.' missing '.implode(',',$missing));
 $row=$data['rows'][0]??[];
 foreach ($row as $key=>$value) {
  if ($value === null || $value === '' || !is_scalar($value)) continue;
  $response=$r->dispatch(new Request('GET','/api/'.$path,'',['grid_search'=>(string)$value],[],[],''));
  $filtered=json_decode($response->body,true)['data'];
  if (!$filtered['total']) throw new RuntimeException($path.' cannot search '.$key);
  if (count($filtered['fields'])!==count($data['fields'])) throw new RuntimeException('Search lost metadata');
 }
}
echo "All public base columns and search metadata verified.\n";
