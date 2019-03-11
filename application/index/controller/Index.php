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
        $goodsloginc = new GoodsLogic();


        $catelist= $goodsloginc->getlist();

        $img= $goodsloginc->getimage();
//        dump($catelist);

        $this->assign('catelist',$catelist);
    }
}
