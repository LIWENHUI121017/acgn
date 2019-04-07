<?php
namespace app\common\logic;

use app\common\model\Order;
use app\index\controller\User;
use think\Collection;
use think\console\command\make\Model;
use think\Db;
use app\common\model\Cart;
use think\Session;
//订单逻辑
class OrderLogic extends My_Logic
{
    protected $payList;
    protected $userId;
    protected $user;
    private $totalAmount = 0;//订单总价
    private $orderAmount = 0;//应付金额
    private $shippingPrice = 0;//物流费
    private $goodsPrice = 0;//商品总价
    private $cutFee = 0;//共节约多少钱
    private $totalNum = 0;// 商品总共数量
    private $userMoney = 0;//使用余额
    private $couponPrice = 0;//优惠券抵消金额


    public function __construct()
    {
        $this->table = 'Order';
    }

    //设置userid
    public function setUserId($user_id)
    {
        $this->userId = $user_id;
    }

    //生成订单界面
    public function get_order_list($where = array(), $field = '*')
    {
        $cartlist = Db::name('cart')
            ->alias('a')
            ->join('goods s', 'a.goods_id=s.id')
            ->where($where)
            ->field($field)
            ->select();
        foreach ($cartlist as $k => $v) {
            $cartlist[$k]['goodstotalprice'] = floatval($v['goods_price']) * $v['goods_num'];

        }
        return $cartlist;

    }

    //确认收货
    public function confirm_order($id,$user_id = 0){
        $where=['id' =>$id];
        if($user_id){
            $where['user_id'] = $user_id;
        }
        $order = $this->get_one($where);
        if($order['order_status'] != 3){
            return array('status'=>-1,'msg'=>'该订单不能收货确认');
        }
        if(empty($order['pay_time']) || $order['pay_status'] != 1){
            return array('status'=>-1,'msg'=>'商家未确定付款，该订单暂不能确定收货');
        }
        $data['order_status'] = 4; // 已收货
        $data['pay_status'] = 1; // 已付款
        $data['confirm_time'] = time(); // 收货确认时间
        $where=['id'=>$id];
        $res = $this->edit($where,$data);
        if(!$res){
            return array('status'=>-3,'msg'=>'操作失败');
        }
        return array('status'=>1,'msg'=>'操作成功','url'=>url('Order/order_detail',['orderid'=>$id]));
    }

    //评价商品
    public function add_comment($data,$user){
        if(!$data['order_id'] || !$data['goods_id'])
            return array('status'=>0,'msg'=>'参数错误','result'=>'');
        //检查订单是否已完成
        $where = ['id'=>$data['order_id']];
        $order = $this->get_one($where);
        if($order['order_status'] == 3){
            return array('status'=>0,'msg'=>'该笔订单还未确认收货','result'=>'');
        }
        //检查是否已评论过
        $where= ['order_id'=>$data['order_id'],'goods_id'=>$data['goods_id'],'user_id'=>$user['id']];
        $check = $this->get_one($where,'*','Comment');
        if ($check){
            return json(['status'=>0,'msg'=>'你已经评价过了']);
        }

            $row = $this->add($data,'Comment');

            if($row){
                $w=['id'=>$data['rec_id']];
                $d = ['is_comment'=>1];
                $res = $this->edit($w,$d,'OrderGoods');
                if (!$res){
                    return json(['status'=>0,'msg'=>'评价失败']);
                }

                //更新订单商品表状态
                model('goods')->where(array('id'=>$data['goods_id']))->setInc('comment_count',1); // 评论数加一
                // 查看这个订单是否全部已经评论,如果全部评论了 修改整个订单评论状态
                $where=["order_id"=>$data['order_id'],'is_comment'=>0];
                $comment_count  = $this->get_count($where,'OrderGoods');
                if($comment_count == 0) // 如果所有的商品都已经评价了 订单状态改成已评价
                {
                    $where=["id"=>$data['order_id']];
                    $this->edit($where,['order_status'=>6],'Order');
                }

                return array('status'=>1,'msg'=>'评论成功');
            }
            return array('status'=>0,'msg'=>'评论失败');



    }

