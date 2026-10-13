# Development and patch workflow

## Stable root README

The root `README.md` contains the project name, short overview, installation instructions and development command list. It is excluded from Paste & Replace patches. Do not use it for release history, changing design decisions, detailed matching rules or validation reports.

The v1.0.1 delivery contains the requested revised README in `Initial-README/README.md`, outside the patch directory, for one-time manual placement. Subsequent patch deliveries do not include or replace the root README.

## Documentation organization

Update relevant files under `docs/` with each patch. Maintain the documentation index, decision register, feature descriptions and a release note with the actual validation performed. Never claim checks that were not run.

## Patch delivery

Deliver only changed/new files in a `Paste-Replace/` directory, keeping their paths relative to the project root. Include required compiled assets and the updated Vite manifest when frontend source changes. Include relevant `docs/` changes. Exclude root README, `.env`, dependencies, local databases, logs, compiled Blade caches and development secrets.

Copy the contents of `Paste-Replace/` into the existing project root. Preserve the existing `.env` and APP_KEY. Do not run `key:generate`, `migrate:fresh` or `migrate:refresh` when applying a patch.

Run `composer install` if Composer dependency files change, and `npm ci` if npm dependency files change. Run `php artisan migrate` only when a patch contains new migrations. Run `php artisan optimize:clear` after applying the patch. Built production assets are supplied; use `npm run build` when rebuilding locally. Remove `public/hot` if a development server is no longer running and production assets are being used.

## Validation

Run checks appropriate to the change: `php artisan test`, `npm run build`, and Blade compilation for view changes. Use `php artisan view:cache` to verify templates, then `php artisan view:clear` when resuming local development. Document runtime and database coverage in the release note.

Update the footer version in `config/matching.php` for released patches. Keep matching behavior and stored source values unchanged during shell-only changes.

For password-toggle changes, also run `node --test tests/ui/password-toggle.test.mjs`. Ownership changes require the included migration before route access.
