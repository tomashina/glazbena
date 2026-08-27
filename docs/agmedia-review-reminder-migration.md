# AGmedia Review Reminder: prijelaz s OrderReviews modula

`AGmedia Review Reminder` je neovisan OpenCart modul. Ne koristi iSenseLabs datoteke,
licencu, predloške ni njihov endpoint. Cilj prijelaza je sačuvati povijest slanja i
spriječiti da kupac dobije drugi podsjetnik za istu narudžbu.

## Što migracija čuva

Migracija [20260827_agmedia_review_reminder_migrate.sql](../database/migrations/20260827_agmedia_review_reminder_migrate.sql)
može se sigurno ponoviti i ne briše stare podatke. Kao namjerni, reverzibilni cutover
korak mijenja samo `oc_orderreviews_setting.Enabled` na `no`; druge stare
`oc_orderreviews_*` podatke ne mijenja. Kod svakog kasnijeg ponavljanja prvo treba
privremeno isključiti i novi cron; SQL se ne izvršava paralelno ni sa starim ni s
novim workerom.

| Stari izvor ili status | Novi status | Razlog |
| --- | --- | --- |
| `orderreviews_mail_log` | `sent` | Potvrđeni povijesni zapis slanja ima najveći prioritet. |
| postojeća recenzija u `orderreviews_log` | `suppressed` | Podsjetnik više nije potreban. |
| `pending` | `pending` | Nije bilo SMTP pokušaja. |
| `retry` | `retry` | SMTP nije prihvatio poruku; odgoda se čuva. |
| `processing` | `retry` | Stari worker je taj status zapisivao prije SMTP `DATA` granice; pokušaj se poništava. |
| `sending` ili `unknown` | `unknown` | Ishod može biti prihvaćen; automatsko ponovno slanje je trajno blokirano. |
| `sent`, `failed`, `cancelled`, `suppressed` | isti terminalni status | Povijesna odluka ostaje terminalna. |
| nepoznati budući status | `unknown` | Najsigurniji izbor protiv duplog slanja. |
| `orderreviews_suppression` | nova suppression tablica za svaki store | Adresa ostaje blokirana u svim trgovinama. |

Migracija ne stvara lažne retke u tablici pokušaja. Zato stari događaji ne troše novi
globalni limit pokušaja u zadnjih sat vremena.

Operativne postavke migriraju se samo jedanput:

- svi statusi narudžbi iz uključenih starih predložaka postaju novi popis statusa;
- ako je bilo više odgoda, uzima se najveća vrijednost kako podsjetnik ne bi krenuo
  ranije samo zbog spajanja predložaka;
- prenose se valjane vrijednosti za batch, maksimalnu starost i broj pokušaja, a
  stari satni limit ograničava se na novi sigurni maksimum `2`;
- novi modul ostaje isključen (`status = 0`) do ručne provjere;
- zaseban marker `agmedia_review_reminder_migration / 20260827_legacy_settings`
  sprječava da ponovno pokretanje SQL-a pregazi naknadne izmjene.

Stari subject, HTML poruka, obrazac, kuponi, licence i licence tokeni namjerno se ne
kopiraju. Novi modul isporučuje vlastite hrvatske i engleske predloške, vlastite linkove
na OpenCart recenzije i obavezni potpisani `{unsubscribe_url}`.

## Važna razlika u računanju odgode

Novi worker računa starost od nepromjenjivog `order.date_added`. Promjena statusa ili
uređivanje narudžbe ne pokreću odgodu ispočetka. Stari predlošci mogli su koristiti
`date_modified`. Prije uključivanja zato provjeriti je li migrirana odgoda primjerena;
granica `max_age_days` sprječava obradu cijele stare povijesti.

## Redoslijed aktivacije na liveu

1. Napraviti backup baze.
2. U EasyCronu isključiti stari URL
   `route=extension/module/orderreviews/sendEmails`. Ne ostavljati oba crona aktivna.
   Stari modul zasad ne deinstalirati: njegova `uninstall()` metoda briše povijesne
   `oc_orderreviews_*` tablice potrebne za migraciju, audit i mogući povratak.
