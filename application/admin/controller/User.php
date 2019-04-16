<?php
namespace app\admin\controller;



use app\common\logic\AttributeLogic;
use app\common\logic\SpecLogic;
use app\common\logic\UserLogic;
use think\Db;
use think\Loader;
use think\Request;

class User extends Base
{

    public function _initialize()
    {
        return parent::_initialize();
    }

    public function index(){
        $type=input('type');
        $search=input('search');
        $logic = new UserLogic();
        $user = $logic->get_all_user($type,$search);
//        dump($user);
        $this->assign('user',$user);
        $this->assign('count',count($user));
        return $this->fetch();
    }

    //用户信息详情页面
    public function userinfo(){
        $userid = input('id');
        $logic = new UserLogic();
        $where =['id'=>$userid];
        $user = $logic->get_one_user($where);
        $this->assign('user',$user);
        return $this->fetch();
    }

    //添加会员页面
    public function add(){
        return $this->fetch();
    }

    //搜索
    public function search(){
        $data = input('search');
        $where = [
            'user_nickname'=>['like',"%".$data."%"],
        ];
        $logic =new UserLogic();
        $res = $logic->get_all($where);

        $this->assign('user',$res);
        return $this->fetch('search');
    }


    //添加或者修改会员信息
    public function addEditUser(){
        $id = input('id');
        $data=input('post.');
        $logic=new UserLogic();

        if ($id){//有id就是修改
                if ($data['password1']!=$data['password2']){
                    $this->error('你输入的两次密码不一样',url('User/userinfo',array('id'=>$id)));
                }else{
                    $data['user_pwd']=$data['password2'];
                }
            $res = $logic->addEditUser($id,$data);
            if($res){
                $this->success('操作成功',url('User/index'));
            }else{
                $this->error('未作内容修改或修改失败',url('User/userinfo',array('id'=>$id)));
            }
        }else{
            $data['user_pwd']=md5(md5($data['user_pwd']));
            $res = $logic->addEditUser($id,$data);
            if($res){
                $this->success('操作成功',url('User/index'));
            }else{
                $this->error('新增失败',url('User/add'));
            }
        }



    }

    //判断用户名和手机号码是否已经存在
    public function checkEmailPhone(){
        $username=input('name');
        $phone=input('phone');
        $logic = new UserLogic();

        if ($username){
           $where=['user_name'=>$username];
            $res = $logic->get_one($where,'*','User');
            if ($res){
                return json(['status'=>0,'msg'=>'用户名已存在！']);
            }else{
                return json(['status'=>1]);
            }
        }

        if ($phone){
            $where=['user_phone'=>$phone];
            $res = $logic->get_one($where,'*','User');
            if ($res){
                return json(['status'=>0,'msg'=>'手机号码已经被使用过了！']);
            }else{
                return json(['status'=>1]);
            }
        }

    }
}