    //获取的我的收获地址
    public function get_MyAddress($where = array(), $field = '*')
    {
        $addresslist = $this->get_all($where, $field, 'address');
        foreach ($addresslist as $k => $v) {
            $addresslist[$k]['province'] = $this->get_one(array('id' => $v['province']), 'name', 'region')['name'];
            $addresslist[$k]['city'] = $this->get_one(array('id' => $v['city']), 'name', 'region')['name'];
            $addresslist[$k]['county'] = $this->get_one(array('id' => $v['district']), 'name', 'region')['name'];
        }
        return $addresslist;
    }

    //生成订单

    public function set_order($data)
    {
        $list = $this->setlist($data);
        return $this->add($list, 'order');
    }

    // 处理提交数据返回插入订单的数据
    public function setlist($data)
    {
//        dump($data);
        $list['address_id'] = $data['addressid'];
        $list['user_id'] = $this->userId;
        $list['pay_type_id'] = $data['pay_type_id'];
        $list['shipping_id'] = $data['shipping_id'];
        $list['order_status'] = 0;
        $list['shipping_price'] = $data['shipping_price'];
        $list['total_amount'] = $data['total_amount'];
        $list['order_amount'] = $data['order_amount'];
        $list['order_time'] = time();
        $list['user_note'] = $data['user_note'];
        return $list;
    }

    //删除订单中对应购物车的商品
    public function delcartgoods($cartidlist)
    {
        $id = implode(',', $cartidlist);
        $cartmodel = new Cart();
        $res = $cartmodel::destroy($id);
        return $res;
    }

    //把商品记录进订单中的商品表
    public function setordergoods($cartidlist, $field, $orderid)
    {
        foreach ($cartidlist as $key => $value) {
            $cargoodslist[] = model('Cart')->where(['id' => $value])->field($field)->find()->toArray();
        }

        foreach ($cargoodslist as $k => $v) {
            $goodslist[] = [
                'order_id' => $orderid,
                'goods_id' => $v['goods_id'],
                'goods_num' => $v['goods_num'],
                'goods_sn' => $v['goods_sn'],
                'goods_name' => $v['goods_name'],
                'is_comment' => 0,
                'is_send' => 0,
                'spec_key' => $v['spec_key'],
                'spec_key_name' => $v['spec_key_name'],
                'delivery_id' => $v['goods_id'],
                'prom_id' => 0,
                'prom_type' => 0,
            ];
        }

        $orderaction = [
            'order_id' => $orderid,
            'action_user' => 0,
            'shipping_status' => 0,
            'pay_status' => 0,
            'action_note' => '您提交了订单，请等待系统确认',
            'log_time' => time(),
            'status_desc' => '提交订单',
        ];
        $res = $this->addall($goodslist, 'OrderGoods');

        //写入订单操作表里
        $setorderaction = $this->add($orderaction, 'order_action');
        if ($res && $setorderaction) {
            return true;
        } else {
            return false;
        }


    }

    //前端个人中心我的订单
    public function user_get_all_order($offset=50){
        $where = ['user_id'=>$this->userId];
        $res = $this->get_all($where,'*','Order','order_time desc',$offset);
        return $res;
    }

    //取消订单
    public function cancel_order($user_id,$order_id,$action_note='您取消了订单'){
        $where=[
            'id'=>$order_id,
            'user_id'=>$user_id
        ];
        $order = $this->get_one($where);
        if(empty($order))
            return ['status'=>0,'msg'=>'订单不存在','result'=>''];
        if($order['order_status'] == 2){
            return ['status'=>0,'msg'=>'该订单已取消','result'=>''];
        }
        //检查是否未支付的订单
        if($order['pay_status'] > 0 || $order['order_status'] > 0){
            return ['status'=>0,'msg'=>'支付状态或订单状态不允许','result'=>''];
        }
        //改变订单状态
        $data = [
            'order_status'=>2
        ];
        $res = $this->edit($where,$data);
        if (!$res){
            return ['status'=>0,'msg'=>'订单取消失败了','result'=>''];
        }
        $order = $this->get_one($where);
        //记录订单日志
        $data=[
            'order_id'=>$order_id,
            'action_user'=>0,
            'order_status'=>$order['order_status'],
            'shipping_status'=>$order['shipping_status'],
            'pay_status'=>$order['pay_status'],
            'action_note'=>'订单取消付款',
            'log_time'=>time(),
            'status_desc'=>'取消订单',
        ];
        $res1 = $this->add_order_action($data,'OrderAction');
        if (!$res1){
            return ['status'=>0,'msg'=>'订单日志写入失败','result'=>''];
        }
        return ['status'=>1,'msg'=>'操作成功','result'=>''];
    }
    //根据订单id获取订单中的商品
    public function get_order_goods($orderid){
        $where=['a.order_id'=>$orderid];
        $field='a.id,a.order_id,a.goods_id,a.goods_name,a.goods_sn,a.goods_num,a.spec_key_name,a.is_comment,a.is_send,s.original_img,m.price as goods_price';
        $ordergoods = model('OrderGoods')
                    ->alias('a')
                    ->join('goods s','a.goods_id=s.id')
                    ->join('spec_goods_price m','a.spec_key=m.key and a.goods_id=m.goods_id')
                    ->where($where)
                    ->field($field)
                    ->select()
                    ->toArray();
        return $ordergoods;
    }