3. Pričekati da eventualni već pokrenuti HTTP zahtjev završi. Brza provjera:

   ```sql
   SELECT status, COUNT(*) AS total, MAX(updated_at) AS last_change
   FROM oc_orderreviews_delivery
   WHERE status IN ('processing', 'sending')
   GROUP BY status;
   ```

   Migracija će stari `sending` svejedno pretvoriti u terminalni `unknown`, ali se SQL
   ne smije izvršavati paralelno s workerom koji još piše u bazu.

4. U OpenCart adminu otvoriti **Extensions → Extensions → Modules**, pronaći
   **AGmedia Review Reminder** i kliknuti **Install**. Modul se instalira isključen.
5. U phpMyAdminu odabrati točnu live bazu i izvršiti cijelu SQL migraciju. Live prefix
   u datoteci je već `oc_`. Migracija postavlja stari interni `Enabled=no` i marker
   koji stari cron endpoint pretvara u HTTP `410 Gone`; tako ga ni stari ili procurjeli
   EasyCron URL više ne može ponovno pokrenuti.
6. Spremiti rezultat završnih `SELECT` upita. Posebno pregledati broj zapisa
   `manual_review_unknown_records`; oni se namjerno nikad ne šalju automatski.
7. U novom modulu provjeriti:

   - SMTP prikazuje `SMTP` i ispravan host/port;
   - statuse narudžbi i odgodu;
   - `Messages per cron run = 1`;
   - `Maximum attempts per hour = 1` za početno razdoblje;
   - hrvatski i engleski tekst te prisutnost `{products}` i `{unsubscribe_url}`.

8. Kopirati novi zaštićeni URL koji sadrži:

   ```text
   route=extension/module/agmedia_review_reminder/cron&token=...
   ```

   U EasyCronu zamijeniti stari URL tim URL-om. Token se ne objavljuje ni šalje na
   screenshotu.
9. Dok je modul još isključen, pokrenuti EasyCron jednom ručno. Time se provjeravaju
   ruta i token bez slanja.
10. Uključiti modul, spremiti postavke i pokrenuti jedan ručni cron. Prvi test mora
    imati najviše jedan SMTP pokušaj. Zatim provjeriti novi delivery log i primljenu
    poruku prije ostavljanja EasyCrona uključenim.

Cron se može pozivati svakih 15 minuta ili ostaviti na rjeđem postojećem rasporedu.
Globalni satni limit obuhvaća sve storeove i svaki započeti SMTP pokušaj, uključujući
neuspjehe i pad procesa.

## Sigurni SMTP test bez narudžbe

Neovisni transport može se provjeriti bez dodavanja narudžbe u queue. Skripta čita
SMTP i hrvatske postavke novog modula iz OpenCart baze, ali nikad ne ispisuje lozinku
ni cron token. Bez `--send` samo renderira sintetičku hrvatsku poruku u memoriji:

```bash
php scripts/agmedia_review_reminder_smtp_test.php --to=tomislav@agmedia.hr
```

Za privremenu zamjenu samo SMTP hosta može se dodati, primjerice,
`--host=ssl://mail.glazbena-kutija.hr`. To ne mijenja postavku u bazi. Jedna testna
poruka šalje se isključivo eksplicitnim pozivom:

```bash
php scripts/agmedia_review_reminder_smtp_test.php \
  --to=tomislav@agmedia.hr \
  --host=ssl://mail.glazbena-kutija.hr \
  --send
```

Poruka ima oznaku `[TEST]`, lažni broj narudžbe i testni proizvod. Skripta radi samo
`SELECT` upite te ne mijenja kupca, narudžbu, queue, pokušaje ni suppression tablicu.
Izlazni kodovi su: `0` uspjeh, `1` poziv izvan CLI-ja, `2` nevaljani argumenti,
`3` konfiguracija/baza/datoteke, `4` nepotpune SMTP postavke, `5` blokirano slanje
zbog mail enginea ili porta te `6` SMTP transportna greška.

