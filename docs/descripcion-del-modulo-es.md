# DHL Deutschepost 3.2.9 — Descripción del módulo

## Descripción breve

Genere etiquetas DHL Paket y Deutsche Post INTERNETMARKE desde pedidos de PrestaShop y gestione seguimiento, aduanas, manifiestos y devoluciones compatibles en el Back Office.

## Descripción completa

DHL Deutschepost vincula transportistas de PrestaShop con productos DHL y Deutsche Post contratados. El equipo prepara el envío en el pedido, crea y descarga etiquetas compatibles y guarda el número de seguimiento en el transportista del pedido. De forma opcional cambia el estado o envía el correo de tránsito incluido. DHL admite valores predeterminados de paquete, servicios adicionales, exportación y devoluciones; Deutsche Post controla productos INTERNETMARKE, salida PDF/PNG, posición, manifiesto y lista de expedición.

El módulo reduce la introducción repetida de datos entre tienda y portal del transportista. No sustituye el contrato, no calcula precios live de checkout y no habilita servicios no disponibles en la cuenta.

## Funciones principales

- DHL Parcel Shipping API 2.1 para remitente configurado en Alemania.
- Deutsche Post INTERNETMARKE REST en PDF o PNG.
- Asociación de carriers con productos/participaciones.
- Etiquetas DHL individuales y operaciones masivas compatibles.
- Tracking en el pedido y cron protegido para actualización.
- Estado/correo opcionales, aduanas por producto y exportación.
- Formatos, dimensiones y servicios DHL predeterminados.
- Packstation/Postfiliale con mapa Google opcional.
- Etiquetas de devolución y flujo RMA compatible.
- Manifiestos y listas cuando producto/API lo permiten.
- Configuración y activación consciente del contexto multitienda.
- Documentación local en seis idiomas.

## Uso y flujo habitual

Es apropiado para tiendas que expiden DHL Paket contratado desde Alemania o usan INTERNETMARKE. Se selecciona el contexto, se configuran cuenta/remitente, productos y carriers, se revisa el envío del pedido y se crea la etiqueta. El módulo guarda referencias y aplica únicamente el comportamiento de estado/correo elegido. Manifest, devolución y tracking se ejecutan cuando son necesarios y compatibles.

## Ventajas para el comercio

- Menos reintroducción de pedidos y direcciones.
- Etiquetas y seguimiento unidos al pedido.
- Valores controlados para almacén, productos, dimensiones y formato.
- DHL Paket y Deutsche Post en una sola interfaz.
- Controles explícitos de consentimiento, logs y salida.
- Guía y descripción offline en seis idiomas.

## Administración y multitienda

Los apartados **DHL settings**, **DHL DP settings**, **Information** y **DHL Manifest** organizan cuenta, productos/carriers, etiquetas, adicionales, devoluciones, remitente, COD e INTERNETMARKE. Inicio rápido enlaza documentación local, catálogo Silbersaiten, soporte de pago y email.

Los ajustes aceptan todas las tiendas, grupo o tienda individual y la activación sigue el contexto. Algunos recursos son globales/compartidos: formatos y versión PPL, productos descargados, aduanas por producto y tablas sin columna directa de tienda. Manifest/Information requieren una tienda. Por ello no se promete aislamiento físico completo por tienda.

## Privacidad, servicios y compatibilidad

Las solicitudes pueden transmitir identidad, direcciones, contenido/valores, referencias, email y teléfono. Si el consentimiento está desactivado, email y teléfono se envían a DHL por defecto. Se guardan credenciales, etiquetas, paquetes, tracking, RMA, aduanas, productos/precios, archivos y logs opcionales; desinstalar no los elimina automáticamente.

Puede contactar DHL Shipping, Returns, Token, Location Finder y Tracking; Deutsche Post INTERNETMARKE/Tracking; Silbersaiten para PPL; Google Maps si se activa; y correo/HP ePrint. La documentación es local.

El código declara PrestaShop desde `1.6` hasta la versión ejecutada; el CHANGELOG cita PrestaShop 8 y 9. No declara rango PHP, aunque registra correcciones para 7.2 y 8.4. El código actual no es compatible con PHP 8.5 por la firma de `SoapClient::__doRequest()`. Debe probarse el entorno exacto.

## Límites importantes

- País remitente DHL solo Alemania y API fija 2.1.
- Requiere cuentas live y productos contratados.
- Sin motor de tarifas de checkout, cola ni limpieza automática.
- Disponibilidad, precio, plazo y aceptación dependen del transportista.
- Multitienda soportada en configuración, pero no todos los datos se separan por tienda.

## Ventajas clave

- Etiquetas y tracking integrados en PrestaShop.
- DHL Paket e INTERNETMARKE juntos.
- Asociación carrier/producto y valores de almacén.
- Aduanas, servicios, manifiestos y devoluciones compatibles.
- Multitienda documentada sin promesas excesivas.
- Documentación offline en seis idiomas y enlaces de soporte.

## Tres textos muy cortos para la ficha

1. Cree etiquetas DHL Paket e INTERNETMARKE directamente desde pedidos de PrestaShop.
2. Conecte carriers de PrestaShop con DHL/Deutsche Post, tracking, aduanas y devoluciones.
3. Gestione envíos DHL y Deutsche Post contratados desde el Back Office de PrestaShop.
