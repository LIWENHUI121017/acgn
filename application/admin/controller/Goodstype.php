<?php
namespace app\admin\controller;



use app\common\logic\GoodsTypeLogic;
use think\Db;
use think\Loader;

class Goodstype extends Base
{

    public function _initialize()
    {
        return parent::_initialize();
    }

    public function index()
    {
        $typelist = Db::name('goods_type')->order('tid desc')->select();
//        dump($typelist);
//        $page = $typelist->render();//分页页码
        $this->assign('typelist',$typelist);
//        $this->assign('page',$page);
        return $this->fetch();
    }
    public function add(){

       return $this->fetch();
    }

    //提交
    public function addgoodstype(){
       $data = input('post.');
       if (empty($data)) {
           $this->error('模型名称不能为空',url('Admin/Goodstype/add'));
       }else {
           $check = Db::name('goods_type')->where('type_name',$data['type_name'])->find();
               if ($check){
                   $this->error('模型名称重复了',url('Admin/Goodstype/add'));
               }
               $res=Db::name('goods_type')->insert($data);
               if ($res){
                   $this->success('模型名称添加成功',url('Admin/Goodstype/add'));
               }else{
                   $this->error('模型名称添加失败',url('Admin/Goodstype/add'));
               }
           }
       }


    //修改商品模型页面
    public function change(){
        $id = input('id');
        $goodstypelogic = new GoodsTypeLogic();
        $goodstypename = $goodstypelogic->getoneTypelist($id);
//        dump($goodstypename);
        $this->assign('typename',$goodstypename);
        return $this->fetch();
    }

    //修改操作
    public function updatetypename(){
        $data = input('post.');
//        dump($data);
//        exit();
        if (empty($data['typename'])) {
            $this->error('模型名称不能为空',url('Admin/Goodstype/change',array('id'=>$data['tid'])));
        }else {
            $logic = new GoodsTypeLogic();
            $check = $logic->check($data['tid'],$data['typename']);

            if ($check) {
//                dump($check);
                $this->error('模型名称重复了', url('Admin/Goodstype/change',array('id'=>$data['tid'])));
            }else{
                $args = ['type_name'=>$data['typename']];
                $res = Db::name('goods_type')->where('tid',$data['tid'])->update($args);
                if ($res){
                    $this->success('修改成功',url('Admin/Goodstype/index'));
                }else{
                    $this->error('修改失败',url('Admin/Goodstype/index'));
                }
            }
        }
    }

    //删除规格
    public function deletetypename(){
        $id = input('id');
//        dump($id);
//        exit();


            $res= Db::name('goods_type')->where('tid',$id)->delete();


            if ($res){
                $this->success('删除成功',url('/Admin/Goodstype/index'));
            }else{
                $this->success('删除失败',url('/Admin/Goodstype/index'));
            }


    }

}
