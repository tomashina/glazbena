<?php
// Naslov
$_['heading_title'] = 'Glazbena kutija – Sidrene cijene';

// Tekst
$_['text_extension'] = 'Proširenja';
$_['text_list'] = 'Registar sidrenih cijena';
$_['text_edit'] = 'Uredi sidrenu cijenu';
$_['text_filter'] = 'Filtri';
$_['text_publications'] = 'Dnevni cjenici';
$_['text_settings'] = 'Postavke i automatizacija';
$_['text_no_results'] = 'Nema pronađenih zapisa.';
$_['text_all_statuses'] = 'Svi statusi';
$_['text_status_confirmed'] = 'Potvrđeno';
$_['text_status_pending'] = 'Čeka provjeru';
$_['text_status_disabled'] = 'Isključeno';
$_['text_system'] = 'Sustav';
$_['text_missing_count'] = 'Aktivnih artikala bez sidrene cijene: %s';
$_['text_success_edit'] = 'Uspješno: Sidrena cijena i revizijski trag su ažurirani.';
$_['text_success_sync'] = 'Uspješno: Kreirano je %s nedostajućih sidrenih cijena.';
$_['text_success_publish'] = 'Uspješno: Objavljen je cjenik: %s';
$_['text_success_settings'] = 'Uspješno: Postavke sidrenih cijena su spremljene.';
$_['text_reference_rule'] = 'Početna osnovica koristi aktualne cijene koje je odabrao trgovac, uključujući aktivne akcije, s referentnim datumom %s za artikle koji su tada već postojali. Za kasnije dodane artikle potrebno je potvrditi stvarni datum prve objave.';
$_['text_cron_help'] = 'Pozovite URL svaki radni dan prije 08:00 po vremenu Europe/Zagreb i pošaljite ključ u HTTP zaglavlju X-Anchor-Price-Key. Objavljuje jedan dnevni cjenik web trgovine.';
$_['text_audit'] = 'Revizijski trag';

// Stupci
$_['column_product'] = 'Artikl';
$_['column_model'] = 'Model / SKU';
$_['column_net_price'] = 'Neto sidrena cijena';
$_['column_gross_price'] = 'Bruto sidrena cijena';
$_['column_reference_date'] = 'Referentni datum';
$_['column_status'] = 'Status';
$_['column_action'] = 'Radnja';
$_['column_location'] = 'Prodajno mjesto';
$_['column_sequence'] = 'Redni broj';
$_['column_filename'] = 'Datoteka';
$_['column_products'] = 'Artikala';
$_['column_published'] = 'Objavljeno';
$_['column_user'] = 'Korisnik';
$_['column_reason'] = 'Razlog';
$_['column_before'] = 'Prije';
$_['column_after'] = 'Poslije';
$_['column_date_added'] = 'Datum';

// Polja
$_['entry_filter_name'] = 'Naziv artikla';
$_['entry_filter_model'] = 'Model / SKU';
$_['entry_filter_status'] = 'Status provjere';
$_['entry_date_from'] = 'Referentni datum od';
$_['entry_date_to'] = 'Referentni datum do';
$_['entry_price'] = 'Neto iznos';
$_['entry_gross_price'] = 'Bruto iznos';
$_['entry_reference_date'] = 'Referentni datum';
$_['entry_verification_status'] = 'Status provjere';
$_['entry_reason'] = 'Razlog promjene';
$_['entry_default_unit'] = 'Zadana prodajna jedinica';
$_['entry_cron_url'] = 'URL dnevnog cron zadatka';
$_['entry_cron_key'] = 'Cron ključ';

// Gumbi
$_['button_filter'] = 'Filtriraj';
$_['button_clear'] = 'Očisti';
$_['button_sync'] = 'Kreiraj nedostajuće sidrene cijene';
$_['button_publish'] = 'Objavi CSV cjenik';
$_['button_settings'] = 'Spremi postavke';
$_['button_download'] = 'Preuzmi';

// Pomoć
$_['help_reason'] = 'Obvezno. Razlog se trajno čuva u revizijskom tragu.';
$_['help_gross_price'] = 'Bruto snapshot s porezom. Mijenjajte samo kada je potrebno ispraviti sam spremljeni snapshot.';
$_['help_default_unit'] = 'Koristi se za sve artikle jer trgovina nema strukturirano polje prodajne jedinice. Zadano: kom.';

// Upozorenja i greške
$_['warning_publication_due'] = 'Dnevni cjenik nije objavljen do 08:00.';
$_['error_permission'] = 'Upozorenje: Nemate ovlasti za izmjenu modula Sidrene cijene.';
$_['error_not_installed'] = 'Tablice modula ne postoje. Najprije instalirajte modul kroz Proširenja.';
$_['error_not_found'] = 'Tražena sidrena cijena nije pronađena.';
$_['error_price'] = 'Unesite ispravan nenegativan neto iznos.';
$_['error_gross_price'] = 'Unesite ispravan nenegativan bruto iznos.';
$_['error_reference_date'] = 'Unesite ispravan datum u obliku GGGG-MM-DD.';
$_['error_status'] = 'Odaberite ispravan status provjere.';
$_['error_reason'] = 'Razlog mora sadržavati između 3 i 255 znakova.';
$_['error_default_unit'] = 'Zadana jedinica mora sadržavati između 1 i 16 znakova.';
$_['error_file_missing'] = 'Datoteka objave nije dostupna ili joj je istekao rok čuvanja od 30 dana.';

$_['text_csv_import'] = 'Skupni uvoz sidrenih cijena';
$_['entry_csv_file'] = 'CSV datoteka';
$_['button_import_csv'] = 'Uvezi CSV';
$_['button_csv_template'] = 'Preuzmi predložak';
$_['help_csv_import'] = 'Početna osnovica za postojeće artikle koristi aktualne iznose prema odabiru trgovca i referentni datum 2026-09-10. CSV uvoz omogućuje ispravak i potvrdu cijena i datuma uz obvezan razlog. Cijela datoteka provjerava se prije spremanja; ako je jedan redak neispravan, ništa se ne mijenja. Sve promjene ostaju u revizijskom tragu.';
$_['help_csv_columns'] = 'UTF-8, najviše 8 MB i 10.000 artikala; separator ; ili zarez. Obvezni su product_id ili jedinstveni model, price (neto), gross_price (bruto), reference_date (GGGG-MM-DD), verification_status (confirmed, pending ili disabled) i reason (3–255 znakova). Iznosi imaju najviše četiri decimale. Ako su oba identifikatora upisana, moraju odgovarati istom artiklu.';
$_['text_success_import'] = 'Uspješno: Uvezeno je %s sidrenih cijena s revizijskim tragom.';
$_['error_csv_upload'] = 'Odaberite ispravno prenesenu CSV datoteku do 8 MB.';
