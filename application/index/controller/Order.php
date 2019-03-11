<?php
namespace app\index\controller;

use app\common\logic\CartLogic;
use app\common\logic\OrderLogic;
use phpDocumentor\Reflection\Types\Object_;
use think\Db;
use think\Loader;

class Order extends Base {
    public $cartLogic;//购物车模型
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


    public function index()
    {
        if ($this->user_id == 0) {
            $this->error('请先登录', url('index/User/login'));
        }
        //获取用户购物车选中的商品
        $user_id=$this->user_id;
        $where=['a.user_id'=>$user_id,'a.selected'=>1];
        $field = 'a.id as car_id,a.goods_name,a.goods_num,s.original_img,a.goods_price';
        $logic = new OrderLogic();
        $cartlist = $logic->get_order_list($where,$field);
        if (!$cartlist){
            return json(['status'=>0,'msg'=>'你还没有选中要结算的商品！']);
        }
        //计算总金额
        $totalprice=0;
        foreach ($cartlist as $k=>$v) {
            $totalprice += $cartlist[$k]['goodstotalprice'];
        }
        //收获地址
        $where = ['user_id'=>$this->user_id];
        $address = $logic->get_MyAddress($where);

        $this->assign('goodslist',$cartlist);
//        dump($address);
        $this->assign('address',$address);
        $this->assign('totalprice',number_format($totalprice,2));
        return $this->fetch();
    }

    /**
     * 生成订单
     * @param array
     */
    public function setorder(){
        if ($this->user_id == 0) {
            exit(json_encode(array('status' => -100, 'msg' => "登录超时请重新登录!")));
        }
        $data = input('Checkout/a');
        $cartidlist = $data['cart_id'];//订单中的商品
        $validate = Loader::validate('Order');
        if (!$validate->check($data)){
            $error = $validate->getError();
            exit(json_encode(['status' => 0, 'msg' => $error[0]]));
        }
        $logic = new OrderLogic();
        Db::startTrans();
        try{
            $logic->setUserId($this->user_id);
            $orderid = $logic->set_order($data);//写入订单表
            if (!$orderid){
                $this->error('生成订单失败',url('cart/index'));
            }

            $data=[
                'order_sn'=>date('YmdHis').$orderid
            ];
            $where =['id'=>$orderid];
            $res = $logic->edit($where,$data);//自动生成订单号
            if ($res){
                //商品写入订单中的商品表
                $field = 'goods_id,goods_num,goods_name,goods_sn,spec_key,spec_key_name,prom_type,prom_id';
//                exit(json_encode($cartidlist));
                $ordergoods=$logic->setordergoods($cartidlist,$field,$orderid);
                if ($ordergoods){
                    //删除购物车对应的商品
                    $delcart=$logic->delcartgoods($cartidlist);
                    if ($delcart){
                        Db::commit();
//                        exit(json_encode(array('status' => 1, 'msg' => "成功生成订单")));
                        $this->success('成功生成订单',url('index/order/orderpay',array('order_id'=>$orderid)));
                    }
                }else{
                    $this->error('生成订单失败了',url('cart/index'));
                }

            }else{
                $this->error('操作失败',url('cart/index'));
//                exit(json_encode(array('status' => -100, 'msg' => "操作失败!")));
            }

        }catch (\PDOException $e){
            Db::rollback();
        }

    }

    /**
     * 提交订单成功后的页面
     */
    public function orderpay(){
        $orderid = input('order_id');
        $logic = new OrderLogic();
        $where = ['id'=>$orderid];
        $order = $logic->get_one($where,'*','Order');
        $this->assign('order',$order);
        $this->assign('user',$this->user);
        return $this->fetch();
    }

}