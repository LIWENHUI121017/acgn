<?php
namespace app\index\controller;

use app\common\logic\GoodsLogic;
use think\Controller;
use think\cache\driver\Redis;
use think\Db;
use think\Session;


class Goods extends Controller
{


    public function goodsInfo(){
        $id = input('id');
        $get = new GoodsLogic();
        $goodslist = $get->goodslist($id);
//        dump($goodslist);
//        exit();
        Session::set('goodsname',$goodslist[0]['goods_name']);
        $goodsinfo = array();
        $goodsinfo = array_reduce($goodslist, 'array_merge', array());
        $spec_goods_price = Db::name('spec_goods_price')->where("goods_id", $id)->column("key,item_id,price,store_count,price"); // 规格 对应 价格 库存表
//        dump($spec_goods_price);

        $this->assign('goods',$goodslist);
        $this->assign('goodsinfo',$goodsinfo);
        $this->assign('navigate',navigate_goods($id,$type=1));
        $this->assign('spec', $get->getspec($id));
        $this->assign('spec_goods_price', json_encode($spec_goods_price, true));

//dump(json_encode($spec_goods_price, true));
//dump($spec_goods_price);

       return $this->fetch('goodsinfo');

    }

    //价格库存变动
    public function changeprice(){
        $id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num/d');

        $Specprice = new \app\common\model\SpecGoodsPrice();
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

