<?php
namespace app\common\logic;

use app\index\controller\User;
use think\Db;
use think\Session;

class UserLogic extends My_Logic{
    protected $userId;

    public function __construct(){
        $this->table = 'User';
    }
    //设置userid
    public function setUserId($user_id)
    {
        $this->userId = $user_id;
    }
    public function login($username,$password){
        $data['user_name'] = $username;
        $data['user_pwd'] = md5(md5($password));
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
           $data['user_pwd'] = md5(md5($password));
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


        session('user',$reg);
        session('username',$reg['user_name']);
        session('user_id',$reg['id']);
        session('nickname',$reg['user_nickname']);
        session('lastlogin',$reg['user_lastlogin']);

        setcookie('user_id',$reg['id'],null,'/');
        setcookie('nickname',$reg['user_nickname'],null,'/');
        setcookie('cn',0,time()-3600,'/');

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
    public function get_all_user($type,$search){
        $where=array();
        if ($type&&$search){
            if ($type=='nick'){
                $where['user_nickname']=["like","%".$search."%"];
            }else{
                $where['user_name']=["like","%".$search."%"];
            }
        }
        $res=$this->get_all($where);
        return $res;
    }

    //获取一个会员
    public function get_one_user($where,$field='*'){
        $res = $this->get_one($where,$field);
        return $res;
    }

    //新增或修改会员
    public function addEditUser($id,$data){
        if ($id){
            //修改
            $list = [
                'id'=>$data['id'],
                'user_name'=>$data['user_name'],
                'user_phone'=>$data['user_phone'],
                'user_qq'=>$data['user_qq'],
                'user_regtime'=>strtotime($data['user_regtime']),
                'is_disable'=>$data['is_disable'],
            ];
            if (!empty($data['user_pwd'])){
                     $list['user_pwd']=md5(md5($data['user_pwd']));
            }
            $where = ['id'=>$id];
            $res = $this->edit($where,$list);
            return $res;
        }else{
            //新增
            $data['user_regtime'] = time();
            $res = $this->add($data);
            return $res;
        }
    }

    //前端获取所有评价
    public function user_get_all_comment($mode){
       if ($mode=='all') {
           $where=[
               's.user_id'=>$this->userId,
               's.order_status'=>[array('eq',4),array('eq',6), 'or']
           ];
        }elseif($mode=="waitcomment"){
           $where=[
               's.user_id'=>$this->userId,
               's.order_status'=>4,
               'a.is_comment'=>0
           ];
       }else{
           $where=[
               's.user_id'=>$this->userId,
               's.order_status'=>6,
           ];
       }
        $field='s.*,a.id,a.order_id,a.goods_id,a.goods_name,a.goods_sn,a.goods_num,a.spec_key_name,a.is_comment,a.is_send,n.original_img,m.price as goods_price';
        $res = Db::name('order_goods')
            ->alias('a')
            ->join('order s','a.order_id=s.id')
            ->join('goods n','a.goods_id=n.id')
            ->join('spec_goods_price m','a.spec_key=m.key and a.goods_id=m.goods_id')
            ->field($field)
            ->where($where)
            ->select();

        return $res;
    }

    //前端获取所有收藏
    public function user_get_all_collect(){
            $where=[
                'user_id'=>$this->userId,
            ];
            $field = "a.*,s.goods_name,s.original_img,s.goods_inventory,s.goods_price";
         $res = Db::name('goods_collect')
             ->alias('a')
             ->join('goods s','a.goods_id=s.id')
             ->where($where)
             ->field($field)
             ->select();
        return $res;
    }
}
