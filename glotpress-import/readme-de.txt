=== Plogins Withdraw - Right of Withdrawal Button for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, withdrawal, right of withdrawal, eu, refund
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Erfordert Plugins: woocommerce
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Vollständige oder teilweise EU-Widerrufsanträge (Richtlinie 2023/2673) für WooCommerce-Bestellungen, mit einem Admin-Protokoll und E-Mail-Benachrichtigungen.

== Description ==

Plogins Withdraw fügt WooCommerce eine einfache Widerrufsfunktion hinzu, die an die <strong>EU-Richtlinie 2023/2673</strong> angepasst ist (der „Widerrufs-Button“, Art. 11a): eine klare Möglichkeit für Kunden, innerhalb der gesetzlichen Widerrufsfrist zu erklären, dass sie einen Fernabsatzvertrag ganz oder für einzelne Artikel widerrufen.

Es handelt sich um ein <strong>Request-and-Log</strong>-Plugin: Es erfasst die Widerrufserklärung des Kunden, sendet eine Bestätigung per E-Mail an den Kunden und eine Benachrichtigung an den Shop und verfolgt den Status des Antrags im Adminbereich. Es bewegt niemals von selbst Geld — du bearbeitest jede Rückerstattung im normalen WooCommerce-Bestellbildschirm, entsprechend dem Rechtsmodell, bei dem der Kunde erklärt und der Händler handelt.

= What it does =

* <strong>Widerrufsformular</strong>: Der Shortcode `[withdraw_form]` stellt ein zweistufiges Formular dar: Suche eine Bestellung anhand der Nummer und der Rechnungs-E-Mail (funktioniert auch für Gäste), wähle dann Artikel und Mengen aus und sende die Widerrufserklärung ab.
* <strong>Vollständiger oder teilweiser Widerruf</strong>: Der Kunde wählt, wie viele Einheiten jedes Artikels er widerrufen möchte.
* <strong>Widerrufs-Button in „Mein Konto“</strong>: Unter den Bestelldetails erscheint ein Button „Von dieser Bestellung widerrufen“, der auf deine Widerrufsseite mit der bereits ausgefüllten Bestellung verlinkt.
* <strong>Prüfung der Widerrufsfrist</strong>: konfigurierbarer Zeitraum (gesetzlich mindestens 14 Tage), gemessen ab Lieferung (Abschluss der Bestellung) oder, falls nie abgeschlossen, ab dem Bestelldatum.
* <strong>Admin-Protokoll</strong>: Der Bildschirm WooCommerce → Widerrufsanträge listet jeden Antrag mit seinen Artikeln, dem Kunden und dem Status (ausstehend, akzeptiert, abgelehnt, verarbeitet) auf und lässt sich nach Status filtern.
* <strong>E-Mails</strong>: automatische Bestätigung an den Kunden und Benachrichtigung an den Shop.
* <strong>Muster-Widerrufstext</strong>: ein bearbeitbarer Block im Formular für das gesetzliche Muster-Widerrufsformular (Anhang I.B).
* <strong>Gastfreundlich</strong>: kein Konto erforderlich; die Suche nach Bestellnummer und Rechnungs-E-Mail funktioniert für Gastbestellungen.
* <strong>HPOS + Blocks kompatibel</strong>: liest Bestellungen über die WooCommerce-Bestell-API.

= Requirements =

* WordPress 6.5 oder höher
* PHP 8.1 oder höher
* WooCommerce 8.0 oder höher

== Installation ==

1. Installiere und aktiviere WooCommerce.
2. Installiere und aktiviere Plogins Withdraw.
3. Erstelle eine Seite und füge den Shortcode `[withdraw_form]` hinzu.
4. Gehe zu <strong>WooCommerce → Widerruf</strong>, wähle diese Seite als Seite des Widerrufsformulars aus, lege die Widerrufsfrist und die berechtigten Bestellstatus fest und passe die Benachrichtigungs-E-Mail und die Rechtstexte an.

== Frequently Asked Questions ==

= Does it issue refunds automatically? =
Nein. Es erfasst den Widerrufsantrag und verfolgt seinen Status. Bearbeite jede Rückerstattung im normalen WooCommerce-Bestellbildschirm; der Antragsstatus wird auf dem Bildschirm Widerrufsanträge verwaltet.

= Does it work for guest orders? =
Ja. Kunden suchen ihre Bestellung anhand der Bestellnummer und der beim Kauf verwendeten Rechnungs-E-Mail, sodass Gäste einen Widerruf ohne Konto einreichen können.

= Is this legal advice? =
Nein. Das Plugin stellt die technische Widerrufsfunktion und bearbeitbare Rechtstexte bereit. Konfiguriere die Frist und den Wortlaut passend zu deiner Rechtsordnung und dem gesetzlichen Muster-Widerrufsformular.

= Is it compatible with HPOS? =
Ja. Bestellungen werden über die WooCommerce-Bestell-API gelesen, die HPOS-kompatibel ist.

== Screenshots ==

1. Das Widerrufsantragsformular: Bestellsuche nach Nummer und Rechnungs-E-Mail (gastfreundlich), mit der 14-tägigen Widerrufsbelehrung.
2. Artikelauswahl: vollständiger oder teilweiser Widerruf mit Mengen pro Artikel, dem Muster-Widerrufstext und der Erklärung.
3. Einstellungen (WooCommerce → Widerruf): Widerrufsfrist, Formularseite, berechtigte Status, Benachrichtigungs-E-Mail und Rechtstexte, mit dem Antragsprotokoll.

== Translations ==

Plogins Withdraw enthält polnische, deutsche und spanische Übersetzungen der Plugin-Oberfläche. Die Textdomain ist `plogins-withdraw`, sodass Sprachpakete von WordPress.org diese gebündelten Übersetzungen ebenfalls überschreiben oder erweitern können.

== Changelog ==

= 1.0.2 =
* Gebündelte polnische, deutsche und spanische Übersetzungen der Plugin-Oberfläche hinzugefügt.

= 1.0.1 =
* Erste stabile Version.

= 0.1.1 =
* Plugin Check: Korrekturen bei Escaping/Bereinigung/i18n/Hygiene (Tabellennamen-Bezeichner werden jetzt über %i-Platzhalter in vorbereiteten Anweisungen übergeben).

= 0.1.0 =
* Erstveröffentlichung: Shortcode `[withdraw_form]` (Bestellsuche + vollständige/teilweise Artikelauswahl), Widerrufs-Button in „Mein Konto“, konfigurierbare Widerrufsfrist und berechtigte Status, Admin-Antragsprotokoll mit Status, Kunden- und Shop-E-Mails, bearbeitbarer Muster-Widerrufstext. HPOS + Blocks kompatibel.

== Upgrade Notice ==

= 0.1.0 =
Erstveröffentlichung.
