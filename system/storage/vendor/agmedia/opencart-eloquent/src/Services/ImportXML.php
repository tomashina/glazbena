<?php


namespace Agmedia\Services;


use Agmedia\Helpers\Log;
use Agmedia\Models\Product\Product;
use Agmedia\Models\Product\ProductDescription;

class ImportXML
{
    
    /**
     * @var \SimpleXMLElement
     */
    public $xml;
    
    /**
     * @var array
     */
    public $diff_codes;
    
    
    /**
     * ImportXML constructor.
     *
     * @param $xml
     */
    public function __construct($xml)
    {
        $this->xml = $xml;
    }
    
    
    /**
     * @param string $node
     *
     * @return \Illuminate\Support\Collection
     */
    public function collect($node = 'proizvodi')
    {
        return collect(json_decode(json_encode($this->xml), true)[$node]);
    }
    
    
    /**
     * @param string $product_db_column
     *
     * @return array|\Illuminate\Support\Collection
     */
    public function setDiffCodes($product_db_column = 'model')
    {
        $loaded = $this->collect();
        
        $loaded_codes = collect($loaded['proizvod'])->pluck('sifraProizvoda')->sort()->flatten();
        $exist_codes  = Product::pluck($product_db_column)->sort()->flatten();
        
        $this->diff_codes = $loaded_codes->diff($exist_codes);
        
        Log::stockPile($this->diff_codes->count(), 'agpile_product_import');
        
        return $this->diff_codes;
    }
    
    
    /**
     * @param string $product_db_column
     *
     * @return array|\Illuminate\Support\Collection
     */
    public function setDiffEmptyCodes($product_db_column = 'model')
    {
        $loaded = $this->collect();
        $items = [];
    
        foreach (collect($loaded['proizvod']) as $item) {
            if (is_array($item['sifraProizvoda'])) {
                $items[] = $item;
            }
        }
    
        $this->diff_codes = collect($items);
        
        Log::stockPile($this->diff_codes, 'agpile_empty_code_import');
        
        return $this->diff_codes;
    }
    
    
    /**
     * @param string $product_db_column
     *
     * @return array|\Illuminate\Support\Collection
     */
    public function setDiffTranslationsCodes()
    {
        $loaded = $this->collect();
        
        $loaded_codes      = collect($loaded['proizvod'])->pluck('sifraProizvoda')->sort()->flatten();
        $exist_codes       = Product::whereIn('model', $loaded_codes)->pluck('product_id')->sort()->flatten();
        $exist_trans_codes = ProductDescription::where('language_id', ENG_LANGUAGE_ID)->pluck('product_id')->sort()->flatten();
        $temp_diffs        = $exist_codes->diff($exist_trans_codes);
        
        $this->diff_codes = Product::whereIn('product_id', $temp_diffs)->pluck('model')->sort()->flatten();
        
        Log::stockPile($this->diff_codes->count(), 'agpile_translations_import');
        
        return $this->diff_codes;
    }
    
    
    /**
     * @param $item
     *
     * @return bool
     */
    public function passesSetConstrains($item)
    {
        if (in_array($item->sifraProizvoda, $this->diff_codes->all())) {
            return true;
        }
        
        return false;
    }
    
    
    /**
     * @param $item
     *
     * @return array
     */
    public function setProductImportData($item)
    {
        return [
            'sku'               => (string)$item->sifraProizvoda,
            'ean'               => (string)$item->sifraProizvoda,
            'name'              => (string)$item->naziv,
            'short_description' => (string)$item->opis,
            'description'       => (string)$item->dugiopis,
            'quantity'          => (string)$item->zaliha == 'DA' ? 100 : 0,
            'price'             => (string)$item->cijena,
            'image'             => (string)$item->slike->slika->url,
            'category'          => (string)$item->grupaProizvoda,
        ];
    }
    
    
    /**
     * @return int
     */
    public function count()
    {
        $loaded       = $this->collect();
        $loaded_codes = collect($loaded['proizvod'])->pluck('sifraProizvoda');
        
        return $loaded_codes->count();
    }
}