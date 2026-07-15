<?php

class ControllerExtensionFeedFacebookstore extends Controller {
    
    public function index()
    {
        
        $output = '<?xml version="1.0" encoding="UTF-8"?>';
        $output .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">';
        $output .= '<channel>';

         $output .= '<title>Glazbena kutija  Web Shop</title>';
         $output .= '<link>https://www.glazbena-kutija.hr/index.php?route=extension/feed/facebookstore</link>';
         $output .= '<description>Glazbena kutija  Web Shop</description>';
                
                $this->load->model('catalog/product');
                $this->load->model('catalog/category');
        
       /* $products = array_merge(
        
            $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 152)),//polleosport
            $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 205)), //boss
             $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 217)), //dc comics
              $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 214)), //mizuno
               $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 204)), //nike
                $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 155)), //reebok
                 $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 178)), //UA
                  $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 185)), //zoe
                   $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 236)) //ZOE LAttitude
              
    
        );*/

        $products = $this->model_catalog_product->getProducts();
        
        
        foreach ($products as $product) {

            if($product['quantity']>0 || $product['model']!='') {


            
                    $description = strip_tags(html_entity_decode($product['description']));
                    $description = str_replace('&nbsp;', '', $description);
                    $description = str_replace('', '', $description);
                    $description = str_replace('', '', $description);
                    $description = str_replace('&#44', '', $description);
                      $description = str_replace("'", '', $description);
                       $description = str_replace('', '', $description);


                       $metadescription = strtolower($product['meta_title'].'-'.$product['meta_description']);
                      
                    
                    
                    $output .= '<item>';
                    
                    $output .= '<g:id>' . $this->wrapInCDATA($product['product_id']) . '</g:id>';
                    $output .= '<g:title>' . $this->wrapInCDATA($product['name']) . '</g:title>';
                   //$output .= '<g:description>' . $this->wrapInCDATA($description) . '</g:description>';

                    $output .= '<g:description>' . $this->wrapInCDATA($metadescription) . '</g:description>';
                    $output .= '<g:link>' . $this->url->link('product/product', 'product_id=' . $product['product_id']) . '</g:link>';   
                    $output .= '<g:image_link>' . $this->wrapInCDATA('https://www.glazbena-kutija.hr/image/' . $product['image']) . '</g:image_link>';
                      $output .= '<g:brand>' . $this->wrapInCDATA($product['manufacturer']) . '</g:brand>';
                    $output .= '<g:condition>new</g:condition>';
                    $output .= '<g:availability>in stock</g:availability>';

                   
                    $output .= '<g:price>' . $product['price'] . ' EUR</g:price>';

                    if($product['special']!=''){

                        $output .= '<g:sale_price>' . $product['special'] . ' EUR</g:sale_price>';

                    }
                    

                    
                   
                    $output .= '<g:google_product_category>54</g:google_product_category>';





                   // $categories = $this->model_catalog_product->getCategories($product['product_id']);

                    

                 /*   foreach ($categories as $category) {

                        $path = $this->getPath($category['category_id']);

                        

                        if ($path) {

                            $string = '';

                            

                            foreach (explode('_', $path) as $path_id) {

                                $category_info = $this->model_catalog_category->getCategory($path_id);

                                 $categorynew = $this->model_catalog_category->getCategory($category['category_id']);

                                if ($category_info) {
                                    
                                    if ($categorynew['parent_id'] != 0) {

                                        if($category_info['name'] != 'Sve akcije' && $category_info['name'] != 'Akcije'){

                                            if (!$string) {

                                                $string = $category_info['name'];

                                            } else {

                                                $string .= ' &gt; ' . $category_info['name'];

                                            }


                                        }

                                        



                                    }

                                }

                            }

                         
                        if($string){

                            $output .= '<g:google_product_category>' . $string . '</g:google_product_category>';


                        }
                            

                        }

                    }       */       
                    
                   // $options = \Agmedia\Model\Product::getOptionName($product['product_id']);
                    
                    
                  
                    $output .= '</item>';

            }
        }
        $output .= '</channel>';
        $output .= '</rss>';
        
        $this->response->addHeader('Content-Type: application/xml');
        $this->response->setOutput($output);
        
        
    }
    
    
    private function wrapInCDATA($in)
    {
        return "<![CDATA[ " . $in . " ]]>";
        //return $in;
    }
    
    
    private function removeChar($string, $char)
    {
        return str_replace($char, '', $string);
    }


    protected function getPath($parent_id, $current_path = '') {
        $category_info = $this->model_catalog_category->getCategory($parent_id);

        if ($category_info) {
            if (!$current_path) {
                $new_path = $category_info['category_id'];
            } else {
                $new_path = $category_info['category_id'] . '_' . $current_path;
            }

            $path = $this->getPath($category_info['parent_id'], $new_path);

            if ($path) {
                return $path;
            } else {
                return $new_path;
            }
        }
    }   
    
    
    /**
     * Construct category and parent name
     * and return it
     *
     * @param $id
     *
     * @return string
     */
    public function getCategoriesName($id)
    {
        $this->load->model('catalog/category');
        $data = $this->model_catalog_product->getCategories($id);
        $name = '';
        
        foreach ($data as $item) {
            if (empty($category)) {
                $category = $this->model_catalog_category->getCategory($item['category_id']);
                $name     = $category['name'];
                
                if ($category['parent_id'] != 0) {
                    $parent = $this->model_catalog_category->getCategory($category['parent_id']);
                    $name   = $parent['name'] . ' > ' . $category['name'];
                }
            }
        }
        
        return $name;
    }
    
}

?>