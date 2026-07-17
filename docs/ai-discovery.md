# AI discovery / answer-engine optimizacija

Ovaj projekt ima tehnički sloj za klasične tražilice, AI search i product discovery.

## Javni resursi

- `/llms.txt` - kurirani Markdown indeks. To je dopunski, predloženi standard; ne zamjenjuje robots, sitemap, sadržaj ni strukturirane podatke.
- `/ai-context` - ljudski čitljiv pregled stabilnih resursa.
- `/openai-products.jsonl` - javni QA endpoint proizvoda prema OpenAI Stable product feed shemi.
- `/sitemap-index.xml` - glavni XML sitemap index.
- `/robots.txt` - zajednička crawler pravila s eksplicitnim AI discovery agentima.

## Strukturirani podaci

- Product stranice: `Product`, `Offer`, `AggregateRating`, `BreadcrumbList` i `ItemPage`.
- Kategorije, brandovi i akcije: `CollectionPage` + `ItemList` s proizvodima te breadcrumbima.
- Trgovina: `OnlineStore`, kontaktni podaci i `MerchantReturnPolicy`.
- Dostupnost razlikuje `InStock`, `BackOrder`, `OutOfStock` i `Discontinued` prema količini i statusu artikla.

## Produkcijski WAF blocker

Live audit 16. srpnja 2026. pokazao je:

- `OAI-SearchBot`: HTTP 403
- `ChatGPT-User`: HTTP 403
- `Claude-SearchBot`, `Claude-User`, `PerplexityBot`, `Googlebot`, `Bingbot`: HTTP 200
- training botovi `GPTBot` i `ClaudeBot`: HTTP 403

Za prikazivanje u ChatGPT search rezultatima treba u Cloudflare/WAF-u dopustiti verificirani `OAI-SearchBot` promet i objavljene OpenAI IP raspone. Odluka o training botovima (`GPTBot`, `ClaudeBot`) odvojena je od search/discovery dopuštenja.

Nakon WAF izmjene pokrenuti:

```bash
php scripts/ai_crawler_audit.php https://www.glazbena-kutija.hr
```

## Deploy

1. Deployati kod i osvježiti OpenCart OCMOD modifications/cache.
2. Provjeriti da `/llms.txt`, `/ai-context`, `/openai-products.jsonl` i IndexNow key vraćaju HTTP 200.
3. Riješiti OpenAI discovery WAF 403.
4. Pokrenuti puni regresijski monitor.
5. U Google Search Consoleu provjeriti Product/Merchant listing izvještaje i ponovno poslati sitemap.

```bash
php scripts/ai_regression_monitor.php https://www.glazbena-kutija.hr --with-crawlers
```
