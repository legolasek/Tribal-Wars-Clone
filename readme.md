# Silnik Gry Plemiona

Ten projekt to nowoczesna implementacja silnika gry przeglądarkowej typu Tribal Wars (Plemiona), oparta na czystym PHP, HTML, CSS i JavaScript. Projekt został zainspirowany starą wersją silnika wykonaną przez Bartekst221, ale został całkowicie przepisany z wykorzystaniem nowoczesnych praktyk.

## Zaimplementowane funkcjonalności

Główne funkcjonalności zaimplementowane w projekcie to:

1. **System rejestracji i logowania**
   - Bezpieczne przechowywanie haseł z wykorzystaniem nowoczesnych algorytmów
   - Walidacja danych wejściowych
   - System sesji

2. **System zarządzania wioskami**
   - Tworzenie nowych wiosek
   - Zmiana nazwy wioski
   - Zarządzanie populacją
   - Produkcja surowców w czasie rzeczywistym

3. **System budynków**
   - Budowa i rozbudowa budynków
   - System zależności między budynkami
   - Koszty i czas budowy zależne od poziomu
   - Specjalne bonusy z budynków
   - Kolejka budowy z dynamicznym czasem
   - Anulowanie budowy

4. **System surowców**
   - Produkcja drewna, gliny i żelaza
   - Magazynowanie surowców
   - Automatyczna aktualizacja zasobów w czasie rzeczywistym
   - Rozbudowa budynków produkcyjnych

5. **System czasu rzeczywistego**
   - Budynki budują się w czasie rzeczywistym
   - Jednostki rekrutują się w czasie rzeczywistym
   - Surowce produkowane są w czasie rzeczywistym

6. **System mapy**
   - Wizualizacja mapy świata (podstawowa implementacja)
   - Koordynaty X/Y dla wiosek
   - System terenu

7. **System wojskowy**
   - Różne typy jednostek
   - System rekrutacji jednostek w koszarach/stajni (w trakcie implementacji)
   - Kolejka rekrutacji z dynamicznym czasem
   - Anulowanie rekrutacji
   - System walki (atak/obrona) (podstawowa implementacja)

8. **System wiadomości i raportów**
   - Wysyłanie wiadomości między graczami (w trakcie implementacji)
   - Raporty z ataków i walk (podstawowa implementacja)
   - System sojuszy i plemion (planowane)

## Inspiracje z VeryOldTemplate

Stara wersja silnika (VeryOldTemplate) została wykorzystana jako inspiracja dla następujących rozwiązań:

1. **System budynków**
   - Struktura tabeli building_requirements podobna do needbuilds z VeryOldTemplate

2. **System wiosek**
   - System aktualizacji surowców działa na podobnej zasadzie
   - Automatyczne sprawdzanie zakończenia budowy budynków

3. **System funkcji pomocniczych**
   - Wiele funkcji pomocniczych w lib/functions.php (jeśli istnieją) zostało zainspirowanych przez stare funkcje
   - System formatowania czasu, dat
   - Funkcje obliczania odległości i innych parametrów

## Ulepszenia względem starej wersji

1. **Bezpieczeństwo**
   - Przejście z przestarzałego `mysql_*` na `mysqli` z prepared statements
   - Lepsze hashowanie haseł
   - Walidacja wszystkich danych wejściowych
   - Oddzielenie API i frontendu - wykorzystanie AJAX do komunikacji z backendem

2. **Struktura kodu**
   - Większa modułowość i reużywalność kodu
   - Wykorzystanie klas i obiektów (Managery, Modele)
   - Separacja logiki biznesowej od prezentacji

3. **Funkcjonalność**
   - Bardziej elastyczny system budynków
   - Rozbudowany system zależności między budynkami
   - Szczegółowe opisy i bonusy budynków (w oparciu o konfigurację)
   - Dynamiczna aktualizacja zasobów, kolejek budowy i rekrutacji na frontendzie (AJAX, JavaScript)

4. **Baza danych**
   - Lepiej zaprojektowana struktura tabel
   - Relacje między tabelami z wykorzystaniem kluczy obcych
   - Indeksy dla szybszego wyszukiwania

### Ulepszenia UI/UX
- **Ulepszone style** - nowoczesny wygląd z zachowaniem stylistyki Plemion (w trakcie)
- **Tooltips** - dodanie podpowiedzi przy elementach interfejsu (w trakcie)
- **Paski postępu** - animowane paski postępu dla budowy i rekrutacji
- **Responsywność** - lepsze dostosowanie do różnych urządzeń (w trakcie)
- **System powiadomień Toast** - dla lepszej informacji zwrotnej dla użytkownika

## Struktura projektu

```
├── ajax/
│   ├── buildings/      # Endpointy AJAX związane z budynkami
│   │   └── logs/
│   └── logs/
├── config/             # Pliki konfiguracyjne (np. config.php)
├── css/                # Style CSS (main.css)
├── docs/               # Dokumentacja projektu
├── img/                # Obrazy i grafiki (budynki, jednostki, tła, ikony)
├── js/                 # Skrypty JavaScript (resources.js, buildings.js, units.js, itp.)
├── lib/                # Klasy PHP
│   ├── managers/       # Klasy zarządzające logiką biznesową (BuildingConfigManager, itp.)
│   ├── models/         # Modele danych
│   └── utils/          # Klasy pomocnicze
│   └── *.php           # Inne klasy pomocnicze (np. BuildingManager, VillageManager, UnitManager)
├── logs/               # Logi aplikacji
├── sql_create_*.sql    # Skrypty SQL do tworzenia tabel
├── *.php               # Główne pliki aplikacji (index.php, game.php, login.php, register.php, itp.)
└── readme.md           # Ten plik
```

## Instalacja

1. Sklonuj lub pobierz pliki projektu do katalogu `htdocs` w XAMPP.
2. Upewnij się, że masz działający serwer MySQL (część XAMPP).
3. Utwórz bazę danych MySQL o nazwie `tribal_wars_new`.
4. Zaimportuj strukturę bazy danych, uruchamiając skrypty `sql_create_*.sql` znajdujące się w katalogu głównym projektu (np. za pomocą phpMyAdmin lub klienta MySQL). Możesz również skorzystać ze skryptu instalacyjnego `install.php`.
5. Skonfiguruj plik `config/config.php` podając dane do połączenia z bazą danych (login, hasło - domyślnie root i brak hasła dla XAMPP).
6. Otwórz stronę w przeglądarce: `http://localhost/`
7. Postępuj zgodnie z instrukcjami na ekranie (rejestracja, tworzenie wioski).

## Dokumentacja

Szczegółowa dokumentacja kodu i bazy danych znajduje się w katalogu `docs/` (jeśli istnieje). Może zawierać pliki takie jak `database.md`, `api.md`, itp.

## Dalszy rozwój

Projekt może być dalej rozwijany poprzez implementację i rozbudowę planowanych funkcjonalności, takich jak:
1. System sojuszy/plemion
2. System handlu między graczami
3. System nagród i osiągnięć
4. Rozszerzenie mapy i systemu walki
5. Implementację systemu eventów i zadań
6. Ukończenie paneli akcji dla pozostałych budynków (Kuźnia, Targ, itp.)
7. Dalsze ulepszenia UI/UX i responsywności

## Autorzy

Projekt oparty na grze plemiona.pl, przepisany i rozwijany przez PSteczka.

## Licencja

Projekt dostępny jest na licencji MIT.