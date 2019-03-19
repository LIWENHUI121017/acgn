<?php
namespace app\common\model;

use think\Model;

class Order extends Model{
    public function order(){
       $res = $this
           ->alias('a')
           ->join('address s','a.address_id=s.address_id')
           ->select()
            ->toArray();
       return $res;
    }

}