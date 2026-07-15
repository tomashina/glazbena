<?php
// Heading
$_['heading_title']                = 'GLS Hrvatska';

// Text
$_['text_extension']               = 'Dostava';
$_['text_success']                 = 'Uspješno: GLS postavke su spremljene!';
$_['text_edit']                    = 'Izmjeni GLS dostavu';
$_['text_enabled']                 = 'Omogućeno';
$_['text_disabled']                = 'Onemogućeno';
$_['text_all_zones']               = 'Sve zone';
$_['text_none']                    = 'Nema';
$_['text_all_points']              = 'ParcelShop i paketomat';
$_['text_parcel_shop']             = 'Samo ParcelShop';
$_['text_parcel_locker']           = 'Samo paketomat';
$_['text_shipment_created']        = 'GLS naljepnica je poslana u MyGLS.';
$_['text_shipment_exists']         = 'GLS naljepnica je već poslana u MyGLS.';

// Entry
$_['entry_api_url']                = 'API URL';
$_['entry_username']               = 'Korisničko ime';
$_['entry_password']               = 'Lozinka';
$_['entry_client_number']          = 'Client number';
$_['entry_pickup_name']            = 'Naziv pošiljatelja';
$_['entry_pickup_street']          = 'Ulica pošiljatelja';
$_['entry_pickup_house_number']    = 'Kućni broj pošiljatelja';
$_['entry_pickup_house_info']      = 'Dodatak kućnom broju';
$_['entry_pickup_city']            = 'Grad pošiljatelja';
$_['entry_pickup_postcode']        = 'Poštanski broj pošiljatelja';
$_['entry_pickup_country']         = 'Država pošiljatelja';
$_['entry_pickup_email']           = 'Email pošiljatelja';
$_['entry_pickup_phone']           = 'Telefon pošiljatelja';
$_['entry_order_prefix']           = 'Prefix broja narudžbe';
$_['entry_content']                = 'Sadržaj pošiljke';
$_['entry_printer_type']           = 'Tip naljepnice';
$_['entry_print_position']         = 'Pozicija ispisa';
$_['entry_hide_phone']             = 'Sakrij telefone na labeli';
$_['entry_pickup_days']            = 'Pickup za dana';
$_['entry_filter_type']            = 'Vrsta GLS mjesta';
$_['entry_cost']                   = 'Cijena dostave';
$_['entry_free_total']             = 'Besplatno iznad';
$_['entry_tax_class']              = 'Porezna stopa';
$_['entry_geo_zone']               = 'Geo zona';
$_['entry_status']                 = 'Status';
$_['entry_sort_order']             = 'Redoslijed sortiranja';

// Help
$_['help_api_url']                 = 'GLS REST API za Hrvatsku: %s/ParcelService.svc/json/PrepareLabels';
$_['help_pickup_days']             = '0 znači danas, 1 znači sutra. GLS API prima datum preuzimanja u zahtjevu za labelu.';
$_['help_house_number']            = 'GLS traži kućni broj kao zasebno numeričko polje.';

// Button
$_['button_create_shipment']       = 'Pošalji naljepnicu u GLS';
$_['button_label']                 = 'GLS labela';

// Error
$_['error_permission']             = 'Upozorenje: Nemate ovlasti mijenjati GLS dostavu!';
$_['error_missing_credentials']    = 'GLS korisničko ime, lozinka i client number moraju biti upisani za kreiranje pošiljke.';
$_['error_missing_point']          = 'Narudžba nema odabran GLS ParcelShop ili paketomat.';
$_['error_missing_label']          = 'GLS labela nije pronađena za ovu narudžbu.';
$_['error_not_gls_order']          = 'Narudžba nije GLS dostava.';
