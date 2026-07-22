<?php



class ControllerExtensionFeedJeftinije extends Controller
{
    
    public function index()
    {
        
        $output = '<?xml version="1.0" encoding="UTF-8"?>';
        $output .= '<CNJExport>';

        $this->load->model('catalog/product');

        $products = $this->model_catalog_product->getProducts();
        $currency_code = $this->currency->has('EUR') ? 'EUR' : $this->config->get('config_currency');
        $currency_value = $this->currency->getValue($currency_code);
        
        
        foreach ($products as $product) {


                  if($product['quantity']>0){

                        $stock = 'in stock';

                    }

                    else{

                        $stock = 'out of stock';


                    }


                       if($product['quantity']>0){

                        $ducan = 'today';

                    }

                    else{

                        $ducan = 'no';


                    }


          


               

      if($product['quantity']>0 && $product['price'] > 50){
           
                        
                        $description = strip_tags(html_entity_decode($product['description']));
                        $description = str_replace('&nbsp;', '', $description);
                        $description = str_replace('', '', $description);
                        $description = str_replace('', '', $description);
                        $description = str_replace('&#44', '', $description);
                        
                        
                        $output .= '<Item>';
                        
                        $output .= '<ID>'. $this->wrapInCDATA($product['product_id']) .'</ID>';
                        $output .= '<name>'. $this->wrapInCDATA($product['name']) .'</name>';
                        
                        $output .= '<description>'. $this->wrapInCDATA($description) .'</description>';


                         $output .= '<link>'. $this->url->link('product/product', 'product_id=' . $product['product_id']).'</link>';
                        
                        $output .= '<mainImage>'. $this->wrapInCDATA(HTTPS_SERVER.'image/' . $product['image']).'</mainImage>';



                       $ocimages = $this->getProductImages($product['product_id']);
                       $str = '';

                           foreach ($ocimages as $ocimage) {

                            $str .= HTTPS_SERVER.'image/'.$ocimage['image'].',';
                               
                           }


                         $str2 = rtrim($str, ',');
                                $str='';
                            if($str2){

                                   $output .='<moreImages>'.$this->wrapInCDATA($str2).'</moreImages>';


                            }
                    

                       $str2='';

                       if ((float) $product['special'])
                                {
                                    $productPrice = $this->currency->format($this->tax->calculate($product['special'], $product['tax_class_id']), $currency_code, $currency_value, false);

                                    $productregularPrice = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id']), $currency_code, $currency_value, false);
                                }
                                else
                                {
                                    $productPrice = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id']), $currency_code, $currency_value, false);

                                    $productregularPrice = 0;
                                }


            

                              $output .= '<price>'. number_format($productPrice, 2, ',', '') .'</price>';
                                if($productregularPrice !=0){

                                   

                                     $output .= '<regularPrice>'. number_format($productregularPrice, 2, ',', '') .'</regularPrice>';

                                }


        
                  

                                 $output .= '<curCode>'. $currency_code .'</curCode>';


                                
                                  $output .= '<availability>'. $this->wrapInCDATA($product['quantity'].' komada na stanju') .'</availability>';

                                   $output .='<inStoreAvailability>';
                                 $output .= '<store><![CDATA[Harambašićeva 60, 10 000 Zagreb]]></store>';
                                 $output .= '<availability>'. $ducan.'</availability>';
                                 $output .= '<quantity>'. $product['quantity'].'</quantity>';
                             $output .= '</inStoreAvailability>';




        
                           
                            $output .= '<fileUnder>'. $this->wrapInCDATA($this->getCategoriesName($product['product_id'])) .'</fileUnder>';

                             $output .= '<brand>'. $this->wrapInCDATA($product['manufacturer']) .'</brand>';
                                 $output .= '<EAN>'. $this->wrapInCDATA($product['ean']) .'</EAN>';
                           $output .= '<productModel>'. $this->wrapInCDATA($product['model']) .'</productModel>';

                            $output .= '<condition>'. $this->wrapInCDATA('new') .'</condition>';

                              $output .= '<stock>'.$this->wrapInCDATA($stock).'</stock>';

 $output .= '<quantity>'. $product['quantity'].'</quantity>';
                               $output .= '<deliveryTimeMin>3</deliveryTimeMin>';
                              $output .= '<deliveryTimeMax>10</deliveryTimeMax> ';

                              $output .= '<deliveryCost>30</deliveryCost>';
                              
           
                       $output .= '</Item>';
            }
           
        }
        $output .= '</CNJExport>';
        
       $this->response->addHeader('Content-Type: application/xml; charset=utf-8');
     $this->response->setOutput($output);
        
        
    }
    
    
    private function wrapInCDATA($in)
    {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', (string)$in) . ']]>';
    }
    
    
    private function removeChar($string, $char)
    {
        return str_replace($char, '', $string);
    }


    public function getProductImages($product_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC");

        return $query->rows;
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



        
        foreach ($data as $key => $item) {
            if (empty($category)) {

              if($key != 0){

                $category = $this->model_catalog_category->getCategory($item['category_id']);

                $name     = $category['name'];
                
                if ($category['parent_id'] != 0) {
                    $parent = $this->model_catalog_category->getCategory($category['category_id']);
                    $name   =  $category['name'];
                }


              }
              
            }
        }
        
        return $name;
    }
    
}

?>
