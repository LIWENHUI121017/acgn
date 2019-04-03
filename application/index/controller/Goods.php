<?php
namespace app\index\controller;

use app\common\logic\GoodsLogic;
use app\common\model\SpecGoodsPrice;
use think\Controller;
use think\cache\driver\Redis;
use think\Db;
use think\Session;


class Goods extends Base{
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

    //商品详情
    public function goodsInfo(){
        $id = input('id');
        $userid = $this->user_id;
        $get = new GoodsLogic();
        $goodslist = $get->goodslist($id);
        Session::set('goodsname',$goodslist[0]['goods_name']);
        $goodsinfo = array();
        $goodsinfo = array_reduce($goodslist, 'array_merge', array());
        $spec_goods_price = Db::name('spec_goods_price')->where("goods_id", $id)->column("key,item_id,price,store_count,price"); // 规格 对应 价格 库存表
        $collect = Db::name('goods_collect')->where(['goods_id'=>$id,'user_id'=>$userid])->find();
        $this->assign('collect',$collect);
        $this->assign('goods',$goodslist);
        $this->assign('goodsinfo',$goodsinfo);
        $this->assign('navigate',navigate_goods($id,$type=1));
        $this->assign('spec', $get->getspec($id));
        $this->assign('spec_goods_price', json_encode($spec_goods_price, true));
        return $this->fetch('goodsinfo');

    }

    //收藏操作
    public function collect(){
        $userid=$this->user_id;
        if ($userid>0){
            $status = input('status');
            $goodsid = input('goodsid');
            $logic = new GoodsLogic();
            if ($status==1){//收藏
                $data=[
                    'user_id'=>$userid,
                    'goods_id'=>$goodsid,
                    'add_date'=>time(),
                    ];
                $res = $logic->add($data,'goods_collect');
                if ($res>0){
                    return json(['status'=>1,'msg'=>'已经成功收藏！','collect'=>$status]);
                }else{
                    return json(['status'=>0,'msg'=>'收藏失败！']);
                }
            }else{
                $where = [
                    'user_id'=>$userid,
                    'goods_id'=>$goodsid
                ];
                $res=$logic->del($where,'goods_collect');
                if ($res>0){
                    return json(['status'=>1,'msg'=>'已经取消收藏','collect'=>$status]);
                }else{
                    return json(['status'=>0,'msg'=>'取消收藏失败！']);
                }
            }



        }else{
            return json(['status'=>0,'msg'=>'请先登录！']);
        }
    }

    //价格库存变动
    public function changeprice(){
        $id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num/d');

        $Specprice = new SpecGoodsPrice();
        $SpecGoodsPrice = $Specprice::get($item_id);

    if (!empty($SpecGoodsPrice)){
       return json(['status' => 1, 'msg' => '该商品没有参与活动', 'result' => ['Specprice' => $SpecGoodsPrice]]);
    }



    }




}

