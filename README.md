# Glazbena kutija

OpenCart 3.0.3.2 webshop, pripremljen za lokalni razvoj kroz Laravel Herd i PHP 7.4.

## Lokalno pokretanje

Preduvjeti:

- Laravel Herd s PHP 7.4
- MySQL 8+
- lokalni SQL dump `glazbena.sql`

```bash
cd /putanja/do/glazbena
herd link glazbena
herd isolate 7.4
herd secure glazbena

mysql -uroot -e "CREATE DATABASE glazbena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -e "CREATE USER IF NOT EXISTS 'glazbena'@'localhost' IDENTIFIED BY 'glazbena'; GRANT ALL PRIVILEGES ON glazbena.* TO 'glazbena'@'localhost';"
mysql -uglazbena -pglazbena glazbena < /putanja/do/glazbena.sql
```

Stranica je zatim dostupna na [https://glazbena.test](https://glazbena.test), a administracija na [https://glazbena.test/admin/](https://glazbena.test/admin/).

## Konfiguracija

Lokalne zadane vrijednosti nalaze se u `config.php` i `admin/config.php`. Za druga okruženja mogu se zadati environment varijable:

```text
APP_URL
DB_DRIVER
DB_HOSTNAME
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD
DB_PREFIX
CACHE_ENGINE
IMPORT_XML_VALIDATION_KEY
IMPORT_XML_PRODUCTS_URL
PAYPAL_CLIENT_ID
PAYPAL_CLIENT_SECRET
PAYPAL_SANDBOX_CLIENT_ID
PAYPAL_SANDBOX_CLIENT_SECRET
```

Produkcijske tajne ne smiju se spremati u Git.

## Baza i slike

SQL dump, generirane slike iz `image/cache` i biblioteka proizvoda iz `image/catalog` namjerno nisu u Gitu. Trenutna biblioteka medija ima oko 3 GB i treba se prenijeti zasebno (backup, objektni storage ili rsync). Nakon kloniranja kopiraj `image/catalog` iz sigurnog backupa; OpenCart će po potrebi ponovno napraviti `image/cache`.
