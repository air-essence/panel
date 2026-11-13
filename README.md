# Panel zamówień

Aplikacja webowa, która zastępuje arkusze Google do obsługi zamówień, anulacji, zwrotów, reklamacji i przesyłek.
Porównuje dane wpisywane przez pracowników z BaseLinkerem (i sklepami WooCommerce) i pokazuje rozbieżności.

Koncepcja i plan MVP: dokument „Koncepcja narzędzia do śledzenia zamówień (zamiast arkuszy Google)” w projekcie Claude.

## Co już działa

- logowanie (blokada po 5 nieudanych próbach, nieaktywne konta nie mogą się zalogować),
- role: pracownik, kierownik, administrator,
- słowniki: spółki (Swing, Trusted, Roynal), kanały sprzedaży (10 sklepów i marketplace'ów), statusy zamówień z grupami do reguł rozbieżności,
- zmiany w słownikach tylko dla administratora, podgląd dla wszystkich,
- synchronizacja z BaseLinkerem co 5 minut, tylko do odczytu: statusy, źródła zamówień, zamówienia z produktami (EAN), płatnością i numerem przesyłki,
- lista zamówień z wyszukiwaniem i filtrami oraz karta zamówienia,
- anulacje: wymagane pola (powód, data, co z przesyłką, zwrot pieniędzy z kwotą i datą), jedna otwarta anulacja na zamówienie, blokada zamknięcia przed zwrotem pieniędzy, historia zmian oraz ostrzeżenia, gdy sprawa nie zgadza się z danymi z BaseLinkera,
- „Do wyjaśnienia”: rozbieżności między anulacjami w panelu a statusami w BaseLinkerze (anulowane z numerem przesyłki, zwrot pieniędzy przy zamówieniu w realizacji, anulacja tylko po jednej stronie). Rozbieżność można oznaczyć jako wyjaśnioną z obowiązkowym opisem,
- powiadomienia: licznik rozbieżności w menu i w tytule karty (odświeżany co minutę) oraz powiadomienia systemowe o nowych rozbieżnościach krytycznych i błędach synchronizacji.

## Powiadomienia

Licznik działa zawsze. Powiadomienia systemowe przeglądarka pokazuje tylko po kliknięciu „Włącz powiadomienia” i tylko gdy panel jest otwarty przez HTTPS albo pod `localhost`. Na serwerze firmowym trzeba więc ustawić HTTPS (np. certyfikat wewnętrzny lub Caddy). Karta z panelem musi być otwarta, choćby w tle.

## BaseLinker

1. W BaseLinkerze: Moje konto → API → wygeneruj token.
2. Wpisz go w pliku `.env` na serwerze: `BASELINKER_TOKEN=...`. Nigdy nie wklejaj tokenu do repozytorium ani do czatu.
3. Uruchom pierwszy raz ręcznie: `php artisan panel:synchronizuj-baselinker`.
4. Na pulpicie pojawią się źródła bez kanału. Przypisz je w „Kanały sprzedaży”, a zamówienia połączą się z kanałem i spółką.
5. Statusy z BaseLinkera łączą się z istniejącymi po nazwie. Nowe dostają grupę „Do ustalenia” do uzupełnienia.

Panel używa wyłącznie metod `get*` API (blokada w `App\Services\BaseLinker\Client`). Każda synchronizacja odświeża zamówienia z ostatnich `BASELINKER_SYNC_DAYS` dni (domyślnie 30).

Automatyczne uruchamianie: na serwerze dodaj do crona `* * * * * cd /ścieżka/do/panelu && php artisan schedule:run >> /dev/null 2>&1`.

## Wymagania

- PHP 8.3 lub nowszy z rozszerzeniami: pdo_sqlite (lub pdo_mysql / pdo_pgsql), mbstring, openssl, xml
- Composer 2

## Uruchomienie lokalne

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan panel:dodaj-uzytkownika twoj@email.pl --name="Imię Nazwisko" --role=admin
php artisan serve
```

Panel będzie pod adresem http://localhost:8000.

Na serwerze firmowym zamiast SQLite można ustawić MySQL lub PostgreSQL w pliku `.env` (`DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

## Przydatne polecenia

| Polecenie | Co robi |
| --- | --- |
| `php artisan panel:dodaj-uzytkownika email --role=pracownik` | dodaje konto (hasło podajesz w konsoli, min. 10 znaków) |
| `php artisan db:seed --class=DictionarySeeder` | dodaje brakujące wpisy słowników, nie nadpisuje zmian z panelu |
| `php artisan panel:synchronizuj-baselinker [--dni=7]` | pobiera dane z BaseLinkera |
| `php artisan test` | uruchamia testy |
| `./vendor/bin/pint` | formatuje kod |

## Następne kroki (plan MVP)

7. Instalacja na serwerze firmowym i kopie zapasowe.
