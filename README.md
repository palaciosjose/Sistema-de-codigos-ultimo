# Web Codigos 5.0

## Database Configuration

Database credentials are no longer stored in `instalacion/basededatos.php`.
Instead, the application loads them from environment variables or from a
non-tracked file `config/db_credentials.php`.

1. **Using environment variables**: set `DB_HOST`, `DB_USER`, `DB_PASSWORD`
   and `DB_NAME` in your server environment.
2. **Using a credentials file**: copy `config/db_credentials.sample.php` to
   `config/db_credentials.php` and fill in your database details. This file is
   ignored by Git so your credentials remain private.

During installation the system will automatically create
`config/db_credentials.php` with the data you provide.

## Installation

For a step-by-step guide to installing the system, see
[docs/INSTALACION.md](docs/INSTALACION.md).

After cloning the repository run `composer install` to download the PHP
dependencies.

## License validation

The system verifies its license with the remote server every 24 hours. If the
server responds with an HTTP 4xx code, the license is marked as invalid
immediately and an overlay covers the interface to block access. Network errors
keep the previous validation date so the 76¥2day grace period starts from the last
successful check; once that period ends the overlay also activates and the system
remains blocked until the license is renewed.

Administrators can trigger a manual check by visiting
[manual_license_check.php](manual_license_check.php) or renew the license through
[renovar_licencia.php](renovar_licencia.php).

### Updating existing installations

After updating the license client, run a manual synchronization once so the
server can provide the new `license_type` and `expires_at` fields. You can do

## Development

Before pushing changes, run `composer lint` to ensure all PHP files are free of syntax errors.

this from the admin panel (**Licencia** tab ¡ú **Actualizar Datos de Licencia**)
or by visiting `admin/sync_license.php` directly.

## User Manual

Once installed and logged in, a **Manual** option appears in the navigation bar.
It links to an interactive help page (`manual.php`) with basic usage
instructions. The same content is also available in
[docs/MANUAL_USO.md](docs/MANUAL_USO.md).

## Telegram Bot (Experimental)

A new Telegram bot is being integrated to replicate the web search features. The initial skeleton lives under `telegram_bot/` and requires Composer dependencies. To install them run:

```bash
composer install
```

Configure your bot token in `telegram_bot/config/bot_config.php` and set up the webhook to point to `telegram_bot/webhook.php`.
From version 5.0.1 these valores pueden modificarse desde la pesta0Š9a **Bot Telegram** del panel de administraci¨®n.


Once configured, you can query codes via /codigo <id> or search with /buscar <palabras>. The bot uses the same database as the website, so results are consistent across both platforms.