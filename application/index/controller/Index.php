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
        $newgoods = $goodslogic->get_all(['is_new'=>1,'is_on_sale'=>1],'*','Goods','goods_time');
//        dump($catelist);die;
        $this->assign('catelist',$catelist);
        $this->assign('newgoods',$newgoods);
    }

}
