<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
class Shop {
    const CONTEXT_SHOP = 1;
    public $id = 1;
    public $id_shop_group = 1;
    public static function isFeatureActive() { return true; }
    public static function getContext() { return 1; }
    public function getBaseURL($secure) { return 'https://example.com/'; }
}
class TestLink {
    public function getAdminLink($controller) { return 'https://example.com/admin/index.php?token=valid-token'; }
}
class Module {
    public $name, $tab, $version, $author, $need_instance, $bootstrap, $ps_versions_compliancy;
    public $displayName, $description, $confirmUninstall, $context;
    public $permission = true;
    public function __construct() {
        $this->context = new stdClass();
        $this->context->shop = new Shop();
        $this->context->controller = new stdClass();
        $this->context->controller->php_self = 'index';
        $this->context->employee = new stdClass();
        $this->context->employee->id = 1;
        $this->context->link = new TestLink();
    }
    public function l($text) { return $text; }
    public function getPermission($action, $employee) { return $this->permission; }
    public function displayError($text) { return '<p>' . $text . '</p>'; }
    public function displayConfirmation($text) { return '<p>' . $text . '</p>'; }
}
class Configuration {
    public static $values = array();
    public static function get($key, $language = null, $group = null, $shop = null) {
        if (isset(self::$values[$shop . ':' . $key])) { return self::$values[$shop . ':' . $key]; }
        return $key === 'PS_SHOP_NAME' ? 'Default shop' : '';
    }
    public static function updateValue($key, $value, $html, $group, $shop) { self::$values[$shop . ':' . $key] = $value; return true; }
}
class Country {
    public $id = 0;
    public $iso_code = '';
    public function __construct($id) { $this->id = $id; }
}
class Validate {
    public static function isLoadedObject($object) { return !empty($object->id); }
}
class Language {
    public static function getLanguages($active, $shop) { return array(array('id_lang' => 1, 'name' => 'Español')); }
}
class Tools {
    public static $values = array();
    public static function getValue($key) { return isset(self::$values[$key]) ? self::$values[$key] : false; }
    public static function getAdminTokenLite($controller) { return 'valid-token'; }
}
