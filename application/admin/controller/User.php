<?php
namespace app\admin\controller;



use app\common\logic\AttributeLogic;
use app\common\logic\SpecLogic;
use app\common\logic\UserLogic;
use think\Db;
use think\Loader;

class User extends Base
{

    public function _initialize()
    {
        return parent::_initialize();
    }

    public function index()
    {
        $logic = new UserLogic();
        $user = $logic->get_all_user();
//        dump($user);
        $this->assign('user',$user);
        return $this->fetch();
    }

    //用户信息详情页面
    public function userinfo(){
        $userid = input('id');
        $logic = new UserLogic();
        $where =['id'=>$userid];
        $user = $logic->get_one_user($where);
//        dump($user);

        $this->assign('user',$user);
        return $this->fetch();
    }

    //搜索
    public function search(){
        $data = input('search');
        $where = ['user_nickname','like',"%".$data."%"];
        $logic =new UserLogic();
        $res = $logic->get_all($where);
        $this->assign('user',$res);
        return $this->fetch('search');
    }
}
