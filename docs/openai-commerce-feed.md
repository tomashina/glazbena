# OpenAI / ChatGPT product feed

## Implementacija

Javni QA endpoint:

```text
https://www.glazbena-kutija.hr/openai-products.jsonl
```

Feed koristi OpenAI Stable non-Ads product shemu. Obavezna polja uključuju discovery/checkout zastavice, stabilni `item_id`, naziv, plain-text opis, canonical URL, brand, JPEG sliku, EUR cijenu, dostupnost, prodavatelja, povrate te HR geo oznake.

Checkout ostaje na web trgovini (`is_eligible_checkout=false`). Feed namjerno ne izmišlja jedinstvenu dostavnu cijenu jer postoje prag besplatne dostave i iznimke za glomazne artikle.

## Merchant onboarding sažetak

- Company: `Glazbena kutija d.o.o.`
- Website: `https://www.glazbena-kutija.hr`
- Headquarter country: `Croatia`
- Primary category: odabrati `Musical Instruments` ako je ponuđeno, inače najbližu retail/other kategoriju
- Interest: integracija product feeda za prikaz proizvoda u ChatGPT search rezultatima
- Feed size: `0-1M` (trenutno 4.216 jedinstvenih artikala u lokalnom full snapshotu)
- Checkout: na merchant webu, bez direktnog ChatGPT checkouta

Predloženi opis:

```text
Glazbena kutija is a Croatian musical instruments and pro audio retailer. We maintain a structured product discovery feed aligned with OpenAI's Stable non-Ads product schema. It includes canonical product URLs, plain-text descriptions, brand, JPEG images, EUR prices, current availability, return policy links, category paths and optional sale/review signals. Checkout remains on the merchant website.

QA endpoint:
https://www.glazbena-kutija.hr/openai-products.jsonl
```

Trenutni podatkovni gap: katalog nema pouzdane GTIN vrijednosti (UPC/JAN su uglavnom placeholderi), pa feed GTIN ne izmišlja i ne šalje. `mpn` koristi MPN, model ili SKU prema redoslijedu dostupnosti.

## Provjera

```bash
php scripts/openai_product_feed_audit.php "https://www.glazbena-kutija.hr/openai-products.jsonl?limit=100" 100
php scripts/openai_merchant_readiness.php "https://www.glazbena-kutija.hr/openai-products.jsonl?limit=1000"
```

## Produkcijski snapshot

OpenAI preporučuje full snapshot barem jednom dnevno, stabilan naziv datoteke i push na dodijeljeni SFTP. Podržan je `jsonl.gz`.

```bash
php scripts/openai_product_feed_snapshot.php \
  https://www.glazbena-kutija.hr/openai-products.jsonl \
  /sigurna/putanja/glazbena-kutija-products.jsonl.gz
```

Nakon odobrenja merchant onboardinga snapshot se šalje na OpenAI SFTP istim nazivom pri svakom osvježavanju. Javni endpoint ostaje koristan za QA, ali nije zamjena za službeni delivery kanal.

## Službeni izvori

- OpenAI Commerce get started: https://developers.openai.com/commerce/guides/get-started
- File upload overview: https://developers.openai.com/commerce/specs/file-upload/overview
- Stable products schema: https://developers.openai.com/commerce/specs/file-upload/products
- Merchant prijava: https://chatgpt.com/merchants/
