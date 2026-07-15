<?php
class ControllerStartupSession extends Controller {
    public function index() {
        // API bypass (ostavi kako je)
        if (isset($this->request->get['api_token']) && isset($this->request->get['route']) && substr($this->request->get['route'], 0, 4) == 'api/') {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "api_session` WHERE TIMESTAMPADD(HOUR, 1, date_modified) < NOW()");
            $api_query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "api` `a` LEFT JOIN `" . DB_PREFIX . "api_session` `as` ON (a.api_id = as.api_id) LEFT JOIN " . DB_PREFIX . "api_ip `ai` ON (a.api_id = ai.api_id) WHERE a.status = '1' AND `as`.`session_id` = '" . $this->db->escape($this->request->get['api_token']) . "' AND ai.ip = '" . $this->db->escape($this->request->server['REMOTE_ADDR']) . "'");
            if ($api_query->num_rows) {
                $this->session->start($this->request->get['api_token']);
                $this->db->query("UPDATE `" . DB_PREFIX . "api_session` SET `date_modified` = NOW() WHERE `api_session_id` = '" . (int)$api_query->row['api_session_id'] . "'");
            }
            return;
        }

        // ---------- LAZY SESSION + BOT GUARD ----------
        $method = $this->request->server['REQUEST_METHOD'] ?? 'GET';
        $route  = $this->request->get['route'] ?? '';
        $ua     = strtolower($this->request->server['HTTP_USER_AGENT'] ?? '');

        // 1) Ne pokreći sesiju za očite botove i HEAD/OPTIONS
        $is_bot = (bool)preg_match('~(bot|crawler|spider|slurp|ahrefs|semrush|yandex|bingpreview|facebookexternalhit|python-requests|wget|curl)~i', $ua);
        if ($is_bot || $method === 'HEAD' || $method === 'OPTIONS') {
            return;
        }

        // 2) Pokreni sesiju samo kad je stvarno trebamo:
        //    - već postoji PHPSESSID cookie
        //    - Nije GET (POST/PUT/DELETE)
        //    - ruta je jedna od "stateful" (account/checkout/cart/affiliate/compare/wishlist/api/* itd.)
        $stateful_routes = [
            'account', 'account/login', 'account/register', 'account/account',
            'checkout', 'checkout/cart', 'checkout/checkout',
            'product/compare', 'account/wishlist',
            'api/', 'extension/module', 'common/currency', 'common/language'
        ];
        $has_session_cookie = isset($_COOKIE[$this->config->get('session_name')]);
        $is_stateful_route  = ($route && (
            $route === 'checkout/checkout' ||
            $route === 'checkout/cart'     ||
            strpos($route, 'account') === 0 ||
            strpos($route, 'api/') === 0   ||
            strpos($route, 'product/compare') === 0 ||
            strpos($route, 'account/wishlist') === 0
        ));

        if (!$has_session_cookie && $method === 'GET' && !$is_stateful_route) {
            // Čisti anon GET bez cookieja -> NE pokreći sesiju
            return;
        }
        // ----------------------------------------------

        // Ako smo došli ovdje, sesija je potrebna
        if (isset($_COOKIE[$this->config->get('session_name')])) {
            $session_id = $_COOKIE[$this->config->get('session_name')];
        } else {
            $session_id = '';
        }

        $this->session->start($session_id);

        // Ispravno postavi cookie s modernim opcijama
        $name  = $this->config->get('session_name');
        $value = $this->session->getId();

        // Odredi domain/path – koristi osnovni host i '/' path
        $host = parse_url(defined('HTTPS_SERVER') ? HTTPS_SERVER : HTTP_SERVER, PHP_URL_HOST);
        if (!$host) { $host = $_SERVER['HTTP_HOST'] ?? ''; }

        $cookie_options = [
            'expires'  => 0,               // session cookie
            'path'     => '/',             // uvijek root
            'domain'   => $host,           // npr. glazbena-kutija.hr
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        // PHP 7.3+ signature
        setcookie($name, $value, $cookie_options);
    }
}
