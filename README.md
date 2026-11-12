# Presta SEO · módulo autónomo

Publica la identidad de una tienda PrestaShop en datos estructurados `Organization` / `OnlineStore`, y permite completar metadatos vacíos desde el panel de administración. **No requiere Python, API key, cron, servicios externos ni suscripciones.** No envía información fuera de la tienda.

## Instalación

1. Haz una copia de seguridad y pruébalo primero en una copia de tu tienda.
2. En **Módulos → Gestor de módulos → Subir un módulo**, selecciona `prestaseo.zip`.
3. Abre **Configurar**. Si hay multitienda, selecciona una tienda concreta.
4. Revisa nombre, contacto, logotipo y dirección propuestos desde la configuración de PrestaShop. Completa razón social y perfiles oficiales si corresponde. Puedes dejar vacíos los datos que no quieras publicar.
5. Comprueba que otro módulo o el tema no publique ya una entidad equivalente. Marca **Publicar datos estructurados en la portada** y guarda.
6. Vacía la caché de página/CDN si la usas. Revisa el código de la portada: debe aparecer `id="prestaseo-organization"` y el JSON correcto. Valida la URL con la [prueba de Google](https://search.google.com/test/rich-results) y, para comprobar el vocabulario, con [Schema.org Validator](https://validator.schema.org/). Comprueba también Search Console tras el rastreo.

Si instalas desde Git, la carpeta con `prestaseo.php` debe llamarse `prestaseo` y colocarse dentro de `/modules/`. El ZIP instalable tiene exactamente esa carpeta raíz. El paquete de código fuente incluye además pruebas y configuración de CI.

## Funciones

### Organization

- Hook `displayHeader`, exclusivamente en la portada (`php_self=index`).
- Tipos disponibles: `OnlineStore`, `Organization`, `Store`, `SportingGoodsStore`.
- Identificador estable por URL de tienda: `/#organization`.
- Nombre, razón social, logo, contacto, dirección y perfiles oficiales mediante `sameAs`.
- Datos propuestos desde la configuración local; la publicación queda desactivada hasta revisar y guardar.
- Vista previa del JSON-LD en el panel. Salida protegida para que un valor no pueda cerrar el elemento `script`.
- Configuración independiente por tienda. No intenta identificar automáticamente todos los marcados de terceros: el usuario debe revisar posibles duplicados.

### Metadatos de productos y categorías

- Selecciona recurso, idioma activo e ID inicial. Lee los siguientes **25 registros**, ordenados por ID, de esa tienda e idioma.
- Presenta propuestas solo para `meta_title` / `meta_description` vacíos. El título proviene del nombre; la descripción del texto corto, o del texto largo si no hay texto corto. No inventa características ni usa IA.
- Acorta borradores a 60/155 caracteres como criterio editorial; no son límites impuestos por Google. Revisa su calidad. El módulo no ofrece edición individual de las propuestas: si un lote contiene textos que no quieres publicar, no lo apliques y edita esos productos desde el catálogo.
- Aplica el lote únicamente al pulsar **Aplicar este lote revisado**. La vista previa se guarda en el servidor, está asociada al empleado y caduca a los 60 minutos. Solo hay una vista previa pendiente por tienda; una nueva sustituye a la anterior.
- Bloquea los registros y comprueba sus valores antes de escribir. Conserva otros campos, idiomas y tiendas. Actualización, comprobación de valores y respaldo forman parte de una transacción.
- Requiere **InnoDB** en las tablas afectadas y en la de respaldo. Si no se cumple, no escribe ni convierte tablas.
- Historial de los 20 últimos lotes en el panel. La reversión usa el respaldo y se detiene si alguien ha vuelto a cambiar los mismos campos.
- Para continuar el catálogo, introduce el último ID mostrado como punto inicial del siguiente lote. Un lote puede no tener propuestas aunque todavía haya registros posteriores.

No modifica precios, existencias, nombres de productos, URLs, descripciones comerciales ni metadatos ya rellenados. No corrige automáticamente títulos existentes que mencionan precios. Tampoco altera títulos generados por código propio del tema, ni añade Product/Offer, políticas de envío o devoluciones. La mejora principal de este módulo es la identidad de la empresa y el relleno revisado de campos vacíos.

## Compatibilidad y pruebas

**Rango objetivo declarado: PrestaShop 1.7.0.0 a 9.x.** Código escrito con sintaxis PHP 5.6 o posterior y APIs clásicas de Module, Configuration, Db y hooks. PHP debe ser el soportado por tu versión de PrestaShop. No se garantiza compatibilidad con versiones futuras 10.x.

El rango declarado permite instalar el módulo; **no significa que se haya certificado en todas esas versiones**. Se han ejecutado comprobaciones de sintaxis y pruebas locales de comportamiento con PHP 8.5.12, usando dobles de PrestaShop/base de datos. La CI del repositorio está preparada para comprobar sintaxis y lógica en PHP 5.6, 7.4, 8.1, 8.4 y 8.5. No se ha ejecutado todavía contra una instalación real de PrestaShop/MySQL.

Antes de usarlo en producción, verifica en tu versión:

- Instalación y configuración en el gestor de módulos; permisos del empleado.
- Portada con el tema real, activación/desactivación y validación de JSON-LD.
- Un lote pequeño en un idioma, otro idioma y, si procede, otra tienda.
- Aplicación, respaldo, reversión y conflicto con una edición posterior.
- Que los metadatos se reflejan tras vaciar caché/CDN y que el marcado de producto existente sigue igual.

El tema debe ejecutar el hook estándar `displayHeader`. Temas que no lo ejecuten necesitan una corrección propia. El módulo no puede purgar de forma universal las cachés de módulos externos o CDN.

Ejecutar pruebas locales:

```sh
php tests/run.php
php -l prestaseo.php
php -l classes/SeoData.php
php -l classes/MetaService.php
```

## Datos, permisos y desinstalación

Todas las acciones se ejecutan desde la configuración del módulo dentro del back office, con su token de administración y peticiones POST. No hay endpoints públicos de escritura ni credenciales adicionales. Los campos de salida HTML se escapan; los cambios SQL se limitan a los campos permitidos y a la tienda elegida.

Los respaldos de metadatos se guardan en `{prefijo}prestaseo_backup`, con tienda, empleado, fecha, estado y los valores anterior/nuevo. La configuración usa `PRESTASEO_SETTINGS`; la vista previa, `PRESTASEO_PLAN`. No se incluyen estas tablas ni datos reales en el repositorio público.

**Desinstalar no restaura los metadatos.** Desengancha el módulo y deja de publicar su JSON-LD, pero conserva configuración y tabla de respaldos para recuperarlos al reinstalar. Si quieres deshacer cambios, reviértelos antes de desinstalar. Para una eliminación completa, tras guardar tus copias y desinstalar, tu administrador puede borrar la tabla de respaldo y las claves `PRESTASEO_SETTINGS`/`PRESTASEO_PLAN` y sus relaciones en `configuration_shop`, si esa tabla existe en tu versión. No se ejecuta borrado de datos automático.

Una copia de la base de datos completa sigue siendo necesaria. El historial del módulo solo revierte los campos que él modificó. No incorpora descarga de respaldos ni restauración de toda la tienda.

## Publicación del proyecto

Puedes subir el contenido de esta carpeta a un repositorio público con licencia MIT. No hacen falta claves ni ficheros privados. El ZIP instalable excluye las pruebas y CI; el paquete de fuente los conserva. Este proyecto es independiente y no es un módulo oficial de PrestaShop ni una certificación de Google.

## Referencias

- [PrestaShop 1.7: hooks](https://devdocs.prestashop-project.org/1.7/modules/concepts/hooks/)
- [PrestaShop: compatibilidad declarada de módulos](https://devdocs.prestashop-project.org/9/modules/concepts/module-class/)
- [Google: Organization](https://developers.google.com/search/docs/appearance/structured-data/organization)

Publicar datos estructurados ayuda a describir la empresa, pero no garantiza mejoras de posiciones, resultados enriquecidos ni menciones en asistentes de IA.
