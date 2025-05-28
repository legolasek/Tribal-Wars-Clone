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

4. **System surowców**
   - Produkcja drewna, gliny i żelaza
   - Magazynowanie surowców
   - Automatyczna aktualizacja zasobów
   - Rozbudowa budynków produkcyjnych

5. **System czasu rzeczywistego**
   - Budynki budują się w czasie rzeczywistym
   - Jednostki rekrutują się w czasie rzeczywistym
   - Surowce produkowane są w czasie rzeczywistym

6. **System mapy**
   - Wizualizacja mapy świata
   - Koordynaty X/Y dla wiosek
   - System terenu

7. **System wojskowy**
   - Różne typy jednostek
   - System rekrutacji
   - System walki (atak/obrona)

8. **System wiadomości i raportów**
   - Wysyłanie wiadomości między graczami
   - Raporty z ataków i walk
   - System sojuszy i plemion

## Inspiracje z VeryOldTemplate

Stara wersja silnika (VeryOldTemplate) została wykorzystana jako inspiracja dla następujących rozwiązań:

1. **System budynków**
   - Klasa BuildingManager zainspirowana przez klasę `builds` ze starej wersji
   - System bonusów z budynków podobny do systemu w starej wersji
   - Struktura tabeli building_requirements podobna do needbuilds z VeryOldTemplate

2. **System wiosek**
   - Klasa VillageManager zainspirowana przez funkcje zarządzające wioskami ze starej wersji
   - System aktualizacji surowców działa na podobnej zasadzie
   - Automatyczne sprawdzanie zakończenia budowy budynków

3. **System funkcji pomocniczych**
   - Wiele funkcji pomocniczych w lib/functions.php zostało zainspirowanych przez stare funkcje
   - System formatowania czasu, dat
   - Funkcje obliczania odległości i innych parametrów

## Ulepszenia względem starej wersji

1. **Bezpieczeństwo**
   - Przejście z przestarzałego `mysql_*` na `mysqli` z prepared statements
   - Lepsze hashowanie haseł
   - Walidacja wszystkich danych wejściowych

2. **Struktura kodu**
   - Większa modułowość i reużywalność kodu
   - Wykorzystanie klas i obiektów
   - Separacja logiki biznesowej od prezentacji

3. **Funkcjonalność**
   - Bardziej elastyczny system budynków
   - Rozbudowany system zależności między budynkami
   - Szczegółowe opisy i bonusy budynków

4. **Baza danych**
   - Lepiej zaprojektowana struktura tabel
   - Relacje między tabelami z wykorzystaniem kluczy obcych
   - Indeksy dla szybszego wyszukiwania

## Wprowadzone ulepszenia

### 1. Architektura i struktura kodu
- **Autoloader** - automatyczne ładowanie klas
- **Obsługa błędów** - system obsługi błędów i wyjątków
- **Organizacja kodu** - podział na foldery (managers, models, utils)

### 2. Dynamiczne aktualizacje UI
- **Aktualizacja zasobów w czasie rzeczywistym** - bez przeładowywania strony
- **Dynamiczna kolejka budowy** - aktualizacja czasów i postępów w czasie rzeczywistym
- **System powiadomień** - ulepszony system powiadomień użytkownika

### 3. Bezpieczeństwo
- **Obsługa błędów i wyjątków** - lepsza obsługa i logowanie błędów
- **Walidacja wejść** - ulepszona walidacja danych wejściowych
- **Oddzielenie API i frontendu** - wykorzystanie AJAX do komunikacji z backendem

### 4. Interfejs użytkownika
- **Ulepszone style** - nowoczesny wygląd z zachowaniem stylistyki Plemion
- **Tooltips** - dodanie podpowiedzi przy zasobach i budynkach
- **Paski postępu** - animowane paski postępu dla budowy i rekrutacji
- **Responsywność** - lepsze dostosowanie do różnych urządzeń

## Struktura projektu

```
├── ajax/               # Endpointy AJAX do dynamicznych aktualizacji
│   └── buildings/      # Endpointy związane z budynkami
├── config/             # Pliki konfiguracyjne
├── css/                # Style CSS
├── img/                # Obrazy i grafiki
├── js/                 # Skrypty JavaScript
├── lib/                # Klasy PHP
│   ├── managers/       # Klasy zarządzające logiką biznesową
│   ├── models/         # Modele danych
│   └── utils/          # Klasy pomocnicze
├── logs/               # Logi aplikacji
└── VeryOldTemplate/    # Stara wersja (tylko jako referencja)
```

## Instalacja

1. Sklonuj repozytorium do katalogu `htdocs` w XAMPP
2. Utwórz bazę danych MySQL o nazwie `plemiona`
3. Uruchom skrypty SQL z katalogu głównego projektu
4. Skonfiguruj plik `config/config.php` z danymi do połączenia z bazą danych
5. Otwórz stronę w przeglądarce: http://localhost/

## Dokumentacja

Szczegółowa dokumentacja kodu znajduje się w katalogu `docs/`. Ważne pliki:

- `docs/budowanie_fix.md` - dokumentacja systemu budynków
- `docs/database.md` - dokumentacja struktury bazy danych
- `docs/api.md` - dokumentacja API dla funkcji AJAX

## Dalszy rozwój

Projekt może być dalej rozwijany poprzez:
1. Implementację systemu sojuszy/plemion
2. Rozbudowę systemu handlu między graczami
3. Dodanie systemu nagród i osiągnięć
4. Rozszerzenie mapy i systemu walki
5. Implementację systemu eventów i zadań

## Autorzy

Projekt oparty na grze plemiona.pl, napisany przez PSteczka.

## Licencja

Projekt dostępny jest na licencji MIT.