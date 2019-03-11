<?php
namespace app\admin\controller;
use think\Controller;
use think\Db;
use think\Session;
use think\Loader;

class Base extends Controller{

    public $admin_id = 0;
    protected $rules;//权限验证规则

    public function _initialize(){

        if(!session('admin_id')){
            $this->redirect('/Admin/login');
            $this->error('请登录','/Admin/login','',1);
        }else{
            $this->admin_id = session('admin_id') ;
        }

        Loader::import("org/Auth", EXTEND_PATH);
        $auth=new \Auth();
//      $this->current_action = request()->module().'/'.request()->controller().'/'.lcfirst(request()->action());
        $this->rules = request()->controller().'/'.lcfirst(request()->action());
        $result = $auth->check($this->rules,$this->admin_id);
        if($result){
//            $res = Db::name('auth_group')
//                ->alias('a')
//                ->join('auth_group_access b','a.id = b.group_id')
//                ->where('b.uid' ,$this->admin_id)
//                ->field('id')
//                ->find();
//            var_dump($res);
//            $this->assign('role',$res['id']);

           $res =  $auth->getGroups($this->admin_id);//返回所查询Id对应authRules和authRulesAcces所有的字段

            $this->assign('role',$res[0]['group_id']);
        }else{

            return json(['status'=>0,'msg'=>'']);

        }


    }
}