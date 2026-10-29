<?php
// Run with: php tests/anonymous_cart_queries_test.php
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once __DIR__ . '/../system/engine/registry.php';
require_once __DIR__ . '/../system/library/cart/cart.php';

define('DB_PREFIX', 'test_');
$checks = 0;

function anonymousCartAssert($condition, $message) {
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

class AnonymousCartTestDatabase {
    public $queries = array();

    public function query($sql) {
        $this->queries[] = $sql;
        return (object)array('rows' => array(), 'row' => array('total' => 0), 'num_rows' => 0);
    }

    public function escape($value) {
        return addslashes((string)$value);
    }
}

class AnonymousCartTestSession {
    public $data = array();
    public $id = '';

    public function getId() {
        return $this->id;
    }
}

class AnonymousCartTestCustomer {
    public $id = 0;

    public function getId() {
        return $this->id;
    }
}

function anonymousCartFixture($session_id = '', $customer_id = 0) {
    $registry = new Registry();
    $db = new AnonymousCartTestDatabase();
    $session = new AnonymousCartTestSession();
    $session->id = $session_id;
    $customer = new AnonymousCartTestCustomer();
    $customer->id = $customer_id;
    $registry->set('db', $db);
    $registry->set('session', $session);
    $registry->set('customer', $customer);

    return array(new Cart\Cart($registry), $db, $session);
}

list($cart, $db, $session) = anonymousCartFixture();
anonymousCartAssert($cart->getProducts() === array(), 'An anonymous request without a session must have an empty cart.');
anonymousCartAssert($cart->countProducts() === 0, 'An anonymous request must show zero cart items.');
anonymousCartAssert($cart->getSubTotal() === 0, 'An anonymous request must have a zero subtotal.');
anonymousCartAssert($cart->getTaxes() === array(), 'An anonymous request must have no cart tax.');
anonymousCartAssert($cart->hasProducts() === 0, 'An anonymous request must not have products.');
anonymousCartAssert(count($db->queries) === 0, 'Cart construction and repeated header totals must issue no queries without a session.');

// The guard must use the live session rather than remember constructor state.
$session->id = 'new-shopping-session';
$cart->getProducts();
anonymousCartAssert(count($db->queries) === 1 && strpos($db->queries[0], "session_id = 'new-shopping-session'") !== false, 'A session started later must immediately enable cart reads.');
$cart->add(123, 2);
anonymousCartAssert(strpos($db->queries[2], 'INSERT INTO test_cart') === 0 && strpos($db->queries[2], "session_id = 'new-shopping-session'") !== false, 'Adding a product after starting a session must keep the existing cart write path.');

list($cart, $db) = anonymousCartFixture('guest-shopping-session');
anonymousCartAssert(count($db->queries) === 1 && strpos($db->queries[0], 'DELETE FROM test_cart') === 0, 'Active guest sessions must retain expired-cart cleanup.');
$cart->getProducts();
anonymousCartAssert(count($db->queries) === 2 && strpos($db->queries[1], "session_id = 'guest-shopping-session'") !== false, 'Active guests must retain their existing cart query.');

list($cart, $db, $session) = anonymousCartFixture('api-shopping-session');
$session->data['api_id'] = 8;
$cart->getProducts();
anonymousCartAssert(count($db->queries) === 2 && strpos($db->queries[1], "api_id = '8'") !== false, 'API sessions must retain maintenance and reads scoped to their API ID.');

list($cart, $db) = anonymousCartFixture('customer-shopping-session', 42);
anonymousCartAssert(count($db->queries) === 3, 'Logged-in customers must retain cleanup, session reassignment and guest-cart merge lookup.');
anonymousCartAssert(strpos($db->queries[1], 'UPDATE test_cart SET session_id') === 0 && strpos($db->queries[1], "customer_id = '42'") !== false, 'Customer cart reassignment must remain unchanged.');
anonymousCartAssert(strpos($db->queries[2], "customer_id = '0'") !== false && strpos($db->queries[2], "session_id = 'customer-shopping-session'") !== false, 'Guest-cart merge must still use the active customer session.');

// Customer ownership also preserves the path if an integration supplies an ID
// before its session has been initialized.
list($cart, $db) = anonymousCartFixture('', 42);
$cart->getProducts();
anonymousCartAssert(count($db->queries) === 4, 'A customer ID must preserve existing cart behavior even without a session ID.');

echo 'PASS: ' . $checks . " anonymous cart query assertions; warnings are test failures.\n";
