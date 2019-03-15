<?php
namespace app\index\controller;

use app\common\logic\CartLogic;
use app\common\logic\UserLogic;
use think\Cache;
use think\cache\driver\Redis;
use think\Controller;
use think\Db;
use alisms\SendSms;
use think\Session;

class User extends Base
{
    public $user_id = 0;
    public $user = array();

    public function _initialize() {
        parent::_initialize();
        if(session('?user'))
        {
            $session_user = session('user');
            $user = Db::name('user')->where("id", $session_user['id'])->find();
            session('user',$user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['id'];
            $this->assign('user',$user); //存储用户信息
            $this->assign('user_id',$this->user_id);
        }

    }

    public function index(){
        return $this->fetch();
    }
    public function login()
    {
        if($this->user_id > 0){
            $this->redirect('Index/Index/index');
        }
        return $this->fetch();
    }
    public function plogin()
    {
        if($this->user_id > 0){
            $this->redirect('Index/Index/index');
        }
        return $this->fetch();
    }
    //登录功能
    public function userlogin(){
        $username = input('username');
        $password = input('password');
        $vertify = input('vertify');

        if(!captcha_check($vertify)){
            return json(['status'=>0,'msg'=>'验证码输入错误']);
        };
        $userlogic = new UserLogic();
        $res = $userlogic->login($username,$password);

        if($res['status'] == 1){
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作

        }
        return json($res);
    }
    //手机验证登录
    public function phonelogin(){
        $phone = input('phone');
        $code = input('code');
        $cache = cookie('code');
        if ($cache){
            if ($cache != $code){
                return json(['status'=> 0,'msg'=>'手机验证码错误，请重新输入！']);
            }else{
                $data = Db::name('user')->where('user_phone',$phone)->find();
                $user = $data;
                $userlogic = new UserLogic();
                $userlogic->handleLogin($user,$data);
                $url = '/Index/index/index';
                return json(['status'=>1,'url'=>$url]);

            }
        }else{
            return json(['status'=> 0,'msg'=>'验证码过期，请重新发送！']);
        }

        }

    public function logout(){
        $id = session('id');
        session_unset($id);
        Session::clear('');
        if (!session('id')){
            $this->redirect('/Index/index/index');
        }else{
            $this->error('登出失败了！','/Index/index/index','',1);
        }

    }

//注册
    public  function reg(){
        return view('reg');
    }
    public function userreg(){
        $username = input('username');
        $password = input('password');
        $password2 = input('password2');
        $code = input('code');
        $phone = input('phone');
        if ($password != $password2){
            return json(['status'=> 0,'msg'=>'你输入的两个密码不一样！']);
        }

        //验证手机短信验证码
        $cache = cookie('code');
        $code = input('code');
        if ($cache){
            if ($cache != $code){
                return json(['status'=> 0,'msg'=>'手机验证码错误，请重新输入！']);
            }
        }elseif(!$cache){
            return json(['status'=> 0,'msg'=>'验证码过期，请重新发送！']);
        }

        $userlogic = new UserLogic();
       $res = $userlogic->reg($username,$password,$phone);

       if ($res){
           $this->success('注册成功！','/Index/index/index');
       }else{
           $this->error('注册失败！','/Index/user/reg');
       }

    }

    //发送短信
    public function sendreg(){
        $sms = new SendSms();
        //设置关键的四个配置参数，其实配置参数应该写在公共或者模块下的config配置文件中，然后在获取使用，这里我就直接使用了。
        $sms->accessKeyId = 'LTAI8CDXqHonU0Ck';
        $sms->accessKeySecret = 'FzvkfdtZvEAwagKS0wKBAay99EBJsT';
        $sms->signName = 'acgn周边商城';
        $sms->templateCode = 'SMS_151576238';

        $status = input('status');
        //注册操作
        if ($status==1){
            //$mobile为手机号
            $phone = input('phone');
            $check = new UserLogic();
            $check_phone = $check->checkphone($phone);
            if (!$check_phone){
                return json(['status'=>3,'msg'=>'电话已经被使用！']);
            }
        }else{
            $phone = input('phone');
        }

        //模板参数，自定义了随机数，你可以在这里保存在缓存或者cookie等设置有效期以便逻辑发送后用户使用后的逻辑处理
        $code = mt_rand(1000,9999);
        cookie('code',$code,300);
        $templateParam = array("code"=>$code);
        $m = $sms->send($phone,$templateParam);
        //类中有说明，默认返回的数组格式，如果需要json，在自行修改类，或者在这里将$m转换后在输出
        if($m['Code']== "OK"){
            return json(['status'=> 1]);
//            var_dump($m);
        }elseif ($m['Code']=="isv.BUSINESS_LIMIT_CONTROL"){
            return json(['status'=> 0]);
        }


    }
    //登录发送短信
    public function sendlogin(){
        $sms = new SendSms();
        //设置关键的四个配置参数，其实配置参数应该写在公共或者模块下的config配置文件中，然后在获取使用，这里我就直接使用了。
        $sms->accessKeyId = 'LTAI8CDXqHonU0Ck';
        $sms->accessKeySecret = 'FzvkfdtZvEAwagKS0wKBAay99EBJsT';
        $sms->signName = 'acgn周边商城';
        $sms->templateCode = 'SMS_151576238';

        //$mobile为手机号
        $phone = input('phone');
        $user = Db::name('user')->where('user_phone',$phone)->find();
        if (!$user){
            return json(['status'=> 3,'msg'=>'该号码还未注册！']);
        }
        //模板参数，自定义了随机数，你可以在这里保存在缓存或者cookie等设置有效期以便逻辑发送后用户使用后的逻辑处理
        $code = mt_rand(1000,9999);
        cookie('code',$code,300);
        $templateParam = array("code"=>$code);
        $m = $sms->send($phone,$templateParam);
        //类中有说明，默认返回的数组格式，如果需要json，在自行修改类，或者在这里将$m转换后在输出
        if($m['Code']== "OK"){
            return json(['status'=> 1,'msg'=>$code]);
//            var_dump($m);
        }elseif ($m['Code']=="isv.BUSINESS_LIMIT_CONTROL"){
            return json(['status'=> 0]);
        }


    }

    public function test(){
        $code = mt_rand(1000,9999);
        cookie('code',$code,300);



            return json(['status'=> 1,'code'=>$code]);

    }


}

