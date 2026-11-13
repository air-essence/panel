@echo off
setlocal
cd /d "%~dp0"
title Panel zamowien (wersja testowa)

echo.
echo  Panel zamowien - uruchomienie wersji testowej
echo  ==============================================
echo.

where php >nul 2>nul
if errorlevel 1 goto brak_php
where composer >nul 2>nul
if errorlevel 1 goto brak_php

if not exist vendor\autoload.php (
    echo [1/4] Pobieram biblioteki. Za pierwszym razem trwa to kilka minut...
    call composer install --no-interaction --no-progress
    if errorlevel 1 goto blad
) else (
    echo [1/4] Biblioteki sa juz pobrane.
)

if not exist .env (
    echo [2/4] Tworze plik ustawien .env
    copy /y .env.example .env >nul
    call php artisan key:generate --force
    if errorlevel 1 goto blad
) else (
    echo [2/4] Plik ustawien .env juz istnieje.
)

set "WGRAJ=T"
if exist database\database.sqlite (
    echo.
    choice /c TN /m "[3/4] Baza testowa juz istnieje. Wyczyscic ja i wgrac dane przykladowe od nowa"
    if errorlevel 2 set "WGRAJ=N"
) else (
    type nul > database\database.sqlite
)

if "%WGRAJ%"=="T" (
    echo [3/4] Wgrywam dane przykladowe...
    call php artisan migrate:fresh --seed --seeder=DemoSeeder --force
    if errorlevel 1 goto blad
) else (
    echo [3/4] Zostawiam obecne dane. Uzupelniam tylko nowe tabele.
    call php artisan migrate --force
    if errorlevel 1 goto blad
)

echo.
echo [4/4] Panel startuje pod adresem http://localhost:8000
echo.
echo   Konta testowe (haslo dla wszystkich: demo-panel):
echo     admin@demo.pl       - administrator
echo     kierownik@demo.pl   - kierownik
echo     pracownik@demo.pl   - pracownik
echo.
echo   Nie zamykaj tego okna, dopoki testujesz. Aby zatrzymac panel: zamknij okno lub Ctrl+C.
echo.
start "" cmd /c "timeout /t 3 /nobreak >nul & start http://localhost:8000"
call php artisan serve --port=8000
goto koniec

:brak_php
echo Nie znaleziono PHP lub Composera.
echo Zainstaluj darmowy program Laravel Herd: https://herd.laravel.com/windows
echo Po instalacji zamknij to okno i uruchom plik ponownie.
echo.
pause
exit /b 1

:blad
echo.
echo Cos poszlo nie tak. Zrob zrzut ekranu tego okna i wyslij go w projekcie.
echo.
pause
exit /b 1

:koniec
pause