    //获取订单详情信息
    public function get_order_detail($orderid){
        $where = ['a.id'=>$orderid];
//        dump($orderid);
//        die;
        $res = Db::name('order')
                ->alias('a')
                ->join('address s','a.address_id=s.address_id')
                ->where($where)
                ->find();
        $res['province']=$this->get_one(['id'=>$res['province']],'name','Region')['name'];
        $res['city']=$this->get_one(['id'=>$res['city']],'name','Region')['name'];
        $res['district']=$this->get_one(['id'=>$res['district']],'name','Region')['name'];
        $res['town']=$this->get_one(['id'=>$res['town']],'name','Region')['name'];

        return $res;

    }
    //后台获取所有订单信息
    public function get_all_order()
    {
        $res = Db::name('order')
            ->alias('a')
            ->join('address s', 'a.address_id=s.address_id')
            ->order('order_time desc')
            ->select();
        foreach ($res as $k => $v) {
            //支付类别
            switch ($v['pay_type_id']) {
                case 1:
                    $res[$k]['pay_type_id'] = '钱包支付';
                    break;
            }
            //快递类别
            switch ($v['shipping_id']) {
                case 1:
                    $res[$k]['shipping_id'] = '顺丰快递';
                    break;
                case 2:
                    $res[$k]['shipping_id'] = '中通快递';
                    break;
                default:
                    $res[$k]['shipping_id'] = '';
            }
        }

        return $res;
    }

    //后台获取订单详情
    public function get_one_order($where=array(),$field='*'){
        $order =  Db::name('order')
            ->alias('a')
            ->join('address s', 'a.address_id=s.address_id')
            ->where($where)
            ->find();
//
//        ["province"] => int(28240)
//        ["city"] => int(28241)
//        ["county"] => int(28308)
            $order['province']=$this->get_one(['id'=>$order['province']],'name','Region')['name'];
            $order['city']=$this->get_one(['id'=>$order['city']],'name','Region')['name'];
            $order['district']=$this->get_one(['id'=>$order['district']],'name','Region')['name'];
            $order['town']=$this->get_one(['id'=>$order['town']],'name','Region')['name'];
        switch ($order['pay_type_id']) {
            case 1:
                $order['pay_type_id'] = '钱包支付';
                break;
        }
        switch ($order['shipping_id']) {
            case 1:
                $order['shipping_id'] = '顺丰快递';
                break;
            default:
                $order['shipping_id'] = '';
        }

        //获取订单用户
        $where=['id'=>$order['user_id']];
        $field='*';
        $order['users']=$this->get_one($where,$field,'user');
        //获取订单中的商品
        $where=['order_id'=>$order['id']];
        $order['orderGoods']=Db::name('order_goods')
            ->alias('a')
            ->join('goods s','a.goods_id=s.id')
            ->where($where)
            ->select();
        foreach ($order['orderGoods'] as $k=>$v) {
            $order['orderGoods'][$k]['goods_total'] =$v['goods_num']*$v['goods_price'];
        }
        return $order;
    }

    //获取全部订单日志
    public function get_all_order_action(){
        $orderaction=array();
        $table = 'OrderAction';
        $res = $this->get_all(array(),'*',$table,'log_time desc');
        foreach ($res as $k=>$v) {
            if ($v['action_user']==0){
                $user = Db::name('order')->alias('a')->join('user s','s.id=a.user_id')
                    ->where('a.id',$v['order_id'])
                    ->field('s.user_name')
                    ->find();
//                dump($user);
                $res[$k]['action_user']='用户：'.$user['user_name'];
            }else{
                $res[$k]['action_user']='管理员：'.$this->get_one(['id'=>$v['action_user']],'*','Admin')['admin_name'];

            }
        }
        return $res;
    }

