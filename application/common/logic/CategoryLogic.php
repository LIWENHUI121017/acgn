<?php
namespace app\common\logic;

use app\common\model\Cart;
use app\common\model\Goods;
use app\common\model\SpecGoodsPrice;
use app\common\util\acgnException;
use think\Config;
use think\Db;
use think\Model;
use think\Session;

class CategoryLogic extends Model{
    //获取所有商品分类
    public function     getAllcategory(){
        $tree = $arr = $result = array();
        $cat_list = Db::name('goods_category')->order('id')->select();//所有分类
        if($cat_list){
            foreach ($cat_list as $val){
                if($val['level'] == 2){
                    $arr[$val['pid']][] = $val;
                }
                if($val['level'] == 3){
                    $crr[$val['pid']][] = $val;
                }
                if($val['level'] == 1){
                    $tree[] = $val;
                }
            }

            foreach ($arr as $k=>$v){
                foreach ($v as $kk=>$vv){
                    $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
                }
            }

            foreach ($tree as $val){
                $val['tmenu'] = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
                $result[$val['id']] = $val;
            }
        }
        return $result;

    }

    //判断商品分类信息
    public function checkcate($data){
        $categorylist = Db::name('goods_category')->select();
        foreach ($categorylist as $key=>$value){
            if ($value['name']==$data['name']){
                return ['status'=>0,'msg'=>'商品分类名称重复了'];
            }

        }
    }

    //判断此分类是否有下级分类
    public function checkchildren($id){
            $res = Db::name('goods_category')->where('pid',$id)->order('sort_order descs')->find();
            return $res;
    }
}
