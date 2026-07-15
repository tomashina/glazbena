<?php


namespace Agmedia\Models\Order;


use Illuminate\Database\Eloquent\Model;

class OrderService extends Model
{
    
    /**
     * @var bool
     */
    public $timestamps = false;
    
    /**
     * @var string
     */
    protected $table = 'order_service';
    
    /**
     * @var string
     */
    protected $primaryKey = 'order_service_id';
    
    /**
     * @var array
     */
    protected $guarded = [
        'order_service_id'
    ];
    
}