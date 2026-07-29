<?php
// Heading
$_['heading_title'] = 'WebP generator proizvoda';

// Text
$_['text_home'] = 'Početna stranica';
$_['text_extension'] = 'Proširenja';
$_['text_tool'] = 'Skupno generiranje WebP slika';
$_['text_intro'] = 'Generator unaprijed stvara WebP cache za glavne i dodatne slike proizvoda. Originalne slike i putanje u bazi ostaju nepromijenjene.';
$_['text_on_demand'] = 'Web trgovina već automatski stvara WebP pri prvom prikazu. Ovaj alat samo unaprijed priprema cijeli katalog kako kupac ili crawler ne bi čekao prvu konverziju.';
$_['text_source_images'] = 'Slike proizvoda';
$_['text_cached_webp'] = 'WebP datoteke u cacheu';
$_['text_cache_size'] = 'Veličina WebP cachea';
$_['text_target_count'] = 'Mogućih varijanti';
$_['text_dimensions'] = 'Dimenzije koje će se generirati';
$_['text_dimension_name'] = 'Namjena';
$_['text_dimension_size'] = 'Dimenzija';
$_['text_quality'] = 'WebP kvaliteta';
$_['text_batch_size'] = 'Slika po koraku';
$_['text_batch_help'] = 'Manje serije su sigurnije na sporijem serveru.';
$_['text_force'] = 'Ponovno generiraj postojeće WebP datoteke';
$_['text_force_help'] = 'Bez ove opcije postojeće i ažurne datoteke se brzo preskaču.';
$_['text_ready'] = 'Spremno za pokretanje.';
$_['text_starting'] = 'Pokrećem generator…';
$_['text_processing'] = 'Obrađeno %1$d od %2$d izvornih slika.';
$_['text_stopping'] = 'Zaustavljam nakon trenutne serije…';
$_['text_stopped'] = 'Generiranje je zaustavljeno. Možete ga ponovno pokrenuti; postojeće datoteke bit će preskočene.';
$_['text_complete'] = 'Generiranje je završeno.';
$_['text_generated'] = 'Generirano';
$_['text_skipped'] = 'Preskočeno';
$_['text_failed'] = 'Greške';
$_['text_errors'] = 'Posljednje greške';
$_['text_confirm_force'] = 'Ponovno generiranje može potrajati i zamijenit će postojeći WebP cache. Nastaviti?';

// Sizes
$_['size_product'] = 'Popisi proizvoda';
$_['size_thumb'] = 'Glavna slika proizvoda';
$_['size_popup'] = 'Uvećana slika';
$_['size_additional'] = 'Dodatne slike';
$_['size_related'] = 'Povezani proizvodi';
$_['size_compare'] = 'Usporedba';
$_['size_wishlist'] = 'Lista želja';
$_['size_cart'] = 'Košarica';

// Buttons
$_['button_generate'] = 'Generiraj WebP slike';
$_['button_stop'] = 'Zaustavi';
$_['button_cancel'] = 'Povratak';

// Errors
$_['error_permission'] = 'Nemate ovlasti za korištenje WebP generatora.';
$_['error_method'] = 'Neispravan način poziva generatora.';
$_['error_webp_support'] = 'PHP GD nema WebP podršku. Generator se ne može pokrenuti.';
$_['error_request'] = 'Zahtjev prema serveru nije uspio. Provjerite zapisnik grešaka i pokušajte ponovno.';
$_['error_generation'] = 'Generiranje nije uspjelo. Detalji su zapisani u OpenCart zapisnik.';
$_['error_invalid_path'] = 'Neispravna ili nesigurna putanja slike.';
$_['error_unsupported_type'] = 'Format slike nije JPG, PNG ili WebP.';
$_['error_missing_source'] = 'Izvorna slika ne postoji.';
$_['error_invalid_image'] = 'Datoteka nije ispravna slika.';
$_['error_invalid_size'] = 'Dimenzija slike nije ispravna.';
$_['error_directory_not_writable'] = 'Cache direktorij nije dostupan za pisanje.';
$_['error_conversion_failed'] = 'WebP konverzija nije uspjela.';
