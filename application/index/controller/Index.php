<?php
namespace app\index\controller;

use app\common\logic\GoodsLogic;
use think\Controller;
use think\db;
class Index extends Base
{
    


    public function index()
    {
       $this->showgoods();

        return $this->fetch();
    }

    public function showgoods(){
        $goodslogic = new GoodsLogic();
        $catelist= $goodslogic->getlist();
//        dump($catelist);die;
        $this->assign('catelist',$catelist);
    }
}
