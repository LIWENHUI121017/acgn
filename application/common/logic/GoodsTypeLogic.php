<?php
namespace app\common\logic;

use app\common\model\GoodsCategory;
use app\common\model\Goods;
use think\Config;
use think\Db;
use think\Session;

class GoodsTypeLogic{
    public function getoneTypelist($id){
        $res = Db::name('goods_type')->where('tid',$id)->find();
        return $res;
    }
    //判断修改模型名称是否重复
    public function check($id,$typename){
        $check = Db::name('goods_type')->whereNotIn('tid',$id)->select();
        foreach ($check as $value){
            if ($value['type_name']==$typename){
                return true;
            }
        }

    }


}
