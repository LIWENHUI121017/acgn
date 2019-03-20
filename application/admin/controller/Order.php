<?php
namespace app\admin\controller;

use app\common\logic\OrderLogic;
use app\common\logic\Shippinglogic;
use Symfony\Component\Yaml\Tests\DumperTest;


class Order extends Base
{
    public  $order_status;
    public  $pay_status;
    public  $shipping_status;

    public function _initialize()
    {
        parent::_initialize();
        $this->order_status = config('ORDER_STATUS');
        $this->pay_status = config('PAY_STATUS');
        $this->shipping_status = config('SHIPPING_STATUS');
        // 订单 支付 发货状态
        $this->assign('order_status',$this->order_status);
        $this->assign('pay_status',$this->pay_status);
        $this->assign('shipping_status',$this->shipping_status);
    }

    //订单列表
    public function index(){
        $logic = new OrderLogic();
        $order = $logic->get_all_order();
//        $page = $order->render();//分页页码

//        $this->assign('page',$page);
        $this->assign('order',$order);
        return $this->fetch();
    }

    //订单详情
    public function detail(){
        $orderid=input('orderid');

        $logic = new OrderLogic();
        $where = ['a.id'=>$orderid];
        $order = $logic->get_one_order($where);
//        dump($order);
        $this->assign('order',$order);

        return $this->fetch();
    }
    //获取订单的操作日志
    public function getOrderAction(){
        $order_id = input('order_id');
        $logic = new OrderLogic();
        $orderlog = $logic->get_order_action($order_id);
        return json(['status'=>1,'data'=>$orderlog]);
    }

    //订单发货
    public function delivery(){
        $orderid=input('orderid');

        $orderlogic = new OrderLogic();
        $where = ['a.id'=>$orderid];
        $order = $orderlogic->get_one_order($where);
//        dump($order);
        //获取配送方式
        $shippinglogic = new Shippinglogic();
        $shipping = $shippinglogic->get_all();
//        dump($shipping);
        $this->assign('order',$order);
        $this->assign('shipping',$shipping);

        return $this->fetch();
    }

    public function toDelivery(){
       $data['order_id'] = input('order_id');
       $data['shippingtype'] = input('shippingtype');
       $data['shippingcode'] = input('shippingcode');//发货方式
       $data['shipping_code'] = input('code');
       $data['note'] = input('note');
       $logic = new Shippinglogic();
        //检查发货单是否存在
       $where = ['order_id'=>$data['order_id']];
       $check = $logic->get_one($where,'*','Delivery');
       if (!$check){
           $res = $logic->set_delivery($data);
           if ($res){
               return json(['status'=>1,'msg'=>'操作成功！']);
           }else{
               return json(['status'=>0,'msg'=>'操作失败！']);
           }
       }else{

       }

    }

}
