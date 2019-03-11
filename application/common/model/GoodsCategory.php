<?php
namespace app\common\model;

use think\Model;

class GoodsCategory extends Model
{
    public function goods()
    {
        return $this->belongsTo('Goods');
    }


}