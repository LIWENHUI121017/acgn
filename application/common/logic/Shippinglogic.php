<?php
namespace app\common\logic;


//物流逻辑
class Shippinglogic extends My_Logic
{

    public function __construct()
    {
        $this->table = 'Shipping';
    }

    //发货处理
    public function set_delivery($data,$shipping,$order){
        $list['order_id']=$data['order_id'];

        $where=['address_id'=>$order['address_id']];
        $address = $this->get_one($where,'*','Address');//地址信息
        $list['order_sn']=$order['order_sn'];
        $list['user_id']=$order['user_id'];
        $list['consignee']=$address['consignee'];
        $list['zipcode']=$address['zipcode'];
        $list['phone']=$address['phone'];
        $list['address']=$address['address'];
        if ( $data['shippingcode']==0){
            $list['shipping_code']= $data['shipping_code'];
        }
        $list['shipping_price']=$order['shipping_price'];
        $list['shipping_name']=$shipping['shipping_name'];
        $list['admin_note']=$data['note'];
        $list['create_time']=time();
        $list['admin_id']=session('admin_id');
        //写进发货表
        $res = $this->add($list,'Delivery');
        return $res;
    }


}
