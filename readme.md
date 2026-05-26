Before deploying create .env file based on this template:

```# .env template
APP_ENV=dev
APP_SECRET=yMAE96M0GqpKXDy9bqQ4gV60arJAkf6Y
DATABASE_URL="mysql://yfai:yfai@127.0.0.1:3306/yfai?serverVersion=10.11.2-MariaDB&charset=utf8mb4"
LOCK_DSN=flock
CORS_ALLOW_ORIGIN=^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$
WEBSITE_NAME="YFAI"
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE="ms+BdAzFhis7vwYP1IG6sQptvGggxLE2Hc8CGv2nRDI="
MAILER_DSN=null://null
```
excute this command to generate JWT keys:
Composer install, 
Npm install 
// Tailwind build
php bin/console tailwind:build 
npm run build 

```bash
mkdir -p config/jwt
php bin/console lexik:jwt:generate-keypair
```

to create a user execute:

```bash
php bin/console app:create:user
```

## Running tests

PHPUnit is configured to run in the `test` environment against an isolated SQLite database at `var/phpunit.db`.
The test bootstrap recreates the SQLite schema before the suite runs, so local MySQL credentials are not required for the default test command.

```bash
php bin/phpunit --testdox
```

If you intentionally override `DATABASE_URL` to use MySQL/MariaDB for tests, create the test database and grant the configured user access before running PHPUnit. Symfony's test Doctrine config appends a `_test` suffix to the database name.
