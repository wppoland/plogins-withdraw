=== Plogins Withdraw - Right of Withdrawal Button for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, withdrawal, right of withdrawal, eu, refund
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Requiere plugins: woocommerce
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Solicitudes de derecho de desistimiento total o parcial de la UE (Directiva 2023/2673) para pedidos de WooCommerce, con un registro en la administración y notificaciones por correo electrónico.

== Description ==

Plogins Withdraw añade una función de desistimiento sencilla a WooCommerce, alineada con la <strong>Directiva de la UE 2023/2673</strong> (el «botón de desistimiento», art. 11a): una forma clara de que los clientes declaren que desisten de un contrato a distancia, en su totalidad o por artículo, dentro del plazo legal de desistimiento.

Es un complemento de <strong>solicitud y registro</strong>: registra la declaración de desistimiento del cliente, envía por correo electrónico una confirmación al cliente y una notificación a la tienda, y hace un seguimiento del estado de la solicitud en la administración. Nunca mueve dinero por sí solo; procesa cualquier reembolso en la pantalla de pedido normal de WooCommerce, siguiendo el modelo legal en el que el cliente declara y el comerciante actúa.

= What it does =

* <strong>Formulario de desistimiento</strong>: el shortcode `[withdraw_form]` genera un formulario de dos pasos: busca un pedido por número y correo electrónico de facturación (también funciona para invitados), luego selecciona artículos y cantidades y envía la declaración de desistimiento.
* <strong>Desistimiento total o parcial</strong>: el cliente elige de cuántas unidades de cada artículo desea desistir.
* <strong>Botón de desistimiento en Mi cuenta</strong>: aparece un botón «Desistir de este pedido» debajo de los detalles del pedido y enlaza con tu página de desistimiento con el pedido ya rellenado.
* <strong>Comprobación del plazo de desistimiento</strong>: plazo configurable (mínimo legal de 14 días), medido desde la entrega (finalización del pedido) o, si nunca se completa, desde la fecha del pedido.
* <strong>Registro en la administración</strong>: la pantalla WooCommerce → Solicitudes de desistimiento enumera cada solicitud con sus artículos, cliente y estado (pendiente, aceptada, rechazada, procesada), y se puede filtrar por estado.
* <strong>Correos electrónicos</strong>: confirmación automática al cliente y notificación a la tienda.
* <strong>Texto de desistimiento modelo</strong>: un bloque editable en el formulario para el modelo legal de formulario de desistimiento (Anexo I.B).
* <strong>Apto para invitados</strong>: no se necesita cuenta; la búsqueda por número de pedido y correo electrónico de facturación funciona para pedidos de invitados.
* <strong>Compatible con HPOS + Blocks</strong>: lee los pedidos a través de la API de pedidos de WooCommerce.

= Requirements =

* WordPress 6.5 o posterior
* PHP 8.1 o posterior
* WooCommerce 8.0 o posterior

== Installation ==

1. Instala y activa WooCommerce.
2. Instala y activa Plogins Withdraw.
3. Crea una página y añade el shortcode `[withdraw_form]`.
4. Ve a <strong>WooCommerce → Desistimiento</strong>, selecciona esa página como la página del formulario de desistimiento, establece el plazo de desistimiento y los estados de pedido válidos, y ajusta el correo electrónico de notificación y los textos legales.

== Frequently Asked Questions ==

= Does it issue refunds automatically? =
No. Registra la solicitud de desistimiento y hace un seguimiento de su estado. Procesa cualquier reembolso en la pantalla de pedido normal de WooCommerce; el estado de la solicitud se gestiona en la pantalla Solicitudes de desistimiento.

= Does it work for guest orders? =
Sí. Los clientes buscan su pedido con el número de pedido y el correo electrónico de facturación usado en el pago, de modo que los invitados pueden enviar un desistimiento sin cuenta.

= Is this legal advice? =
No. El complemento proporciona la función técnica de desistimiento y textos legales editables. Configura el plazo y la redacción para que coincidan con tu jurisdicción y el modelo legal de formulario de desistimiento.

= Is it compatible with HPOS? =
Sí. Los pedidos se leen a través de la API de pedidos de WooCommerce, que es compatible con HPOS.

== Screenshots ==

1. El formulario de solicitud de desistimiento: búsqueda de pedidos por número y correo electrónico de facturación (apto para invitados), con el aviso del derecho de desistimiento de 14 días.
2. Selección de artículos: desistimiento total o parcial con cantidades por artículo, el texto de desistimiento modelo y la declaración.
3. Ajustes (WooCommerce → Desistimiento): plazo de desistimiento, página del formulario, estados válidos, correo electrónico de notificación y textos legales, con el registro de solicitudes.

== Translations ==

Plogins Withdraw incluye traducciones al polaco, alemán y español de la interfaz del complemento. El dominio de texto es `plogins-withdraw`, por lo que los paquetes de idioma de WordPress.org también pueden sobrescribir o ampliar estas traducciones incluidas.

== Changelog ==

= 1.0.2 =
* Se añadieron traducciones incluidas al polaco, alemán y español de la interfaz del complemento.

= 1.0.1 =
* Primera versión estable.

= 0.1.1 =
* Plugin Check: correcciones de escapado/saneamiento/i18n/higiene (los identificadores de nombres de tablas ahora se pasan mediante marcadores de posición %i en las consultas preparadas).

= 0.1.0 =
* Lanzamiento inicial: shortcode `[withdraw_form]` (búsqueda de pedidos + selección total/parcial de artículos), botón de desistimiento en Mi cuenta, plazo de desistimiento configurable y estados válidos, registro de solicitudes en la administración con estados, correos electrónicos al cliente y a la tienda, texto de desistimiento modelo editable. Compatible con HPOS + Blocks.

== Upgrade Notice ==

= 0.1.0 =
Lanzamiento inicial.
