# Sidrene cijene i digitalni cjenik

Modul je prilagođen prema Libermediji za OpenCart 3.0.5.0 i Basel temu. U administraciji je dostupan kroz **Katalog → Sidrene cijene**, a također kroz popis modula.

## Početne cijene

Prema izričitoj uputi za Glazbenu, početni iznosi uzimaju se iz **trenutačnih maloprodajnih cijena**, uključujući važeću akciju ili količinski popust za jedan komad, a referentni datum za postojeći asortiman je **10. 9. 2026.** Prikaz glasi „Cijena na 10. 9. 2026.”; datum stvarnog unosa i izvor podataka ostaju zabilježeni u reviziji. Iznosi početnog stanja nisu rekonstruirani iz povijesnog cjenika.

Sidrena cijena sprema se odvojeno od cijene proizvoda. Naknadno spremanje proizvoda ili redoviti uvoz ne prepisuje postojeći zapis. Ručne izmjene i CSV uvoz bilježe administratora, razlog te staru i novu vrijednost. Propušteni proizvodi koje pronađe dnevna sinkronizacija ostaju na provjeri dok administrator potvrdi cijenu i datum.

JAN je HOT oznaka, ISBN uvezena cijena, a UPC interna oznaka ove trgovine. **Barkod za cjenik čita se samo iz EAN-a.** Prazan EAN je dopušten; upisani EAN mora imati valjanu GTIN kontrolnu znamenku.

## Instalacija i objava

Prije produkcijske instalacije napraviti backup baze i aplikacijskih datoteka. Prenijeti ovaj Git commit, pa iz korijena projekta pokrenuti s istim PHP-om i konfiguracijom baze kao webshop:

```sh
php scripts/anchor_price.php install
php scripts/anchor_price.php refresh
php scripts/anchor_price.php audit
php scripts/anchor_price.php publish
```

Instalacija je ponovljiva: čuva već unesene cijene, reviziju i publikacije. Instalira tablice, postavke, administracijske dozvole i OpenCart evente. Osvježavanje ponovno generira OpenCart modifikacije i briše predmemoriju predložaka. Dnevna objava provjerava sve aktivne proizvode; nepotpuni podaci zaustavljaju cijelu objavu.

## CSV uvoz

Administracija sadrži CSV predložak i obrazac za prijenos. Koristiti UTF-8 CSV, s ID-om proizvoda ili jedinstvenim modelom, iznosima, referentnim datumom, statusom i razlogom izmjene. Datoteka se u cijelosti provjerava prije zapisa. Neispravan redak poništava cijeli uvoz; uspješne izmjene upisuju se u jednoj transakciji i imaju revizijski trag.

## Javni URL-ovi

- Arhiva: `/index.php?route=information/price_list`
- Najnoviji CSV: `/index.php?route=information/price_list/latest&format=csv`
- Najnoviji XML: `/index.php?route=information/price_list/latest&format=xml`

Footer sadrži poveznicu na arhivu. CSV i XML predstavljaju isti dnevni snimak podataka, s aktualnom i sidrenom cijenom, datumom, EAN-om, količinom i dostupnošću. XML se izrađuje iz provjerenog arhivskog CSV-a, pa naknadne promjene proizvoda ne mijenjaju staru objavu. Datoteke imaju SHA-256 kontrolni zbroj; neispravne ili istekle datoteke nisu javno dostupne. Objave ostaju dostupne 30 dana.

## Dnevni raspored na poslužitelju

U hosting cron dodati dnevno pokretanje, primjerice u 06:00 po vremenskoj zoni Europe/Zagreb:

```cron
0 6 * * * cd /apsolutna/putanja/do/webshopa && /putanja/do/php scripts/anchor_price.php publish
```

Ponavljanje istoga dana vraća postojeću provjerenu publikaciju. Zaključavanje baze sprječava istodobne objave. Za vanjski cron postoji endpoint `/index.php?route=extension/module/anchor_price/cron`, s ključem iz administracije isključivo u HTTP zaglavlju `X-Anchor-Price-Key`. Ključ se ne stavlja u URL ili Git. Ovaj Git push sam po sebi ne mijenja produkciju niti hosting raspored.

## Provjere

```sh
php tests/anchor_price_csv_import_test.php
php tests/anchor_price_publication_test.php
php tests/anchor_price_frontend_test.php
php scripts/anchor_price.php audit
```

Integracijski testovi koriste izolirane tablice i privremene datoteke. Testiraju uvoz i rollback, potvrđene cijene, akcije, CSV/XML snimke, dnevnu nepromjenjivost, kontrolne zbrojeve, arhivu i skrivanje cijena. Pregledničke provjere obuhvaćaju desktop i mobitel, javne stranice i obavijest o zakonskom jamstvu.
