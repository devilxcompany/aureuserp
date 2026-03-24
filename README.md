# Aureus ERP

ERP Management for Devil X Company — built with Laravel 11 + Filament 3.

## Requirements

- PHP 8.2+
- Composer 2+
- SQLite (development) or PostgreSQL/Supabase (production)

## Quick Start (Local Development)

```bash
# 1. Install dependencies
composer install

# 2. Set up environment
cp .env.example .env
php artisan key:generate

# 3. Set up database (SQLite by default)
touch database/database.sqlite
php artisan migrate --seed

# 4. Start the server
php artisan serve
```

Visit **http://localhost:8000/admin** to access the admin panel.

### Default Admin Credentials

| Field    | Value             |
|----------|-------------------|
| Email    | admin@admin.com   |
| Password | admin123456       |

## Production Setup (Supabase PostgreSQL)

Update `.env` to use PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=db.your-project.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-db-password
```

Then run:

```bash
php artisan migrate --seed
```

## Directory Structure

```
app/
├── Filament/          # Filament admin resources & pages
│   ├── Pages/
│   ├── Resources/
│   └── Widgets/
├── Http/
│   └── Controllers/
├── Models/
│   └── User.php
└── Providers/
    └── Filament/
        └── AdminPanelProvider.php

database/
├── migrations/        # Database schema
└── seeders/
    ├── DatabaseSeeder.php
    └── AdminUserSeeder.php

routes/
├── web.php            # Web routes
└── api.php            # API routes
```

## Features

- ✅ **Admin Panel** — Filament 3 admin interface at `/admin`
- ✅ **Authentication** — Login/logout with session management
- ✅ **Roles & Permissions** — Spatie Laravel Permission
- ✅ **SQLite** — Zero-config local development
- ✅ **PostgreSQL/Supabase** — Production-ready database support

## Tech Stack

- **Framework:** Laravel 11
- **Admin UI:** Filament 3
- **Roles:** Spatie Laravel Permission
- **Database:** SQLite (dev) / PostgreSQL (prod)
