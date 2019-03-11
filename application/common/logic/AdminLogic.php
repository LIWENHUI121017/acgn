<?php
namespace app\common\logic;

use think\Db;
use think\Session;

class AdminLogic
{
    public function index()
    {
       return $this->fetch();
    }

    public function login($username,$password){

        if (empty($username) || empty($password)){
            return ['status' => 0, 'msg' => '请填写账号密码'];
        }

        $data['admin_name'] = $username;
        $data['admin_pwd'] = md5(md5($password));

        $admin = Db::name('admin')->where($data)->find();

        if (!$admin){
            return ['status' => 0, 'msg' => "输入的账号或密码不正确"];
        }

        $this->handleLogin($admin);
        $url = '/Admin/index/index';
        return ['status' => 1, 'url' => "$url"];
    }

    public function handleLogin($admin){
        Db::name('admin')->where('id',$admin['id'])->update([
            'admin_login' => time(),
        ]);

        session('admin_id',$admin['id']);
        session('admin_name',$admin['admin_name']);
        session('admin_name',$admin['admin_name']);
        session('last_login',$admin['admin_login']);
    }

    public function logout($adminId){
        session_unset();
        session_destroy();
        Session::clear('');

    }
}
