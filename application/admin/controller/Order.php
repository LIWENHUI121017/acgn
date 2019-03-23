<?php
namespace app\admin\controller;

use app\common\logic\OrderLogic;
use app\common\logic\Shippinglogic;
use Symfony\Component\Yaml\Tests\DumperTest;
use think\Db;


class Order extends Base
{
    public  $order_status;
    public  $pay_status;
    public  $shipping_status;
    public $admin_id = 0;
    public $admin = array();

    public function _initialize()
    {
        parent::_initialize();
        if(session('?admin'))
        {
            $session_user = session('admin');
            $admin = Db::name('user')->where("id", $session_user['id'])->find();
            session('admin',$admin);  //覆盖session 中的 user
            $this->admin = $admin;
            $this->admin_id = $admin['id'];
        }
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
//        dump($order);
        $this->assign('order',$order);
        return $this->fetch();
    }

    //订单详情
    public function detail(){
        $orderid=input('orderid');
        $logic = new OrderLogic();
        //检查是否有此订单
        $check=$logic->get_one(['id'=>$orderid]);
        if (!$check){
            $this->error('订单不存在或已经删除',url('admin/order/orderaction'));
        }
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

    //发货
    public function toDelivery(){
       $admin_id = session('admin_id');
       $data['order_id'] = input('order_id');
       $data['shipping_id'] = input('shippingtype');//物流公司id
       $data['shippingcode'] = input('shippingcode');//发货方式
       $data['shipping_code'] = input('code');
       $data['note'] = input('note');
       $shippinglogic = new Shippinglogic();
       $orderlogic = new OrderLogic();
        $where=['id'=>$data['shipping_id']];
        $shipping = $shippinglogic->get_one($where,'*','Shipping');//快递信息
        $where=['id'=>$data['order_id']];
        $order = $orderlogic->get_one($where,'*','Order');//订单信息
        //检查发货单是否存在
       $where = ['order_id'=>$data['order_id']];
       $check = $shippinglogic->get_one($where,'*','Delivery');
       Db::startTrans();
       try{
           if (!$check){//不存在执行插入
               $shippingres = $shippinglogic->set_delivery($data,$shipping,$order);
               if ($shippingres){
                $orderres = $orderlogic->edit_order($data,$order);
                if ($orderres){
                    //记录订单日志
                    $data2=[
                        'order_id'=>$data['order_id'],
                        'action_user'=>$admin_id,
                        'order_status'=>1,
                        'shipping_status'=>1,
                        'pay_status'=>1,
                        'action_note'=>$data['note'],
                        'status_desc'=>'发货成功!',
                        'log_time'=>time(),
                    ];
                    $table = 'order_action';
                    $logres = $orderlogic->add_order_action($data2,$table);
                    if ($logres){
                        Db::commit();
                        return json(['status'=>1,'msg'=>'操作成功！']);
                    }else{
                        return json(['status'=>0,'msg'=>'操作日志失败！']);
                    }
                }else{
                    return json(['status'=>0,'msg'=>'操作订单失败！']);
                }
               }else{
                   return json(['status'=>0,'msg'=>'操作发货失败！']);
               }
           }else{
               return json(['status'=>0,'msg'=>'商品已发货，无需再发！']);
           }

       }catch (\PDOException $e){
           Db::rollback();
       }


    }

    //管理员处理订单，确认或者取消确认
    public function ChangeOrderstatus(){
        $admin_id=$this->admin_id;
        $orderid = input('order_id');
        $note = input('note');
        $status = input('status');
        $logic = new OrderLogic();
        if ($status==2){
            $status=0;
            $status_desc='取消确认';
        }elseif ($status==5){
            $status_desc='作废订单';
        }elseif ($status==3){
            $del = $logic->deleteorder($orderid);
            if ($del){
                return json(['status'=>3,'msg'=>'移除订单成功']);
            }else{
                return json(['status'=>0,'msg'=>'操作移除订单失败']);
            }
        }else{
            $status_desc='确认订单';
        }
        $where = ['id'=>$orderid];
        $data = ['order_status'=>$status];
        $res = $logic->editOrderStatus($where,$data);//改变订单状态
        $order=$logic->get_one($where);
//        dump($order);
//        die;
        if ($res){
            $data2=[
                'order_id'=>$orderid,
                'action_user'=>$admin_id,
                'order_status'=>$order['order_status'],
                'shipping_status'=>$order['shipping_status'],
                'pay_status'=>$order['pay_status'],
                'status_desc'=>$status_desc,
                'action_note'=>$note,
                'log_time'=>time(),
            ];
            $table = 'order_action';
            $res1 = $logic->add_order_action($data2,$table);
            if ($res1){
                return json(['status'=>1,'msg'=>'操作成功']);
            }else{
                return json(['status'=>0,'msg'=>'操作记录日志失败']);
            }
        }else{
            return json(['status'=>0,'msg'=>'操作修改订单失败']);
        }
    }

    //删除订单或者日志
    public function delete(){
        $orderid=input('orderid');
        $actionid=input('actionid');

//        dump($actionid);
        $logic = new OrderLogic();
        if ($orderid){
            $where=['id'=>$orderid];
            $res = $logic->deleteorderOraction($where);
        }else if ($actionid){
            $table = 'OrderAction';
            $where=['action_id'=>$actionid];
            $res = $logic->deleteorderOraction($where,$table);
        }else{
            return json(['status'=>0,'msg'=>'参数错误']);
        }
        if ($res){
            return json(['status'=>1,'msg'=>'删除成功']);
        }else{
            return json(['status'=>0,'msg'=>'操作失败']);
        }
    }

    //订单日志页面
    public function orderaction(){
        $logic = new OrderLogic();
        $orderaction = $logic->get_all_order_action();
//        dump($orderaction);
        $this->assign('orderaction',$orderaction);
        return $this->fetch('orderaction');
    }

}
