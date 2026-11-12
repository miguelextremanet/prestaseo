<?php
/** Local behavioural tests. No real PrestaShop/database is used. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('_PS_VERSION_', '1.7.0.0');
define('_DB_PREFIX_', 'ps_');
function pSQL($value, $html = false) { return addslashes($value); }
function bqSQL($value) { return str_replace('`', '', $value); }
function check($condition, $message) {
    if (!$condition) { throw new Exception('FAIL: ' . $message); }
    echo 'OK: ' . $message . PHP_EOL;
}
function rejects($callback, $message) {
    $caught = false;
    try { call_user_func($callback); } catch (Exception $error) { $caught = true; }
    check($caught, $message);
}
class Db
{
    public static $instance;
    public $rows = array();
    public $backups = array();
    public $engine = 'InnoDB';
    public $log = array();
    public $snapshot;
    public $failUpdate = false;
    public static function getInstance() { return self::$instance; }
    private function rowKey($sql) {
        preg_match('/FROM `ps_(product|category)_lang`/', $sql, $r);
        preg_match('/id_(?:product|category)=(\d+) AND id_lang=(\d+) AND id_shop=(\d+)/', $sql, $ids);
        return $r[1] . ':' . $ids[1] . ':' . $ids[2] . ':' . $ids[3];
    }
    public function getRow($sql, $cache = true) {
        if (strpos($sql, 'SHOW TABLE') === 0) { return array('Engine' => $this->engine); }
        if (strpos($sql, 'prestaseo_backup') !== false) {
            preg_match('/id_prestaseo_backup=(\d+) AND id_shop=(\d+)/', $sql, $m);
            return isset($this->backups[$m[1]]) && $this->backups[$m[1]]['id_shop'] == $m[2] ? $this->backups[$m[1]] : false;
        }
        $key = $this->rowKey($sql);
        return isset($this->rows[$key]) ? $this->rows[$key] : false;
    }
    public function execute($sql) {
        $this->log[] = $sql;
        if ($sql === 'START TRANSACTION') { $this->snapshot = array($this->rows, $this->backups); return true; }
        if ($sql === 'ROLLBACK') { $this->rows = $this->snapshot[0]; $this->backups = $this->snapshot[1]; return true; }
        if ($sql === 'COMMIT') { return true; }
        if (strpos($sql, 'UPDATE `ps_product_lang`') === 0 || strpos($sql, 'UPDATE `ps_category_lang`') === 0) {
            if ($this->failUpdate) { return false; }
            preg_match('/UPDATE `ps_(product|category)_lang` SET `(meta_title|meta_description)`=\'(.*?)\' WHERE id_(?:product|category)=(\d+) AND id_lang=(\d+) AND id_shop=(\d+)$/s', $sql, $m);
            $key = $m[1] . ':' . $m[4] . ':' . $m[5] . ':' . $m[6];
            $this->rows[$key][$m[2]] = stripslashes($m[3]);
            return true;
        }
        if (strpos($sql, 'INSERT INTO `ps_prestaseo_backup`') === 0) {
            preg_match('/VALUES \((\d+),(\d+),NOW\(\),\'applied\',\'(.*)\'\)$/s', $sql, $m);
            $this->backups[] = array('id_shop' => (int) $m[1], 'status' => 'applied', 'payload' => stripslashes($m[3]));
            return true;
        }
        if (strpos($sql, 'UPDATE `ps_prestaseo_backup`') === 0) {
            preg_match('/id_prestaseo_backup=(\d+)/', $sql, $m);
            $this->backups[$m[1]]['status'] = 'reverted';
            return true;
        }
        throw new Exception('Unexpected SQL in fixture: ' . $sql);
    }
    public function Insert_ID() { return count($this->backups) - 1; }
    public function executeS($sql, $array = true, $cache = true) { return array(); }
}

require dirname(__FILE__) . '/../classes/SeoData.php';
require dirname(__FILE__) . '/../classes/MetaService.php';

$settings = PrestaSeoData::validate(array('name' => 'Mi tienda', 'type' => 'OnlineStore', 'enabled' => 1,
    'same_as' => "https://example.com/profile\nhttps://example.com/profile", 'address_country' => 'ES'));
$schema = PrestaSeoData::schema($settings, 'https://example.com/shop/');
check($schema['@id'] === 'https://example.com/shop/#organization', 'stable shop-scoped identity');
check(count($schema['sameAs']) === 1, 'deduplicates official profiles');
check(!isset($schema['legalName']) && !isset($schema['email']), 'does not invent optional business data');
check(strpos(PrestaSeoData::json(array('name' => '</script><script>bad()</script>')), '<') === false, 'JSON cannot close the script element');
check(PrestaSeoData::shorten('áéíóú bicicleta', 7) === 'áéíóú…', 'Unicode shortening');
rejects(function () { PrestaSeoData::validate(array('name' => 'Shop', 'type' => 'OnlineStore', 'logo' => 'javascript:alert(1)')); }, 'rejects unsafe URL');
rejects(function () { PrestaSeoData::validate(array('name' => array('bad'), 'type' => 'OnlineStore')); }, 'rejects malformed form values');
$rows = array(array('id_entity' => 1, 'id_lang' => 1, 'name' => 'Bicicleta', 'meta_title' => '',
    'meta_description' => '', 'description_short' => '<p>Para paseos &amp; ocio.</p>', 'description' => 'Long'));
$changes = PrestaSeoData::proposals($rows, 'product');
check(count($changes) === 2 && $changes[1]['after'] === 'Para paseos & ocio.', 'proposals derive only from existing content');
$rows[0]['meta_title'] = 'Keep this';
check(count(PrestaSeoData::proposals($rows, 'category')) === 1, 'preserves existing metadata');
$rows[0]['description_short'] = '<p></p>';
check(PrestaSeoData::proposals($rows, 'product')[0]['after'] === 'Long', 'falls back from empty HTML to full description');

function fixture() {
    $db = new Db();
    $db->rows = array('product:1:1:1' => array('meta_title' => '', 'meta_description' => ''),
        'product:1:2:1' => array('meta_title' => 'Other language'),
        'product:1:1:2' => array('meta_title' => 'Other shop'));
    // Reserve zero so backup IDs behave as an auto-increment starting at 1.
    $db->backups = array(0 => array('id_shop' => 0));
    Db::$instance = $db;
    return $db;
}
$db = fixture();
$service = new PrestaSeoMetaService(1);
$plan = array('shop' => 1, 'changes' => $changes);
$id = $service->apply($plan, 1);
check($db->rows['product:1:1:1']['meta_title'] === 'Bicicleta', 'applies metadata');
check($db->rows['product:1:2:1']['meta_title'] === 'Other language' && $db->rows['product:1:1:2']['meta_title'] === 'Other shop', 'preserves other languages and shops');
check($db->backups[$id]['status'] === 'applied', 'backup committed with data');
$service->restore($id, 1);
check($db->rows['product:1:1:1']['meta_title'] === '' && $db->backups[$id]['status'] === 'reverted', 'restores original values');
rejects(function () use ($service, $id) { $service->restore($id, 1); }, 'prevents repeated reversal');
$db = fixture();
$service = new PrestaSeoMetaService(1);
$db->rows['product:1:1:1']['meta_description'] = 'Concurrent edit';
rejects(function () use ($service, $plan) { $service->apply($plan, 1); }, 'detects conflict in later field');
check($db->rows['product:1:1:1']['meta_title'] === '' && count($db->backups) === 1, 'rolls back earlier writes and backup on conflict');
$db = fixture();
$db->engine = 'MyISAM';
$service = new PrestaSeoMetaService(1);
rejects(function () use ($service, $plan) { $service->apply($plan, 1); }, 'refuses non-transactional tables');
check(count($db->log) === 0, 'no write on unsupported engine');
$db = fixture();
$db->failUpdate = true;
$service = new PrestaSeoMetaService(1);
rejects(function () use ($service, $plan) { $service->apply($plan, 1); }, 'database failure stops batch');
check(end($db->log) === 'ROLLBACK', 'rolls back database failure');
$db = fixture();
$service = new PrestaSeoMetaService(2);
rejects(function () use ($service, $plan) { $service->apply($plan, 1); }, 'rejects cross-shop plan');
require dirname(__FILE__) . '/module-fixtures.php';
require dirname(__FILE__) . '/../prestaseo.php';
$db = fixture();
$module = new Prestaseo();
check($module->hookDisplayHeader(array()) === '', 'schema stays disabled until reviewed');
$enabled = array('name' => 'Test shop', 'type' => 'OnlineStore', 'enabled' => 1);
Configuration::$values['1:PRESTASEO_SETTINGS'] = PrestaSeoData::json(PrestaSeoData::validate($enabled));
$rendered = $module->hookDisplayHeader(array());
check(strpos($rendered, 'id="prestaseo-organization"') !== false, 'home page publishes JSON-LD');
$module->context->controller->php_self = 'product';
check($module->hookDisplayHeader(array()) === '', 'does not inject Organization on product pages');
$module->context->controller->php_self = 'index';
$module->context->shop->id = 2;
check($module->hookDisplayHeader(array()) === '', 'does not reuse another shop settings');
$module->context->shop->id = 1;
$panel = $module->getContent();
check(strpos($panel, 'prestaseo_token') !== false && strpos($panel, 'metadatos') !== false, 'renders autonomous back-office configuration');
Tools::$values = array('prestaseo_action' => 'save', 'prestaseo_token' => 'wrong', 'organization' => $enabled);
$_SERVER['REQUEST_METHOD'] = 'POST';
check(strpos($module->getContent(), 'token no válido') !== false, 'rejects invalid CSRF token');
Tools::$values['prestaseo_token'] = 'valid-token';
Tools::$values['organization']['name'] = 'Changed name';
check(strpos($module->getContent(), 'Datos guardados') !== false, 'authorized settings save');
check($module->settings()['name'] === 'Changed name', 'persists settings in selected shop');
$module->permission = false;
check(strpos($module->getContent(), 'No tienes permiso') !== false, 'denies employees without module configure permission');
echo 'All local tests passed.' . PHP_EOL;
