=== Plogins Withdraw - Right of Withdrawal Button for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, withdrawal, right of withdrawal, eu, refund
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Wymaga wtyczek: woocommerce
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pełne lub częściowe wnioski o unijne prawo do odstąpienia od umowy (Dyrektywa 2023/2673) dla zamówień WooCommerce, z dziennikiem w panelu administracyjnym i powiadomieniami e-mail.

== Description ==

Plogins Withdraw dodaje do WooCommerce łatwą funkcję odstąpienia od umowy, zgodną z <strong>unijną dyrektywą 2023/2673</strong> („przycisk odstąpienia”, art. 11a): przejrzysty sposób, w jaki klienci mogą zadeklarować odstąpienie od umowy zawartej na odległość, w całości lub dla poszczególnych pozycji, w ustawowym terminie odstąpienia.

To wtyczka typu <strong>zgłoś i zarejestruj</strong>: rejestruje oświadczenie klienta o odstąpieniu, wysyła potwierdzenie e-mail do klienta i powiadomienie do sklepu oraz śledzi status wniosku w panelu administracyjnym. Nigdy nie przenosi pieniędzy samodzielnie — każdy zwrot środków przetwarzasz na zwykłym ekranie zamówienia WooCommerce, zgodnie z modelem prawnym, w którym klient składa oświadczenie, a przedsiębiorca działa.

= What it does =

* <strong>Formularz odstąpienia</strong>: shortcode `[withdraw_form]` wyświetla dwuetapowy formularz: wyszukaj zamówienie po numerze i adresie e-mail rozliczeniowym (działa też dla gości), następnie wybierz pozycje i ilości i wyślij oświadczenie o odstąpieniu.
* <strong>Odstąpienie całkowite lub częściowe</strong>: klient wybiera, ile sztuk każdej pozycji chce zwrócić.
* <strong>Przycisk odstąpienia w Moim koncie</strong>: pod szczegółami zamówienia pojawia się przycisk „Odstąp od tego zamówienia”, który prowadzi do Twojej strony odstąpienia z wypełnionym zamówieniem.
* <strong>Sprawdzanie terminu odstąpienia</strong>: konfigurowalny okres (ustawowe minimum 14 dni), liczony od dostawy (realizacji zamówienia) lub, jeśli zamówienie nie zostało zrealizowane, od daty zamówienia.
* <strong>Dziennik w panelu administracyjnym</strong>: ekran WooCommerce → Wnioski o odstąpienie zawiera listę wszystkich wniosków wraz z pozycjami, klientem i statusem (oczekujący, zaakceptowany, odrzucony, przetworzony), z filtrowaniem według statusu.
* <strong>E-maile</strong>: automatyczne potwierdzenie dla klienta i powiadomienie do sklepu.
* <strong>Wzorcowy tekst odstąpienia</strong>: edytowalny blok na formularzu z ustawowym wzorem formularza odstąpienia (Załącznik I.B).
* <strong>Przyjazny dla gości</strong>: konto nie jest potrzebne; wyszukiwanie po numerze zamówienia i adresie e-mail rozliczeniowym działa dla zamówień gości.
* <strong>Zgodny z HPOS + Blocks</strong>: odczytuje zamówienia przez API zamówień WooCommerce.

= Requirements =

* WordPress 6.5 lub nowszy
* PHP 8.1 lub nowszy
* WooCommerce 8.0 lub nowszy

== Installation ==

1. Zainstaluj i włącz WooCommerce.
2. Zainstaluj i włącz Plogins Withdraw.
3. Utwórz stronę i dodaj shortcode `[withdraw_form]`.
4. Przejdź do <strong>WooCommerce → Odstąpienie</strong>, wybierz tę stronę jako stronę formularza odstąpienia, ustaw termin odstąpienia i kwalifikujące się statusy zamówień oraz dostosuj e-mail powiadomienia i teksty prawne.

== Frequently Asked Questions ==

= Does it issue refunds automatically? =
Nie. Rejestruje wniosek o odstąpienie i śledzi jego status. Każdy zwrot środków przetwórz na zwykłym ekranie zamówienia WooCommerce; statusem wniosku zarządzasz na ekranie Wnioski o odstąpienie.

= Does it work for guest orders? =
Tak. Klienci wyszukują swoje zamówienie po numerze zamówienia i adresie e-mail rozliczeniowym użytym podczas składania zamówienia, dzięki czemu goście mogą złożyć oświadczenie o odstąpieniu bez konta.

= Is this legal advice? =
Nie. Wtyczka zapewnia techniczną funkcję odstąpienia i edytowalne teksty prawne. Skonfiguruj termin i treść tak, aby odpowiadały Twojej jurysdykcji i ustawowemu wzorowi formularza odstąpienia.

= Is it compatible with HPOS? =
Tak. Zamówienia są odczytywane przez API zamówień WooCommerce, które jest zgodne z HPOS.

== Screenshots ==

1. Formularz wniosku o odstąpienie: wyszukiwanie zamówienia po numerze i adresie e-mail rozliczeniowym (przyjazny dla gości), z informacją o 14-dniowym prawie do odstąpienia.
2. Wybór pozycji: odstąpienie całkowite lub częściowe z ilościami dla poszczególnych pozycji, wzorcowy tekst odstąpienia i oświadczenie.
3. Ustawienia (WooCommerce → Odstąpienie): termin odstąpienia, strona formularza, kwalifikujące się statusy, e-mail powiadomienia i teksty prawne, z dziennikiem wniosków.

== Translations ==

Plogins Withdraw zawiera polskie, niemieckie i hiszpańskie tłumaczenia interfejsu wtyczki. Domena tekstowa to `plogins-withdraw`, więc pakiety językowe z WordPress.org mogą również nadpisywać lub rozszerzać te dołączone tłumaczenia.

== Changelog ==

= 1.0.2 =
* Dodano dołączone polskie, niemieckie i hiszpańskie tłumaczenia interfejsu wtyczki.

= 1.0.1 =
* Pierwsza stabilna wersja.

= 0.1.1 =
* Plugin Check: poprawki escapowania/sanityzacji/i18n/higieny (identyfikatory nazw tabel przekazywane teraz przez symbole zastępcze %i w przygotowanych zapytaniach).

= 0.1.0 =
* Pierwsze wydanie: shortcode `[withdraw_form]` (wyszukiwanie zamówienia + wybór pozycji w całości/części), przycisk odstąpienia w Moim koncie, konfigurowalny termin odstąpienia i kwalifikujące się statusy, dziennik wniosków w panelu administracyjnym ze statusami, e-maile do klienta i sklepu, edytowalny wzorcowy tekst odstąpienia. Zgodny z HPOS + Blocks.

== Upgrade Notice ==

= 0.1.0 =
Pierwsze wydanie.