    //获取对应订单的订单日志
    public function get_order_action($order_id){
        $where = ['order_id'=>$order_id];
        $filed = '*';
        $user = Db::name('user')->alias('a')->join('order s','a.id=s.user_id')
            ->where('s.id',$order_id)
            ->field('a.user_name')
            ->find();
        $res = $this->get_all($where,$filed,'OrderAction','log_time desc');
        foreach ($res as $k=>$v) {
            if ($v['action_user']==0){
                $res[$k]['user_name']="用户：".$user['user_name'];
            }else{
                $where=['id'=>$v['action_user']];
                $filed='admin_name';
                $admin = $this->get_one($where,$filed,'Admin');
                $res[$k]['user_name']="管理员：".$admin['admin_name'];
            }
            $res[$k]['log_time']=date('Y-m-d H:i:s',$v['log_time']);
            //订单状态
            switch ($v['order_status']) {
                case 0:
                    $res[$k]['order_status'] = '待确认';
                    break;
                case 1:
                    $res[$k]['order_status'] = '已确认';
                    break;
                case 2:
                    $res[$k]['order_status'] = '已取消';
                    break;
                case 3:
                    $res[$k]['order_status'] = '待收货';
                    break;
                case 4:
                    $res[$k]['order_status'] = '已签收';
                    break;
                case 5:
                    $res[$k]['order_status'] = '已作废';
                    break;
            }
            //付款状态
            switch ($v['pay_status']) {
                case 0:
                    $res[$k]['pay_status'] = '未支付';
                    break;
                case 1:
                    $res[$k]['pay_status'] = '已支付';
                    break;
            }
            //发货状态
            switch ($v['shipping_status']) {
                case 0:
                    $res[$k]['shipping_status'] = '未发货';
                    break;
                case 1:
                    $res[$k]['shipping_status'] = '已发货';
                    break;
            }
        }
        return $res;
    }

    //写入订单日志
    public function add_order_action($data,$table=''){
      $res = $this->add($data,$table);
      return $res;
    }

    //写入消费日志
    public function userLog($user_id, $user_money = 0, $desc = '',$order_id = 0 ,$order_sn = ''){
        $user_log = array(
            'user_id'       => $user_id,
            'user_money'    => $user_money,
            'change_time'   => time(),
            'desc'   => $desc,
            'order_id' => $order_id,
            'order_sn' => $order_sn
        );
            M('account_log')->add($user_log);
            return true;

    }

    //发货后订单参数改变
    public function edit_order($data,$order){
        $where = ['id'=>$data['order_id']];
        $data1 = [
            'order_status'=>3,
            'shipping_status'=>1,
            'shipping_id'=>$data['shipping_id'],
            'shipping_price'=>$order['shipping_price'],
            'shipping_time'=>time()
        ];

        $res = $this->edit($where,$data1,'Order');
//        dump($res);
        return $res;
    }

    //订单中的商品参数改变
    public function edit_ordergoods($where,$data){
        $res = $this->edit($where,$data,'OrderGoods');
        return $res;
    }
    //改变订单状态
    public function editOrderStatus($where=array(),$data){
            $res = $this->edit($where,$data);
            return $res;
    }

    //移除或删除订单或者日志
    public function deleteorderOraction($where,$table=''){

        return $this->del($where,$table);
    }

    //获得评价商品信息
    public function get_commit_list($id){
        $where = ['a.id'=>intval($id)];
        $field='a.id,a.order_id,a.goods_id,a.goods_name,a.goods_sn,a.goods_num,a.spec_key_name,a.spec_key,a.is_comment,a.is_send,o.order_sn,o.order_time,g.original_img,m.price as goods_price';
        $res = model('OrderGoods')
            ->alias('a')
            ->join('order o','a.order_id=o.id')
            ->join('spec_goods_price m','a.spec_key=m.key and a.goods_id=m.goods_id')
            ->join('goods g','a.goods_id=g.id')
            ->where($where)
            ->field($field)
            ->find()
            ->toArray();

        return $res;
    }

}