## Kontrolni SQL nakon migracije

```sql
SELECT status, COUNT(*) AS total
FROM oc_agmedia_review_reminder_delivery
GROUP BY status
ORDER BY status;

SELECT store_id, order_id, COUNT(*) AS duplicates
FROM oc_agmedia_review_reminder_delivery
GROUP BY store_id, order_id
HAVING COUNT(*) > 1;

SELECT COUNT(*) AS attempts_last_hour
FROM oc_agmedia_review_reminder_attempt
WHERE date_added >= DATE_SUB(NOW(), INTERVAL 1 HOUR);

SELECT COUNT(*) AS unknown_do_not_resend
FROM oc_agmedia_review_reminder_delivery
WHERE status = 'unknown';
```

Drugi upit mora vratiti nula redaka. `unknown` nije greška migracije: to je karantena
za poruke čiji se prijašnji SMTP ishod ne može dokazati.

## Povratak bez duplog slanja

Ako novi worker još nema nijedan pokušaj, dovoljno je isključiti novi EasyCron i novi
modul; stari cron može se privremeno vratiti tek nakon potvrde da nije bilo slanja i
ručnog uklanjanja cutover markera:

```sql
DELETE FROM oc_setting
WHERE store_id = 0
  AND code = 'module_agmedia_review_reminder'
  AND `key` = 'module_agmedia_review_reminder_legacy_endpoint_disabled';
```

Nakon toga stari modul treba ponovno uključiti u njegovim postavkama. Ovo je samo
kontrolirani rollback; oba workera nikad ne smiju biti uključena istodobno.

Ako tablica pokušaja sadrži retke, ne uključivati stari cron odmah. Prvo zaustaviti novi
cron i novi modul, a zatim u starom sent-logu terminalizirati potvrđene ili neizvjesne
nove isporuke:

```sql
INSERT INTO oc_orderreviews_mail_log (`date`, `store_id`, `order_id`)
SELECT COALESCE(d.sent_at, d.sending_at, d.date_modified), d.store_id, d.order_id
FROM oc_agmedia_review_reminder_delivery d
WHERE d.status IN ('sent', 'sending', 'unknown')
  AND NOT EXISTS (
    SELECT 1
    FROM oc_orderreviews_mail_log old_log
    WHERE old_log.store_id = d.store_id AND old_log.order_id = d.order_id
  );

INSERT INTO oc_orderreviews_suppression
  (`email`, `reason`, `source`, `created_at`, `updated_at`)
SELECT DISTINCT s.email, s.reason, 'agmedia_rollback', NOW(), NOW()
FROM oc_agmedia_review_reminder_suppression s
WHERE NOT EXISTS (
  SELECT 1 FROM oc_orderreviews_suppression old_s
  WHERE LOWER(TRIM(old_s.email)) = LOWER(TRIM(s.email))
);
```

Tek nakon te zaštite i pregleda statusa smije se uključiti stari worker. Nove tablice se
ne brišu: ostaju audit i deduplikacijska zaštita za kasniju ponovnu instalaciju.

Nakon duljeg urednog rada novog modula stare tablice mogu se zasebno arhivirati. Ne
koristiti **Uninstall** starog modula kao način uklanjanja licencnog upozorenja dok taj
arhiv nije napravljen; upozorenje se ne pojavljuje u novom, neovisnom modulu.

## Test migracije za razvoj

```bash
php tests/agmedia_review_reminder_migration_test.php
```

Test stvara samo jedinstvenu privremenu bazu ili jedinstveno prefiksirane tablice u
lokalnoj razvojnoj bazi, provjerava sva mapiranja i ponovno izvršavanje te ih na kraju
uklanja. Nikada ga ne pokretati s produkcijskim DB varijablama.
