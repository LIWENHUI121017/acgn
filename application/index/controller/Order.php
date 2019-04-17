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
//        dump($cartlist);
        //检查是否有库存不足的商品，商品数量为0
        foreach ($cartlist as $k=>$v) {
            if ($v['goods_num']==0&&$v['selected']==1){
                return json(['status'=>0,'msg'=>$v['goods_name'].'商品数量必须大于0']);
            }
        }
        if (!$cartlist){
//            return json(['status'=>0,'msg'=>'你还没有选中要结算的商品！']);
            $this->error('你还没有选中要结算的商品',url('index/cart/index'));
        }
        //计算总金额
        $totalprice=0;
        foreach ($cartlist as $k=>$v) {
            $totalprice += floatval($cartlist[$k]['goodstotalprice']);
        }
        //收获地址
        $where = ['user_id'=>$this->user_id];
        $address = $logic->get_MyAddress($where);

        $this->assign('goodslist',$cartlist);
//            dump($totalprice);
//            die;
        $this->assign('address',$address);
        $this->assign('totalprice',$totalprice);
        return $this->fetch();
    }

    //生成订单
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

    //提交订单成功后的页面
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

    //付款
    public function toPay(){
        $userid=$this->user_id;
        $user = $this->user;
        $orderid = input('orderid');
        $status = input('status');
        $paypwd = input('pwdfirst');
        $userlogic = new UserLogic();
        if ($status==2||$status==3){//新建支付密码同时绑定手机号码
            $paypwdsecond = input('pwdsecond');
            $code = input('code');
            $cookiecode = cookie('code');
            $phone = input('phone');
            if ($paypwd!=$paypwdsecond){
                return json(['status'=>-100,'msg'=>'输入的两次密码不一样！']);
            }

            if ($code!=$cookiecode){
                return json(['status'=>-100,'msg'=>'验证码错误！']);
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
                    return json(['status'=>200,'msg'=>'新建支付密码成功！请重新点击下一步进行支付！']);
                }
            }else{
                $where = ['id' => $userid];
                $data = [
                    'paypwd' => md5(md5($paypwd)),
                ];
                $res = $userlogic->edit($where, $data);
                if ($res) {
                    return json(['status'=>200,'msg'=>'新建支付密码成功！请重新点击下一步进行支付！']);
                }
            }

        }else{
            $where=['id'=>$userid,'paypwd'=>md5(md5($paypwd))];
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
                   //判断余额是否足够扣除
                   if ($user['user_money']<$order['order_amount']){
                       return json(['status'=>-500,'msg'=>'余额不足，请充值后使用！']);
                   }
                   $where=['id'=>$userid];
                   $money = $user['user_money']-$order['order_amount'];
//                   dump($money);
                   $data=['user_money'=>$money];
                   $useres = $userlogic->edit($where,$data,'User');

                   //写入用户账户日志
                   $data=[
                       'user_id'=>$this->user_id,
                       'user_money'=>"-".$order['order_amount'],
                       'change_time'=>time(),
                       'desc'=>'订单支付',
                       'order_sn'=>$order['order_sn'],
                       'order_id'=>$orderid,
                   ];
                   $res1 = $userlogic->add($data,'UserLog');
                   if (!$res1){
                       return json(['status'=>0,'msg'=>'写入日志失败']);
                   }

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
                       //每个商品销量加1
                       $where = ['id'=>$v['goods_id']];
                       $sales = Db::name('goods')->where($where)->setInc('goods_sales', 1);
                        if (!$sales){
                            return json(['status'=>-100,'msg'=>'销量增加失败！']);
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

    //订单支付成功
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
    //我的订单
    public function myorder(){
        self::isLogin();
        $user_id =$this->user_id;
        $mode=input('mode');
        $search=input('search','');
        if ($mode=='search'&&empty($search)){
            $this->error('请输入搜索词','/index/order/myorder/type/myorder/mode/all');
        }
//        dump($where);die;
        if ($user_id>0){
            $orderlogic = new OrderLogic();
            $orderlogic->setUserId($user_id);
            $order = $orderlogic->user_get_all_order(50,$mode,$search);

            foreach ($order as $key=>$value){
                $order[$key]['goodsinfo'] = $orderlogic->get_order_goods($value['id']);
            }
//            dump($order);
//            die;
//            $this->assign('goods',$goods);
            $this->assign('order',$order);
            $this->assign('mode',$mode);
            return $this->fetch();
        }
    }

    //判断是否登录
    public function isLogin(){
        if(!session('user')){
            $this->redirect('User/login');
//            $this->error('你还没登录呢！',url('User/login'));
        }
    }

    //订单详情页面
    public function order_detail(){
        $orderid = input('orderid');
        $logic = new OrderLogic();
        $order=$logic->get_order_detail($orderid);
        $order['order_goods']=$logic->get_order_goods($orderid);
        //获取收获地址信息
//        dump($order);
//        die;
        $shipping = Db::name('shipping')->column("id,shipping_name,shipping_money");

//        dump($shipping);
//        die;
        $this->assign('shipping',$shipping);
        $this->assign('order',$order);
        return $this->fetch();
    }

    //确认收货
    public function order_confirm(){
        $id = input('order_id/d', 0);
        $logic = new OrderLogic();
        $data = $logic->confirm_order($id, $this->user_id);
        return $data;
    }

    //订单评价
    public function comment_list(){
        self::isLogin();
        $id = input('id');
        $logic = new OrderLogic();

        $ordergoods = $logic->get_commit_list($id);
//        dump($ordergoods);
//        die;
        $this->assign('order_info',$ordergoods);
        return $this->fetch();
    }

    //评价操作
    public function add_comment(){
        $user = $this->user;
//        dump($user);
//        die;
        $data['rec_id'] = input('rec_id/d');
        $data['goods_id'] = input('goods_id/d');
        $hide_username = input('is_anonymous');
        if ($hide_username==0) {
            $data['username'] = $user['user_nickname'];
        }
        $data['is_anonymous'] = $hide_username;  //是否匿名评价:0不是\1是
        $data['order_id'] = input('order_id/d');
        $data['service_rank'] = input('service_rank');
        $data['deliver_rank'] = input('deliver_rank');
        $data['goods_rank'] = input('goods_rank');
        $data['is_show'] = 1; //默认显示
        $data['content'] = input('content');
        $data['add_time'] = time();
        $data['user_id'] = $this->user_id;
//        dump($data);
//        die;
        $logic = new OrderLogic();
        Db::startTrans();
        try{
            $res = $logic->add_comment($data,$user);//写入评价表
            if ($res){
                Db::commit();
                return $res;
            }
        }catch (\PDOException $e){
            Db::rollback();
        }




    }

}

