<?php

class ControllerExtensionFeedBazzar extends Controller
{
    
    public function index()
    {
        
        $output = '<?xml version="1.0" encoding="UTF-8"?>';
        $output .= '<products>';
        
         $this->load->model('catalog/product');
                $this->load->model('catalog/category');
        
        $products = array_merge(
            $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 13)), //aer
            $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 11)),//alhambra
            $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 18)),//aosen
            $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 22)),//apc
            $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 23)),//artbeat
            $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 24)),//austin
            $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 29)),//caline
                 $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 31)),//cherub
                 $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 32)),//D'ADDARIO
                 $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 33)),//daadi
                  $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 35)),//dejung
                   $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 16)),//dunlop
                    $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 38)),//enomusic
                     $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 39)),//ergoplay
                      $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 40)),//ernhall
                       $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 43)),//flight
                        $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 45)),//gewa
                         $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 51)),//iberica
                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 52)),//JQ
    
                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 55)),//Kepma

                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 56)),//KLOTZ

                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 14)),//   Meinl
                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 70)),//JMUSEDO
                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 72)),//Nino Percussion
                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 76)),//PARTSLAND
                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 17)),//PEARL FLUTES
                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 81)),//ROCKSON
                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 86)),//sandona
                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 87)),//SAVAREZ
                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 91)),//SILK ROAD
                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 94)),//TENSON
                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 97)),//WAY HUGE
                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 99)),//WINGO

                          $this->model_catalog_product->getProducts(array('filter_manufacturer_id' => 100)) //WITTNER
            
        );
        
        foreach ($products as $product) {
            
            $description = strip_tags(html_entity_decode($product['description']));
            $description = str_replace('&nbsp;', '', $description);
            $description = str_replace('', '', $description);
            $description = str_replace('', '', $description);
            $description = str_replace('&#44', '', $description);
            
            if($product['isbn']>50 && $product['quantity']>0){

                    
                        $output .= '<product>';
                        
                        $output .= '<id>'. $this->wrapInCDATA($product['model']) .'</id>';
                        $output .= '<title>'. $this->wrapInCDATA($product['name']) .'</title>';
                         $output .= '<stock>'. $this->wrapInCDATA($product['quantity']) .'</stock>';
                           $output .= '<vendor_price>'. $this->wrapInCDATA($product['isbn']) .'</vendor_price>';
                        
                        $output .= '<description>'. $this->wrapInCDATA($description) . '</description>';
                          $output .= '<images>';
                        
                        $output .= '<image>'. $this->wrapInCDATA('https://www.glazbena-kutija.hr/image/' . $product['image']) .'</image>';
                    
                            $additional_images = $this->model_catalog_product->getProductImages($product['product_id']);

                        
                                    if($additional_images) {

                                       foreach ($additional_images as $additional_image) {

                                           $output .=  '<image>'. $this->wrapInCDATA('https://www.glazbena-kutija.hr/image/' . $additional_image['image']) .'</image>';

                                         }

                                    }
                      
                         $output .= '</images>';
                    
                        $output .= '</product>';

                }
        }
        $output .= '</products>';
        
        $this->response->addHeader('Content-Type: application/xml');
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
