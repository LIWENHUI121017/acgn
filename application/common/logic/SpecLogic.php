<?php
namespace app\common\logic;


use app\common\model\SpecGoodsPrice;
use think\Db;
use think\Model;


class SpecLogic extends Model{
    //获取所有商品规格
    public function getallSpeclist($tid){
        $where=array();
        if ($tid){
            $where['type_id']=$tid;
        }
        $spec = Db::name('spec')
            ->alias('a')
            ->join('goods_type s','a.type_id=s.tid')
            ->where($where)
            ->select();
        foreach ($spec as $key=>$value){
            $item=$this->getSpecItem($value['id']);
            $spec[$key]['spec_item']=implode('，', $item);
        }

//        $speclist = Db::name('spec')->select();


        return $spec;
    }

    //获取单个商品规格
    public function getoneSpeclist($id){
        $spec = Db::name('spec')
            ->alias('a')
            ->join('goods_type s','a.type_id=s.tid')
            ->where('a.id',$id)
            ->select();
        foreach ($spec as $key=>$value){
            $item=$this->getSpecItem($value['id']);
            $spec[$key]['spec_item']=implode('，', $item);
        }

//        $speclist = Db::name('spec')->select();


        return $spec;
    }

    //获取新增商品的单个商品规格
    public function getgoodsaddoneSpeclist($id){
        $spec = Db::name('spec')->where('type_id',$id)->select();
        return $spec;
    }

    //获取规格项
    public function getSpecItem($spec_id)
    {
        $arr = Db::name('spec_item')->where("spec_id = $spec_id")->order('id')->select();
        $arr = get_id_val($arr, 'id','item');
        return $arr;
    }

    //判断修改规格名称是否重复
    public function check($id,$name){
        $check = Db::name('spec')->whereNotIn('id',$id)->select();

        foreach ($check as $value){
            if ($value['name']==$name){
                return true;
            }
        }

    }
}
