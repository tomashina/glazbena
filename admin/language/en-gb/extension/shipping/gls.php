<?php
// Heading
$_['heading_title']                = 'GLS Croatia';

// Text
$_['text_extension']               = 'Shipping';
$_['text_success']                 = 'Success: GLS settings have been saved!';
$_['text_edit']                    = 'Edit GLS shipping';
$_['text_enabled']                 = 'Enabled';
$_['text_disabled']                = 'Disabled';
$_['text_all_zones']               = 'All Zones';
$_['text_none']                    = 'None';
$_['text_all_points']              = 'ParcelShop and Locker';
$_['text_parcel_shop']             = 'ParcelShop only';
$_['text_parcel_locker']           = 'Locker only';
$_['text_shipment_created']        = 'GLS label has been sent to MyGLS.';
$_['text_shipment_exists']         = 'GLS label has already been sent to MyGLS.';

// Entry
$_['entry_api_url']                = 'API URL';
$_['entry_username']               = 'Username';
$_['entry_password']               = 'Password';
$_['entry_client_number']          = 'Client number';
$_['entry_pickup_name']            = 'Sender name';
$_['entry_pickup_street']          = 'Sender street';
$_['entry_pickup_house_number']    = 'Sender house number';
$_['entry_pickup_house_info']      = 'House number info';
$_['entry_pickup_city']            = 'Sender city';
$_['entry_pickup_postcode']        = 'Sender postcode';
$_['entry_pickup_country']         = 'Sender country';
$_['entry_pickup_email']           = 'Sender email';
$_['entry_pickup_phone']           = 'Sender phone';
$_['entry_order_prefix']           = 'Order number prefix';
$_['entry_content']                = 'Parcel content';
$_['entry_printer_type']           = 'Label type';
$_['entry_print_position']         = 'Print position';
$_['entry_hide_phone']             = 'Hide phones on labels';
$_['entry_pickup_days']            = 'Pickup days offset';
$_['entry_filter_type']            = 'GLS point type';
$_['entry_cost']                   = 'Shipping cost';
$_['entry_free_total']             = 'Free above';
$_['entry_tax_class']              = 'Tax class';
$_['entry_geo_zone']               = 'Geo zone';
$_['entry_status']                 = 'Status';
$_['entry_sort_order']             = 'Sort order';

// Help
$_['help_api_url']                 = 'GLS REST API for Croatia: %s/ParcelService.svc/json/PrepareLabels';
$_['help_pickup_days']             = '0 means today, 1 means tomorrow.';
$_['help_house_number']            = 'GLS requires the house number as a separate numeric field.';

// Button
$_['button_create_shipment']       = 'Send label to GLS';
$_['button_label']                 = 'GLS label';

// Error
$_['error_permission']             = 'Warning: You do not have permission to modify GLS shipping!';
$_['error_missing_credentials']    = 'GLS username, password and client number are required to create shipments.';
$_['error_missing_point']          = 'The order has no selected GLS ParcelShop or locker.';
$_['error_missing_label']          = 'GLS label was not found for this order.';
$_['error_not_gls_order']          = 'The order is not GLS shipping.';
