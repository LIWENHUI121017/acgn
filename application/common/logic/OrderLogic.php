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

    /**
     * 生成订单界面
     * @return array
     */
    public function get_order_list($where = array(), $field = '*')
    {
        $cartlist = Db::name('cart')
            ->alias('a')
            ->join('goods s', 'a.goods_id=s.id')
            ->where($where)
            ->field($field)
            ->select();
        foreach ($cartlist as $k => $v) {
            $cartlist[$k]['goodstotalprice'] = number_format(floatval($v['goods_price']) * $v['goods_num'], 2);

        }
        return $cartlist;

    }


    //获取的我的收获地址

    public function get_MyAddress($where = array(), $field = '*')
    {
        $addresslist = $this->get_all($where, $field, 'address');
        foreach ($addresslist as $k => $v) {
            $addresslist[$k]['province'] = $this->get_one(array('id' => $v['province']), 'name', 'region')['name'];
            $addresslist[$k]['city'] = $this->get_one(array('id' => $v['city']), 'name', 'region')['name'];
            $addresslist[$k]['county'] = $this->get_one(array('id' => $v['county']), 'name', 'region')['name'];
        }
        return $addresslist;
    }

    /**
     * 生成订单
     * @param array
     */
    public function set_order($data)
    {
        $list = $this->setlist($data);
        return $this->add($list, 'order');
    }

    /**
     * 处理提交数据返回插入订单的数据
     * @param array
     * @return  array
     */
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

    /**
     * 删除订单中对应购物车的商品
     * @param array
     */
    public function delcartgoods($cartidlist)
    {
        $id = implode(',', $cartidlist);
        $cartmodel = new Cart();
        $res = $cartmodel::destroy($id);
        return $res;
    }

    /**
     * 把商品记录进订单中的商品表
     * @param array
     */
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

    /**
     * 后台获取所有订单信息
     * @return array
     */
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
            $order['county']=$this->get_one(['id'=>$order['county']],'name','Region')['name'];
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

    //改变订单状态
    public function editOrderStatus($where=array(),$data){
            $res = $this->edit($where,$data);
            return $res;
    }
    //移除或删除订单或者日志
    public function deleteorderOraction($where,$table){

        return $this->del($where,$table);
    }

}
