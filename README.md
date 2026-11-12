# 🛒 Presta SEO

Módulo para PrestaShop que publica la identidad de la tienda en datos estructurados `Organization` / `OnlineStore` y permite rellenar los metadatos vacíos de productos y categorías desde el panel de administración.

🇪🇸 [Español](#-español) · 🇬🇧 [English](#-english)

---

# 🇪🇸 Español

## 📦 Instalación

1. Haz una copia de seguridad y pruébalo primero en una copia de tu tienda.
2. En **Módulos → Gestor de módulos → Subir un módulo**, selecciona `prestaseo.zip`.
3. Abre **Configurar**. Si tienes multitienda, selecciona una tienda concreta.
4. Revisa el nombre, contacto, logotipo y dirección, que se toman de la configuración de PrestaShop. Añade la razón social y los perfiles oficiales si los tienes. Deja vacío lo que no quieras publicar.
5. Comprueba que el tema u otro módulo no publiquen ya los mismos datos. Marca **Publicar datos estructurados en la portada** y guarda.
6. Vacía la caché de página o CDN si la usas. En el código de la portada debe aparecer `id="prestaseo-organization"` con el JSON correcto. Valida la URL con la [prueba de resultados enriquecidos de Google](https://search.google.com/test/rich-results) y con el [validador de Schema.org](https://validator.schema.org/). Revisa también Search Console cuando Google vuelva a rastrear la página.

Si instalas desde Git, la carpeta que contiene `prestaseo.php` debe llamarse `prestaseo` y estar dentro de `/modules/`.

## ⚙️ Funciones

### 🏢 Organization

- Hook `displayHeader`, solo en la portada (`php_self=index`).
- Tipos disponibles: `OnlineStore`, `Organization`, `Store`, `SportingGoodsStore`.
- Identificador fijo por URL de tienda: `/#organization`.
- Nombre, razón social, logotipo, contacto, dirección y perfiles oficiales (`sameAs`).
- Los datos se proponen desde la configuración de la tienda y no se publican hasta que los revisas y guardas.
- Vista previa del JSON-LD en el panel. La salida está escapada para que ningún valor pueda cerrar la etiqueta `script`.
- Configuración independiente para cada tienda.
- El módulo no detecta marcados de terceros: revisa tú si hay duplicados.

### 📝 Metadatos de productos y categorías

- Eliges recurso, idioma e ID inicial, y el módulo lee los siguientes **25 registros** ordenados por ID para esa tienda e idioma.
- Solo propone textos para `meta_title` y `meta_description` vacíos. El título sale del nombre y la descripción del resumen (o de la descripción larga si no hay resumen). No usa IA ni se inventa nada.
- Los borradores se recortan a 60 y 155 caracteres. Es un criterio editorial, no un límite de Google. Las propuestas no se pueden editar una a una: si un lote tiene textos que no te convencen, no lo apliques y edita esos productos desde el catálogo.
- El lote solo se guarda al pulsar **Aplicar este lote revisado**. La vista previa queda en el servidor, ligada al empleado, y caduca a los 60 minutos. Hay una única vista previa pendiente por tienda.
- Antes de escribir bloquea los registros y comprueba que no han cambiado. No toca otros campos, idiomas ni tiendas. La actualización y el respaldo van en la misma transacción.
- Necesita tablas **InnoDB**. Si no lo son, no escribe nada ni convierte tablas.
- Historial de los 20 últimos lotes en el panel. Puedes revertir un lote; la reversión se detiene si alguien ha vuelto a cambiar esos campos.
- Para seguir con el catálogo, usa el último ID mostrado como inicio del siguiente lote. Un lote puede salir sin propuestas aunque queden registros detrás.

No modifica precios, existencias, nombres, URLs, descripciones ni metadatos que ya estén rellenos. No añade marcado Product/Offer ni políticas de envío o devolución, y no cambia títulos que genere el propio tema.

## 🧩 Compatibilidad

- **PrestaShop 1.7.0.0 a 9.x.** No se garantiza en futuras versiones 10.x.
- Código compatible con PHP 5.6 o superior. Usa la versión de PHP que admita tu PrestaShop.
- El tema debe ejecutar el hook estándar `displayHeader`.

Que el módulo se instale en esas versiones no quiere decir que se haya probado en todas. Las pruebas automáticas usan dobles de PrestaShop y de la base de datos; todavía no se ha probado sobre una instalación real con MySQL. Antes de usarlo en producción comprueba en tu tienda:

- Instalación, configuración y permisos del empleado.
- La portada con tu tema y la validación del JSON-LD.
- Un lote pequeño en un idioma, luego en otro y, si tienes, en otra tienda.
- Aplicar, revertir y qué pasa si alguien edita el producto entre medias.
- Que los cambios se ven tras vaciar caché y CDN y que el marcado de producto sigue igual.

## 🧪 Pruebas

```sh
php tests/run.php
php -l prestaseo.php
php -l classes/SeoData.php
php -l classes/MetaService.php
```

## 🔐 Datos, permisos y desinstalación

Todo se hace desde la configuración del módulo en el back office, con el token de administración y peticiones POST. No hay endpoints públicos ni hace falta ninguna credencial adicional.

Los respaldos se guardan en la tabla `{prefijo}prestaseo_backup` (tienda, empleado, fecha, estado y valores anterior y nuevo). La configuración está en `PRESTASEO_SETTINGS` y la vista previa en `PRESTASEO_PLAN`.

**Desinstalar no restaura los metadatos.** El módulo deja de publicar el JSON-LD, pero conserva la configuración y los respaldos por si lo vuelves a instalar. Si quieres deshacer cambios, revierte los lotes antes de desinstalar. Para borrarlo todo, elimina a mano la tabla de respaldos y las claves `PRESTASEO_SETTINGS` y `PRESTASEO_PLAN`.

Haz siempre copia completa de la base de datos: el historial del módulo solo revierte los campos que él mismo ha cambiado.

## 📜 Licencia

Publicado con **licencia MIT**, una licencia de software libre. Cualquiera puede descargarlo, usarlo, copiarlo, modificarlo y distribuirlo gratis, también en proyectos comerciales, siempre que conserve el aviso de autor y la licencia. Texto completo en [LICENSE](LICENSE).

## ⚠️ Sin garantía

Este software se entrega **tal cual, sin garantía de ningún tipo**. El autor no se hace responsable de ningún daño, pérdida de datos, problema de posicionamiento ni de cualquier otro perjuicio derivado de su uso. Lo usas bajo tu propia responsabilidad.

Presta SEO es un proyecto independiente: no es un módulo oficial de PrestaShop ni está avalado por Google. Publicar datos estructurados no garantiza mejores posiciones ni resultados enriquecidos.

## 👤 Autor

**Miguel Angel García** · [eXtremaNET Consultores](https://www.extremanet.com) · 2026<br>
✉️ magarcia@extremanet.com

## 🔗 Referencias

- [PrestaShop 1.7: hooks](https://devdocs.prestashop-project.org/1.7/modules/concepts/hooks/)
- [PrestaShop: clase Module y compatibilidad](https://devdocs.prestashop-project.org/9/modules/concepts/module-class/)
- [Google: Organization](https://developers.google.com/search/docs/appearance/structured-data/organization)

---

# 🇬🇧 English

## 📦 Installation

1. Back up your shop and try the module on a copy first.
2. Go to **Modules → Module Manager → Upload a module** and select `prestaseo.zip`.
3. Open **Configure**. On a multistore setup, pick a specific shop.
4. Check the name, contact details, logo and address, which are taken from the PrestaShop settings. Add the legal name and official profiles if you have them. Leave empty anything you don't want to publish.
5. Make sure your theme or another module isn't already publishing the same data. Tick **Publicar datos estructurados en la portada** (publish structured data on the home page) and save.
6. Clear your page cache or CDN if you use one. The home page source should contain `id="prestaseo-organization"` with the right JSON. Validate the URL with [Google's Rich Results Test](https://search.google.com/test/rich-results) and the [Schema.org Validator](https://validator.schema.org/). Check Search Console as well once Google recrawls the page.

If you install from Git, the folder containing `prestaseo.php` must be named `prestaseo` and live inside `/modules/`.

## ⚙️ Features

### 🏢 Organization

- `displayHeader` hook, home page only (`php_self=index`).
- Available types: `OnlineStore`, `Organization`, `Store`, `SportingGoodsStore`.
- Fixed identifier per shop URL: `/#organization`.
- Name, legal name, logo, contact, address and official profiles (`sameAs`).
- Data is suggested from the shop settings and nothing is published until you review and save it.
- JSON-LD preview in the admin panel. Output is escaped so no value can close the `script` tag.
- Separate settings for each shop.
- The module does not detect third-party markup: check for duplicates yourself.

### 📝 Product and category metadata

- Choose the resource, language and starting ID; the module reads the next **25 records** by ID for that shop and language.
- It only suggests text for empty `meta_title` and `meta_description` fields. The title comes from the name and the description from the summary (or the long description if there is no summary). No AI, nothing made up.
- Drafts are trimmed to 60 and 155 characters. That is an editorial choice, not a Google limit. Suggestions can't be edited one by one: if a batch has text you don't like, don't apply it and edit those products from the catalog instead.
- A batch is only saved when you click **Aplicar este lote revisado** (apply this reviewed batch). The preview is stored on the server, tied to the employee, and expires after 60 minutes. There is one pending preview per shop.
- Before writing, records are locked and checked for changes. Other fields, languages and shops are left alone. The update and the backup run in the same transaction.
- Requires **InnoDB** tables. If they aren't, nothing is written and no tables are converted.
- History of the last 20 batches in the panel. You can roll back a batch; the rollback stops if someone has changed those fields again.
- To continue through the catalog, use the last ID shown as the start of the next batch. A batch may have no suggestions even if there are more records after it.

It does not change prices, stock, names, URLs, descriptions or metadata that is already filled in. It does not add Product/Offer markup or shipping/return policies, and it does not change titles generated by the theme itself.

## 🧩 Compatibility

- **PrestaShop 1.7.0.0 to 9.x.** Not guaranteed on future 10.x releases.
- Code is compatible with PHP 5.6 or later. Use the PHP version your PrestaShop supports.
- Your theme must run the standard `displayHeader` hook.

Being installable on those versions doesn't mean it has been tested on all of them. The automated tests use PrestaShop and database stubs; it has not yet been tested on a real install with MySQL. Before using it in production, check on your shop:

- Installation, configuration and employee permissions.
- The home page with your theme and JSON-LD validation.
- A small batch in one language, then another and, if you have one, another shop.
- Applying, rolling back, and what happens if someone edits the product in between.
- That changes show up after clearing cache and CDN, and that product markup is unchanged.

## 🧪 Tests

```sh
php tests/run.php
php -l prestaseo.php
php -l classes/SeoData.php
php -l classes/MetaService.php
```

## 🔐 Data, permissions and uninstalling

Everything runs from the module settings in the back office, using the admin token and POST requests. There are no public endpoints and no extra credentials are needed.

Backups are stored in the `{prefix}prestaseo_backup` table (shop, employee, date, status, old and new values). Settings live in `PRESTASEO_SETTINGS` and the preview in `PRESTASEO_PLAN`.

**Uninstalling does not restore metadata.** The module stops publishing the JSON-LD but keeps its settings and backups in case you reinstall. If you want to undo changes, roll back the batches before uninstalling. To remove everything, manually delete the backup table and the `PRESTASEO_SETTINGS` and `PRESTASEO_PLAN` keys.

Always keep a full database backup: the module history only rolls back the fields it changed itself.

## 📜 License

Released under the **MIT License**, a free software license. Anyone can download, use, copy, modify and distribute it free of charge, including in commercial projects, as long as the copyright and license notice are kept. Full text in [LICENSE](LICENSE).

## ⚠️ No warranty

This software is provided **as is, without warranty of any kind**. The author accepts no liability for any damage, data loss, ranking issues or any other harm arising from its use. You use it at your own risk.

Presta SEO is an independent project: it is not an official PrestaShop module and is not endorsed by Google. Publishing structured data does not guarantee better rankings or rich results.

## 👤 Author

**Miguel Angel García** · [eXtremaNET Consultores](https://www.extremanet.com) · 2026<br>
✉️ magarcia@extremanet.com

## 🔗 References

- [PrestaShop 1.7: hooks](https://devdocs.prestashop-project.org/1.7/modules/concepts/hooks/)
- [PrestaShop: Module class and compatibility](https://devdocs.prestashop-project.org/9/modules/concepts/module-class/)
- [Google: Organization](https://developers.google.com/search/docs/appearance/structured-data/organization)
