# DHL Deutschepost 3.2.9 — Manual de usuario

## Empiece aquí

DHL Deutschepost conecta los pedidos de PrestaShop con los envíos para clientes comerciales de DHL Paket y con Deutsche Post INTERNETMARKE. Desde el Back Office permite crear etiquetas, guardar números de seguimiento, generar etiquetas por lotes, utilizar los manifiestos y devoluciones compatibles y facilitar direcciones Packstation/Postfiliale en el checkout.

El módulo no contrata servicios con DHL, no activa productos, no calcula tarifas en tiempo real durante el checkout y no determina qué prestaciones incluye su contrato. Antes de configurar el modo Live, obtenga de DHL los datos contractuales indicados a continuación.

## Datos necesarios antes de configurar

| Dato | Dónde se obtiene | Para qué sirve |
| --- | --- | --- |
| Contrato de cliente comercial DHL | Su contacto comercial de DHL | Envíos DHL Paket reales |
| Usuario y contraseña de GKP | Post & DHL Business Customer Portal | Autenticación en modo Live |
| EKP | Datos contractuales de GKP o documentación del contrato | Identificación de la cuenta |
| Números de facturación (*Abrechnungsnummern*) | Posiciones del contrato en GKP | Producto/procedimiento y participación |
| Activación de DHL Retoure | Contrato de DHL | Etiquetas de devolución, si se necesitan |
| Cuenta Portokasse | Deutsche Post | INTERNETMARKE, si se utiliza |

Si falta algún dato, solicítelo al contacto responsable del contrato. El módulo no puede averiguar ni activar números contractuales.

## Primer acceso al portal de DHL

1. Abra el **Post & DHL Business Customer Portal (GKP)** en <https://geschaeftskunden.dhl.de/>.
2. Use el usuario personal recibido con el contrato y la contraseña inicial o el enlace de restablecimiento.
3. Complete la activación o el cambio de contraseña que solicite el portal.
4. Si no recibió los datos, utilice **Forgot password/Passwort vergessen** o contacte con el administrador de la cuenta o con DHL. El acceso inicial suele enviarse después de tramitar el alta como cliente comercial.
5. Para una integración API en producción, DHL recomienda un **usuario de sistema para clientes comerciales**. Créelo o solicítelo cuando un administrador personal ya pueda entrar en GKP.

El usuario de sistema sirve para la API y no puede iniciar sesión en la interfaz web de GKP. Conserve al menos un usuario administrador personal. No introduzca un client ID o client secret de DHL Developer Portal: el módulo no dispone de esos campos y autentica las peticiones Live con las credenciales GKP configuradas.

## Dónde encontrar EKP, producto y participación

En GKP, abra la zona del contrato —normalmente **Vertragsdaten > Vertragspositionen**— y localice la **Abrechnungsnummer** de cada producto que vaya a utilizar. La navegación del portal puede cambiar; el dato decisivo es el número asignado a la posición contractual.

Un número de facturación de DHL Paket tiene 14 caracteres:

`1234567890 01 01`

| Parte | Longitud | Ejemplo | Introducción en el módulo |
| --- | --- | --- | --- |
| EKP | 10 caracteres | `1234567890` | Campo **EKP** |
| Procedimiento/producto | 2 caracteres | `01` | Seleccione el **producto DHL** correspondiente |
| Participación | 2 caracteres | `01` | Campo **Participation** del producto |

No pegue los 14 caracteres en Participation ni introduzca allí el código central del producto.

| Procedimiento | Producto disponible en el módulo |
| --- | --- |
| `01` | DHL Paket |
| `53` | DHL Paket International |
| `54` | DHL Europaket |
| `62` | DHL Kleinpaket |
| `66` | Warenpost International |

Añada únicamente productos incluidos en el contrato. Un producto puede tener varias participaciones. DHL puede asignar valores numéricos o alfanuméricos, aunque el campo independiente **Return participation** de esta versión admite exactamente dos dígitos.

Para devoluciones, copie la participación de la posición contractual DHL Retoure correspondiente. `01` es frecuente, pero no está garantizado. La documentación antigua hablaba de credenciales separadas para Retoure Portal y de un portal ID; la versión actual no contiene esos campos ni usa ese acceso antiguo.

## Requisitos y compatibilidad

- PrestaShop: el módulo declara 1.6 como mínimo y la versión de PrestaShop en ejecución como máximo. El CHANGELOG incluye cambios para PrestaShop 8 y 9.
- PHP: no existe un intervalo formal. Se documentan correcciones para PHP 7.2 y 8.4. El código actual no es compatible con PHP 8.5 por la firma de `SoapClient::__doRequest()`; utilice una versión compatible como PHP 8.4 hasta que se adapte.
- Entorno: se usan HTTPS saliente, cURL, JSON y mbstring. `logs`, `pdfs` y `data` deben permitir escritura.
- DHL: el país remitente disponible es Alemania (`DE`) y la API de envíos está fijada en `2.1`.
- Cuentas: Live necesita credenciales GKP y productos contratados; INTERNETMARKE necesita Portokasse.

