<?php
/** Pure data helpers, compatible with PHP 5.6+. MIT license. */
class PrestaSeoData
{
    public static function clean($text)
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8')));
    }

    public static function shorten($text, $limit)
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars)) {
            throw new Exception('Texto UTF-8 no válido.');
        }
        if (count($chars) <= $limit) {
            return $text;
        }
        $part = implode('', array_slice($chars, 0, $limit - 1));
        $space = strrpos($part, ' ');
        if ($space !== false && $space > strlen($part) / 2) {
            $part = substr($part, 0, $space);
        }
        return rtrim($part) . '…';
    }

    public static function validUrl($url)
    {
        $parts = parse_url($url);
        return filter_var($url, FILTER_VALIDATE_URL) && isset($parts['scheme'])
            && in_array(strtolower($parts['scheme']), array('http', 'https'), true)
            && !isset($parts['user']) && !isset($parts['pass']);
    }

    public static function validate($input)
    {
        $out = array();
        $fields = array('name', 'legal_name', 'logo', 'telephone', 'email', 'street_address',
            'address_locality', 'postal_code', 'address_country', 'same_as', 'type');
        foreach ($fields as $field) {
            if (isset($input[$field]) && !is_string($input[$field])) {
                throw new Exception('Formato no válido: ' . $field);
            }
            $out[$field] = isset($input[$field]) ? trim($input[$field]) : '';
            if (strlen($out[$field]) > ($field === 'same_as' ? 4000 : 1000)
                || preg_match('/[<>\x00-\x08\x0b\x0c\x0e-\x1f]/', $out[$field])) {
                throw new Exception('Contenido no válido: ' . $field);
            }
        }
        if ($out['name'] === '') {
            throw new Exception('El nombre de la tienda es obligatorio.');
        }
        if (!in_array($out['type'], array('Organization', 'OnlineStore', 'Store', 'SportingGoodsStore'), true)) {
            throw new Exception('Tipo de organización no válido.');
        }
        if ($out['logo'] !== '' && !self::validUrl($out['logo'])) {
            throw new Exception('La URL del logotipo no es válida.');
        }
        if ($out['email'] !== '' && !filter_var($out['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo no es válido.');
        }
        if ($out['address_country'] !== '' && !preg_match('/^[A-Z]{2}$/', $out['address_country'])) {
            throw new Exception('Usa el código ISO del país, por ejemplo ES.');
        }
        $links = preg_split('/\r\n|\r|\n/', $out['same_as'], -1, PREG_SPLIT_NO_EMPTY);
        if (count($links) > 20) {
            throw new Exception('Máximo 20 perfiles oficiales.');
        }
        foreach ($links as $link) {
            if (!self::validUrl(trim($link))) {
                throw new Exception('URL no válida en los perfiles oficiales.');
            }
        }
        $out['enabled'] = !empty($input['enabled']) ? 1 : 0;
        return $out;
    }

    public static function schema($settings, $url)
    {
        $data = array('@context' => 'https://schema.org', '@type' => $settings['type'],
            '@id' => rtrim($url, '/') . '/#organization', 'name' => $settings['name'],
            'url' => rtrim($url, '/') . '/');
        foreach (array('legal_name' => 'legalName', 'logo' => 'logo', 'telephone' => 'telephone', 'email' => 'email') as $key => $property) {
            if (!empty($settings[$key])) {
                $data[$property] = $settings[$key];
            }
        }
        if (!empty($settings['same_as'])) {
            $links = preg_split('/\r\n|\r|\n/', $settings['same_as'], -1, PREG_SPLIT_NO_EMPTY);
            $data['sameAs'] = array_values(array_unique(array_map('trim', $links)));
        }
        $address = array('@type' => 'PostalAddress');
        foreach (array('street_address' => 'streetAddress', 'address_locality' => 'addressLocality',
            'postal_code' => 'postalCode', 'address_country' => 'addressCountry') as $key => $property) {
            if (!empty($settings[$key])) {
                $address[$property] = $settings[$key];
            }
        }
        if (count($address) > 1) {
            $data['address'] = $address;
        }
        return $data;
    }

    public static function json($value)
    {
        $json = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception('No se pudo codificar el contenido como JSON.');
        }
        return $json;
    }

    public static function proposals($rows, $resource)
    {
        $out = array();
        foreach ($rows as $row) {
            $description = !empty($row['description_short']) ? self::clean($row['description_short']) : '';
            if ($description === '') {
                $description = self::clean($row['description']);
            }
            $sources = array('meta_title' => self::clean($row['name']), 'meta_description' => $description);
            foreach ($sources as $field => $source) {
                if (trim((string) $row[$field]) === '' && $source !== '') {
                    $out[] = array('resource' => $resource, 'id' => (int) $row['id_entity'],
                        'language' => (int) $row['id_lang'], 'field' => $field,
                        'before' => (string) $row[$field], 'after' => self::shorten($source, $field === 'meta_title' ? 60 : 155));
                }
            }
        }
        return $out;
    }
}
