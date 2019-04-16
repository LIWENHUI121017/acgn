<?php
namespace app\common\logic;


use app\common\model\SpecGoodsPrice;
use think\Db;
use think\Model;


class AttributeLogic extends Model{
    //获取所有商品属性
    public function getallattlist($tid){
        $where=array();
        if ($tid){
            $where['type_id']=$tid;
        }
        $att = Db::name('goods_attribute')
            ->alias('a')
            ->join('goods_type s','a.type_id=s.tid')
            ->where($where)
            ->select();
        foreach ($att as $key=>$value){
            if ($value['attr_input_type']==0){
                $att[$key]['attr_typename']='手工录入';
            }elseif ($value['attr_input_type']==1){
                $att[$key]['attr_typename']='从列表中选择';
            }else{
                $att[$key]['attr_typename']='多行文本框';
            }
        }


        return $att;
    }

    //获取单个商品属性
    public function getoneattlist($id){
        $attr = Db::name('goods_attribute')
            ->alias('a')
            ->join('goods_type s','a.type_id=s.tid')
            ->where('a.attr_id',$id)
            ->select();
        foreach ($attr as $key=>$value){
            if ($value['attr_input_type']==0){
                $attr[$key]['attr_typename']='手工录入';
            }elseif ($value['attr_input_type']==1){
                $attr[$key]['attr_typename']='从列表中选择';
            }else{
                $attr[$key]['attr_typename']='多行文本框';
            }
        }




        return $attr;
    }

    //获取规格项
    public function getSpecItem($spec_id)
    {
        $arr = Db::name('spec_item')->where("spec_id = $spec_id")->order('id')->select();
        $arr = get_id_val($arr, 'id','item');
        return $arr;
    }

    //判断修改属性名称是否重复
    public function check($id,$name){
        $check = Db::name('goods_attribute')->where('attr_id','not in',$id)->select();
//return $id;
        foreach ($check as $value){
            if ($value['attr_name']==$name){
                return true;
            }
        }

    }
}
