<?php
namespace app\common\logic;

use app\index\controller\User;
use think\Collection;
use think\console\command\make\Model;
use think\Db;
use app\common\model\Cart;
use think\Session;
//订单逻辑
class OrderLogic extends My_Logic{
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


    public function __construct(){
        $this->table = 'Order';
    }

    //设置userid
    public function setUserId($user_id){
        $this->userId = $user_id;
    }

    /**
     * 生成订单界面
     * @return array
     */
    public function get_order_list($where = array(),$field = '*'){
        $cartlist = Db::name('cart')
                ->alias('a')
                ->join('goods s','a.goods_id=s.id')
                ->where($where)
                ->field($field)
                ->select();
        foreach ($cartlist as $k=>$v) {
            $cartlist[$k]['goodstotalprice'] = number_format(floatval($v['goods_price']) * $v['goods_num'],2);

        }
        return $cartlist;

    }

    /**
     * 获取的我的收获地址
     */
    public function get_MyAddress($where = array(),$field = '*'){
      $addresslist = $this->get_all($where,$field,'address');
      foreach ($addresslist as $k=>$v){
          $addresslist[$k]['province']=$this->get_one(array('id'=>$v['province']),'name','region')['name'];
          $addresslist[$k]['city']=$this->get_one(array('id'=>$v['city']),'name','region')['name'];
          $addresslist[$k]['county']=$this->get_one(array('id'=>$v['county']),'name','region')['name'];
      }
      return $addresslist;
    }

    /**
     * 生成订单
     * @param array
     */
    public function set_order($data){
            $list = $this->setlist($data);
          return $this->add($list,'order');
    }
    /**
     * 处理提交数据返回插入订单的数据
     * @param array
     * @return  array
     */
    public function setlist($data){
//        dump($data);
        $list['address_id']=$data['addressid'];
        $list['user_id']=$this->userId;
        $list['pay_type_id']=$data['pay_type_id'];
        $list['shipping_id']=$data['shipping_id'];
        $list['order_status']=0;
        $list['shipping_price']=$data['shipping_price'];
        $list['total_amount']=$data['total_amount'];
        $list['order_amount']=$data['order_amount'];
        $list['order_time']=time();
        $list['user_note']=$data['user_note'];
        return $list;
    }

    /**
     * 删除订单中对应购物车的商品
     * @param array
     */
    public function delcartgoods($cartidlist){
        $id = implode(',',$cartidlist);
        $cartmodel = new Cart();
        $res = $cartmodel::destroy($id);
        return $res;
    }
    /**
     * 把商品记录进订单中的商品表
     * @param array
     */
    public function setordergoods($cartidlist,$field,$orderid){
        foreach ($cartidlist as $key=>$value) {
            $cargoodslist[] = model('Cart')->where(['id'=>$value])->field($field)->find()->toArray();
        }

        foreach ($cargoodslist as $k=>$v){
            $goodslist[] =[
                'order_id'=>$orderid,
                'goods_id'=>$v['goods_id'],
                'goods_num'=>$v['goods_num'],
                'goods_sn'=>$v['goods_sn'],
                'goods_name'=>$v['goods_name'],
                'is_comment'=>0,
                'is_send'=>0,
                'spec_key'=>$v['spec_key'],
                'spec_key_name'=>$v['spec_key_name'],
                'delivery_id'=>$v['goods_id'],
                'prom_id'=>0,
                'prom_type'=>0,
            ];
        }

        $orderaction=[
            'order_id'=>$orderid,
            'action_user'=>0,
            'shipping_status'=>0,
            'pay_status'=>0,
            'action_note'=>'您提交了订单，请等待系统确认',
            'log_time'=>time(),
            'status_desc'=>'提交订单',
        ];
        $res = $this->addall($goodslist,'OrderGoods');

            //写入订单操作表里
            $setorderaction=$this->add($orderaction,'order_action');
        if ($res&&$setorderaction){
            return true;
         }else{
             return false;
         }



    }

}
