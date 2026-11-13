# Panel zamówień

Aplikacja webowa, która zastępuje arkusze Google do obsługi zamówień, anulacji, zwrotów, reklamacji i przesyłek.
Porównuje dane wpisywane przez pracowników z BaseLinkerem (i sklepami WooCommerce) i pokazuje rozbieżności.

Koncepcja i plan MVP: dokument „Koncepcja narzędzia do śledzenia zamówień (zamiast arkuszy Google)” w projekcie Claude.

## Co już działa

- logowanie (blokada po 5 nieudanych próbach, nieaktywne konta nie mogą się zalogować),
- role: pracownik, kierownik, administrator,
- słowniki: spółki (Swing, Trusted, Roynal), kanały sprzedaży (10 sklepów i marketplace'ów), statusy zamówień z grupami do reguł rozbieżności,
- zmiany w słownikach tylko dla administratora, podgląd dla wszystkich.

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
| `php artisan test` | uruchamia testy |
| `./vendor/bin/pint` | formatuje kod |

## Następne kroki (plan MVP)

3. Pobieranie zamówień z BaseLinkera tylko do odczytu (token API w `.env`, nigdy w repozytorium).
4. Moduł anulacji z wymaganymi polami i historią zmian.
5. Reguły rozbieżności i pulpit „Do wyjaśnienia”.
