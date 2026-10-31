# Zaštita kataloga od preopterećenja

## Incident 31. listopada 2026.

U produkcijskom HTTPS access logu od 18:00 do približno 18:42 po vremenu
Europe/Zagreb zabilježeno je 28.375 zahtjeva; 24.642 sadržavala su `sort`, `order`,
`limit` ili `page`. Promet je bio raspoređen na više IP adresa i različite
User-Agent vrijednosti. Samo 14 zahtjeva odnosilo se na javni cjenik ili modul
sidrenih cijena. To podupire zaključak da je glavni okidač bio val varijanti
kataloga, uz skupu obradu filtera, a ne dnevna objava sidrenih cijena.

## Ponašanje

- `index.php` provjerava GET/HEAD varijante javnog kataloga prije pokretanja baze,
  sesija i predložaka. Obični URL-ovi bez tih parametara i eksplicitne rute
  kupnje, računa, API-ja i feedova ne ulaze u ograničenje.
- Najviše tri takva zahtjeva obrađuju se istodobno. Prekoračenje vraća HTTP 429
  i `Retry-After: 2`; zaključavanje se otpušta završetkom zahtjeva.
- Po IP adresi dopušten je početni nalet od 30 zahtjeva, uz obnavljanje jednog
  dopuštenja u sekundi. Koristi se `REMOTE_ADDR`; proslijeđena HTTP zaglavlja i
  naziv bota ne mogu dati iznimku.
- Broj proizvoda po stranici ograničen je na 100; stranica na 10.000. Neispravni
  parametri se normaliziraju prije upita i izrade ključeva predmemorije.
- Stanje zaštite nalazi se u privatnom poddirektoriju
  `DIR_CACHE/catalog-request-guard/`, s ograničenim brojem datoteka i zapisa.
  Ako pohrana nije dostupna, zahtjev se propušta. Postojeći checkout ne ovisi
  o toj pohrani.
- Ukupni broj proizvoda dijeli predmemoriju između različitih stranica i
  sortiranja. Digital Elephant Filter broji u bazi te ponovno koristi uređeni
  popis ID-eva između stranica umjesto ponavljanja cijelog upita.
- Datotečna predmemorija nove zapise čita i zamjenjuje preko stalnog naziva
  `cache.KEY.data`, bez pretraživanja direktorija pri redovnom dohvatu i zapisu.
  Stari zapisi ostaju čitljivi tijekom prijelaza. Brisanje prefiksa i dalje
  poništava sve pripadajuće ključeve, a zaključano čišćenje radi jednom u minuti.
  `set()` zamjenjuje točan ključ; eksplicitni `delete()` poništava prefiks.

Zaštita smanjuje posljedice opaženog obrasca prometa. Ne zamjenjuje ograničenje
prometa na web poslužitelju/CDN-u za velike distribuirane napade ili druge rute.

## Provjere prije objave

Pokrenuti PHP-om 8.1, koji koristi produkcija:

```sh
php tests/catalog_request_guard_test.php
php tests/digital_elephant_catalog_cache_test.php
php tests/timed_catalog_cache_test.php
php tests/file_cache_index_test.php
php tests/anonymous_cart_queries_test.php
php tests/anchor_price_frontend_test.php
git diff --check
```

## Git objava na postojećoj produkciji

Produkcijski korijen je `/home/newzuzi/glazbena`, a PHP
`/opt/cpanel/ea-php81/root/usr/bin/php`. Koristi se grana
`codex/opencart-3.0.5.0` i GitHub `origin`.

Prije ažuriranja pregledati `git status` i razlike. Sačuvati sve lokalne promjene,
konfiguracije i tajne. Ako lokalna izmjena preklapa dolazni commit, prvo utvrditi
je li već sadržana u dolaznoj Git reviziji; ne prepisivati je naslijepo. Privatni
Git stash za provjerene preklapajuće datoteke može sačuvati prethodno stanje.
Ne primjenjivati ga ponovno preko iste izmjene koja je upravo stigla kroz Git.

Nakon lokalnih provjera, pregleda razlika, commita i potvrđenog pusha:

```sh
umask 022
git fetch origin
git merge --ff-only origin/codex/opencart-3.0.5.0
/opt/cpanel/ea-php81/root/usr/bin/php scripts/anchor_price.php refresh
```

`refresh` je postojeći postupak projekta za regeneriranje OCMOD-a i predložaka;
ne objavljuje dnevni cjenik niti mijenja spremljene sidrene cijene. Provjeriti
novi commit na produkciji, sintaksu izmijenjenih datoteka, kategoriju s varijantama,
sortiranje, filter, proizvod sa sidrenom cijenom, košaricu i susjedni Zuzi webshop.
Potvrditi postojanje datoteka zaključavanja nakon jednog stvarnog zahtjeva.
Ne izvoditi masovni test opterećenja na zajedničkom produkcijskom serveru.

Pri povratku na stariju verziju datotečne predmemorije obavezno ponovno pokrenuti
postojeći `refresh` nakon Git povrata, jer stari backend ne poznaje novu omotnicu
zapisa. Povrat izvornog koda mora ostati kroz Git.
