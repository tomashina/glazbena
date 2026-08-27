<?php
// Naslov
$_['heading_title'] = 'AG media podsjetnik za recenziju';

// Tekst
$_['text_extension'] = 'Proširenja';
$_['text_success'] = 'Postavke podsjetnika za recenziju su spremljene.';
$_['text_token_success'] = 'Novi cron token je generiran. Prije sljedećeg izvršavanja zamijenite URL u EasyCronu.';
$_['text_settings'] = 'Postavke slanja';
$_['text_email'] = 'Predložak e-maila';
$_['text_cron'] = 'Zaštićeni cron URL';
$_['text_independent_notice'] = 'Ovo je neovisno AG media proširenje. Ne koristi OrderReviews licencu, usluge vanjskog dobavljača, kupone ni vanjsko praćenje.';
$_['text_core_review_notice'] = 'Gumb za svaki proizvod otvara standardni OpenCart obrazac za recenziju proizvoda. Prva verzija ne izrađuje niti šalje kupone.';
$_['text_placeholders'] = 'Dostupne oznake:';
$_['text_token_warning'] = 'S ovim URL-om postupajte kao s lozinkom. Svatko tko ga ima može pokrenuti worker. Nemojte ga javno objaviti niti pokazivati na snimkama zaslona.';
$_['text_confirm_regenerate'] = 'Postojeći EasyCron URL odmah će prestati raditi. Generirati novi token?';
$_['text_recent_deliveries'] = 'Posljednja slanja';
$_['text_no_deliveries'] = 'Još nema poruka u redu za slanje.';

// Polja
$_['entry_status'] = 'Status';
$_['entry_min_order_date'] = 'Početni datum narudžbi';
$_['entry_order_status'] = 'Dopušteni statusi narudžbe';
$_['entry_delay_days'] = 'Odgoda nakon narudžbe (dana)';
$_['entry_batch_size'] = 'Poruka po cron pozivu';
$_['entry_hourly_limit'] = 'Najviše pokušaja na sat';
$_['entry_max_age_days'] = 'Najstarija narudžba (dana)';
$_['entry_max_attempts'] = 'Najviše pokušaja po poruci';
$_['entry_sender_name'] = 'Naziv pošiljatelja';
$_['entry_sender_email'] = 'E-mail pošiljatelja';
$_['entry_smtp'] = 'Način slanja';
$_['entry_subject'] = 'Predmet';
$_['entry_body'] = 'HTML poruka';
$_['entry_cron_url'] = 'EasyCron URL';

// Pomoć
$_['help_order_status'] = 'U obzir ulaze samo narudžbe koje trenutačno imaju jedan od označenih statusa. Ponovni pokušaj se otkazuje ako narudžba više nije dopuštena.';
$_['help_min_order_date'] = 'Trajna sigurnosna granica. Narudžbe prije 27. 8. 2026. nikad se ne stavljaju u red niti šalju, čak ni ako se kasnije poveća maksimalna starost.';
$_['help_delay_days'] = 'Pričekaj ovoliko punih dana od datuma narudžbe prije dodavanja podsjetnika u red. Dopušteno: 0–90.';
$_['help_batch_size'] = 'Mala vrijednost štiti mail server. Započnite s 1. Dopušteno: 1–10.';
$_['help_hourly_limit'] = 'Tvrda sigurnosna granica za domenu. Broji se svaki SMTP pokušaj, uključujući neuspješne. Započnite s 1. Dopušteno: 1–2, čime ostaje rezerva ispod hosting granice od 5.';
$_['help_max_age_days'] = 'Starije narudžbe ne dodaju se u red. Vrijednost mora biti jednaka odgodi ili veća. Dopušteno: 1–365.';
$_['help_max_attempts'] = 'Privremene greške koriste sve dužu odgodu. Potvrđeno neispravni primatelji odmah se blokiraju. Dopušteno: 1–5.';
$_['help_sender'] = 'Vrijednosti se čitaju iz Sustav → Postavke → E-mail i ovdje se ne mogu mijenjati. SMTP host, port, korisničko ime i lozinka također se preuzimaju iz OpenCart postavki e-maila.';
$_['help_cron'] = 'Zalijepite cijeli HTTPS URL u EasyCron. Raspored svakih 15 minuta je siguran; granica serije i satna granica i dalje vrijede.';

// Gumbi
$_['button_copy'] = 'Kopiraj';
$_['button_regenerate'] = 'Generiraj novi token';

// Stupci
$_['column_order'] = 'Narudžba';
$_['column_email'] = 'Primatelj';
$_['column_status'] = 'Status';
$_['column_attempts'] = 'Pokušaji';
$_['column_updated'] = 'Ažurirano / poslano';
$_['column_error'] = 'Posljednja greška';

// Statusi slanja
$_['status_pending'] = 'Na čekanju';
$_['status_processing'] = 'Obrada';
$_['status_sending'] = 'Slanje';
$_['status_retry'] = 'Čeka ponovni pokušaj';
$_['status_sent'] = 'Poslano';
$_['status_failed'] = 'Neuspjelo';
$_['status_cancelled'] = 'Otkazano';
$_['status_suppressed'] = 'Blokirano';
$_['status_unknown'] = 'Nepoznato / ručna provjera';

// Greške
$_['error_permission'] = 'Nemate ovlasti za izmjenu podsjetnika za recenziju.';
$_['error_smtp_not_ready'] = 'Autenticirani SMTP nije potpuno podešen. Cron neće slati dok u Sustav → Postavke → E-mail nisu spremljeni SMTP način slanja, host, korisničko ime i lozinka.';
$_['error_reviews_disabled'] = 'OpenCart recenzije proizvoda su isključene. Uključite ih u postavkama trgovine prije aktiviranja ovog modula.';
$_['warning_guest_reviews'] = 'Recenzije gostiju su isključene. Kupci koji su naručili bez korisničkog računa morat će se prijaviti i možda neće moći ostaviti recenziju.';
$_['error_order_status'] = 'Označite barem jedan dopušteni status narudžbe.';
$_['error_min_order_date'] = 'Datum mora biti valjan i ne smije biti prije %s.';
$_['error_number_range'] = 'Unesite cijeli broj od %d do %d.';
$_['error_max_age_delay'] = 'Granica starosti narudžbe ne može biti kraća od odgode slanja.';
$_['error_subject'] = 'Predmet mora imati između 3 i 180 znakova.';
$_['error_body'] = 'Poruka mora sadržavati barem 10 vidljivih znakova i najviše 65.535 znakova.';
$_['error_body_placeholders'] = 'Poruka mora sadržavati oznake {products} i {unsubscribe_url}.';
