# Plogins Withdraw, stan prac

Ostatnia aktualizacja: 2026-09-08

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

## W toku, ROBOTA NIEZATWIERDZONA W GIT

Baza porownania: **`5726641`**. Workflow `wf_c5fb45d0-460` (maile WC + zegar art. 13)
**przerwal sie na limicie sesji**: 2 agentow projektowych skonczylo, 4 budujacych
i weryfikujacych padlo. W drzewie roboczym lezy polowa implementacji, ktorej
**nikt nie uruchomil**.

Projekt do dokonczenia: `../_drafts/withdraw-email-art13-design.md` (pelny opis obu projektow).

Jest:
- `src/Email/` 9 klas (AbstractWithdrawEmail, AbstractStatusEmail, Acknowledgement,
  NewRequest, Accepted, Rejected, Processed, UnderReview, AccessLink),
- `templates/emails/withdraw-accepted.php`, `withdraw-acknowledgement.php`,
  `templates/emails/plain/withdraw-acknowledgement.php`,
- `src/Service/EmailService.php` (napisany, **nigdzie niepodpiety**),
- `src/Service/ReturnPolicy.php`,
- kolumna `declaration` (`config/defaults.php`, `src/Migrator.php`,
  `src/Service/RequestRepository.php::create()` przyjmuje `$declaration`),
- ustawienia `return_address`, `return_cost` (domyslnie `not_stated`), `return_cost_note`.

Brakuje:
- szablonow `withdraw-new-request.php`, `withdraw-status.php`, `withdraw-access-link.php`
  i wariantow `plain/` dla wszystkich poza acknowledgement,
- wpisu `EmailService` w `config/services.php` i w **obu** galeziach `config/hooks.php`
  (POST na sklepie nie jest `is_admin()`, `admin-post.php` jest),
- akcji `withdraw/status_changed` i `withdraw/link_issued`,
- usuniecia czterech zywych `wp_mail()`: `WithdrawalService.php` 654, 819, 837
  i `RequestsAdmin.php` 88,
- przekazania oswiadczenia do `RequestRepository::create()` z `handleSubmit()`,
- calego zegara 14 dni z art. 13 (agent padl przed napisaniem czegokolwiek),
- sanityzacji `return_cost` po stronie serwera (`Settings::sanitize()`),
- pola `recipient` w `NewRequestEmail` z migracja z `notify_email`.

## Pulapki, ktore musza byc sprawdzone zanim to pojdzie

1. **`WC()->mailer()` boot.** Wlasna klasa `WC_Email` rejestrujaca trigger w konstruktorze
   nigdy nie wystrzeli, jesli nic tego konstruktora nie zbuduje. `WC()->mailer()` musi byc
   wywolane na priorytecie 1 na **wlasnej** akcji. Wzorzec: `polski/src/Service/EmailService.php`.
   Kosztowalo polski trzy wydania.
2. **Nie wieszac na `woocommerce_init`.** Wtyczka bootuje na `init` p. 10, a WooCommerce
   odpala `woocommerce_init` z `init` p. 0, wiec handler tam dodany to martwy kod.
3. **Zakaz regresji art. 11a ust. 4.** Potwierdzenie ma dalej niesc pelna tresc oswiadczenia
   oraz date i godzine zlozenia. To juz jest wydane prawo.
4. **`return_cost` domyslnie `not_stated` i tak ma zostac.** Art. 14 ust. 1 w zw. z art. 6
   ust. 1 lit. i: konsument placi za odeslanie tylko jesli przedsiebiorca poinformowal go
   o tym wczesniej. Domyslne `customer` kazaloby klientom placic za cos, do czego sklep
   moze nie miec prawa.
5. **Art. 13 ust. 2**: zwrot obejmuje takze koszt standardowej dostawy, nie sama cene towaru.

## Backlog po mailach

- Wyniki per pozycja i obowiazkowy powod odrzucenia.
- Zalacznik I(A) i I(B) jako generowana tresc z `SellerIdentity`.
- Paginacja i widok szczegolow rejestru: wiersz 101 jest nieosiagalny, argument `search`
  jest przyjmowany i nigdy nie przekazywany.
