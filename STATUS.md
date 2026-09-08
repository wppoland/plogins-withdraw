# Plogins Withdraw, stan prac

Ostatnia aktualizacja: 2026-09-08 (po wydaniu 1.6.0)

## Wydane

- wp.org: **1.3.0** (potwierdzone przez API). Tagi SVN + wydania GitHub aktualne.
- Zbudowane i wydane w tej sesji: 1.0.7 (pierwszy SVN), 1.0.8, 1.1.0, 1.2.0, 1.3.0.
- Art. 11a w calosci wdrozony: etykieta i staly dostep (`[withdraw_link]` + link w stopce),
  osobny krok potwierdzenia (`templates/form-review.php`, przycisk tylko "Confirm withdrawal"),
  potwierdzenie na trwalym nosniku z pelna trescia oswiadczenia oraz data i godzina.
- Art. 16(m): `DigitalConsentService`, wyklucza pozycje tylko gdy sa spelnione trzy warunki
  (zgoda, potwierdzenie utraty prawa, rozpoczete spelnianie swiadczenia czytane z uprawnien
  do pobran WooCommerce).
- `AccessLink`: token 16 bajtow, w bazie tylko SHA-256, TTL 60 minut, zuzywany dopiero po
  zlozeniu oswiadczenia.

## Maile WooCommerce, ZROBIONE w 1.4.0

Workflow `wf_c5fb45d0-460` padl na limicie sesji po dwoch agentach projektowych i zostawil
polowe implementacji. Dokonczone recznie, wydane jako 1.4.0 (commit 219e84c, wp.org
potwierdzone). Projekt zostal w `../_drafts/withdraw-email-art13-design.md`.

Co weszlo:
- 7 klas `WC_Email` w `src/Email/` plus 10 szablonow w `templates/emails/`,
  kazdy z wlasna sekcja w WooCommerce, Ustawienia, E-maile.
- `EmailService` wpiety w `config/services.php` i w OBA branche `config/hooks.php`.
- Zadnego `wp_mail()` w `src/`. Trzy nowe akcje: `withdraw/declared` (byla),
  `withdraw/status_changed`, `withdraw/link_issued`.
- Kolumna `declaration`: tresc oswiadczenia zamrozona w chwili zlozenia.
- Art. 14 ust. 1: `return_address`, `return_cost` (domyslnie `not_stated`),
  `return_cost_note` plus `ReturnPolicy`.
- Art. 13 ust. 1: kolumna "Refund due" w rejestrze, 14 dni od oswiadczenia, na czerwono po terminie.
- Dokumentacja EN i PL poprawiona i wdrozona (przy okazji trzy stare falszywe twierdzenia).

Weryfikacja: `tests/emails-check.php`, 42 asercje, ALL GREEN.
Uruchomienie: `wp eval-file wp-content/plugins/plogins-withdraw/tests/emails-check.php` w wp-env.
Test resetuje singleton `WC_Emails` przez refleksje, bo WP-CLI buduje mailer przy starcie
i bez tego pulapka z leniwym mailerem nie moze w ogole wystapic. Dwa pierwsze przebiegi
przechodzily mierzac cos innego.

Plugin Check na zbudowanej paczce: 2 ostrzezenia, oba to znany falszywy alarm
`Prefix_Scanner` na nazwach hookow ze slashem (`withdraw/magic_link`, `withdraw/status`).

## 1.5.0, rejestr wnioskow

- Paginacja po 25, wczesniej lista konczyla sie na 100 najnowszych i starszych nie dalo sie otworzyc.
- Wyszukiwarka po e-mailu i numerze zamowienia. Argument `search` byl przyjmowany przez
  `RequestRepository::all()` odkad powstal i nikt go nigdy nie przekazywal.
- Ekran szczegolow wniosku: uzasadnienie, oswiadczenie, obie daty, termin zwrotu. Bez tokenu.
- Notatka przy statusie, WYMAGANA przy odrzuceniu, drukowana w mailu przy kazdym statusie.
- `RequestRepository::total()` liczy tym samym WHERE co `all()`, zeby pager nie prowadzil
  na puste strony.

## 1.6.0, teksty ustawowe

- `StatutoryText`: zalacznik I lit. B (wzor oswiadczenia) i lit. A (wzor pouczenia) generowane
  z danych sprzedawcy, tlumaczone, z ustawionym okresem odstapienia.
- Nowy shortcode `[withdraw_instructions]` (atrybuty `heading`, `form`) plus filtr
  `withdraw/model_instructions`.
- Ustawienia `seller_name`, `seller_email`, `seller_phone`.
- `intro_text` i `model_form_text` domyslnie PUSTE. Wczesniej byly angielskimi zdaniami
  z pliku konfiguracyjnego, drukowanymi klientowi doslownie razem z `[seller name and address]`,
  a stringa z configu nie da sie przetlumaczyc.
- Migracja `sweepLegacyTexts()` czysci te teksty TYLKO przy dokladnym dopasowaniu, wiec
  wlasne brzmienie sklepu i reczne tlumaczenie przezywaja.
- Zdanie o koszcie zwrotu trafia do pouczenia dopiero po ustawieniu reguly: samo w sobie jest
  pouczeniem z art. 6 ust. 1 lit. i, na ktore sklep powoluje sie pozniej.

Weryfikacja calosci: `tests/emails-check.php`, 72 asercje, ALL GREEN.

## Backlog

- Wyniki per pozycja i obowiazkowy powod odrzucenia.
- Zalacznik I(A) i I(B) jako generowana tresc z `SellerIdentity`.
- Paginacja i widok szczegolow rejestru: wiersz 101 jest nieosiagalny, argument `search`
  jest przyjmowany i nigdy nie przekazywany.
