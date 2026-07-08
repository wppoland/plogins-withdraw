=== Plogins Withdraw - Right of Withdrawal Button for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, withdrawal, right of withdrawal, eu, refund
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Requiere complementos: woocommerce
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Solicitudes de derecho de desistimiento total o parcial de la UE (Directiva 2023/2673) para pedidos de WooCommerce, con un registro de administrador y notificaciones por correo electrónico.

== Description ==

Plogins Withdraw añade una función de retiro sencilla a WooCommerce, alineada con la <strong>Directiva de la UE 2023/2673</strong> (el "botón de retiro", Art. 11a): una forma clara para que los clientes declaren que desisten de un contrato a distancia, en su totalidad o por artículo, dentro del período de retiro legal.

Es un complemento de <strong>solicitud y registro</strong>: registra la declaración de retiro del cliente, envía por correo electrónico una confirmación al cliente y una notificación a la tienda, y rastrea el estado de la solicitud en el administrador. Nunca mueve dinero por sí solo, procesa cualquier reembolso en la pantalla de pedido normal de WooCommerce, coincidiendo con el modelo legal donde el cliente declara y el comerciante actúa.

= What it does =

* <strong>Formulario de retiro</strong>: el código abreviado `[withdraw_form]` genera un formulario de dos pasos: busque un pedido por número y correo electrónico de facturación (también funciona para invitados), luego seleccione artículos y cantidades y envíe la declaración de retiro.
* <strong>Retiro total o parcial</strong>: el cliente elige de cuántos de cada artículo desea retirar.
* <strong>Botón de retiro en Mi cuenta</strong>: aparece un botón "Retirar de este pedido" debajo de los detalles del pedido y enlaces a su página de retiro con el pedido precompletado.
* <strong>Verificación del período de desistimiento</strong>: período configurable (mínimo legal 14 días), medido desde la entrega (finalización del pedido) o, si nunca se completa, desde la fecha del pedido.
* <strong>Registro de administración</strong>: una pantalla de Solicitudes de retiro de WooCommerce → enumera cada solicitud con sus artículos, cliente y estado (pendiente, aceptado, rechazado, procesado), filtrable por estado.
* <strong>Emails</strong>: confirmación automática al cliente y notificación a la tienda.
* <strong>Texto del modelo de desistimiento</strong>: bloque editable en el formulario del modelo de formulario de desistimiento reglamentario (Anexo I.B).
* <strong>Apto para huéspedes</strong>: no se necesita cuenta; la búsqueda de número de pedido + correo electrónico de facturación funciona para pedidos de invitados.
* <strong>Compatible con HPOS + Blocks</strong>: lee pedidos a través de la API de pedidos de WooCommerce.

= Requirements =

*WordPress 6.5 o posterior
*PHP 8.1 o posterior
* WooCommerce 8.0 o posterior

== Installation ==

1. Instale y active WooCommerce.
2. Instale y active Plogins Withdraw.
3. Cree una página y añade el código corto `[withdraw_form]`.
4. Vaya a <strong>WooCommerce → Retiro</strong>, seleccione esa página como la página del formulario de retiro, establezca el período de retiro y los estados de los pedidos elegibles, y ajuste el correo electrónico de notificación y los textos legales.

== Frequently Asked Questions ==

= Does it issue refunds automatically? =
No. Esto registra la solicitud de retiro y rastrea su estado. Procese cualquier reembolso en la pantalla normal de pedidos de WooCommerce; el estado de la solicitud se gestiona en la pantalla Solicitudes de retiro.

= Does it work for guest orders? =
Sí. Los clientes buscan su pedido con el número de pedido y el correo electrónico de facturación utilizado al realizar el pago, para que puedan realizar un retiro sin una cuenta.

= Is this legal advice? =
No. El complemento proporciona la función de retiro técnico y textos legales editables. Configure el período y la redacción para que coincidan con su jurisdicción y el modelo de formulario de retiro legal.

= Is it compatible with HPOS? =
Sí. Los pedidos se leen a través de la API de pedidos de WooCommerce, que es compatible con HPOS.

== Screenshots ==

1. El formulario de solicitud de retiro: búsqueda de pedidos por número y correo electrónico de facturación (apto para huéspedes), con el aviso de derecho de retiro de 14 días.
2. Selección de artículos: retiro total o parcial con cantidades por artículo, el texto del modelo de retiro y la declaración.
3. Configuración (WooCommerce → Retiro): período de retiro, página del formulario, estados elegibles, correo electrónico de notificación y textos legales, con el registro de solicitudes.

== Changelog ==

= 1.0.1 =
* Primera versión estable.

= 0.1.1 =
* Comprobación de complementos: correcciones de escape/desinfección/i18n/higiene (los identificadores de nombres de tablas ahora se pasan a través de marcadores de posición %i en declaraciones preparadas).

= 0.1.0 =
* Lanzamiento inicial: código abreviado `[withdraw_form]` (búsqueda de pedidos + selección completa/parcial de artículos), botón de retiro de Mi cuenta, período de retiro configurable y estados elegibles, registro de solicitudes de administrador con estados, correos electrónicos de clientes y tiendas, texto de retiro de modelo editable. Compatible con bloques HPOS +.

== Upgrade Notice ==

= 0.1.0 =
Lanzamiento inicial.
