<?php


namespace Agmedia\Models\Category;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Category extends Model
{
    
    /**
     * @var bool
     */
    public $timestamps = false;
    
    /**
     * @var string
     */
    protected $table = 'category';
    
    /**
     * @var string
     */
    protected $primaryKey = 'category_id';
    
    /**
     * @var array
     */
    protected $guarded = [
        'category_id'
    ];
    
}