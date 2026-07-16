<?php
// Heading
$_['heading_title']      = 'CorvusPay';

// Text 
$_['text_payment']       = 'Plaćanja';
$_['text_success']       = 'Uspješno: Napravili ste promjene u CorvusPay korisničkom računu!';
$_['text_corvuspay']       = '<a href="http://www.CorvusPay.info/" target="_blank"><img src="view/image/payment/corvuspay.png" alt="CorvusPay" title="CorvusPay" style="border: 1px solid #EEEEEE;" /></a>';
$_['text_successful']    = 'On';
$_['text_off']           = 'Off';
$_['text_edit']          = 'Editiraj CorvusPay';
$_['help_entry_callback']= 'Ovaj URL mora biti postavljen kao URL za uspjeh u CorvusPay Merchant portalu, uz HTTP metodu POST.';
$_['help_entry_total']   = 'Minimalna ukupna suma koja mora biti na narudžbi prije nego što ovaj način plaćanja postane aktivan.';
$_['help_entry_pending_status'] = 'Opcionalno. Koristi zaseban status koji nije označen kao Processing ili Complete, primjerice "Čeka CorvusPay". Ako je isključeno, narudžba ostaje u Missing Orders do uspješnog odgovora.';
      
// Entry
$_['entry_merchant']     = 'ID prodajnog mjesta (ShopID):';
$_['entry_password']     = 'Tajni ključ (SecretKey):';
$_['entry_callback']     = 'URL za slanje odgovora:';

$_['entry_authorisationtype']     = 'Tip autorizacije:';
$_['entry_authorisationtype0']     = 'Authtorizacija u dva koraka (predautorizacija)';
$_['entry_authorisationtype1']     = 'Autorizacija u jednom koraku (automatska autorizacija)';

$_['entry_test']         = 'Test mod:';
$_['entry_total']        = 'Ukupno:';
$_['entry_order_status'] = 'Status uspješnog plaćanja:';
$_['entry_pending_status'] = 'Status tijekom plaćanja:';
$_['entry_geo_zone']     = 'Geo zona:';
$_['entry_status']       = 'Status:';
$_['entry_sort_order']   = 'Redoslijed sortiranja:';

// Error
$_['error_permission']   = 'Upozorenje: Nemate ovlasti da mijenjate CorvusPay korisnički račun!';
$_['error_merchant']     = 'ID prodajnog mjesta (ShopID)!';
$_['error_password']     = 'Lozinka je obavezan podatak!';
$_['error_order_status']  = 'Odaberite status za uspješno CorvusPay plaćanje!';
$_['error_pending_status'] = 'Status tijekom plaćanja ne smije biti u OpenCart Processing ili Complete statusima jer bi prerano rezervirao zalihu i potrošio kupone.';
?>
