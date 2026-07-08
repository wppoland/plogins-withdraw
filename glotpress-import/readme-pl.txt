=== Plogins Withdraw - Right of Withdrawal Button for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, withdrawal, right of withdrawal, eu, refund
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Wymaga wtyczek: woocommerce
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pełne lub częściowe unijne prawo do odstąpienia od umowy (Dyrektywa 2023/2673) dla zamówień WooCommerce, z dziennikiem administratora i powiadomieniami e-mail.

== Description ==

Plogins Withdraw dodaje do WooCommerce łatwą funkcję odstąpienia od umowy, zgodną z <strong>dyrektywą UE 2023/2673</strong> („przycisk wypłaty”, art. 11a): przejrzysty sposób, w jaki klienci mogą zadeklarować, że odstępują od umowy zawartej na odległość, w całości lub na sztukę, w ustawowym terminie odstąpienia od umowy.

Jest to wtyczka <strong>request-and-log</strong>: rejestruje deklarację klienta o odstąpieniu od umowy, wysyła e-mail z potwierdzeniem do klienta i powiadomieniem do sklepu oraz śledzi status żądania w panelu administracyjnym. Nigdy nie przenosi pieniędzy samodzielnie, wszelkie zwroty przetwarzasz na normalnym ekranie zamówienia WooCommerce, zgodnie z modelem prawnym, w którym klient deklaruje, a przedsiębiorca działa.

= What it does =

* <strong>Formularz wypłaty</strong>: krótki kod `[withdraw_form]` wyświetla dwuetapowy formularz: wyszukaj zamówienie według numeru i adresu e-mail dotyczącego płatności (działa również w przypadku gości), następnie wybierz pozycje i ilości, a następnie prześlij deklarację wypłaty.
* <strong>Całkowita lub częściowa wypłata</strong>: klient wybiera, z ilu sztuk każdego artykułu chce zrezygnować.
* <strong>Przycisk wypłaty na Moim koncie</strong>: pod szczegółami zamówienia pojawia się przycisk „Wypłata z tego zamówienia” oraz linki do strony wypłaty z wstępnie wypełnionym zamówieniem.
* <strong>Sprawdzanie okresu odstąpienia od umowy</strong>: konfigurowalny okres (ustawowe minimum 14 dni), liczony od dostawy (realizacji zamówienia) lub, jeśli nie został zrealizowany, od daty zamówienia.
* <strong>Dziennik administratora</strong>: ekran WooCommerce → Żądania wypłaty zawiera listę wszystkich żądań wraz z ich pozycjami, klientami i statusem (oczekujące, zaakceptowane, odrzucone, przetworzone), z możliwością filtrowania według statusu.
* <strong>E-maile</strong>: automatyczne potwierdzenie dla klienta i powiadomienie sklepu.
* <strong>Wzórowy tekst odstąpienia</strong>: edytowalny blok na formularzu ustawowego wzoru formularza odstąpienia od umowy (Załącznik I.B).
* <strong>Przyjazny dla gości</strong>: konto nie jest potrzebne; numer zamówienia + wyszukiwanie adresu e-mail rozliczeniowego działa w przypadku zamówień gości.
* <strong>Kompatybilny z HPOS + Blocks</strong>: odczytuje zamówienia za pośrednictwem interfejsu API zamówień WooCommerce.

= Requirements =

* WordPress 6.5 lub nowszy
* PHP 8.1 lub nowszy
* WooCommerce 8.0 lub nowszy

== Installation ==

1. Zainstaluj i aktywuj WooCommerce.
2. Zainstaluj i aktywuj Plogins Withdraw.
3. Utwórz stronę i dodaj krótki kod `[withdraw_form]`.
4. Przejdź do <strong>WooCommerce → Wypłata</strong>, wybierz tę stronę jako stronę formularza wypłaty, ustaw okres odstąpienia od umowy i statusy kwalifikujących się zamówień, a także dostosuj e-mail z powiadomieniem i tekst prawny.

== Frequently Asked Questions ==

= Does it issue refunds automatically? =
Nie. Rejestruje żądanie wypłaty i śledzi jego status. Przetwarzaj zwrot pieniędzy na normalnym ekranie zamówienia WooCommerce; statusem żądania zarządza się na ekranie Żądania wypłaty.

= Does it work for guest orders? =
Tak. Klienci sprawdzają swoje zamówienie, podając numer zamówienia i adres e-mail rozliczeniowy użyty przy kasie, dzięki czemu goście mogą dokonać wypłaty bez konta.

= Is this legal advice? =
Nie. Wtyczka zapewnia funkcję wycofania technicznego i edytowalne teksty prawne. Skonfiguruj okres i treść tak, aby odpowiadały Twojej jurysdykcji i ustawowemu modelowi formularza odstąpienia od umowy.

= Is it compatible with HPOS? =
Tak. Zamówienia są odczytywane poprzez API zamówień WooCommerce, które jest kompatybilne z HPOS.

== Screenshots ==

1. Formularz wniosku o wypłatę: wyszukiwanie zamówienia według numeru i adresu e-mail rozliczeniowego (przyjazny dla gości), z 14-dniowym wyprzedzeniem dotyczącym prawa do odstąpienia od umowy.
2. Wybór artykułu: wycofanie całkowite lub częściowe z podaniem ilości przypadających na sztukę, wzór tekstu wycofania i oświadczenie.
3. Ustawienia (WooCommerce → Wypłata): okres odstąpienia od umowy, strona formularza, kwalifikujące się statusy, e-mail z powiadomieniem i teksty prawne, z dziennikiem żądań.

== Changelog ==

= 1.0.1 =
* Pierwsza stabilna wersja.

= 0.1.1 =
* Sprawdzenie wtyczki: poprawki dotyczące ucieczki/sanizacji/i18n/higieny (identyfikatory nazw tabel są teraz przekazywane przez symbole zastępcze %i w przygotowanych instrukcjach).

= 0.1.0 =
* Pierwsza wersja: krótki kod `[withdraw_form]` (wyszukiwanie zamówienia + pełny/częściowy wybór przedmiotu), przycisk wypłaty na Moim koncie, konfigurowalny okres wypłaty i kwalifikujące się statusy, dziennik żądań administratora ze statusami, e-maile klientów i sklepów, edytowalny tekst wycofania modelu. Kompatybilny z HPOS + Blocks.

== Upgrade Notice ==

= 0.1.0 =
Pierwsze wydanie.
