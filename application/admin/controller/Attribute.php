<?php
namespace app\admin\controller;



use app\common\logic\AttributeLogic;
use app\common\logic\SpecLogic;
use think\Db;
use think\Loader;

class Attribute extends Base
{

    public function _initialize()
    {
        return parent::_initialize();
    }

    public function index(){
        $tid = input('tid');
        $logic = new AttributeLogic();
        $attributelist = $logic->getallattlist($tid);
        //获取所有商品模型
        $type = Db::name('goods_type')->select();


        $this->assign('attributelist',$attributelist);
        $this->assign('type',$type);

        return $this->fetch();
    }
    public function add(){
        //获取商品模型
        $itemtypelist = Db::name('goods_type')->order('tid')->select();
        $this->assign('typelist',$itemtypelist);
       return $this->fetch();
    }

    //提交
    public function addattr(){
       $data = input('post.');
//        dump($data);
        //判断属性名称是否重复

        $check = Db::name('goods_attribute')->where('attr_name',$data['attr_name'])->find();
        if ($check){
            $this->error('属性名称已存在',url('Admin/Attribute/add'));
        }
        $validate=Loader::validate('Attribute');
            if (!$validate->check($data)){
                $this->error($validate->getError(),url('Admin/Attribute/add'));
            }else{
                $res = Db::name('goods_attribute')->insert($data);
                if ($res){
                    $this->success('添加属性成功',url('Admin/Attribute/index'));
                }else{
                    $this->error('添加失败了',url('Admin/Attribute/add'));
                }
            }
    }

    //修改页面
    public function change(){
        $id = input('id');
        $logic = new AttributeLogic();
        $attlist = $logic->getoneattlist($id);
//        dump($attlist);

        //获取商品模型
        $itemtypelist = Db::name('goods_type')->order('tid')->select();
        $this->assign('typelist',$itemtypelist);
        $this->assign('attlist',$attlist);
        return $this->fetch();
    }

    //修改操作
    public function updateattr(){
        $data = input('post.');
//        dump($data);
//    exit();
        $validate=Loader::validate('Attribute');
        if (!$validate->check($data)){
            $this->error($validate->getError(),url('Admin/Attribute/add'));
        }else{
            $logic = new AttributeLogic();
            $check = $logic->check($data['attr_id'],$data['attr_name']);
            dump($check);
//            exit();
            if ($check){
                $this->error('属性名称重复了',url('Admin/Attribute/change',array('id'=>$data['attr_id'])));
            }else{
                $args = [
                   'attr_name'=>$data['attr_name'],
                   'type_id'=>$data['type_id'],
                   'attr_input_type'=>$data['attr_input_type'],
                   'attr_values'=>$data['attr_values'],
                ] ;
                $res = Db::name('goods_attribute')->where('attr_id',$data['attr_id'])->update($args);
                if ($res){
                    $this->success('修改成功',url('Admin/Attribute/index'));
                }else{
                    $this->error('修改失败',url('Admin/Attribute/change',array('id'=>$data['attr_id'])));
                }
            }
        }

    }

    //删除规格
    public function deleteattr(){
        $id = input('id');

            $res= Db::name('goods_attribute')->where('attr_id',$id)->delete();


            if ($res){
                return json(['status'=>1,'msg'=>'删除成功']);
//                $this->success('删除成功',url('/Admin/Spec/index'));
            }else{
                return json(['status'=>0,'msg'=>'删除失败']);
//                $this->success('删除失败',url('/Admin/Spec/index'));
            }


    }

}
