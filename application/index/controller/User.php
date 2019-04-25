<?php
namespace app\index\controller;

use app\common\logic\CartLogic;
use app\common\logic\My_Logic;
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
//        dump($user_id);
        if ($user_id>0){
            $user=$this->user;
            $user['user_phone']=hidtel($user['user_phone']);
            $orderlogic = new OrderLogic();
            $orderlogic->setUserId($user_id);
            $order = $orderlogic->user_get_all_order(1,'all');
            if ($order){
                $goods = $orderlogic->get_order_goods($order[0]['id']);
                $this->assign('goods',$goods);
            }

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


            $this->assign('collect',$collect);
            $this->assign('order',$order);
            $this->assign('user',$user);
            return $this->fetch();
        }

    }


    //我的评价
    public function mycomment(){
        self::isLogin();
        $user_id =$this->user_id;
        $mode=input('mode');

        if ($user_id>0){
            $logic = new UserLogic();
            $logic->setUserId($user_id);
            $comment = $logic->user_get_all_comment($mode);
//            dump($comment);
//            die;

            $this->assign('comment',$comment);
            $this->assign('mode',$mode);
            return $this->fetch();
        }
    }

    //我的收藏
    public function mycollect(){
        self::isLogin();
        $user_id =$this->user_id;
        if ($user_id>0){
            $logic = new UserLogic();
            $logic->setUserId($user_id);
            $collect = $logic->user_get_all_collect();
//            dump($collect);
//            die;

            $this->assign('collect',$collect);
            return $this->fetch();
        }
    }

    //个人信息
    public function userinfo(){
        self::isLogin();
        $user_id =$this->user_id;
        if ($user_id>0){
            $logic = new UserLogic();
            $userinfo = $logic->get_one(['id'=>$user_id],'*');
            $userinfo['user_phone']=hidtel($userinfo['user_phone']);
//            dump($userinfo);
            $this->assign('userinfo',$userinfo);
            return $this->fetch();
        }
    }

    //修改个人信息
    public function changeuserinfo(){
        $id =input('id/d');
        $data["user_pic"] =input('user_pic');
        $data["user_nickname"] =input('user_nickname');
        $data["user_sex"] =input('sex');
        $data["birthday"] =strtotime(input('birthday'));
        $logic = new UserLogic();
        $res = $logic->edit(['id'=>$id],$data);
        if ($res){
            $this->success('修改个人信息成功',url('user/userinfo'));
        }else{
            $this->error('修改个人信息失败',url('user/userinfo'));
        }

    }

    //余额
    public function usermoney(){
        self::isLogin();
        $user_id =$this->user_id;
        if ($user_id>0){
            $logic = new UserLogic();
            $logic->setUserId($user_id);
            $userlist = $logic->get_one(['id'=>$user_id],'*');
            $userloglist=$logic->get_all(['user_id'=>$user_id],'*','UserLog');
//            dump($userloglist);
//            die;

            $this->assign('userlist',$userlist);
            $this->assign('userloglist',$userloglist);
            return $this->fetch();
        }
    }
    //处理图片返回路径
    public function img(){
        $file =request()->file('file');
//        dump($file);
//        die;
        if($file){
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
            if($info){
                // 成功上传后 获取上传信息
                $filePathname = $info->getSaveName();
                $path = $this->path.str_replace('\\', '/', $filePathname);
                return json(['status'=>1,'path'=>$path]);
            }else{
                // 上传失败获取错误信息
                echo $file->getError();
            }
        }
        return json(['status'=>0]);
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
        $sms->accessKeyId = 'LTAINy47t2rDMyIC';
        $sms->accessKeySecret = 'i4zVLxCSzxyCVtcbfFosLG9L6imrbJ';
        $sms->signName = 'acgn';
        $sms->templateCode = 'SMS_163725105';

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
        $sms->accessKeyId = 'LTAINy47t2rDMyIC';
        $sms->accessKeySecret = 'i4zVLxCSzxyCVtcbfFosLG9L6imrbJ';
        $sms->signName = 'acgn';
        $sms->templateCode = 'SMS_163725105';

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
//            return json(['status'=> 1,'msg'=>$code]);
            return json(['status'=> 1]);
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


    //判断是否登录
    public function isLogin(){
        if(!session('user')){
            $this->redirect('User/login');
//            $this->error('你还没登录呢！',url('User/login'));
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

    //地址管理
    public function myaddress(){
        self::isLogin();
        $user_id =$this->user_id;
//        dump($user_id);
        if ($user_id>0) {
            $logic = new My_Logic();
            $addresslist = $logic->get_all(['user_id'=>$user_id],'*','Address');
//        dump($addresslist);
            $region=Db::name('region')->column('id,name,level,parent_id');
        }

//        dump($region);
        $this->assign('address',$addresslist);
        $this->assign('count',count($addresslist));
        $this->assign('more',20-count($addresslist));
        $this->assign('region',$region);
        return $this->fetch();
    }

    //安全设置
    public function security(){
        self::isLogin();
        $user_id =$this->user_id;
        if ($user_id>0){
            $logic = new UserLogic();
            $logic->setUserId($user_id);
            $userlist = $logic->get_one(['id'=>$user_id],'*');
//            dump($userlist);


            $this->assign('userlist',$userlist);

            return $this->fetch();
        }
        return $this->fetch();
    }

    //修改登录密码页面
    public function changepwd(){
        self::isLogin();
        $user_id =$this->user_id;
        if ($user_id>0){
            $logic = new UserLogic();
            $logic->setUserId($user_id);
            $userlist = $logic->get_one(['id'=>$user_id],'*');
            $this->assign('userlist',$userlist);

            return $this->fetch();
        }
        return $this->fetch();
    }

    //修改登录密码操作
    public function editpwd(){
        $userid=$this->user_id;
        $user=$this->user;
        $password=input('password');
        $code=input('code');
        $cookiecode = cookie('code');
        $userlogic=new UserLogic();
        if ($code!=$cookiecode){
            return json(['status'=>-100,'msg'=>'验证码错误！']);
        }
        if(!preg_match('/^[a-zA-Z0-9_*-+.]{6,16}$/',$password)) {
            return json(['status'=> -100,'msg'=>'6-16位大小写英文字母、数字或符号的组合！']);
        }
        if($user['user_pwd']== md5(md5($password))){
            return json(['status'=> -100,'msg'=>'密码不能与之前的相同']);
        }
        $where = ['id' => $userid];
        $data = [
            'user_pwd' => md5(md5($password)),
        ];
        $res = $userlogic->edit($where,$data);
//        dump($password);
//        die;
        if ($res) {
            return json(['status'=>200,'msg'=>'修改密码成功！']);
        }else{
            return json(['status'=>-100,'msg'=>'修改密码失败！']);
        }
    }

    //修改手机号码页面
    public function newphone(){
        self::isLogin();
        $user_id =$this->user_id;
        if ($user_id>0){
            $logic = new UserLogic();
            $logic->setUserId($user_id);
            $userlist = $logic->get_one(['id'=>$user_id],'*');
            $this->assign('userlist',$userlist);

            return $this->fetch();
        }
        return $this->fetch();
    }

    //新增手机号码页面
    public function changephone(){
        self::isLogin();
        $user_id =$this->user_id;
        if ($user_id>0){
            $logic = new UserLogic();
            $logic->setUserId($user_id);
            $userlist = $logic->get_one(['id'=>$user_id],'*');
            $this->assign('userlist',$userlist);

            return $this->fetch();
        }
        return $this->fetch();
    }

    //判断手机验证码正确
    public function checkcode(){
        $code=input('code');
        $cookiecode = cookie('code');
        if ($code!=$cookiecode){
            return json(['status'=>-100,'msg'=>'验证码错误！']);
        }else{
            return json(['status'=>200]);
        }
    }

    //新增修改支付密码
    public function addEditPaypwd(){
        $userid=$this->user_id;
        $status = input('status');
        $paypwd = input('pwdfirst');
        $userlogic=new UserLogic();
        $phone = input('phone');
        $code = input('code');
        $cookiecode = cookie('code');
        if ($code!=$cookiecode){
            return json(['status'=>-100,'msg'=>'验证码错误！']);
        }
        if (!$cookiecode){
            return json(['status'=>-100,'msg'=>'验证码过期！请重新发送']);
        }
        if ($status==2||$status==3){//新建支付密码或者同时绑定手机号码
            $paypwdsecond = input('pwdsecond');
            if ($paypwd!=$paypwdsecond){
                return json(['status'=>-100,'msg'=>'输入的两次密码不一样！']);
            }
            if ($status==2) {//执行新建支付密码和绑定手机号码
                if (!$phone){
                    return json(['status'=>-100,'msg'=>'请输入手机号码！']);
                }
                $where = ['id' => $userid];
                $data = [
                    'paypwd' => md5(md5($paypwd)),
                    'user_phone' => $phone,
                ];
                $res = $userlogic->edit($where, $data);
                if ($res) {
                    return json(['status'=>200,'msg'=>'新建支付密码成功！']);
                }
            }else{
                $where = ['id' => $userid];
                $data = [
                    'paypwd' => md5(md5($paypwd)),
                ];
                $res = $userlogic->edit($where, $data);
                if ($res) {
                    return json(['status'=>200,'msg'=>'新建支付密码成功！']);
                }
            }
        }else{//修改支付密码

            $where = ['id' => $userid];
            $data = [
                'paypwd' => md5(md5($paypwd)),
            ];
            $res = $userlogic->edit($where,$data);
            if ($res) {
                return json(['status'=>200,'msg'=>'修改支付密码成功！']);
            }else{
                return json(['status'=>-100,'msg'=>'修改失败！']);
            }
        }
    }

    //新增更改绑定手机号码
    public function addeditUserPhone(){
        $userid=$this->user_id;
        $user=$this->user;
        $userlogic=new UserLogic();
        $phone = input('phone');
        $code = input('code');
        $cookiecode = cookie('code');
        if ($code!=$cookiecode){
            return json(['status'=>-100,'msg'=>'验证码错误！']);
        }
        if (!$cookiecode){
            return json(['status'=>-100,'msg'=>'验证码过期！请重新发送']);
        }
        $check=$userlogic->get_one(['user_phone'=>$phone],'*');
        if ($check){
            return json(['status'=>-100,'msg'=>'该手机已经被使用过了，请换另一个']);
        }
        $where = ['id' => $userid];
        $data = [
            'user_phone' => $phone,
        ];
        $res = $userlogic->edit($where, $data);
        if ($res) {
            return json(['status'=>200,'msg'=>'操作成功！']);
        }else{
            return json(['status'=>-100,'msg'=>'操作失败']);
        }
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

    //删除地址
    public function deladdress(){
        $address_id = input('address_id/d',0);
        $logic = new My_Logic();
        $where = ['address_id'=>$address_id];
        $res = $logic->del($where,'Address');
        if (!$res){
            return json(['status'=>0,'msg'=>'操作失败']);
        }
        return json(['status'=>1,'msg'=>'操作成功']);
    }

    //设置默认地址
    public function setdefaultaddress(){
        $address_id = input('address_id/d',0);
        $logic = new My_Logic();
        //先把所有默认变为0
        $w=['user_id'=>$this->user_id];
        $d = ['is_default'=>0];
        $res1 = $logic->edit($w,$d,'Address');
        if (!$res1){
            return json(['status'=>0,'msg'=>'操作失败']);
        }
        $where = ['address_id'=>$address_id];
        $data=['is_default'=>1];
        $res = $logic->edit($where,$data,'Address');
        if (!$res){
            return json(['status'=>0,'msg'=>'操作失败']);
        }
        return json(['status'=>1,'msg'=>'操作成功']);
    }

    //测试充值
    public function getMoney(){
            $money = input('money');
            $orderid = input('orderid',0);
            $order_sn = input('order_sn',0);
            $userid = $this->user_id;
            $user=$this->user;
            $logic = new UserLogic();
            $where=['id'=>$userid];
            $data=['user_money'=>$money+$user['user_money']];
            $res = $logic->edit($where,$data);
            if (!$res){
                return json(['status'=>0,'msg'=>'充值失败']);
            }else{
                //写入用户账户日志
                $data=[
                    'user_id'=>$this->user_id,
                    'user_money'=>"+".$money,
                    'change_time'=>time(),
                    'desc'=>'充值金额',
                    'order_sn'=>$order_sn,
                    'order_id'=>$orderid,
                    ];
                $res1 = $logic->add($data,'UserLog');
                if (!$res1){
                    return json(['status'=>0,'msg'=>'写入日志失败']);
                }
            }
             return json(['status'=>1,'msg'=>'充值成功,请重新点击支付']);
    }

}

