# OrderReviews sigurno slanje

Ova nadogradnja zadržava postojeće OrderReviews postavke, predloške, review formu i kupone, ali zamjenjuje neograničeno cron slanje kontroliranim delivery workerom.

## Što se mijenja

- EasyCron URL mora sadržavati zaštitni token iz OrderReviews admin prozora.
- Javni stari URL bez tokena vraća HTTP 403.
- Zadano se pokušavaju najviše 2 poruke po izvršavanju i 2 poruke u jednom satu.
- Worker se zaustavlja nakon prve SMTP greške.
- Privremene greške čekaju 75 minuta, zatim 6 sati i 24 sata.
- Samo potvrđena SMTP greška nepostojeće adrese (npr. `5.1.1 user unknown`) trajno stavlja adresu na suppression listu; policy i autentikacijske greške ne blokiraju kupca.
- Stare narudžbe izvan zadanih 45 dana ne ulaze u queue.
- BCC, cron notification mail i sinkrono slanje kod promjene statusa namjerno su isključeni.
- Deduplikacija i sigurnosni log uvijek su uključeni, neovisno o staroj `LOG` postavci.

## Produkcijsko postavljanje

1. Ostaviti postojeći EasyCron isključen.
2. Napraviti backup baze.
3. Deployati izmijenjene datoteke.
4. Izvršiti [SQL migraciju](../database/migrations/20260827_orderreviews_safe_delivery.sql).
5. U OpenCartu otvoriti **Postavke → Trgovina → Mail** i spremiti:

   - Mail Engine: `SMTP`
   - Mail parametri: prazno
   - SMTP host: `ssl://mail.glazbena-kutija.hr`
   - SMTP korisničko ime: mailbox za slanje
   - SMTP lozinka: aktualna lozinka tog mailboxa
   - SMTP port: `465`
   - SMTP timeout: `10`

6. U OrderReviews postavkama ostaviti konzervativne vrijednosti:

   - Messages per run: `2`
   - Maximum attempts per hour: `2`
   - Oldest order: `45`
   - Maximum attempts per message: `3`
   - Email type: `link`

7. Na live serveru prvo napraviti dry-run SMTP testa:

   ```bash
   php scripts/orderreviews_smtp_test.php --to=tomislav@agmedia.hr --host=ssl://mail.glazbena-kutija.hr
   ```

8. Ako je preview ispravan, poslati točno jednu testnu poruku:

   ```bash
   php scripts/orderreviews_smtp_test.php --to=tomislav@agmedia.hr --host=ssl://mail.glazbena-kutija.hr --send
   ```

9. U OrderReviews adminu otvoriti **How to set up the cron job?** i kopirati novi zaštićeni URL u EasyCron. Raspored `31 11/12 * * *` može ostati isti.
10. Uključiti EasyCron tek nakon uspješnog testa. Očekivani odgovor je JSON sa statusom `ok` i brojačima `attempted`, `sent`, `retry`, `failed`, `suppressed` i `skipped`.

## Korisni SQL upiti

Pregled zadnjih delivery statusa:

```sql
SELECT delivery_id, store_id, order_id, reviewmail_id, email, status,
       attempts, available_at, last_attempt_at, sent_at,
       last_smtp_code, last_error
FROM oc_orderreviews_delivery
ORDER BY delivery_id DESC
LIMIT 50;
```

Pregled suppression liste:

```sql
SELECT suppression_id, email, reason, source, created_at, updated_at
FROM oc_orderreviews_suppression
ORDER BY suppression_id DESC;
```

Ručno dodavanje potvrđeno neispravne adrese, bez hardkodiranja u PHP:

```sql
INSERT INTO oc_orderreviews_suppression
       (email, reason, source, created_at, updated_at)
VALUES (LOWER(TRIM('adresa@example.com')), 'Confirmed hard bounce', 'manual', NOW(), NOW())
ON DUPLICATE KEY UPDATE
       reason = VALUES(reason), source = VALUES(source), updated_at = NOW();
```

## Zaustavljanje bez gubitka podataka

Ako se na produkciji pojavi neočekivana greška, prvo isključiti EasyCron i OrderReviews. Ne brisati nove tablice: one čuvaju deduplikaciju, retry i suppression stanje potrebno za siguran nastavak.
