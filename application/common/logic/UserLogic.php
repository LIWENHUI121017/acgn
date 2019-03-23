<?php
namespace app\common\logic;

use app\index\controller\User;
use think\Db;
use think\Session;

class UserLogic extends My_Logic
{
    public function __construct(){
        $this->table = 'User';
    }
    public function login($username,$password){
        $data['user_name'] = $username;
        $data['user_pwd'] = $password;
        $user = Db::name('user')->where($data)->find();
        if (!$user){
            return json(['status'=>0,'msg'=>'输入的账号密码不正确']);
        }
        $this->handleLogin($user,$data);
         $url = '/Index/index/index';
        return (['status'=>1,'url'=>$url,'result' => $user]);
    }
//记录登录日志
    public function handleLogin($user,$data){

            if (empty($user['user_count'])){
                $count = 1;
            }else{
                $count = $user['user_count']+1;
            }
            session('user',$user);
            session('username',$user['user_name']);
            session('user_id',$user['id']);
            session('nickname',$user['user_nickname']);
            session('lastlogin',$user['user_lastlogin']);

        setcookie('user_id',$user['id'],null,'/');
        setcookie('nickname',$user['user_nickname'],null,'/');
        setcookie('cn',0,time()-3600,'/');

        Db::name('user')->where('user_name',$data['user_name'])->update(['user_lastlogin'=>time(),'user_count'=>$count]);

    }

    //检查用户名是否存在
    public function checkusername($username){
        $data['user_name'] = $username;

        $reg = Db::name('user')->where('user_name',$data['user_name'])->find();
        if ($reg){
            return ['status'=>0,'msg'=>'账号重复了'];
        }
    }

    //注册
    public function reg($username,$password,$phone){
           $data['user_name'] = $username;
           $data['user_pwd'] = $password;
           $data['user_regtime'] = time();
           $reg = Db::name('user')->where('user_name',$data['user_name'])->find();
           if ($reg){
               return ['status'=>0,'msg'=>'账号重复了'];
           }
          $res = Db::name('user')->insert($data);
          if (!$res){
              return false;
          }else{
              $user = Db::name('user')->where($data)->find();
              $this->handleReg($user,$data,$phone);

             return true;
          }
    }

    //注册后登录记录日志
    public function handleReg($user,$data,$phone){

        $nickname = $user['user_name'];
        $lastlogin = time();
        if (empty($user['user_count'])){
            $count = 1;
        }else{
            $count = $user['user_count']+1;
        }
            Db::name('user')->where('user_name',$data['user_name'])->update(['user_nickname'=>$nickname,'user_count'=>$count,'user_lastlogin'=>$lastlogin,'user_phone'=>$phone]);
            $reg = Db::name('user')->where('user_name',$data['user_name'])->find();
        session('username',$reg['user_name']);
        session('id',$reg['id']);
        session('nickname',$reg['user_nickname']);
        session('lastlogin',$reg['user_lastlogin']);


    }

    //检查号码是否注册使用过
    public function checkphone($phone){
        $res = Db::name('user')->where('user_phone',$phone)->find();
        if (!$res){
            return true;
        }
            return false;

    }

    //所有会员列表
    public function get_all_user(){
        $res=$this->get_all(array(),$field = '*',$table,$order='');
    }

}
