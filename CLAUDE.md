# CLAUDE.md

Working notes for this repo. See `README.md` for setup and `COOLIFY.md` for deployment.

## Shape of the app

Laravel 13 + Filament 5 at `/admin` for staff, Inertia 3 + React 19 (TypeScript)
+ Tailwind 4 for the customer portal at `/portal`. `/` redirects to the portal
and is reserved for a future public website.

## Languages

`config/locales.php` lists them; the first (Dutch) is the **base language**.
Staff and the admin work in it. Customers get Dutch or French.

- **Content**: the base language lives in the normal columns (`name`,
  `description`…), so queries, search and sorting keep working. Other languages
  live in a `translations` jsonb column, `{"fr": {"name": "…"}}`, via the
  `HasTranslations` trait. Read with `$model->t('name')`: it falls back to
  the base column when a translation is empty. In Filament, add
  `Translations::section(fn ($locale) => [...])` with fields named
  `Translations::field($locale, 'name')`.
- **Interface text**: `lang/{nl,fr}/portal.php`, `orders.php`, `units.php` and
  `delivery.php` must keep identical keys (`LocalisationTest` checks this).
  PHP arrays silently keep the *last* duplicate key, so grep before adding one.
- **Which language**: `CustomerUser::preferredLocale()` returns the login's own choice,
  else the customer's, else the base language. `SetPortalLocale` uses that, else the
  session (switcher on the login page), else the browser. Mails to a
  `CustomerUser` model pick it up automatically (`HasLocalePreference`);
  staff mails are pinned to the base language in `PlaceOrder::notify()`.
- Order items snapshot the product name in every language
  (`translations.fr.product_name`), so a French confirmation stays French.
- `Money::format()` follows the current locale: `€ 1.234,50` / `1 234,50 €`.

## Two kinds of people, two guards

- **Staff** are `User` (guard `web`), log in at `/admin`, and are governed by
  roles and permissions (spatie/laravel-permission).
- **Customers** are `Customer` (the company) with one or more `CustomerUser`
  logins (guard `customer`, broker `customers`). A staff session never grants
  portal access, or the other way round.

`EnsureCustomerCanLogIn` re-checks on every portal request, so switching a login
or a whole customer off in the admin takes effect immediately.

## Permissions

`App\Support\Permissions` is the single list of what can be granted. Adding a
permission is: add it there → `php artisan permissions:sync` → check it in a
policy. The role and user forms are generated from that list (`PermissionMatrix`).

- Policies extend `PermissionPolicy`, which maps Filament abilities onto
  `{group}.{ability}`. Record-level narrowing goes in `canTouch()`.
- `Gate::before` gives super admins every *permission*, not every *ability*:
  structural rules in policies (never delete yourself, never delete a system
  customer type, orders are never created in the admin) still hold for them.
- **No privilege escalation.** `Grants` decides what an admin may hand out:
  only permissions they hold, only roles made entirely of those. Anything
  outside that set is preserved on save, not stripped (`Grants::merge`).
- **Customer-type scoping.** Staff linked to customer types only see those
  customers and orders (`visibleTo()` scopes + `canTouch()`), and only get
  order mails for them. No types linked = everything. A restricted admin can
  only create users restricted to a subset of their own types.

## Prices and orders

- The catalogue (`Product`) is shared; prices live on `PriceListItem`. A null
  price is a *dagprijs*: orderable, priced by staff afterwards.
- **Editing prices**: clicking a list opens `ManagePrices`, which lists *every*
  product with a price field (typed price = in the list, empty = out) and a
  day-price checkbox. The product form has the same per general list. Both
  write through `ListPrices::set()`. New lists can start from another list
  with a markup (`ListPrices::copy`); "Alle prijzen aanpassen" is `ListPrices::adjust`.
