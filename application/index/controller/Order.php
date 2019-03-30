<?php
namespace app\index\controller;

use app\common\logic\CartLogic;
use app\common\logic\GoodsLogic;
use app\common\logic\OrderLogic;
use app\common\logic\UserLogic;
use app\common\model\SpecGoodsPrice;
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
//            return json(['status'=>0,'msg'=>'你还没有选中要结算的商品！']);
            $this->error('你还没有选中要结算的商品',url('index/cart/index'));
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
//        dump($data);
//        die;
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
//        dump($order);
        $this->assign('order',$order);
        $this->assign('user',$this->user);
        return $this->fetch();
    }

    /**
     * 付款
     * @param int $paypwd 支付密码
     * @param int $paypwdsecond 再次输入的支付密码
     * @param int $status 判断执行不同操作的状态码
     * @param int $phone 手机号码
     * @param int $code 用户输入的验证码
     * @return array
     */
    public function toPay(){
        $userid=$this->user_id;
        $user = $this->user;
        $orderid = input('orderid');
//        dump($orderid);
        $status = input('status');
        $paypwd = input('pwdfirst');
        $userlogic = new UserLogic();
        if ($status==2){
            $paypwdsecond = input('pwdsecond');
            $code = input('code');
            $cookiecode = cookie('code');
            $phone = input('phone');
            if ($paypwd!=$paypwdsecond){
                return json(['status'=>-100,'msg'=>'输入的两次密码不一样！']);
            }
            if (!$phone){
                return json(['status'=>-100,'msg'=>'请输入手机号码！']);
            }
            if ($code!=$cookiecode){
                return json(['status'=>-100,'msg'=>'验证码错误！']);
            }
            if ($status==2) {//执行新建支付密码和绑定手机号码

                $where = ['id' => $userid];
                $data = [
                    'paypwd' => $paypwd,
                    'user_phone' => $phone,
                ];
                $res = $userlogic->edit($where, $data);
                if ($res) {
                    return json(['status'=>200,'msg'=>'新建支付密码成功！请重新点击下一步进行支付！']);
                }
            }

        }else{
            $where=['id'=>$userid,'paypwd'=>$paypwd];
//            dump($paypwd);
           $res = $userlogic->get_one($where);
           if ($res){
               Db::startTrans();
               try{
                    //订单状态
                   $orderlogic = new OrderLogic();
                   $where=['id'=>$orderid];
                   $data=['order_status'=>0,'pay_status'=>1,'pay_time'=>time()];
                   $orderres = $orderlogic->edit($where,$data);
                   $order=$orderlogic->get_one($where);

                   //订单日志
                   $data=[
                       'order_id'=>$orderid,
                       'action_user'=>0,
                       'order_status'=>0,
                       'shipping_status'=>0,
                       'pay_status'=>1,
                       'action_note'=>'支付成功，耐心等候发货!!',
                       'status_desc'=>'',
                       'log_time'=>time(),
                   ];
                   $table = 'order_action';
                   $orderactionres = $orderlogic->add_order_action($data,$table);

                   //扣除账号金额
                   $where=['id'=>$userid];
                   $money = $user['user_money']-$order['order_amount'];
//                   dump($money);
                   $data=['user_money'=>$money];
                   $useres = $userlogic->edit($where,$data,'User');

                   //商品库存减少
                   $where=['order_id'=>$orderid];
                   $field = 'goods_id,spec_key,goods_num';
                   $ordergoodsres = $orderlogic->get_all($where,$field,'OrderGoods');
                   $goodslogic = new GoodsLogic();
                   foreach ($ordergoodsres as $k=>$v) {
                       if ($v['spec_key']==0){//没有商品规格
                           $where=['id'=>$v['goods_id']];
                           $field = 'goods_inventory';
                           $goods = $goodslogic->get_one($where,$field,'Goods');
                           $data=['goods_inventory'=>$goods['goods_inventory']-$v['goods_num']];
                           $res = $goodslogic->edit($where,$data);
                       }else{//有商品规格
                            $where=[
                                'goods_id'=>$v['goods_id'],
                                'key'=>$v['spec_key'],
                                ];
                           $field = 'store_count';
                           $goods = $goodslogic->get_one($where,$field,'SpecGoodsPrice');
                           $data=['store_count'=>$goods['store_count']-$v['goods_num']];
                           $res = $goodslogic->edit($where,$data,'SpecGoodsPrice');
                       }
                   }

                   if ($orderres&&$orderactionres&&$useres&&$res){
                       Db::commit();
                       $url = "/index/order/paysuccess/order_id/$orderid";
                       return json(['status'=>300,'msg'=>'支付成功！','url'=>$url]);
                   }else{
                       return json(['status'=>-100,'msg'=>'支付失败，请联系管理员！']);
                   }

               }catch (\PDOException $e){
                   Db::rollback();
               }

           }else{
               return json(['status'=>-100,'msg'=>'支付密码错误！']);
           }
        }

    }

    /**
     * 订单支付成功
     */
    public function paysuccess(){
        $orderid = input('order_id');
        $logic = new OrderLogic();
        $where = ['id'=>$orderid];
        $order = $logic->get_one($where,'*','Order');
        if ($order['pay_status']!=1){
          $this->redirect('index/order/orderpay',array('order_id'=>$orderid));
        }
        $this->assign('order',$order);
        $this->assign('user',$this->user);
        return $this->fetch();
    }

    //取消订单
    public function cancel_order(){
        $id = input('id/d');
        $logic = new OrderLogic();
        $data = $logic->cancel_order($this->user_id,$id);
        return json($data);
    }
}