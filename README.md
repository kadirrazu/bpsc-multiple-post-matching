# BPSC Multiple Post Matching System

A Laravel 13, MySQL and Tabler application for uploading post-wise candidate Excel files, preserving all dynamic fields, reviewing cross-post identity matches, and exporting individual or combined candidate datasets. The interface is available to authenticated staff only.

## Installation

Requirements: PHP 8.3+, Composer, MySQL 8+, Node.js 20.19+ or 22.12+. Enable PDO MySQL, mbstring, intl, fileinfo, GD, ZIP, DOM/XML and iconv; tests also require PDO SQLite. Configure PHP to accept uploads up to 20 MB.

Use a new project folder and an empty database for the initial installation:

```bash
composer install
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate
```

Set `APP_URL` and your `DB_*` credentials in `.env`, then run:

```bash
php artisan migrate
php artisan db:seed
npm ci
npm run build
php artisan serve
```

Initial administrator: `testuser@gmail.com` / `12345678`. Change the password after signing in. Reseeding does not reset an existing administrator. Application time zone: Asia/Dhaka.

## Development workflow commands

Run the development server and Vite in separate terminals:

```bash
php artisan serve
npm run dev
```

Validation and asset preparation:

```bash
php artisan test
npm run build
php artisan optimize:clear
```

When a change includes a new migration:

```bash
php artisan migrate
```

Project decisions, detailed documentation and patch workflow are organized in [`docs/`](docs/README.md).
