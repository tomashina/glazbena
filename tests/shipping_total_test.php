<?php
// Offline regression coverage: php tests/shipping_total_test.php
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line);
});
$root = dirname(__DIR__);
require $root . '/system/engine/registry.php';
require $root . '/system/engine/model.php';
require $root . '/system/library/config.php';
require $root . '/system/library/cart/tax.php';
require $root . '/catalog/model/extension/total/shipping.php';
require $root . '/catalog/model/extension/shipping/ultimate_shipping.php';

$checks = 0;
function shippingAssert($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
class ShippingTestCart {
    public $shipping = true;
    public function hasShipping() { return $this->shipping; }
}
$registry = new Registry();
$config = new Config();
$config->set('total_shipping_sort_order', 3);
$registry->set('config', $config);
$cart = new ShippingTestCart();
$registry->set('cart', $cart);
$session = (object)array('data' => array());
$registry->set('session', $session);
$tax = new Cart\Tax($registry);
$rates = new ReflectionProperty(Cart\Tax::class, 'tax_rates');
$rates->setAccessible(true);
$vat = array('tax_rate_id' => 11, 'name' => 'VAT', 'rate' => 25, 'type' => 'P', 'priority' => 1);
$fixed = array('tax_rate_id' => 12, 'name' => 'Fixed shipping tax', 'rate' => 2, 'type' => 'F', 'priority' => 2);
$rates->setValue($tax, array(7 => array(11 => $vat, 12 => $fixed), 8 => array(11 => $vat)));
$registry->set('tax', $tax);
$model = new ModelExtensionTotalShipping($registry);

function shippingCalculate($model, $method) {
    global $session;
    $session->data = $method === null ? array() : array('shipping_method' => $method);
    $totals = array();
    $taxes = array(11 => 25.0);
    $amount = 100.0;
    $context = array('totals' => &$totals, 'taxes' => &$taxes, 'total' => &$amount);
    $model->getTotal($context);
    return array('totals' => $totals, 'taxes' => $taxes, 'total' => $amount);
}

foreach (array(array('cost' => 6.4), array('cost' => 0.0), array('cost' => 6.4, 'tax_class_id' => 0), array('cost' => 6.4, 'tax_class_id' => null)) as $method) {
    $method['title'] = 'Delivery';
    $result = shippingCalculate($model, $method);
    shippingAssert(count($result['totals']) === 1, 'Untaxed and zero-cost shipping must retain a shipping line.');
    shippingAssert(abs($result['total'] - (100 + $method['cost'])) < 0.00001, 'Missing tax metadata must preserve the delivery charge in the total.');
    shippingAssert($result['taxes'] === array(11 => 25.0), 'Missing or zero tax class must leave existing product taxes unchanged.');
    shippingAssert($result['totals'][0]['sort_order'] === 3 && $result['totals'][0]['title'] === 'Delivery', 'Shipping line metadata must remain unchanged.');
}
$result = shippingCalculate($model, array('title' => 'Taxed delivery', 'cost' => 8.0, 'tax_class_id' => '7'));
shippingAssert($result['total'] === 108.0, 'Shipping total must add its net charge once.');
shippingAssert($result['taxes'][11] === 27.0 && (float)$result['taxes'][12] === 2.0, 'Configured VAT must accumulate into existing tax buckets and fixed tax must be added.');
$result = shippingCalculate($model, array('title' => 'Free taxed delivery', 'cost' => 0.0, 'tax_class_id' => 8));
shippingAssert($result['total'] === 100.0 && $result['taxes'][11] === 25.0, 'Zero-cost percentage-tax shipping must not change total or existing taxes.');
$result = shippingCalculate($model, array('title' => 'Zero-cost fixed tax', 'cost' => 0.0, 'tax_class_id' => 7));
shippingAssert((float)$result['taxes'][12] === 2.0, 'An explicitly configured fixed shipping tax must keep its existing zero-cost behavior.');
$result = shippingCalculate($model, null);
shippingAssert($result['totals'] === array() && $result['total'] === 100.0, 'No selected method must not create a shipping charge.');
$cart->shipping = false;
$result = shippingCalculate($model, array('title' => 'Delivery', 'cost' => 8.0, 'tax_class_id' => 7));
shippingAssert($result['totals'] === array() && $result['taxes'] === array(11 => 25.0), 'A non-shipping cart must not add shipping or shipping taxes.');
$cart->shipping = true;

// Exercise the actual quote-to-session synchronization used by getQuote().
$ultimate = new ModelExtensionShippingUltimateShipping($registry);
$sync = new ReflectionMethod($ultimate, 'updateSelectedQuote');
$sync->setAccessible(true);
$quotes = array(
    'ultimate_shipping_0' => array('code' => 'ultimate_shipping.ultimate_shipping_0', 'title' => 'Standard delivery', 'cost' => 8.0, 'tax_class_id' => 7),
    'ultimate_shipping_1' => array('code' => 'ultimate_shipping.ultimate_shipping_1', 'title' => 'Free delivery', 'cost' => 0.0, 'tax_class_id' => 8)
);
$session->data = array();
$sync->invoke($ultimate, $quotes);
$default = $session->data['shipping_method'];
shippingAssert($default['tax_class_id'] === 7 && $default['cost'] === 8.0, 'A preselection quote must copy tax class together with cost/title.');
shippingAssert(!isset($default['code']), 'Preselection must leave the configured checkout default method free to choose.');
$result = shippingCalculate($model, $default);
shippingAssert($result['taxes'][11] === 27.0 && (float)$result['taxes'][12] === 2.0, 'Tax configured by Ultimate Shipping must survive preselection and reach order totals.');
$session->data['shipping_method'] = array('code' => 'pickup.pickup', 'title' => 'Collect in store', 'cost' => 0.0, 'tax_class_id' => 0);
$pickup = $session->data['shipping_method'];
$sync->invoke($ultimate, $quotes);
shippingAssert($session->data['shipping_method'] === $pickup, 'Generating delivery quotes must not overwrite selected Pickup.');
$session->data['shipping_method'] = array('code' => 'ultimate_shipping.ultimate_shipping_1', 'title' => 'Old delivery', 'cost' => 50.0, 'tax_class_id' => 7);
$sync->invoke($ultimate, $quotes);
shippingAssert($session->data['shipping_method']['cost'] === 0.0 && $session->data['shipping_method']['tax_class_id'] === 8, 'Selected Ultimate quote must refresh its own cost and tax class, including zero cost.');
shippingAssert($session->data['shipping_method']['code'] === 'ultimate_shipping.ultimate_shipping_1', 'Refreshing a quote must retain the selected code.');
$selected = $session->data['shipping_method'];
$sync->invoke($ultimate, array('ultimate_shipping_0' => $quotes['ultimate_shipping_0']));
shippingAssert($session->data['shipping_method'] === $selected, 'An unavailable selected quote must not be silently replaced by the first rate.');
$sync->invoke($ultimate, array());
shippingAssert($session->data['shipping_method'] === $selected, 'An empty quote response must leave selected shipping untouched.');

echo 'PASS: ' . $checks . " shipping total and quote synchronization assertions; warnings are test failures.\n";
