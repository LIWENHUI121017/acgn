<?php
namespace app\admin\controller;



use app\common\logic\GoodsLogic;

class Index extends Base
{



    public function index()
    {
       $goods = controller('Goods');
        return $goods->index();


    }
}