Haga copia de seguridad, pruebe primero en staging, compruebe el acceso HTTPS y decida qué transportistas de PrestaShop corresponden a cada producto. En multitienda, seleccione el contexto correcto antes de guardar.

## Instalación y actualización

### Instalación nueva

1. En **Módulos > Gestor de módulos**, elija **Subir un módulo** y seleccione el ZIP de release.
2. Instale **DHL Deutschepost** y abra **Configurar**.
3. Siga la conexión inicial descrita debajo.

### Actualizar conservando la configuración

Suba el ZIP nuevo desde el gestor o despliegue el directorio `dhldp` completo sobre el existente. No desinstale antes. Ejecute la actualización del módulo cuando PrestaShop la ofrezca. La ruta de upgrade conserva las claves y datos existentes.

Vacíe la caché de PrestaShop y del navegador solo si después de actualizar todavía aparecen plantillas, traducciones o recursos antiguos.

## Primera conexión con DHL

1. Abra **DHL settings**.
2. Use **Sandbox** para aprender el proceso sin el contrato real; el módulo incorpora las credenciales de sandbox necesarias. Seleccione **Live** solo para producción.
3. En Live, introduzca el usuario GKP/API, su contraseña y el EKP de diez caracteres. Al guardar se comprueba la cuenta. Los asteriscos mostrados después solo indican que hay una contraseña almacenada.
4. En **DHL Products**, añada cada producto contratado: seleccione el producto por los dos caracteres centrales de la Abrechnungsnummer e introduzca los dos últimos como Participation.
5. En **Carriers**, asigne cada transportista de PrestaShop a la combinación correcta. Las acciones DHL solo aparecen en pedidos con un transportista asignado.
6. Introduzca el remitente separando calle y número. También puede usar una referencia de remitente GKP y copiarla exactamente.
7. Seleccione el formato de etiqueta adecuado. Para la primera prueba, deje desactivados el cambio automático de estado, la aceptación de avisos, las devoluciones inmediatas y los servicios adicionales.
8. Guarde y cree una etiqueta para un pedido de prueba.

Sandbox valida el flujo del módulo, no el contenido del contrato Live. Realice además una prueba controlada en Live.

## Crear la primera etiqueta DHL

1. Abra un pedido de prueba cuyo transportista esté asignado a DHL.
2. En el área DHL del pedido, elija **Generate label**.
3. Revise calle y número del destinatario, código postal, país, peso y dimensiones. Use **Update delivery address** para corregir la dirección antes de reintentar.
4. Para exportación, revise descripción, valor, país de origen y código arancelario de cada posición.
5. Seleccione solo servicios admitidos por producto y contrato y envíe la solicitud.
6. Abra el PDF y compruebe remitente, destinatario, producto, formato y número de envío.
7. Confirme que el seguimiento se guardó y que el estado o correo configurado se ejecutó una sola vez.

Separe siempre el número de la calle en su campo específico. Una dirección combinada es una causa frecuente de rechazo.

## Etiquetas por lotes

1. Abra la lista de pedidos de PrestaShop y seleccione pedidos con transportistas DHL válidos.
2. Ejecute la acción masiva **Generate DHL labels**.
3. Revise cada fallo: una dirección, un peso o un servicio no válido puede afectar solo a ese pedido.
4. Use **Print last labels** cuando necesite recuperar el último lote.

Empiece con pocos pedidos. El proceso masivo sigue necesitando direcciones, pesos y datos aduaneros correctos.

## Problemas habituales de la primera etiqueta

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| Se rechaza la cuenta al guardar | Usuario, contraseña o EKP Live incorrectos; usuario bloqueado | Compruebe el acceso personal a GKP, el usuario API por separado, restablezca su contraseña y compare el EKP con el contrato. |
| Funciona GKP pero no el módulo | Se confundieron usuario personal y usuario de sistema, o caducó la contraseña | Use las credenciales previstas para API; el usuario de sistema no entra en la web de GKP. |
| No aparece la acción DHL | El transportista no está asignado en esa tienda | Guarde de nuevo la asignación en el contexto del pedido. |
| Producto/participación rechazados | Se tomó la parte equivocada del número | En los 14 caracteres, posiciones 11–12 = producto y 13–14 = participación. |
| Dirección rechazada | Número unido a la calle, código postal o país no válido | Separe calle/número y revise el formato del destino. |
| Peso o dimensiones rechazados | Valor vacío, cero, conversión errónea o límite superado | Revise peso de productos y embalaje; conversión `1` para kg o `0.001` para gramos. |
| Falla una exportación | Faltan descripción, valor, origen o arancel | Complete cada posición; la validación admite aranceles de 6, 8 o 10 dígitos. |
| Servicio no disponible | No lo admite producto, destino o contrato | Retire el servicio o confirme la posición con DHL. |
| No se guarda el PDF | `pdfs` no permite escritura o falló la API | Revise permisos/espacio y active temporalmente el log enmascarado. |

