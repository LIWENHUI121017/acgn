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

//    //加入购物车
//    public function buycar(){
//
//        $goods_id = input('goods_id');
//        $item_id = input('item_id');
//        $goods_num = input('goods_num');
//        //规格名字
//        $res = Db::name('spec_goods_price')
//            ->where('item_id',$item_id)
//            ->field('key_name')
//            ->select();
//        $key_name = array();
//        foreach($res as  $v){
//            $key_name['key_name'] = $v['key_name'];
//        }
////        //商品名字
////        $res1 = Db::name('goods')
////            ->where('id',$goods_id)
////            ->field('goods_name')
////            ->select();
////        $goods_name = array();
////        foreach($res1 as  $v1){
////            $goods_name['goods_name'] = $v1['goods_name'];
////        }
//
//
//        if (!Session::has('goods')){
//            //购物车为空
//            $arr = array(
//              array(
//                  'id'=>$goods_id,
//                  'item_id'=>$item_id,
//                  'num'=>$goods_num)
//            );
//            foreach ($arr as $vv){
//                $arr[0]['key_name'] = $key_name['key_name'];
////                $arr[0]['goods_name'] = $goods_name['goods_name'];
//            }
//            if (!Session::has('id')) {
//               Session::set('goods',$arr);
//                  }else{
//                     Session::set('goods',$arr);
//                  }
//
////            dump(Session::get('goods'));
////              dump($arr);
////
//        }else{
//            //购物车不为空
//            $carlist =  Session::get('goods');
////            dump($carlist);
////
//           $status = false;//判断是否已经存在同样的商品
//           foreach ($carlist as $v){
//                if ($v['id']==$goods_id && $v['item_id']==$item_id){
//                    $status = true;
//                }
//           }
//           if ($status){
//               for ($i=0;$i<count($carlist);$i++){
//                   if ($carlist[$i]['id']==$goods_id && $carlist[$i]['item_id']==$item_id){
//                       $carlist[$i]['num']+=$goods_num;
//                   }
//               }
////
//                    Session::set('goods',$carlist);
//           } else{
//               //购物车不为空且有别的商品
//               $arr1 = array(
//                   'id'=>$goods_id,
//                   'item_id'=>$item_id,
//                   'num'=>$goods_num
//               );
//               $arr1['key_name']=$key_name['key_name'];
////               $arr1['goods_name']=$key_name['goods_name'];
//               $carlist[] = $arr1;
//               Session::set('goods',$carlist);
//           }
//
//
//
//        }
//
////        Session::get();
//
//        return json(['status' => 1, 'msg' => '成功加入购物车！']);
//    }


}

