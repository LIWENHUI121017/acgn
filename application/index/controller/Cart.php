<?php
namespace app\index\controller;


use app\common\logic\CartLogic;
use app\common\util\acgnException;
use think\Controller;
use think\cache\driver\Redis;
use think\Db;
use think\Session;


class Cart extends Base {
    public $cartLogic;//购物车模型
    public $user_id = 0;
    public $user = array();


    public function _initialize() {
        parent::_initialize();
        $this->cartLogic = new CartLogic();
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

    public function index()
    {
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList();//用户购物车
//        $userCartGoodsTypeNum = $cartLogic->getUserCartGoodsTypeNum();//获取用户购物车商品总数
//        $this->assign('userCartGoodsTypeNum', $userCartGoodsTypeNum);
//        dump($cartList[0]['goods']);
        $this->assign('cartList', $cartList);//普通购物车列表
//         dump($cartList);
        return $this->fetch();
    }


    //加入购物车
    public function addCart(){
//        return(1);
        $carlogic = new CartLogic();

        $goods_id = input('goods_id');
        $item_id = input('item_id');
        if ($item_id){
            $carlogic->specGoodsPriceModel($item_id);
        }

        $goods_num = input('goods_num');

        $carlogic->setUserId($this->user_id);
        $carlogic->goodsModel($goods_id);
        $carlogic->goodsBuyNum($goods_num);
        try {
            $carlogic->checkGoods();
//            dump($this->user_id);
            return json(['status' => 1, 'msg' => '加入购物车成功']);
        } catch (acgnException $t) {
            $error = $t->getErrorArr();
            return json($error);
        }


    }

    //更新购物车
    public function updatecart(){
        $data = input('cart/a',[]);
//        dump($data);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->updateCart($data);
        $carlist = $cartLogic->gettotalprice();
//        dump($result);
//        dump($carlist);
        if ($carlist){
            return json(['status'=>1,'data'=>$carlist]);
        }else{
            return json(['status'=>0]);
        }

    }

}