## Decisiones de configuración importantes

- **Peso:** calcúlelo desde productos solo si los pesos están mantenidos; use `1` para kg o `0.001` para gramos y añada el embalaje realista.
- **Impresora:** seleccione su formato; `100x70mm` solo está previsto para DHL Kleinpaket y Warenpost International.
- **Estado y correo:** empiece sin automatismos y actívelos después de descartar mensajes duplicados.
- **Avisos:** no los acepte automáticamente durante las primeras pruebas.
- **Servicios adicionales:** routing, GoGreen, GoGreen Plus, edad, Premium y devoluciones dependen del contrato y pueden tener coste.
- **Privacidad:** con la confirmación desactivada, el módulo envía por defecto email y teléfono a DHL. Defina la base legal y la información al cliente.
- **Packstation/Postfiliale:** funcionan sin mapa. Google Maps es opcional y exige una clave restringida por dominio/API.
- **Devoluciones:** active la gestión ampliada junto con las devoluciones de PrestaShop y tras probar países permitidos. El envío inmediato está desactivado por defecto.
- **Logs:** actívelos solo para diagnóstico; los logs y PDF no se purgan automáticamente.

## Primera configuración de INTERNETMARKE

1. Regístrese o inicie sesión en **Portokasse**: <https://portokasse.deutschepost.de/portokasse/>. Un alta nueva puede requerir un código de activación enviado por correo postal.
2. Abra **DHL DP settings** e introduzca el usuario y contraseña de Portokasse/INTERNETMARKE.
3. En la primera conexión, Portokasse puede pedir autorización permanente para la aplicación comercial. Revísela en **Meine Daten > Geschäftsanwendungen**.
4. Recupere los formatos de página y actualice la lista PPL si es necesario.
5. Asigne solo transportistas destinados a Deutsche Post, elija producto, salida `pdf` o `png` y complete el remitente.
6. Para hojas PDF, ajuste página, fila y columna e imprima una prueba.

Si falla el acceso, pruebe directamente Portokasse y restablezca allí la contraseña. Ver un producto en el módulo no garantiza que la cuenta pueda comprarlo ni su precio actual.

## Multitienda

Las páginas admiten los contextos todas las tiendas, grupo y tienda individual, con la herencia normal de PrestaShop. Guarde valores generales en todas las tiendas y credenciales, remitentes y asignaciones diferentes en cada tienda concreta.

Los ajustes operativos suelen leerse para la tienda del pedido. Sin embargo, las seis tablas del módulo no tienen `id_shop` directo; aduanas de producto y lista Deutsche Post se comparten, y versión PPL/formatos son globales. Manifest e Information exigen contexto de tienda individual. Pruebe una etiqueta en cada tienda operativa.

## Datos, privacidad, servicios y límites

Las credenciales y opciones se guardan en configuración de PrestaShop; etiquetas, paquetes, consentimientos, aduanas e INTERNETMARKE en seis tablas `dhldp_*`; archivos en `pdfs`; logs opcionales en `logs`; y productos Deutsche Post en `data/ppl.csv`.

Según el servicio, se pueden transmitir a DHL o Deutsche Post nombres, direcciones, contenido, valores, referencias, email y teléfono. Integraciones opcionales: DHL Location Finder, Google Maps, `prestamodule.silberserver.de` para PPL, correo de la tienda y HP ePrint. La documentación local no necesita recursos externos.

El remitente DHL actual solo puede estar en Alemania, la API está fijada en 2.1 y no hay tarifas de checkout ni worker en segundo plano. `cron_track.php` actualiza el seguimiento con la clave secreta; proteja la URL. El flujo antiguo de remitente austríaco, el login separado de Retoure Portal y la URL manual de tracking no corresponden a esta versión.

La desinstalación quita pestañas y hooks, pero conserva tablas, configuración, etiquetas, logs y lista de productos. Elimínelos manualmente solo si desea un borrado completo y después de una copia de seguridad.

## Lista de comprobación antes de producción

- La cuenta Live se valida con el usuario API y EKP correctos.
- Cada transportista apunta al producto y participación contratados.
- Se revisaron remitente, calle/número, conversión de peso y formato de impresión.
- Se probaron por separado una etiqueta nacional, una exportación necesaria y las devoluciones.
- Estados, emails y consentimiento coinciden con la política de la tienda.
- Cada contexto multitienda tiene una prueba propia.
- El log de diagnóstico queda desactivado.

