<?php


namespace Agmedia\Models\Product;


use Agmedia\Models\Category\CategoryDescription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Product extends Model
{
    
    /**
     * @var bool
     */
    public $timestamps = false;
    
    /**
     * @var string
     */
    protected $table = 'product';
    
    /**
     * @var string
     */
    protected $primaryKey = 'product_id';
    
    /**
     * @var array
     */
    protected $guarded = [
        'product_id'
    ];
    
    /**
     * Relations methods
     */
    
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function options()
    {
        return $this->hasMany(ProductOption::class, 'product_id', 'product_id')->with('value');
    }
    
    /**
     * Query scope methods
     */
    
    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeSkus($query)
    {
        return ProductOption::where('product_id', $this->product_id)->pluck('sku');
    }
    
    
    /**
     * @param $product
     *
     * @return array
     */
    public function make($product)
    {
        $response = [
            'sku'                 => $product['sku'],
            'upc'                 => '',
            'ean'                 => '',
            'jan'                 => '',
            'isbn'                => '',
            'mpn'                 => '',
            'location'            => '',
            'price'               => $product['price'],
            'tax_class_id'        => 9,
            'quantity'            => $product['quantity'],
            'minimum'             => 1,
            'subtract'            => 1,
            'stock_status_id'     => 6,
            'shipping'            => 1,
            'date_available'      => Carbon::now()->subDay()->format('Y-m-d'),
            'length'              => '',
            'width'               => '',
            'height'              => '',
            'length_class_id'     => 1,
            'weight'              => '',
            'weight_class_id'     => 1,
            'status'              => 1,
            'sort_order'          => 0,
            'manufacturer'        => '',
            'manufacturer_id'     => 0,
            'category'            => '',
            'filter'              => '',
            'download'            => '',
            'related'             => '',
            'image'               => $product['image'],
            'points'              => '',
            'product_store'       => [0 => 0],
            'product_description' => $this->getDescription($product),
            'product_image'       => '',
            'product_layout'      => [0 => ''],
            'product_category'    => [0 => $this->getCategory($product)]
        ];
        
        $response['model'] = $product['sku'];
        
        return $response;
    }
    
    
    /**
     * @param $product
     *
     * @return array[]
     */
    private function getDescription($product)
    {
        return [
            2 => [
                'name'             => $product['name'],
                'description'      => $product['description'],
                'meta_title'       => $product['name'],
                'custom_alt'       => '',
                'custom_h1'        => '',
                'custom_h2'        => '',
                'custom_imgtitle'  => '',
                'meta_description' => $product['short_description'],
                'meta_keyword'     => $product['name'],
                'tag'              => '',
            ]
        ];
    }
    
    
    /**
     * @param $product
     *
     * @return integer|null
     */
    private function getCategory($product)
    {
        $category_name = substr($product['category'], 0, strpos($product['category'], ' /'));
        
        return CategoryDescription::where('name', $category_name)->pluck('category_id')->first();
    }
    
}