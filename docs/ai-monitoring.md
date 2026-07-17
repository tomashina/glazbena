# AI discovery monitoring

Pojedinačne provjere:

```bash
php scripts/llms_txt_audit.php https://www.glazbena-kutija.hr/llms.txt
php scripts/openai_product_feed_audit.php "https://www.glazbena-kutija.hr/openai-products.jsonl?limit=100" 100
php scripts/jsonld_audit.php https://www.glazbena-kutija.hr/gitare ItemList
php scripts/jsonld_audit.php https://www.glazbena-kutija.hr/ OnlineStore
php scripts/indexnow_audit.php https://www.glazbena-kutija.hr
php scripts/ai_crawler_audit.php https://www.glazbena-kutija.hr
```

Objedinjena provjera bez sporijeg crawler matriksa:

```bash
php scripts/ai_regression_monitor.php https://www.glazbena-kutija.hr
```

Puna provjera nakon deploya ili WAF promjene:

```bash
php scripts/ai_regression_monitor.php https://www.glazbena-kutija.hr --with-crawlers
```

Preporuka je pokretati osnovni monitor jednom dnevno, a puni crawler audit nakon svake promjene robots/WAF/CDN pravila.
