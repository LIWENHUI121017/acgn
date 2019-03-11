<?php
namespace app\admin\controller;



use app\common\logic\SpecLogic;
use think\Db;
use think\Loader;

class Spec extends Base
{

    public function _initialize()
    {
        return parent::_initialize();
    }

    public function index()
    {
        $speclogic = new SpecLogic();
        $speclist = $speclogic->getallSpeclist();
//        dump($speclist);
//        $page = $speclist->render();//分页页码
        $this->assign('speclist',$speclist);
//        $this->assign('page',$page);
        return $this->fetch();
    }
    public function add(){
        //获取商品模型
        $itemtypelist = Db::name('goods_type')->order('tid')->select();
        $this->assign('typelist',$itemtypelist);
       return $this->fetch();
    }

    //提交
    public function addspec(){
       $data = input('post.');
       $validate = Loader::validate('Spec');
       if (!$validate->check($data)){
           $this->error($validate->getError(),url('Admin/spec/add'));
       }else{
           //判断规格名称是否重复
           $isrepeat = Db::name('spec')->where('name',$data['name'])->find();
           if ($isrepeat){
               $this->error('规格名称重复了',url('Admin/Spec/index'));
           }
               Db::startTrans();
               try{
                   //处理规格项
                   $item = explode('，',$data['item']);
               dump($item);
                   $args1 = [
                       'type_id'=>$data['tid'],
                       'name'=>$data['name']
                   ];
//               dump($args1);
                   $spec_id = Db::name('spec')->insertGetId($args1);

                   foreach ($item as $k1=>$v1){
                       $args2 = [
                           'spec_id'=>$spec_id,
                           'item'=>$v1
                       ];
                       $res = Db::name('spec_item')->insert($args2);

                       Db::commit(); //提交事务
                   }
                   if ($res){
                       $this->success('添加成功',url('/Admin/Spec/index'));
                   }else{
                       $this->error('添加失败了',url('/Admin/Spec/index'));
                   }

               }catch (\PDOException $e){
                   Db::rollback(); //回滚事务
               }



       }

    }

    //修改页面
    public function change(){
        $id = input('id');
        $speclogic = new SpecLogic();
        $speclist = $speclogic->getoneSpeclist($id);
//        dump($speclist);

        //获取商品模型
        $itemtypelist = Db::name('goods_type')->order('tid')->select();
        $this->assign('typelist',$itemtypelist);
        $this->assign('speclist',$speclist);
        return $this->fetch();
    }

    //修改操作
    public function updatespec(){
        $data = input('post.');
//        dump($data);
//    exit();
        $validate = Loader::validate('Spec');
        if (!$validate->check($data)){
            $this->error($validate->getError(),url('Admin/spec/add'));
        }else{
            $logic = new SpecLogic();
            $check = $logic->check($data['id'],$data['name']);

            if ($check){
                $this->error('规格名称重复了',url('Admin/Spec/change',array('id'=>$data['id'])));
            }

                Db::startTrans();
            try{
                $args2 = [
                    'name'=>$data['name'],
                    'type_id'=>$data['tid'],
                ];
                Db::name('spec')->where('id',$data['id'])->update($args2);
                //查询是否有item
                $check=Db::name('spec_item')->where('spec_id',$data['id'])->select();

                if ($check){
                    //处理规格项
                    $item = explode('，',$data['item']);
                    $res=Db::name('spec_item')->where('spec_id',$data['id'])->delete();
                }else{
                    //处理规格项
                    $item = explode('，',$data['item']);
                    $res = true;
                }


                if ($res){
                        foreach ($item as $k1=>$v1){
                            $args2 = [
                                'spec_id'=>$data['id'],
                                'item'=>$v1
                            ];
                            $res = Db::name('spec_item')->insert($args2);
                        }
                    Db::commit();
                    if ($res){
                        $this->success('修改成功',url('/Admin/Spec/index'));
                    }else{
                        $this->error('修改失败了',url('/Admin/Spec/index'));
                    }
                }else{
                    $this->error('修改出错',url('/Admin/Spec/index'));
                }
                 }catch (\PDOException $e){
                      Db::rollback(); //回滚事务
                 }

        }

    }

    //删除规格
    public function deletespec(){
        $id = input('id');
        Db::startTrans();
        try{
            //先删spec表
            $res1= Db::name('spec')->where('id',$id)->delete();
            $res2= Db::name('spec_item')->where('spec_id',$id)->delete();

            Db::commit();
            if ($res1&&$res2){
                $this->success('删除成功',url('/Admin/Spec/index'));
            }else{
                $this->success('删除失败',url('/Admin/Spec/index'));
            }
        }catch (\PDOException $e){
            Db::rollback();
        }

    }

}
