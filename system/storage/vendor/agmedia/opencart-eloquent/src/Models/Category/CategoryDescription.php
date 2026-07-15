<?php


namespace Agmedia\Models\Category;


use Illuminate\Database\Eloquent\Model;

class CategoryDescription extends Model
{
    
    /**
     * @var bool
     */
    public $timestamps = false;
    
    /**
     * @var string
     */
    protected $table = 'category_description';
    
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