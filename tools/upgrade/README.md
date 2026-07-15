# OpenCart 3.0.5.0 deployment

This upgrade keeps the live database as the source of truth. Do not import the
local development database on production: orders, customers and content created
while development was in progress would be lost.

## Before deployment

1. Put the production site behind a hosting-level backup/snapshot if available.
2. Select PHP 8.1 for both the website and CLI.
3. Pull the tested Git revision into the production document root.
4. Choose a private backup directory outside the public web root.

## Run the upgrade

From the project root, run:

```sh
php tools/upgrade/deploy.php \
  --base-url=https://www.glazbena-kutija.hr/ \
  --backup-dir=/absolute/private/path/glazbena-upgrade-backup \
  --confirm=UPGRADE-3.0.5.0
```

The script:

- dumps the current live database before changing it;
- saves all OCMOD enabled/disabled states;
- enables maintenance mode;
- runs the official OpenCart database migrations;
- installs the locked Composer dependencies;
- applies the Glazbena compatibility patches and refreshes OCMOD;
- adds idempotent indexes for category, product, action-price and SEO URL queries;
- restores the previous maintenance state.

`Opencart SEO Pack PRO` is removed completely because its legacy OCMOD and admin
tools are incompatible with PHP 8.1/OpenCart 3.0.5.0. OpenCart SEO URLs and the
existing multilingual URL handling remain active.

## Verification

Check the homepage, a category, a product, a bundle product, cart add/remove,
checkout start and the admin dashboard. Confirm that new and recent production
orders are still present.

## Rollback

If the upgrade fails, leave maintenance mode enabled. Restore the code revision
that was deployed immediately before the upgrade and import the SQL dump printed
by the script. The matching OCMOD status JSON is saved beside that dump.
