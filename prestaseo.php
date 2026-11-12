<?php
/** Presta SEO — MIT license. No credentials or external services. */
if (!defined('_PS_VERSION_')) {
    exit;
}
require_once dirname(__FILE__) . '/classes/SeoData.php';
require_once dirname(__FILE__) . '/classes/MetaService.php';

class Prestaseo extends Module
{
    const SETTINGS = 'PRESTASEO_SETTINGS';
    const PLAN = 'PRESTASEO_PLAN';

    public function __construct()
    {
        $this->name = 'prestaseo';
        $this->tab = 'seo';
        $this->version = '0.2.0';
        $this->author = 'Presta SEO contributors';
        $this->need_instance = 0;
        $this->bootstrap = true;
        // Target range, not a claim of integration certification for every release.
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => '9.99.99');
        parent::__construct();
        $this->displayName = $this->l('Presta SEO: identidad y metadatos');
        $this->description = $this->l('Publica Organization y prepara metadatos vacíos con vista previa y reversión.');
        $this->confirmUninstall = $this->l('Se desactivará la publicación. Los metadatos y respaldos se conservarán. Revierte los lotes antes de desinstalar si lo necesitas.');
    }

    public function install()
    {
        return parent::install() && $this->registerHook('displayHeader') && Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'prestaseo_backup` ('
            . 'id_prestaseo_backup INT UNSIGNED NOT NULL AUTO_INCREMENT, id_shop INT UNSIGNED NOT NULL,'
            . 'id_employee INT UNSIGNED NOT NULL, date_add DATETIME NOT NULL, status VARCHAR(16) NOT NULL,'
            . 'payload MEDIUMTEXT NOT NULL, PRIMARY KEY (id_prestaseo_backup), KEY shop_id (id_shop)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function uninstall()
    {
        // Deliberately retain backup data and settings for recovery on reinstall.
        return parent::uninstall();
    }

    private function shopId()
    {
        return (int) $this->context->shop->id;
    }

    private function getValue($key)
    {
        return Configuration::get($key, null, (int) $this->context->shop->id_shop_group, $this->shopId());
    }

    private function putValue($key, $value)
    {
        if (!Configuration::updateValue($key, $value, true, (int) $this->context->shop->id_shop_group, $this->shopId())) {
            throw new Exception('No se pudo guardar la configuración.');
        }
    }

    private function baseUrl()
    {
        return $this->context->shop->getBaseURL(true);
    }

    public function settings()
    {
        $saved = json_decode((string) $this->getValue(self::SETTINGS), true);
        if (is_array($saved)) {
            return $saved;
        }
        $country = new Country((int) $this->getValue('PS_SHOP_COUNTRY_ID'));
        $logo = $this->getValue('PS_LOGO');
        return array('enabled' => 0, 'type' => 'OnlineStore', 'name' => (string) $this->getValue('PS_SHOP_NAME'),
            'legal_name' => '', 'logo' => $logo ? $this->baseUrl() . 'img/' . $logo : '',
            'telephone' => (string) $this->getValue('PS_SHOP_PHONE'), 'email' => (string) $this->getValue('PS_SHOP_EMAIL'),
            'street_address' => trim($this->getValue('PS_SHOP_ADDR1') . ' ' . $this->getValue('PS_SHOP_ADDR2')),
            'address_locality' => (string) $this->getValue('PS_SHOP_CITY'), 'postal_code' => (string) $this->getValue('PS_SHOP_CODE'),
            'address_country' => Validate::isLoadedObject($country) ? $country->iso_code : '', 'same_as' => '');
    }

    public function hookDisplayHeader($params)
    {
        if (!isset($this->context->controller->php_self) || $this->context->controller->php_self !== 'index') {
            return '';
        }
        $settings = $this->settings();
        if (empty($settings['enabled'])) {
            return '';
        }
        try {
            $settings = PrestaSeoData::validate($settings);
            return '<script type="application/ld+json" id="prestaseo-organization">'
                . PrestaSeoData::json(PrestaSeoData::schema($settings, $this->baseUrl())) . '</script>';
        } catch (Exception $error) {
            return ''; // Invalid settings must not break the storefront.
        }
    }

    private function esc($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function formStart()
    {
        return '<form method="post" action="' . $this->esc($this->context->link->getAdminLink('AdminModules')
            . '&configure=' . $this->name) . '"><input type="hidden" name="prestaseo_token" value="'
            . $this->esc(Tools::getAdminTokenLite('AdminModules')) . '">';
    }

    public function getContent()
    {
        if (!isset($this->context->employee) || !Validate::isLoadedObject($this->context->employee)
            || !$this->getPermission('configure', $this->context->employee)) {
            return $this->displayError($this->l('No tienes permiso para configurar este módulo.'));
        }
        if (Shop::isFeatureActive() && Shop::getContext() !== Shop::CONTEXT_SHOP) {
            return $this->displayError($this->l('Selecciona una tienda concreta en el selector multitienda.'));
        }
        $html = '';
        $service = new PrestaSeoMetaService($this->shopId());
        $action = Tools::getValue('prestaseo_action');
        if ($action) {
            try {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST'
                    || !is_string(Tools::getValue('prestaseo_token'))
                    || !hash_equals(Tools::getAdminTokenLite('AdminModules'), Tools::getValue('prestaseo_token'))) {
                    throw new Exception('Petición o token no válido.');
                }
                if ($action === 'save') {
                    $input = Tools::getValue('organization');
                    if (!is_array($input)) {
                        throw new Exception('Datos de organización no válidos.');
                    }
                    $this->putValue(self::SETTINGS, PrestaSeoData::json(PrestaSeoData::validate($input)));
                    $html .= $this->displayConfirmation($this->l('Datos guardados. Si usas caché de página o CDN, vacíala para comprobar el resultado.'));
                } elseif ($action === 'preview') {
                    $language = (int) Tools::getValue('language');
                    $allowed = array();
                    foreach (Language::getLanguages(true, $this->shopId()) as $lang) {
                        $allowed[] = (int) $lang['id_lang'];
                    }
                    if (!in_array($language, $allowed, true)) {
                        throw new Exception('Selecciona un idioma activo de esta tienda.');
                    }
                    $plan = $service->preview(Tools::getValue('resource'), max(0, (int) Tools::getValue('after')), $language);
                    $plan['employee'] = (int) $this->context->employee->id;
                    $this->putValue(self::PLAN, PrestaSeoData::json($plan));
                } elseif ($action === 'apply') {
                    $plan = json_decode((string) $this->getValue(self::PLAN), true);
                    if (!is_array($plan) || (int) $plan['employee'] !== (int) $this->context->employee->id
                        || time() - (int) $plan['created'] > 3600) {
                        throw new Exception('La vista previa ha caducado o pertenece a otro empleado. Genera una nueva.');
                    }
                    $id = $service->apply($plan, $this->context->employee->id);
                    $this->putValue(self::PLAN, '');
                    $html .= $this->displayConfirmation($this->l('Lote aplicado y verificado. Respaldo número: ') . (int) $id);
                } elseif ($action === 'restore') {
                    $service->restore((int) Tools::getValue('backup_id'), $this->context->employee->id);
                    $html .= $this->displayConfirmation($this->l('Lote revertido. Vacía la caché de página/CDN si la utilizas.'));
                } else {
                    throw new Exception('Acción desconocida.');
                }
            } catch (Exception $error) {
                $html .= $this->displayError($this->esc($error->getMessage()));
            }
        }
        $html .= '<div class="panel"><h2>Identidad de la tienda · Organization</h2>'
            . '<p>Revisa los datos propuestos desde la configuración de tu tienda. Activa solo si tu tema u otro módulo no publica ya la misma entidad. '
            . 'El marcado se publica en la portada; no garantiza posiciones ni menciones en IA.</p>';
        $settings = $this->settings();
        $html .= $this->formStart() . '<input type="hidden" name="prestaseo_action" value="save">'
            . '<label><input type="checkbox" name="organization[enabled]" value="1"' . (!empty($settings['enabled']) ? ' checked' : '')
            . '> Publicar datos estructurados en la portada</label><br><label>Tipo <select name="organization[type]">';
        foreach (array('OnlineStore', 'Organization', 'Store', 'SportingGoodsStore') as $type) {
            $html .= '<option value="' . $type . '"' . ($settings['type'] === $type ? ' selected' : '') . '>' . $type . '</option>';
        }
        $html .= '</select></label>';
        foreach (array('name' => 'Nombre comercial', 'legal_name' => 'Razón social (opcional)', 'logo' => 'URL del logotipo',
            'telephone' => 'Teléfono internacional', 'email' => 'Correo de contacto', 'street_address' => 'Dirección pública',
            'address_locality' => 'Localidad', 'postal_code' => 'Código postal', 'address_country' => 'País ISO (ES, PT…)') as $key => $label) {
            $html .= '<div class="form-group"><label>' . $label . '<input class="form-control" type="text" name="organization[' . $key
                . ']" value="' . $this->esc(isset($settings[$key]) ? $settings[$key] : '') . '" maxlength="1000"></label></div>';
        }
        $html .= '<div class="form-group"><label>Perfiles oficiales (una URL por línea)<textarea class="form-control" rows="4" name="organization[same_as]">'
            . $this->esc($settings['same_as']) . '</textarea></label></div><button class="btn btn-primary" type="submit">Guardar datos</button></form>';
        $html .= '<h3>Vista previa del JSON-LD</h3><pre>' . $this->esc(PrestaSeoData::json(PrestaSeoData::schema($settings, $this->baseUrl()))) . '</pre></div>';
        $html .= '<div class="panel"><h2>Completar metadatos vacíos</h2><p>Lee 25 registros por lote y un idioma. Propone títulos desde el nombre y descripciones desde el texto existente. '
            . 'Revisa la calidad antes de aplicar. No modifica metadatos existentes, precios, stock, nombres ni URLs. '
            . 'Prueba primero en una copia de la tienda. Los temas con títulos propios pueden ignorar estos campos.</p>';
        $html .= $this->formStart() . '<input type="hidden" name="prestaseo_action" value="preview">'
            . '<label>Recurso <select name="resource"><option value="product">Productos</option><option value="category">Categorías</option></select></label> '
            . '<label>Idioma <select name="language">';
        foreach (Language::getLanguages(true, $this->shopId()) as $lang) {
            $html .= '<option value="' . (int) $lang['id_lang'] . '">' . $this->esc($lang['name']) . '</option>';
        }
        $html .= '</select></label> <label>Leer desde ID mayor que <input type="number" name="after" value="0" min="0"></label> '
            . '<button class="btn btn-default" type="submit">Generar vista previa</button></form>';
        $plan = json_decode((string) $this->getValue(self::PLAN), true);
        if (is_array($plan) && (int) $plan['employee'] === (int) $this->context->employee->id) {
            $html .= '<p>Registros leídos: ' . (int) $plan['rows_read'] . '. Para el siguiente lote introduce: ' . (int) $plan['last_id'] . '.</p>'
                . '<table class="table"><thead><tr><th>Recurso / ID</th><th>Idioma</th><th>Campo</th><th>Valor anterior</th><th>Propuesta</th></tr></thead><tbody>';
            foreach ($plan['changes'] as $change) {
                $html .= '<tr><td>' . $this->esc($change['resource']) . ' / ' . (int) $change['id'] . '</td><td>' . (int) $change['language']
                    . '</td><td>' . $this->esc($change['field']) . '</td><td>' . $this->esc($change['before']) . '</td><td>' . $this->esc($change['after']) . '</td></tr>';
            }
            $html .= '</tbody></table>';
            if (!empty($plan['changes'])) {
                $html .= $this->formStart() . '<input type="hidden" name="prestaseo_action" value="apply">'
                    . '<button class="btn btn-primary" type="submit">Aplicar este lote revisado</button></form>';
            }
        }
        $html .= '<h3>Últimos respaldos de esta tienda</h3><p>La reversión se detiene si los campos han cambiado desde su aplicación. '
            . 'Los respaldos se conservan al desinstalar; no sustituyen una copia de seguridad completa.</p>';
        foreach ($service->history() as $row) {
            $html .= '<div>#' . (int) $row['id_prestaseo_backup'] . ' · ' . $this->esc($row['date_add']) . ' · ' . $this->esc($row['status']);
            if ($row['status'] === 'applied') {
                $html .= $this->formStart() . '<input type="hidden" name="prestaseo_action" value="restore"><input type="hidden" name="backup_id" value="'
                    . (int) $row['id_prestaseo_backup'] . '"><button class="btn btn-default" type="submit">Revertir este lote</button></form>';
            }
            $html .= '</div>';
        }
        return $html . '</div>';
    }
}
