# Integrated ERP (ISEP-ERP)

Inventory, sales, payments and employee management for one organisation.
Laravel 13, Vue 3, Inertia.js, Tailwind CSS and MySQL, following the project SRS.

## Requirements

PHP 8.3 or newer, Composer 2, Node.js with npm, MySQL 8 (Laragon works well on Windows), Git.

## First-time setup

1. Clone the repository and open the folder.
2. Install the packages:

        composer install
        npm install

3. Create the settings file and the application key:

        copy .env.example .env
        php artisan key:generate

4. In phpMyAdmin create TWO empty databases, both with collation utf8mb4_unicode_ci:
   isep_erp (the real data) and isep_erp_test (wiped by every test run).
5. Open .env and set ADMIN_EMAIL and ADMIN_PASSWORD (at least 10 characters, in double quotes).
   Never commit .env.
6. Create the tables and the starting data (roles, permissions, units, Walk-in customer, admin):

        php artisan migrate
        php artisan db:seed

7. Build the front end and start the site, then sign in with ADMIN_EMAIL and ADMIN_PASSWORD:

        npm run build
        php artisan serve

## Tests

        php artisan test

Tests use the isep_erp_test database and WIPE it on every run. Before running them, check that
phpunit.xml says isep_erp_test and never isep_erp.

## Rules for the team

- The schema is exactly the 29 business tables of the SRS. Sessions, cache and queues use files,
  so .env keeps SESSION_DRIVER=file, CACHE_STORE=file, QUEUE_CONNECTION=sync.
- Only migrations change the database. Nobody edits a migration that is already in main.
- Money is decimal(12,2), quantities are decimal(12,3). Never floats.
- The server checks every permission. Hiding a menu item is not security.
- Never commit .env, passwords, API keys or Chapa secrets.
- One branch per task, a Pull Request into main, merged with "Create a merge commit".
- Resolving a conflict: delete the WHOLE marker lines, then run the checks (php -l, npm run build,
  php artisan test) before committing.

## Where things live

| What | Where |
|---|---|
| Routes, one file per module, each required once in routes/web.php | routes/*.php |
| Pages (Vue, capital P) | resources/js/Pages |
| Layout and menu entries | resources/js/Layouts/AppLayout.vue, resources/js/navigation.js |
| Permission names and role map | database/seeders/PermissionSeeder.php, RoleSeeder.php |

## Owners

Access control, audit log, employees, QR attendance, dashboard: Eldana.
Catalogue, inventory, procurement, sales, Chapa payments: Hawi.