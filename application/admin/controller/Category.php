<?php
namespace app\admin\controller;



use app\common\logic\CategoryLogic;
use app\common\logic\GoodsLogic;
use think\Cookie;
use think\Db;
use think\Loader;
use think\File;
use think\Session;
use think\Validate;


class Category extends Base
{
    public $path = '/public/uploads/';//文件保存路劲开头

    public function _initialize()
    {
        return parent::_initialize();
    }

    public function index()
    {
        $categorylogic = new CategoryLogic();
        $categorylist = $categorylogic->getAllcategory();
//        dump($categorylist);
        $this->assign('categorylist',$categorylist);
        return $this->fetch();
    }

    //添加商品分类页面
    public function add(){
        $categorylogic = new CategoryLogic();
        $catelist = $categorylogic->getAllcategory();
//        dump($catelist);
        $this->assign('catelist',$catelist);
        return $this->fetch();
    }

    //添加商品分类
    public function addcate(){
            $data = input('post.');

            $validate =  Loader::validate('Category');
            if(!$validate->check($data)){
//                return json(['status'=>0,'msg'=>$validate->getError()]);
                $this->error($validate->getError(),url('/Admin/Category/add'));
            }else{
                $categorylogic = new CategoryLogic();
                $check = $categorylogic->checkcate($data);
//                dump($check);
                Db::startTrans();
                try{
                    if ($check['status']=='0'){
//                    return json($check);
                        $this->error($check['msg'],url('/Admin/Category/add'));
                    }else{
                        $id = Db::name('goods_category')->insertGetId($data);
                        if (!$id){
                            $this->error('添加失败了',url('/Admin/Category/add'));
                        }else{
                            if ($data['pid']==0){
                                $args['pid_path']=$data['pid'].'_'.$id;
                                $args['level']=1;
                            }else{
                                //获取1级的分类id
                                $pid = Db::name('goods_category')->where('id',$data['pid'])->find();
//                                dump($pid['pid_path']);

//                            exit();
                                $count = count(explode('_',$pid['pid_path']));
                                $args['pid_path']=$pid['pid_path'].'_'.$id;
                                $args['level']=$count;
                            }


                            $res = Db::name('goods_category')->where('id',$id)->update($args);


                        }

                    }
                    Db::commit(); //提交事务
                    if ($res){
                        $this->success('添加成功',url('/Admin/Category/index'),1);
                    }else{
                        $this->error('添加失败了',url('/Admin/Category/index'),1);
                    }
                }catch (\PDOException $e){
                    Db::rollback(); //回滚事务
                }


            }

    }

    //编辑商品分类页面
    public function change(){
        $id = input('id');
        Session::set('categoryid',$id);
        $categorylist = Db::name('goods_category')->where('id',$id)->select();
        $categorylogic = new CategoryLogic();
        $catelist = $categorylogic->getAllcategory();
//        dump($categorylist);
        $this->assign('catelist',$catelist);
        $this->assign('categorylist',$categorylist);
        return $this->fetch();
    }

    //点击确认修改
    public function updateCategroy(){
        $data = input('post.');

        $id = Session::get('categoryid');
        $validate =  Loader::validate('Category');
        if(!$validate->check($data)){
//                return json(['status'=>0,'msg'=>$validate->getError()]);
            $this->error($validate->getError(),url('/Admin/Category/change'));
        }else{
            $categorylogic = new CategoryLogic();
            $check = Db::name('goods_category')->whereNotIn('id',$id)->where('name',$data['name'])->select();
            //检查是否有下级分类
//            $checkChidren = Db::name('goods_category')->where('pid',$id)->select();
            $cate = Db::name('goods_category')->where('id',$id)->find();
            if ($check){
                $this->error('分类名称重复了',url('Admin/Category/index'));
            }else{

//                if ($data['pid']==0){
//                    $args['pid_path']=$data['pid'].'_'.$id;
//                    $args['level']=1;
//                }else{
//                    //获取1级的分类id
//                    $pid = Db::name('goods_category')->where('id',$data['pid'])->find();
//                    dump($pid['pid_path']);
//                    $count = count(explode('_',$pid['pid_path']));
//                    $args['pid_path']=$pid['pid_path'].'_'.$id;
//                    $args['level']=$count;
//                }
//                if ($cate){
//                    $level=$cate['level'];
//                    switch ($level){
//                        case 1:
//
//                    }
//                    $this->error('不能往下级分类选，只能选上一级分类');
                }


                $res = Db::name('goods_category')->where('id',$id)->update($data);
                if ($res){
                    $this->success('更新成功了',url('Admin/category/index'));
                }else{
                    $this->error('更新失败了',url('Admin/category/index'));
                }

            }



        }


    //删除商品分类
    public function delete(){
            $id = input('id');
            $catelogic = new CategoryLogic();

            $check = $catelogic->checkchildren($id);
            if ($check){
                $this->error('此分类还有下级分类，无法删除',url('Admin/category/index'));
            }else{
                $res = Db::name('goods_category')->where('id',$id)->delete();
                if ($res){
                    $this->success('删除成功',url('Admin/category/index'));
                }else{
                    $this->error('删除失败',url('Admin/category/index'));
                }
            }

//            dump($id);
    }


}
