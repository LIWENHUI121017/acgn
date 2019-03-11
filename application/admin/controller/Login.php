<?php
namespace app\admin\controller;

use app\common\logic\AdminLogic;
use think\captcha\Captcha;
use think\Controller;


class login extends Controller
{

    public function index()
    {
       return $this->fetch();

    }

    //管理员登录
    public function login(){
        $vertify = input('vertify');
        $username = input('username');
        $password = input('password');

        if(!captcha_check($vertify)){
           return json(['status'=>0,'msg'=>'验证码输入错误']);
        };
        $adminlogic = new AdminLogic();
        $res = $adminlogic->login($username,$password);
        return json($res);
//        $this->success($res,'Admin/index/index');

        if (session('?admin_id')&&session('admin_id')>0){
            $this->error('您已经登录','Admin/index/index');
        }
        return $this->fetch();
    }


    public function logout(){
        $adminlogic = new AdminLogic();
        $adminlogic->logout(session('admin_id'));

        $this->success('退出成功！','/Admin/login',null,1);
    }

}
