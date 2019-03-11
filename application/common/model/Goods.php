<?php
namespace app\common\model;

use think\Model;

class Goods extends Model
{
    public function cart(){
        return $this->belongsTo('Cart','goods_id','id');
    }

}