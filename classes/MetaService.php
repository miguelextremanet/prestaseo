<?php
/**
 * Presta SEO
 * Miguel Angel García - eXtremaNET Consultores (2026)
 * Licencia MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class PrestaSeoMetaService
{
    private $db;
    private $shop;

    public function __construct($shop)
    {
        $this->db = Db::getInstance();
        $this->shop = (int) $shop;
    }

    private function table($resource)
    {
        if (!in_array($resource, array('product', 'category'), true)) {
            throw new Exception('Recurso no permitido.');
        }
        return _DB_PREFIX_ . $resource . '_lang';
    }

    public function preview($resource, $after, $language)
    {
        $table = $this->table($resource);
        $rows = $this->db->executeS('SELECT id_' . $resource . ' AS id_entity, id_lang, name, meta_title, meta_description, description'
            . ($resource === 'product' ? ', description_short' : '') . ' FROM `' . bqSQL($table) . '`'
            . ' WHERE id_shop=' . $this->shop . ' AND id_lang=' . (int) $language
            . ' AND id_' . $resource . '>' . (int) $after
            . ' ORDER BY id_' . $resource . ' ASC LIMIT 25', true, false);
        if (!is_array($rows)) {
            throw new Exception('No se pudo leer el catálogo.');
        }
        $last = $after;
        foreach ($rows as $row) {
            $last = (int) $row['id_entity'];
        }
        return array('changes' => PrestaSeoData::proposals($rows, $resource), 'last_id' => (int) $last,
            'rows_read' => count($rows), 'shop' => $this->shop, 'created' => time());
    }

    private function requireTransactions($changes)
    {
        $tables = array(_DB_PREFIX_ . 'prestaseo_backup');
        foreach ($changes as $change) {
            $tables[] = $this->table($change['resource']);
        }
        foreach (array_unique($tables) as $table) {
            $status = $this->db->getRow("SHOW TABLE STATUS WHERE Name='" . pSQL($table) . "'", false);
            if (!$status || strcasecmp($status['Engine'], 'InnoDB') !== 0) {
                throw new Exception('Para escribir se requieren tablas InnoDB. No se ha convertido ninguna tabla.');
            }
        }
    }

    private function execute($sql)
    {
        if (!$this->db->execute($sql)) {
            throw new Exception('Error de base de datos; operación cancelada.');
        }
    }

    public function apply($plan, $employee, $restoreId = 0)
    {
        if ((int) $plan['shop'] !== $this->shop || empty($plan['changes']) || count($plan['changes']) > 50) {
            throw new Exception('El lote está vacío, es demasiado grande o pertenece a otra tienda.');
        }
        $changes = $plan['changes'];
        foreach ($changes as $change) {
            $this->table($change['resource']);
            if (!in_array($change['field'], array('meta_title', 'meta_description'), true)
                || (int) $change['id'] < 1 || (int) $change['language'] < 1
                || !is_string($change['before']) || !is_string($change['after'])) {
                throw new Exception('El lote contiene un cambio no permitido.');
            }
        }
        $this->requireTransactions($changes);
        $this->execute('START TRANSACTION');
        try {
            if ($restoreId) {
                $backup = $this->db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'prestaseo_backup` WHERE id_prestaseo_backup='
                    . (int) $restoreId . ' AND id_shop=' . $this->shop . ' FOR UPDATE', false);
                if (!$backup || $backup['status'] !== 'applied') {
                    throw new Exception('El respaldo ya se revirtió o no está disponible.');
                }
            }
            foreach ($changes as $change) {
                $where = 'id_' . $change['resource'] . '=' . (int) $change['id'] . ' AND id_lang='
                    . (int) $change['language'] . ' AND id_shop=' . $this->shop;
                $table = $this->table($change['resource']);
                $field = $change['field'];
                $row = $this->db->getRow('SELECT `' . $field . '` FROM `' . bqSQL($table) . '` WHERE ' . $where . ' FOR UPDATE', false);
                if (!$row || (string) $row[$field] !== $change['before']) {
                    throw new Exception('Hay cambios desde la vista previa. No se ha aplicado el lote; vuelve a revisarlo.');
                }
                $this->execute('UPDATE `' . bqSQL($table) . '` SET `' . $field . "`='" . pSQL($change['after'], true) . "' WHERE " . $where);
                $check = $this->db->getRow('SELECT `' . $field . '` FROM `' . bqSQL($table) . '` WHERE ' . $where, false);
                if (!$check || (string) $check[$field] !== $change['after']) {
                    throw new Exception('La base de datos no conservó el valor solicitado. Se cancela el lote.');
                }
            }
            if ($restoreId) {
                $this->execute('UPDATE `' . _DB_PREFIX_ . "prestaseo_backup` SET status='reverted' WHERE id_prestaseo_backup=" . (int) $restoreId);
                $id = (int) $restoreId;
            } else {
                $this->execute('INSERT INTO `' . _DB_PREFIX_ . 'prestaseo_backup` (id_shop,id_employee,date_add,status,payload) VALUES ('
                    . $this->shop . ',' . (int) $employee . ",NOW(),'applied','" . pSQL(PrestaSeoData::json($plan), true) . "')");
                $id = (int) $this->db->Insert_ID();
            }
            $this->execute('COMMIT');
        } catch (Exception $error) {
            $this->db->execute('ROLLBACK');
            throw $error;
        }
        if (class_exists('Cache')) {
            Cache::clean('Product::*');
            Cache::clean('Category::*');
        }
        return $id;
    }

    public function restore($id, $employee)
    {
        $row = $this->db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'prestaseo_backup` WHERE id_prestaseo_backup=' . (int) $id
            . ' AND id_shop=' . $this->shop, false);
        if (!$row || $row['status'] !== 'applied') {
            throw new Exception('Respaldo no disponible para esta tienda.');
        }
        $plan = json_decode($row['payload'], true);
        if (!is_array($plan) || empty($plan['changes'])) {
            throw new Exception('Respaldo no válido.');
        }
        foreach ($plan['changes'] as &$change) {
            $before = $change['before'];
            $change['before'] = $change['after'];
            $change['after'] = $before;
        }
        unset($change);
        return $this->apply($plan, $employee, $id);
    }

    public function history()
    {
        $rows = $this->db->executeS('SELECT id_prestaseo_backup,date_add,status FROM `' . _DB_PREFIX_
            . 'prestaseo_backup` WHERE id_shop=' . $this->shop . ' ORDER BY id_prestaseo_backup DESC LIMIT 20', true, false);
        return is_array($rows) ? $rows : array();
    }
}
