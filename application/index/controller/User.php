<?php
namespace app\index\controller;

use app\common\logic\CartLogic;
use app\common\logic\OrderLogic;
use app\common\logic\UserLogic;
use app\common\model\Address;
use app\common\validate\UserAddress;
use think\Cache;
use think\cache\driver\Redis;
use think\Config;
use think\Controller;
use think\Db;
use alisms\SendSms;
use think\Loader;
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
        $this->order_status = config('ORDER_STATUS');
        $this->pay_status = config('PAY_STATUS');
        $this->shipping_status = config('SHIPPING_STATUS');
        // 订单 支付 发货状态
        $this->assign('order_status',$this->order_status);
        $this->assign('pay_status',$this->pay_status);
        $this->assign('shipping_status',$this->shipping_status);

    }

    //个人中心
    public function index(){
        self::isLogin();
        $user_id =$this->user_id;
        if ($user_id>0){
            $user=$this->user;
            $user['user_phone']=hidtel($user['user_phone']);
            $orderlogic = new OrderLogic();
            $orderlogic->setUserId($user_id);
            $order = $orderlogic->user_get_all_order(1);
            $goods = $orderlogic->get_order_goods($order[0]['id']);
            $where=[
                'a.user_id'=>$user_id,
            ];
            $collect = model('GoodsCollect')
                ->alias('a')
                ->join('goods s','a.goods_id=s.id')
                ->where($where)
                ->limit(3)
                ->select()
                ->toArray();


            $this->assign('goods',$goods);
            $this->assign('collect',$collect);
            $this->assign('order',$order);
            $this->assign('user',$user);
            return $this->fetch();
        }

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

                    $cartLogic = new CartLogic();
                    $cartLogic->setUserId($user['id']);
                    $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作

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

    //注册页面
    public  function reg(){
        return $this->fetch('reg');
    }
    //点击注册
    public function userreg(){
        $username = input('username');
        $password = input('password');
        $password2 = input('password2');
        $phone = input('phone');
        $userlogic = new UserLogic();

//        if(!preg_match('/((?=.*[0-9])(?=.*[A-z]))|((?=.*[A-z])(?=.*[^A-z0-9]))|((?=.*[0-9])(?=.*[^A-z0-9]))^.{6,16}$/',$password)) {
        if(!preg_match('/^[a-zA-Z0-9_*-+.]{6,16}$/',$password)) {
            return json(['status'=> 0,'msg'=>'6-16位大小写英文字母、数字或符号的组合！']);
        }
        if (empty($username)){
            return json(['status'=> 0,'msg'=>'用户名不能为空！']);
        }
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


       $res = $userlogic->reg($username,$password,$phone);

       if ($res){
           return json(['status'=> 1,'msg'=>'注册成功','url'=>'/Index/index/index']);
//           $this->success('注册成功！','/Index/index/index');
       }else{
           return json(['status'=> 0,'msg'=>'注册失败','url'=>'/Index/user/reg']);
//           $this->error('注册失败！','/Index/user/reg');
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
        $sms->accessKeyId = Config::get('accessKeyId');
        $sms->accessKeySecret = Config::get('accessKeySecret');
        $sms->signName = Config::get('signName');
        $sms->templateCode = Config::get('templateCode');

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

    //模拟发送短信
    public function test(){
        $code = mt_rand(1000,9999);
        cookie('code',$code,300);



            return json(['status'=> 1,'code'=>$code]);

    }


    //我的订单
    public function myorder(){
        self::isLogin();
        $user_id =$this->user_id;
        if ($user_id>0){
            $orderlogic = new OrderLogic();
            $orderlogic->setUserId($user_id);
            $order = $orderlogic->user_get_all_order();
            foreach ($order as $key=>$value){
                $order[$key]['goodsinfo'] = $orderlogic->get_order_goods($value['id']);
            }
//            dump($order);
//            die;
//            $this->assign('goods',$goods);
            $this->assign('order',$order);
            return $this->fetch();
        }
    }


    //判断是否登录
    public function isLogin(){
        if(!session('user')){
            $this->error('你还没登录呢！',url('User/login'));
        }
    }

    //用户地址
    public function address()
    {
        $address_id = input('address_id/d',0);
        $logic = new UserLogic();
        $where = ['address_id'=>$address_id,'user_id'=> $this->user_id];
        $userAddress = $logic->get_one($where,'*','Address');
//        dump($userAddress);
//        die;
        if(empty($userAddress)){
           return json(['status' => 0, 'msg' => '参数错误']);
        }
        $province_list = Db::name('region')->where('parent_id',0)->select();
        $city_list = Db::name('region')->where('parent_id',$userAddress['province'])->select();
        $district_list = Db::name('region')->where('parent_id',$userAddress['city'])->select();
        $town_list = Db::name('region')->where('parent_id',$userAddress['district'])->select();
        return json(['status' => 1, 'msg' => '获取成功','result'=>['user_address'=>$userAddress,'province_list'=>$province_list,'city_list'=>$city_list,'district_list'=>$district_list,'twon_list'=>$town_list]]);
    }

    //地址选择器
    public function region(){
        $parent_id = input('parent_id');
        $selected = input('selected',0);
        $where=[
            'parent_id'=>$parent_id,
        ];
        $logic = new UserLogic();
        $data = $logic->get_all($where,'*','Region');$html = '';
        if ($data) {
            foreach ($data as $h) {
                if ($h['id'] == $selected) {
                    $html .= "<option value='{$h['id']}' selected>{$h['name']}</option>";
                }
                $html .= "<option value='{$h['id']}'>{$h['name']}</option>";
            }
        }
        echo $html;
    }

    //新增或编辑地址
    public function addressSave(){
        $address_id = input('address_id/d',0);
        $data = input('post.');
//        dump($data);
//        die;

        $logic = new UserLogic();
        $userAddressValidate = Loader::validate('UserAddress');
        if (!$userAddressValidate->batch()->check($data)) {
            return json(['status' => 0, 'msg' => '操作失败', 'result' => $userAddressValidate->getError()]);
        }
        if ($address_id>0) {
            //编辑
            $Address = Address::get(['address_id'=>$address_id,'user_id'=> $this->user_id]);
            if(empty($Address)){
               return json(['status' => 0, 'msg' => '参数错误']);
            }
        } else {
            //新增
            $Address = new Address();
            $user_address_count = Db::name('address')->where("user_id", $this->user_id)->count();
            if ($user_address_count >= 20) {
                return json(['status' => 0, 'msg' => '最多只能添加20个收货地址']);
            }
            $data['user_id'] = $this->user_id;
        }
        $Address->data($data, true);
        $row = $Address->allowField(true)->save();
        if ($row !== false) {
            return json(['status' => 1, 'msg' => '操作成功']);
        } else {
            return json(['status' => 0, 'msg' => '操作失败']);
        }
    }


}

