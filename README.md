# Biblioteka Żółtego Tygrysa 🐯📚

Aplikacja internetowa do zarządzania i ewidencji prywatnej kolekcji książek z legendarnej serii historyczno-wojennej **„Żółty Tygrys”** (wydawanej pierwotnie przez Wydawnictwo Ministerstwa Obrony Narodowej, a współcześnie wznawianej przez Wydawnictwo Bellona).

System został zbudowany w nowoczesnym stosie technologicznym **Laravel 13 + Vue 3 + Inertia.js v3 + MySQL + Tailwind CSS v4**.

---

## 🎯 Do czego służy system?

Aplikacja służy jako interaktywny katalog i rejestr zakupów kolekcjonerskich:
- **Śledzenie zakupów:** Szybkie oznaczanie tomów jako *„Kupiona”* lub *„Do kupienia”* za pomocą jednego kliknięcia w checkbox (z optymistycznym UI – stan zapisuje się natychmiastowo w tle w bazie MySQL).
- **Statystyki i postęp kolekcji:** Dynamiczny licznik posiadanych i brakujących tomów, łączna liczba książek w serii oraz procentowy pasek postępu skompletowania zbioru.
- **Wizualny katalog z okładkami:** Zachowane naturalne, pionowe proporcje okładek książek (format 2:3) z funkcją podglądu powiększenia (Lightbox) w wysokiej rozdzielczości.
- **Szczegółowe informacje i opisy:** Tytuł, autor, informacje o wydaniu, streszczenie fabuły oraz bezpośredni link do zakupu w księgarni internetowej (*Świat Książki*).
- **Wyszukiwarka i filtry na żywo:** Błyskawiczne filtrowanie po tytule, autorze, dacie oraz treści opisu. Zakładki statusu (*Wszystkie*, *Do kupienia*, *Kupione*, *Z okładką*) oraz sortowanie (kolejność w spisie, tytuł A-Z, autor A-Z).
- **Tryb ciemny / jasny (Dark Mode):** Przełącznik motywu z automatycznym zapamiętywaniem preferencji użytkownika.
- **Automatyczna synchronizacja CRON:** Cykliczne sprawdzanie kanału RSS wydawnictwa Bellona i automatyczne dodawanie nowych premier książkowych do bazy.

---

## 🛠️ Stos technologiczny

