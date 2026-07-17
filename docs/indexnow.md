# IndexNow

Implementacija koristi queue + cron pristup:

- admin promjene proizvoda, kategorija, brandova i informacijskih stranica upisuju povezane URL-ove u `indexnow_queue`;
- vanjski API se ne poziva tijekom admin spremanja;
- cron šalje batch prema IndexNowu i koristi eksponencijalni retry;
- javni key file je `/2c958bbf39ddd92b3c8a3e680b995e7a.txt`.

Provjera:

```bash
php scripts/indexnow_audit.php https://www.glazbena-kutija.hr
php scripts/indexnow_submit.php https://www.glazbena-kutija.hr --dry-run
```

Primjer crona svakih deset minuta:

```bash
cd /putanja/do/glazbena && /usr/local/bin/php scripts/indexnow_submit.php https://www.glazbena-kutija.hr --limit=1000 >> /putanja/do/logs/glazbena-indexnow.log 2>&1
```

Za ponovni pokušaj trajno označenih neuspjeha:

```bash
php scripts/indexnow_submit.php https://www.glazbena-kutija.hr --retry-failed --limit=1000
```
