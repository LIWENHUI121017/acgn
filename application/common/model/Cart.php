<?php
namespace app\common\model;

use think\Model;

class Cart extends Model
{

    public function goods(){
        return $this->hasOne('Goods', 'id', 'goods_id');
    }
    public static function Cartlist(){

        $data=self::with('goods')->select();
        return $data ;

    }


}