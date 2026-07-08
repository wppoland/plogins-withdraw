=== Plogins Withdraw - Right of Withdrawal Button for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, withdrawal, right of withdrawal, eu, refund
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Erfordert Plugins: woocommerce
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Vollständige oder teilweise EU-Widerrufsanfragen (Richtlinie 2023/2673) für WooCommerce-Bestellungen, mit Administratorprotokoll und E-Mail-Benachrichtigungen.

== Description ==

Plogins Withdraw fügt WooCommerce eine einfache Widerrufsfunktion hinzu, die an die <strong>EU-Richtlinie 2023/2673</strong> angepasst ist (der „Widerrufs-Button“, Art. 11a): eine klare Möglichkeit für Kunden, innerhalb der gesetzlichen Widerrufsfrist zu erklären, dass sie von einem Fernabsatzvertrag ganz oder für einzelne Artikel zurücktreten.

Es handelt sich um ein <strong>Request-and-Log</strong>-Plugin: Es erfasst die Widerrufserklärung des Kunden, sendet eine Bestätigung per E-Mail an den Kunden und eine Benachrichtigung an den Shop und verfolgt den Status der Anfrage im Admin. Es wird niemals Geld von alleine bewegt, Du verarbeitest etwaige Rückerstattungen im normalen WooCommerce-Bestellbildschirm, entsprechend dem Rechtsmodell, bei dem der Kunde eine Erklärung abgibt und der Händler handelt.

= What it does =

* <strong>Widerrufsformular</strong>: Der Shortcode „[withdraw_form]“ stellt ein zweistufiges Formular dar: Suche eine Bestellung anhand der Nummer und der Rechnungs-E-Mail-Adresse (funktioniert auch für Gäste), wähle dann Artikel und Mengen aus und sende die Widerrufserklärung ab.
* <strong>Vollständige oder teilweise Auszahlung</strong>: Der Kunde wählt, wie viele Artikel er zurückgeben möchte.
* <strong>Abhebungsschaltfläche in „Mein Konto“</strong>: Die Schaltfläche „Von dieser Bestellung zurückziehen“ erscheint unter den Bestelldetails und führt zu deiner Abhebungsseite, auf der die Bestellung bereits ausgefüllt ist.
* <strong>Widerrufsfristenprüfung</strong>: konfigurierbarer Zeitraum (gesetzlich mindestens 14 Tage), gemessen ab Lieferung (Bestellungsabschluss) oder, falls nie erfolgt, ab Bestelldatum.
* <strong>Admin-Protokoll</strong>: Auf dem Bildschirm „WooCommerce → Auszahlungsanfragen“ werden alle Anfragen mit ihren Artikeln, Kunden und Status (ausstehend, akzeptiert, abgelehnt, verarbeitet) aufgeführt und nach Status gefiltert.
* <strong>E-Mails</strong>: automatische Bestätigung an den Kunden und Benachrichtigung an den Shop.
* <strong>Muster-Widerrufstext</strong>: ein editierbarer Block auf dem Formular für das gesetzliche Muster-Widerrufsformular (Anhang I.B).
* <strong>Gastfreundlich</strong>: kein Konto erforderlich; Die Suche nach Bestellnummer und Rechnungs-E-Mail funktioniert für Gastbestellungen.
* <strong>HPOS + Blocks kompatibel</strong>: Liest Bestellungen über die WooCommerce-Bestell-API.

= Requirements =

* WordPress 6.5 oder höher
* PHP 8.1 oder höher
* WooCommerce 8.0 oder höher

== Installation ==

1. Installieren und aktiviere WooCommerce.
2. Installieren und aktiviere Plugins Withdraw.
3. Erstelle eine Seite und füge den Shortcode „[withdraw_form]“ hinzu.
4. Gehe zu <strong>WooCommerce → Widerruf</strong>, wähle diese Seite als Widerrufsformularseite aus, lege die Widerrufsfrist und die berechtigten Bestellstatus fest und passe die Benachrichtigungs-E-Mail und die Rechtstexte an.

== Frequently Asked Questions ==

= Does it issue refunds automatically? =
Nein. Dadurch wird der Auszahlungsantrag aufgezeichnet und sein Status verfolgt. Bearbeite etwaige Rückerstattungen im normalen WooCommerce-Bestellbildschirm. Der Antragsstatus wird auf dem Bildschirm „Auszahlungsanträge“ verwaltet.

= Does it work for guest orders? =
Ja. Kunden können ihre Bestellung anhand der Bestellnummer und der Rechnungs-E-Mail-Adresse an der Kasse nachschlagen, sodass Gäste ohne Konto eine Auszahlung vornehmen können.

= Is this legal advice? =
Nein. Das Plugin bietet die technische Widerrufsfunktion und editierbare Rechtstexte. Konfiguriere die Frist und den Wortlaut entsprechend deiner Gerichtsbarkeit und dem gesetzlichen Muster-Widerrufsformular.

= Is it compatible with HPOS? =
Ja. Bestellungen werden über die WooCommerce-Bestell-API gelesen, die HPOS-kompatibel ist.

== Screenshots ==

1. Das Widerrufsantragsformular: Bestellsuche nach Nummer und Rechnungs-E-Mail (gastfreundlich), mit der 14-tägigen Widerrufsbelehrung.
2. Artikelauswahl: Vollständiger oder teilweiser Widerruf mit Stückzahl pro Artikel, dem Muster-Widerrufstext und der Erklärung.
3. Einstellungen (WooCommerce → Widerrufsrecht): Widerrufsfrist, Formularseite, Berechtigungsstatus, Benachrichtigungs-E-Mail und Rechtstexte, mit dem Anfrageprotokoll.

== Changelog ==

= 1.0.1 =
* Erste stabile Version.

= 0.1.1 =
* Plugin-Prüfung: Escaping/sanitisation/i18n/hygiene-Korrekturen (Tabellennamen-Bezeichner werden jetzt über %i-Platzhalter in vorbereiteten Anweisungen übergeben).

= 0.1.0 =
* Erstveröffentlichung: Shortcode „[withdraw_form]“ (Auftragssuche + vollständige/teilweise Artikelauswahl), Auszahlungsschaltfläche „Mein Konto“, konfigurierbare Auszahlungsfrist und berechtigte Status, Admin-Anfrageprotokoll mit Status, Kunden- und Shop-E-Mails, bearbeitbarer Modell-Auszahlungstext. HPOS + Blocks kompatibel.

== Upgrade Notice ==

= 0.1.0 =
Erstveröffentlichung.
