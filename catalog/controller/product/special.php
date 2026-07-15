<?php

use Agmedia\Models\Category\Category;

class ControllerProductSpecial extends Controller {
    public function index() {
        $this->load->language('product/special');

        $this->load->model('catalog/product');


        $this->load->model('catalog/category');

        $this->load->model('tool/image');

        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'p.sort_order';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        if (isset($this->request->get['limit'])) {
            $limit = (int)$this->request->get['limit'];
        } else {
            $limit = $this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit');
        }

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $url = '';

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

       /* if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }*/

        if (isset($this->request->get['limit'])) {
            $url .= '&limit=' . $this->request->get['limit'];
        }

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('product/special', $url)
        );

        $data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));

        $data['compare'] = $this->url->link('product/compare');

        $data['products'] = array();

       $filter_category_id = isset($this->request->get['category_id']) ? (int)$this->request->get['category_id'] : 0;

$filter_data = array(
    'filter_category_id' => $filter_category_id,
    'sort'  => $sort,
    'order' => $order,
    'start' => ($page - 1) * $limit,
    'limit' => $limit
);

$product_total = $this->model_catalog_product->getTotalProductSpecials($filter_data);
$results = $this->model_catalog_product->getProductSpecials($filter_data);

        foreach ($results as $result) {
            if ($result['image']) {
                $image = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
            } else {
                $image = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
            }

            if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
                $price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                        $price_last_30 = $this->currency->format($this->tax->calculate($result['price_last_30'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                if($this->session->data['currency']=='HRK'){
                        $priceeur = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), 'EUR');
                    }
                    else{
                          $priceeur = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), 'HRK');

                    }
            } else {
                $price = false;
                  $priceeur  ='';
                     $price_last_30 = false;
            }

            if ((float)$result['special']) {
                $special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);

                 if($this->session->data['currency']=='HRK'){
                        $specialeur = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')),  'EUR');
                    }
                    else{
                        $specialeur = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')),  'HRK');

                    }
            } else {
                $special = false;
            }

            if ($this->config->get('config_tax')) {
                $tax = $this->currency->format((float)$result['special'] ? $result['special'] : $result['price'], $this->session->data['currency']);
            } else {
                $tax = false;
            }

            if ($this->config->get('config_review_status')) {
                $rating = (int)$result['rating'];
            } else {
                $rating = false;
            }

            $data['products'][] = array(
                'product_id'  => $result['product_id'],
                'thumb'       => $image,
                'name'        => $result['name'],
                'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
                'price'       => $price,
                'special'     => $special,
                 'stock' => $result['stock_status'],
                'stock_id' => $result['stock_status_id'],
                 'priceeur'       => $priceeur,
                    'specialeur'     => $specialeur,
                           'price_last_30'       => $price_last_30,
                'tax'         => $tax,
                'minimum'     => $result['minimum'] > 0 ? $result['minimum'] : 1,
                'rating'      => $result['rating'],
                'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id'] . $url)
            );
        }
    
        /*******************************************************************************
         *                                Copyright : AGmedia                           *
         *                              email: filip@agmedia.hr                         *
         *******************************************************************************/
        $ag_results = $this->model_catalog_product->getProductSpecials();
        $arr = [];
        $arr2 = [];
        foreach ($ag_results as $result) {
            // get categories of each product, based on product id
            $categories = $this->model_catalog_product->getCategoriesfilter($result['product_id']);
        
            if ( ! isset($this->request->get['mfp_path'])) {
                foreach ($categories as $cat) {
                    $_cat = Category::where('category_id', $cat['category_id'])->where('parent_id', 0)->first();
                
                    if ($_cat) {
                        $arr[] = $_cat->category_id;
                    }
                }
            } else {
                foreach ($categories as $cat) {
                    $_cats = Category::where('category_id', $cat['category_id'])->where('parent_id', $this->request->get['mfp_path'])->get();
                
                    foreach ($_cats as $__cat) {
                        $arr[] = $__cat->category_id;
                    }
                }
            }
        
            foreach ($arr as $item) {
                $arr2[$item]['category_id'] = $item;
            }
        }
        if ( ! empty($arr2)) {
            // select each category name and create a link to it
            foreach($arr2 as $category){
                $category_info = $this->model_catalog_category->getCategory($category['category_id']);
                $data['product_categories'][] = array(
                    'id' => $category['category_id'],
                    'name' => $category_info['name'],
                    'thumb' => $this->model_tool_image->resize($category_info['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height')),
                    'href' => 'special?mfp=path['.$category['category_id'].']'
                );
            }
        }
        /*******************************************************************************
         *                              END Copyright : AGmedia                         *
         *******************************************************************************/

        $url = '';

        if (isset($this->request->get['limit'])) {
            $url .= '&limit=' . $this->request->get['limit'];
        }

        $data['sorts'] = array();

        $data['sorts'][] = array(
            'text'  => $this->language->get('text_default'),
            'value' => 'p.sort_order-ASC',
            'href'  => $this->url->link('product/special', 'sort=p.sort_order&order=ASC' . $url)
        );

        $data['sorts'][] = array(
            'text'  => $this->language->get('text_name_asc'),
            'value' => 'pd.name-ASC',
            'href'  => $this->url->link('product/special', 'sort=pd.name&order=ASC' . $url)
        );

        $data['sorts'][] = array(
            'text'  => $this->language->get('text_name_desc'),
            'value' => 'pd.name-DESC',
            'href'  => $this->url->link('product/special', 'sort=pd.name&order=DESC' . $url)
        );

        $data['sorts'][] = array(
            'text'  => $this->language->get('text_price_asc'),
            'value' => 'ps.price-ASC',
            'href'  => $this->url->link('product/special', 'sort=ps.price&order=ASC' . $url)
        );

        $data['sorts'][] = array(
            'text'  => $this->language->get('text_price_desc'),
            'value' => 'ps.price-DESC',
            'href'  => $this->url->link('product/special', 'sort=ps.price&order=DESC' . $url)
        );

        if ($this->config->get('config_review_status')) {
            $data['sorts'][] = array(
                'text'  => $this->language->get('text_rating_desc'),
                'value' => 'rating-DESC',
                'href'  => $this->url->link('product/special', 'sort=rating&order=DESC' . $url)
            );

            $data['sorts'][] = array(
                'text'  => $this->language->get('text_rating_asc'),
                'value' => 'rating-ASC',
                'href'  => $this->url->link('product/special', 'sort=rating&order=ASC' . $url)
            );
        }

        $data['sorts'][] = array(
            'text'  => $this->language->get('text_model_asc'),
            'value' => 'p.model-ASC',
            'href'  => $this->url->link('product/special', 'sort=p.model&order=ASC' . $url)
        );

        $data['sorts'][] = array(
            'text'  => $this->language->get('text_model_desc'),
            'value' => 'p.model-DESC',
            'href'  => $this->url->link('product/special', 'sort=p.model&order=DESC' . $url)
        );

        $url = '';

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        $data['limits'] = array();

        $limits = array_unique(array($this->config->get('theme_' . $this->config->get('config_theme') . '_product_limit'), 25, 50, 75, 100));

        sort($limits);

        foreach($limits as $value) {
            $data['limits'][] = array(
                'text'  => $value,
                'value' => $value,
                'href'  => $this->url->link('product/special', $url . '&limit=' . $value)
            );
        }

        $url = '';

        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }

        if (isset($this->request->get['limit'])) {
            $url .= '&limit=' . $this->request->get['limit'];
        }

        $pagination = new Pagination();
        $pagination->total = $product_total;
        $pagination->page = $page;
        $pagination->limit = $limit;
    
        if (isset($this->request->get['mfp_path'])) {
            $pagination->url = 'special?page={page}' . $url;
        } else {
            $pagination->url = $this->url->link('product/special', $url . '&page={page}');
        }

        $data['pagination'] = $pagination->render();

        $data['results'] = sprintf($this->language->get('text_pagination'), ($product_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($product_total - $limit)) ? $product_total : ((($page - 1) * $limit) + $limit), $product_total, ceil($product_total / $limit));

        // http://googlewebmastercentral.blogspot.com/2011/09/pagination-with-relnext-and-relprev.html
        if ($page == 1) {
            $this->document->addLink($this->url->link('product/special', '', true), 'canonical');
        } else {
            $this->document->addLink($this->url->link('product/special', 'page='. $page , true), 'canonical');
        }

        if ($page > 1) {
            $this->document->addLink($this->url->link('product/special', (($page - 2) ? '&page='. ($page - 1) : ''), true), 'prev');
        }

        if ($limit && ceil($product_total / $limit) > $page) {
            $this->document->addLink($this->url->link('product/special', 'page='. ($page + 1), true), 'next');
        }

        $data['sort'] = $sort;
        $data['order'] = $order;
        $data['limit'] = $limit;

        $data['continue'] = $this->url->link('common/home');

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->load->model('catalog/category');
$data['categories'] = $this->model_catalog_category->getCategories(0);
$data['current_category_id'] = $filter_category_id;

        $this->response->setOutput($this->load->view('product/special', $data));
    }
}