### Backend:
- **[PHP 8.4](https://www.php.net/)**
- **[Laravel 13](https://laravel.com/)** – routing, kontrolery, migracje, seedery, harmonogram zadań
- **[MySQL / MariaDB](https://www.mysql.com/)** – relacyjna baza danych
- **[Laravel Pint](https://laravel.com/docs/pint)** – standaryzacja i formatowanie kodu PHP
- **[Pest PHP](https://pestphp.com/)** – zautomatyzowane testy jednostkowe i integracyjne

### Frontend:
- **[Vue 3](https://vuejs.org/)** (Composition API, `<script setup>`, TypeScript)
- **[Inertia.js v3](https://inertiajs.com/)** – integracja SPA bez konieczności budowy tradycyjnego REST API
- **[Tailwind CSS v4](https://tailwindcss.com/)** – nowoczesne, responsywne style użytkowe
- **[Reka UI](https://reka-ui.com/)** – dostępne komponenty interfejsu
- **[Lucide Icons](https://lucide.dev/)** – zestaw wektorowych ikon
- **[Vue Sonner](https://vue-sonner.vercel.app/)** – powiadomienia Toast informujące o statusie zapisu

---

## 🗄️ Struktura bazy danych (`books`)

| Kolumna | Typ | Opis |
|---|---|---|
| `id` | `BIGINT UNSIGNED` (PK, Auto-increment) | Unikalny identyfikator tomu |
| `title` | `VARCHAR(255)` | Tytuł książki |
| `author` | `VARCHAR(255)` | Autor książki |
| `details` | `VARCHAR(255)` (Nullable) | Informacje o wydaniu / wydawnictwie / data dodania |
| `image_path` | `VARCHAR(255)` (Nullable) | Ścieżka do lokalnie pobranego pliku okładki (`/images/books/...`) |
| `image_url` | `VARCHAR(255)` (Nullable) | Źródłowy adres URL okładki na CDN |
| `book_url` | `VARCHAR(255)` (Nullable) | Bezpośredni link do podstrony książki w księgarni |
| `description` | `TEXT` (Nullable) | Opis / streszczenie książki |
| `is_purchased` | `BOOLEAN` (Index, domyślnie `false`) | Oznaczenie: czy książka została już kupiona |
| `created_at` / `updated_at` | `TIMESTAMP` | Daty utworzenia i aktualizacji rekordu |

---

## 🚀 Uruchomienie projektu lokalnie

### 1. Wymagania wstępne:
- PHP >= 8.3 lub 8.4 z rozszerzeniami: `pdo_mysql`, `curl`, `mbstring`, `simplexml`, `fileinfo`
- Composer
- Node.js (wersja 20+) i npm
- Serwer bazy danych MySQL / MariaDB (np. w pakiecie XAMPP)

### 2. Klonowanie i konfiguracja środowiska:
```bash
# Wejdź do katalogu projektu
cd c:\xampp\htdocs\work\tygrys

# Zainstaluj zależności PHP i JavaScript
composer install
npm install

# Utwórz plik .env (jeśli nie istnieje) i wygeneruj klucz aplikacji
cp .env.example .env
php artisan key:generate
```

### 3. Konfiguracja bazy danych w pliku `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tygrys
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Wykonanie migracji oraz załadowanie danych początkowych:
```bash
# Utworzenie tabel w bazie danych oraz import tomów z ksiazki.txt
php artisan migrate --seed

# Pobranie okładek, linków i opisów z plików payload*.json
php artisan books:enrich
```

### 5. Kompilacja assetów i uruchomienie serwera:
```bash
# Budowanie produkcyjne frontendu
npm run build

# Uruchomienie serwera deweloperskiego
php artisan serve
```

Aplikacja będzie dostępna pod adresem: **http://localhost:8000/**

---

## ⚙️ Przydatne komendy Artisan

| Polecenie | Opis |
|---|---|
| `php artisan books:enrich` | Skanuje wszystkie pliki `payload*.json`, pobiera okładki do katalogu `public/images/books/` oraz aktualizuje opisy i linki. |
| `php artisan books:fetch-rss` | Odpytuje feed RSS wydawnictwa Bellona (`https://www.bellona.pl/seria/tygrys/feed/`) i automatycznie dodaje nowe tomy do bazy. |
| `php artisan schedule:list` | Wyświetla listę zaplanowanych zadań w harmonogramie CRON. |
| `php artisan schedule:work` | Uruchamia lokalnego workera harmonogramu wykonującego zadania w tle. |
| `php artisan test` | Uruchamia zestaw testów jednostkowych i funkcjonalnych Pest. |
| `vendor/bin/pint --format agent` | Formatowanie kodu PHP zgodnie ze standardami Laravel. |

---

## ⏰ Konfiguracja zadania CRON (Produkcja)

W środowisku produkcyjnym (np. Linux) wystarczy dodać do tabeli zadań cron (`crontab -e`) standardowy wpis wywołujący harmonogram Laravel co minutę:

```cron
* * * * * cd /sciezka/do/aplikacji && php artisan schedule:run >> /dev/null 2>&1
```

Laravel automatycznie uruchomi zadanie `books:fetch-rss` co godzinę zgodnie z regułą zdefiniowaną w pliku `routes/console.php`.

---

## 📄 Licencja

Projekt stworzony na własny użytek do zarządzania kolekcją książek. Otwarty kod źródłowy na licencji [MIT](LICENSE).