- **One price per product per customer**: `CustomerPrices::for($customer)`.
  A list linked to the customer directly beats their type's lists (that is how
  exception prices work, via the "Eigen prijzen" button on a customer); within
  a level the lowest fixed price wins. The portal shows exactly these items and
  `PlaceOrder` only accepts them.
- Which lists a customer can see at all: `Customer::visiblePriceLists()` (type
  lists + their own lists, filtered by `PriceList::currentlyValid()`).
- `PlaceOrder` is the only way an order is created. The portal only sends
  price list item ids + quantities; everything else is looked up again.
- Order items **snapshot** name, unit, price and VAT. Editing the catalogue
  never rewrites an order. After changing items call `$order->recalculate()`.
- Order numbers are `YYYY-NNNNNN` from max()+1; a collision on the unique
  index is retried in `PlaceOrder` (Postgres refuses `FOR UPDATE` on aggregates).
- Order mails go to `OrderRecipients::for($order)`: active staff with
  `orders.receive-notifications` who may see the customer's type, plus the
  type's `notification_emails`. Super admins get them only if given that
  permission directly (Extra rechten on their user).
- Money is formatted on the server (`App\Support\Money`, Belgian notation).
  The portal basket computes an estimate client-side with `Intl` pinned to nl-BE.

## Delivery rules

- `DeliverySchedule`: per ISO weekday `{enabled, cutoff_days, cutoff_time}`
  ("delivery Tuesday, order before Monday 16:00"), plus how far ahead customers can order and an
  optional minimum order amount. Exactly one schedule is the default (enforced on save).
- `DeliveryException`: closures (date range) and extra delivery days (single
  date, optional own deadline, else the day before at 16:00). They apply to
  every schedule, and an extra day beats a closure around it.
- Schedule resolution: the customer's own → their type's → the default. No
  schedule at all = no rules; the date is free and optional.
- `DeliveryCalendar` is the only thing that decides open dates. The portal
  picker, `PlaceOrder`'s server-side check and the admin's live preview all ask
  it. Don't compute delivery dates anywhere else.

## Mail

Production sends through a shared mailbox (OVH Zimbra, ~200 mails/hour per
account). Every queued mail and notification uses `RespectsMailQuota`: an
`outgoing-mail` rate limit (`MAIL_HOURLY_LIMIT`, default 150) where over-quota jobs
wait instead of failing, retry for up to 12 hours, and give up after 3 real exceptions.
New mailables/notifications must use the trait. `php artisan app:mail-test you@x`
sends one directly to check the SMTP settings.

## Portal front-end

- `lang/{locale}/portal.php` is shared whole as the `t` prop. Never hardcode visible
  text in a component; add a key there. `choice()` / `trans()` in `lib/i18n.ts`
  handle `:placeholders` and `{1}|[2,*]` plurals.
- The basket lives in localStorage per portal login (`lib/basket.ts`), keyed by
  price list item id, and is pruned against what the customer can order today.
- Mobile first: the people ordering are often in a kitchen with a phone.

## Filament gotchas

- Callbacks get their arguments **by parameter name**: `fn (Builder $query)`,
  `fn (Unique $rule)`, `fn (Get $get)`. A differently named parameter
  (`fn (Builder $q)`) silently gets a fresh, model-less builder and crashes
  when the form renders.
- Select option keys must match the stored format: `vat_rate` is cast to
  `decimal:2`, so the options are `'6.00'`, not `'6'`, or edit forms show empty.
- Rows in tables with `withCount()` carry `*_count` attributes; never
  `replicate()` such a record straight into a save.
- Eloquent `Collection::only()` filters by model key **and re-indexes** —
  after `keyBy()` use `get()`/`filter()`, not `only()`.
- `AdminFormsTest` opens and submits every form. Add new forms there, because
  a page loading fine says nothing about its modals.

## Checks

```
php artisan test
npm run typecheck
vendor/bin/pint
```

Mail templates are only compiled when rendered — `Mail::fake()` alone would not
catch a broken Blade file, which is why there are explicit render tests.
