# Deploying on Coolify

The app ships as a Docker Compose stack: `docker-compose.yml` + `Dockerfile`.

| Service | What it does |
|---|---|
| `app` | nginx + PHP-FPM on port 8080. Runs migrations, `storage:link` and the Laravel caches on every start. |
| `queue` | `queue:work`: sends the order, invitation and password mails. |
| `scheduler` | `schedule:work`: daily cleanup of expired links and failed jobs. |
| `postgres` | PostgreSQL 17, data in the `postgres` volume. |

Uploads (product photos) live in the `storage` volume and survive redeploys.

## 1. Create the resource

1. **New resource → Docker Compose → your Git repository**, branch `main`.
2. Compose file: `docker-compose.yml`.
3. Set the domain on the **app** service (e.g. `https://bestellen.martinsversevis.be`).
   Coolify routes it to port 8080 and handles TLS.

## 2. Environment variables

Coolify generates `SERVICE_USER_POSTGRES` and `SERVICE_PASSWORD_POSTGRES` by itself.
Fill in the rest:

| Variable | Value |
|---|---|
| `APP_KEY` | Output of `php artisan key:generate --show` (run it locally). **Keep it**: changing it logs everyone out and breaks pending invitation links. |
| `APP_URL` | The public URL, e.g. `https://bestellen.martinsversevis.be`. Used in e-mail links. |
| `MAIL_USERNAME` | The full OVH mailbox address, e.g. `bestellingen@martinsversevis.be`. |
| `MAIL_PASSWORD` | That mailbox's password. |
| `MAIL_FROM_ADDRESS` | **The same address as `MAIL_USERNAME`.** OVH only lets a mailbox send as itself. |
| `MAIL_CUSTOMER_REPLY_TO` | Optional. Where customers' replies to confirmations and invitations go, e.g. the client's own `info@martinsversevis.be`. Needed when the sending mailbox isn't the client's. |

The mail server settings default to OVH (Zimbra and MX Plan use the same ones):
`MAIL_HOST=smtp.mail.ovh.net`, `MAIL_PORT=587`, `MAIL_SCHEME=smtp`. The connection
is still encrypted: on 587 it upgrades to TLS (STARTTLS) before logging in. Only set
them to use another provider.

OVH also accepts SSL on port 465, but many hosting providers block outgoing 465 on
new servers, which shows up as "Connection timed out" in `app:mail-test`. Check
which ports get out from the app container with:

```bash
php -r 'foreach ([465, 587] as $p) { echo $p, ": ", @fsockopen("smtp.mail.ovh.net", $p, $e, $s, 5) ? "open" : "BLOCKED ($s)", PHP_EOL; }'
```

If both are blocked, ask the host to lift the block, or move to a provider with an
HTTP sending API (Brevo, Postmark), which needs a small code change.

### About OVH's free mail

- OVH allows roughly **200 mails per hour per mailbox** and blocks the mailbox
  for spam when that is exceeded. The app stays under it: all mail goes through a
  queue limited to 150/hour (`MAIL_HOURLY_LIMIT`), and anything over waits its
  turn instead of failing. A normal order is 2–4 mails.
- Use a **dedicated mailbox** for the app (e.g. `bestellingen@`), not someone's
  personal one: mails sent by hand count toward the same quota.
- In the OVH control panel, check that the domain has **SPF and DKIM** enabled for
  OVH mail, or customers' spam filters will eat the order confirmations.
- If volume ever grows (newsletters, hundreds of customers), switch to a
  transactional provider (Brevo, Postmark, Mailgun) by changing `MAIL_HOST`,
  `MAIL_PORT`, `MAIL_SCHEME` and the credentials. No code change needed.

## 3. First deploy

After the first successful deploy, open a terminal on the **app** container in Coolify and run:

```bash
php artisan db:seed --force          # permissions, default roles, customer types
php artisan app:create-admin         # the first super admin
```

Both are safe to repeat. After adding a permission in `App\Support\Permissions`,
run `php artisan permissions:sync` on the app container (or `db:seed --force` again).

## 4. Check it works

- `https://<domain>/up` returns 200.
- On the app container: `php artisan app:mail-test you@example.com`. It sends one mail
  directly and prints the SMTP settings in use, with a warning if the sender doesn't
  match the mailbox.
- Log in at `/admin`, create a customer with a portal login, and check that
  the invitation arrives. If it doesn't, look at the **queue** service logs.

## Backups

Enable **Scheduled backups** on the postgres service in Coolify. The `storage`
volume holds uploaded product photos; include it in server backups if photos
matter.
