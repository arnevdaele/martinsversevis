# Martins Verse Vis — bestelportaal

Customer portal and back office for Martins Verse Vis.

- **`/admin`**: staff back office (Filament). Customers and their portal
  logins, customer types, products, price lists, orders, staff accounts, and roles & permissions.
- **`/portal`**: customer portal (Inertia + React), in Dutch or French.
  Customers see the price lists meant for them, pick a delivery day, compose
  an order, and follow up on their orders.
- **`/`**: reserved for a future public website; redirects to the portal for now.

## Local setup

Requirements: PHP 8.4, Composer, Node 22+.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed --seeder=DemoSeeder
php artisan storage:link
composer dev
```

`composer dev` starts the web server, the queue worker, the log viewer and Vite.
Mails go to `storage/logs/laravel.log` (`MAIL_MAILER=log`).

### Demo logins (DemoSeeder only)

The logins are listed in `database/seeders/DemoSeeder.php`:

| Who | Where | Sees |
|---|---|---|
| Super admin | `/admin` | everything |
| Verkoop | `/admin` | only Horeca customers and their orders |
| Chef (Horeca) | `/portal` | the Horeca price list |
| Particulier | `/portal` | the Particulier price list |
| La Marée (French) | `/portal` | the Horeca list, in French, with the Horeca delivery round |

### First admin on a clean install

```bash
php artisan db:seed            # permissions, default roles, customer types — safe to rerun
php artisan app:create-admin   # asks for name, e-mail and password
```

## How it fits together

1. **Customer types** (Zakelijk, Particulier, plus any you add) group customers.
2. **Price lists** are linked to customer types, and optionally to individual
   customers as an exception. They can have a validity period.
3. **Customers** get one or more **portal logins**. Adding a login sends an
   invitation to choose a password.
4. A customer orders from their lists. The order goes to every staff member
   with the "receive order e-mails" permission who can see that customer type,
   plus the extra addresses on the customer type. The customer gets a
   confirmation.
5. Staff fill in any day prices, confirm, and mark the order delivered.

### Delivery rules (Levering)

- **Delivery schedules**: per weekday, whether you deliver and until when customers
  can order for it ("Tuesday, order before Monday 16:00"). Also how far ahead
  customers can order and an optional minimum order amount. One schedule is the default; customer
  types and individual customers can get their own. A live preview shows exactly which days a
  customer would be offered.
- **Closures & extra days**: holidays and one-off extra delivery days,
  for all schedules at once, with a reason customers see.

With no schedule at all, customers pick any date (or none).

### Languages

Customers and their logins have a language (Dutch or French); the portal also
has an NL/FR switch. Products, categories, price lists and delivery notices
have an optional French version (the "Vertalingen" block in each form);
anything left empty shows the Dutch text. Staff mails stay in Dutch. Adding
English later means adding it to `config/locales.php` and adding `lang/en/`.

### Roles & permissions

Three roles ship by default and can all be edited (except Super admin):

- **Super admin**: everything, including staff and roles.
- **Beheerder**: everything except staff and roles.
- **Verkoop**: customers, portal logins and orders; read-only catalogue.

Staff can also be limited to specific customer types, and can get extra
permissions on top of their roles. Nobody can grant a permission they don't
hold themselves.

## Tests

```bash
php artisan test
npm run typecheck
```

## Deployment

See [COOLIFY.md](COOLIFY.md).
