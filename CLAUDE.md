# Panel zamówień — wskazówki dla Claude

- Laravel 13, PHP 8.3, widoki Blade bez buildu frontendu (style w `resources/views/layouts/app.blade.php`).
- Interfejs, komunikaty i trasy po polsku (`/logowanie`, `/kanaly`, `/statusy`). Komunikaty walidacji w `lang/pl/validation.php`.
- Role w `App\Enums\Role`; ograniczanie tras middleware `role:admin`.
- Grupy statusów w `App\Enums\StatusGroup` — na nich opierają się reguły rozbieżności. Nowy status zawsze dostaje grupę.
- Integracje zewnętrzne (BaseLinker, WooCommerce) tylko do odczytu, dopóki właściciel projektu nie zdecyduje inaczej. Tokenów nie zapisujemy w repozytorium.
- Przed commitem: `php artisan test` i `./vendor/bin/pint --test`.
