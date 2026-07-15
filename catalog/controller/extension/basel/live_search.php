<?php
class ControllerExtensionBaselLiveSearch extends Controller {
    public function index() {
        $json = array('products' => array(), 'total' => 0, '__source' => 'basel_live_search');

        // --- Helpers ---
        $norm = function($s) {
            $map = array('š'=>'s','đ'=>'d','č'=>'c','ć'=>'c','ž'=>'z','Š'=>'s','Đ'=>'d','Č'=>'c','Ć'=>'c','Ž'=>'z');
            $s = html_entity_decode((string)$s, ENT_QUOTES, 'UTF-8');
            $s = strtr($s, $map);
            $s = utf8_strtolower($s);
            $s = preg_replace('/\s+/u', ' ', $s);
            return trim($s);
        };
        $hasAlnum = function($s) {
            return (bool)preg_match('/[[:alnum:]]/u', $s);
        };

        // --- Input & validation ---
        $search = isset($this->request->get['filter_name']) ? (string)$this->request->get['filter_name'] : '';
        $search_norm = $norm($search);
        $is_valid = ($search_norm !== '' && utf8_strlen($search_norm) >= 2 && $hasAlnum($search_norm));

        $this->load->language('basel/basel_theme');
        $json['basel_text_view_all']  = $this->language->get('basel_text_view_all');
        $json['basel_text_no_result'] = $this->language->get('basel_text_no_result');
        $json['search_url']           = $this->url->link('product/search');

        // Nevažeći upit -> prazno
        if (!$is_valid) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            $this->response->addHeader('Pragma: no-cache');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // --- Params ---
        $limit         = 5;
        $currency_code = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');
        $image_width   = (int)$this->config->get('theme_default_image_cart_width');
        $image_height  = (int)$this->config->get('theme_default_image_cart_height');
        $title_length  = 80;

        $this->load->model('catalog/product');
        $this->load->model('tool/image');

        $filter_data = array(
            'filter_name'         => $search,
            'filter_tag'          => $search,
            'filter_category_id'  => 0,
            'filter_sub_category' => '',
            'sort'                => 'p.sort_order',
            'order'               => 'ASC',
            'start'               => 0,
            'limit'               => $limit
        );

        // 1) Zbroj iz modela
        $total_from_model = (int)$this->model_catalog_product->getTotalProducts($filter_data);
        if ($total_from_model === 0) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            $this->response->addHeader('Pragma: no-cache');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // 2) Rezultati
        $results = $this->model_catalog_product->getProducts($filter_data);

        // 3) Dodatni “sanity filter” na backendu:
        //    - normaliziraj naziv/tag/kodove
        //    - traži frazu ILI (sve riječi prisutne)
        $phrase = $search_norm;
        $words = array_values(array_filter(explode(' ', $phrase), function($w){ return utf8_strlen($w) >= 2; }));
        $kept = array();

        foreach ($results as $r) {
            $hay = $norm(
                (isset($r['name']) ? $r['name'] : '') . ' ' .
                (isset($r['tag']) ? $r['tag'] : '') . ' ' .
                (isset($r['model']) ? $r['model'] : '') . ' ' .
                (isset($r['sku']) ? $r['sku'] : '') . ' ' .
                (isset($r['upc']) ? $r['upc'] : '') . ' ' .
                (isset($r['ean']) ? $r['ean'] : '') . ' ' .
                (isset($r['jan']) ? $r['jan'] : '') . ' ' .
                (isset($r['isbn']) ? $r['isbn'] : '') . ' ' .
                (isset($r['mpn']) ? $r['mpn'] : '')
            );

            $phrase_match = ($phrase !== '' && mb_strpos($hay, $phrase, 0, 'UTF-8') !== false);
            $all_words_match = true;
            foreach ($words as $w) {
                if (mb_strpos($hay, $w, 0, 'UTF-8') === false) { $all_words_match = false; break; }
            }

            if ($phrase_match || $all_words_match) {
                $kept[] = $r;
            }
        }

        if (empty($kept)) {
            // Ništa nije preživjelo sanity filter -> prazno
            $this->response->addHeader('Content-Type: application/json');
            $this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            $this->response->addHeader('Pragma: no-cache');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // 4) Render JSON iz filtriranih rezultata
        $json['total'] = count($kept);

        foreach ($kept as $result) {
            $image = !empty($result['image'])
                ? $this->model_tool_image->resize($result['image'], $image_width, $image_height)
                : $this->model_tool_image->resize('placeholder.png', $image_width, $image_height);

            if (($this->config->get('config_customer_price') && $this->customer->isLogged()) || !$this->config->get('config_customer_price')) {
                $price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $currency_code);
            } else {
                $price = false;
            }

            $special = !empty($result['special'])
                ? $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $currency_code)
                : false;

            $name = html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8');
            if (utf8_strlen($name) > $title_length) {
                $name = utf8_substr(strip_tags($name), 0, $title_length) . '..';
            }

            // stock badge
            $color = '#4caf50';
            $ss = isset($result['stock_status']) ? $result['stock_status'] : '';
            if (in_array($ss, array('In arrival','U dolasku','Nedostupno','Out Of Stock','Izvan proizvodnje','Discontinued'))) {
                $color = 'red';
            } elseif (in_array($ss, array('Naruči po narudžbi','Order on request'))) {
                $color = '#0054a6';
            }
            $stock_html = ((int)$result['quantity'] <= 0)
                ? '<span class="outofstock" style="text-transform:uppercase;color:' . $color . '">' . $ss . '</span>'
                : '<span class="instock" style="text-transform:uppercase">' . $this->language->get('basel_text_in_stock') . '</span>';

            $json['products'][] = array(
                'product_id' => (int)$result['product_id'],
                'quantity'   => $stock_html,
                'image'      => $image,
                'name'       => $name,
                'price'      => $price,
                'special'    => $special,
                'url'        => $this->url->link('product/product', 'product_id=' . (int)$result['product_id'])
            );
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $this->response->addHeader('Pragma: no-cache');
        $this->response->setOutput(json_encode($json));
    }
}
