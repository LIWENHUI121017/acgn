<?php
namespace app\common\logic;

use app\common\model\Cart;
use app\common\model\Goods;
use app\common\model\SpecGoodsPrice;
use app\common\util\acgnException;
use think\Config;
use think\Db;
use think\Model;
use think\Session;

class CartLogic extends Model
{
    protected $goods;//商品模型
    protected $specGoodsPrice;//商品规格模型
    protected $goodsBuyNum;//购买的商品数量
    protected $session_id;//session_id
    protected $user_id = 0;//user_id


    public function __construct()
    {
        parent::__construct();
        $this->session_id = session_id();
    }

    //设置userid
    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }

    //商品模型
    public function goodsModel($goods_id)
    {
        if ($goods_id > 0) {
            $this->goods = model('Goods')->get($goods_id);
        }
    }

    //商品规格模型
    public function specGoodsPriceModel($item_id)
    {
        if ($item_id > 0) {
            $this->specGoodsPrice = model('SpecGoodsPrice')->get($item_id);
        }
    }

    //设置商品购买数量
    public function goodsBuyNum($goods_num)
    {
        $this->goodsBuyNum = $goods_num;
    }

    //检查要添加的商品信息
    public function checkGoods()
    {
        if (empty($this->goods)) {
            throw new acgnException('加入购物车', 0, ['status' => 0, 'msg' => '商品不存在']);
        }
        //获取用户购物车的商品有多少种
        $userCartCount = Db::name('cart')->where(['user_id' => $this->user_id, 'session_id' => $this->session_id])->count();
        if ($userCartCount >= 20) {
            throw new TpshopException("加入购物车", 0, ['status' => 0, 'msg' => '购物车最多只能放20种商品']);
            return false;
        }
        $specGoodsPriceCount = Db::name('SpecGoodsPrice')->where("goods_id", $this->goods['id'])->count('item_id');
        if (empty($this->specGoodsPrice) && !empty($specGoodsPriceCount)) {
            throw new acgnException("加入购物车", 0, ['status' => 0, 'msg' => '必须传递商品规格', 'result' => ['url' => U('Goods/goodsInfo', ['id' => $this->goods['goods_id']], '', true)]]);
            return false;
        }

        $this->addCart();//加入购物车

    }

    //加入购物车
    private function addCart()
    {
        //如果有规格价格，就使用规格价格，否则使用本店价。
        if (empty($this->specGoodsPrice)) {
            $price = $this->goods['goods_price'];
            $store_count = $this->goods['goods_inventory'];
        } else {
            $price = $this->specGoodsPrice['price'];
            $store_count = $this->specGoodsPrice['store_count'];
        }

        // 查询购物车是否已经存在这商品
        $cart_where = ['user_id' => $this->user_id, 'goods_id' => $this->goods['id'], 'item_id' => ($this->specGoodsPrice['item_id'] ?: '')];
        if (!$this->user_id) {
            $cart_where['session_id'] = $this->session_id;
        }

        $userCartGoods = Cart::get($cart_where);

        //如果商品已经存在
        if ($userCartGoods) {
            //判断购买数量是否超过了存货
            if ($this->goodsBuyNum > $store_count) {
                throw new acgnException('加入购物车', 0, ['status' => 0, 'msg' => '库存不足,剩余' . $store_count]);
                return false;
            }
            $userWantGoodsNum = $userCartGoods['goods_num'] + $this->goodsBuyNum;
            $res = $userCartGoods->save(['goods_num' => $userWantGoodsNum, 'goods_price' => $price]);


        } else {//如果商品不存在

            $cartAddData = array(
                'user_id' => $this->user_id,   // 用户id
                'session_id' => $this->session_id,   // sessionid
                'goods_id' => $this->goods['id'],   // 商品id
                'goods_sn' => $this->goods['goods_sn'],   // 商品货号
                'goods_name' => $this->goods['goods_name'],   // 商品名称
                'goods_price' => $price,  // 原价
                'goods_num' => $this->goodsBuyNum, // 购买数量
                'add_time' => time(), // 加入购物车时间
                'prom_type' => 0,   // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
                'prom_id' => 0,   // 活动id
            );
            if ($this->specGoodsPrice) {
                $cartAddData['item_id'] = $this->specGoodsPrice['item_id'];
                $cartAddData['spec_key'] = $this->specGoodsPrice['key'];
                $cartAddData['spec_key_name'] = $this->specGoodsPrice['key_name']; // 规格 key_name
            }
            $cartResult = Db::name('Cart')->insertGetId($cartAddData);
            if ($cartResult === false) {
                throw new acgnException("加入购物车", 0, ['status' => 0, 'msg' => '加入购物车失败']);
            }
        }


    }

    //更新购物车
    public function updateCart($cart){
        foreach ($cart as $k=>$v){
            if ($this->user_id) {
                $where=['user_id'=>$this->user_id,'id' => $v['id'],];
            }else{
                $where= ['session_id' => $this->session_id];
            }

            $data=[
                'goods_num' => $v['goods_num'],
                'selected'=>$v['selected']
            ];
            $res = Db::name('cart')->where($where)->update($data);
//            dump($this->getLastSql());

        }
        return $res;
    }

    //获取购物车信息
    public function getCartList($selected = 0)
    {
        if ($this->user_id) {
            $cartwhere['user_id'] = $this->user_id;
        } else {
            $cartwhere['session_id'] = $this->session_id;
        }
        if ($selected != 0) {
            $cartwhere['selected'] = 1;
        }
        $cart = new Cart();
        $cartList = $cart->where($cartwhere)->select();// 获取购物车商品
//        dump($cartList);
//        die;
        $cartchecklist = $this->checkcartlist($cartList);
        $logic = new My_Logic();
        foreach ($cartchecklist as $k=>$v){
            if ($v['spec_key']==0){
                $where=['id'=>$v['goods_id']];
                $cartchecklist[$k]['store_count']= $logic->get_one($where,'*','Goods')['goods_inventory'];
            }else{
                $where=['goods_id'=>$v['goods_id'],'key'=>$v['spec_key']];
                $cartchecklist[$k]['store_count']= $logic->get_one($where,'*','SpecGoodsPrice')['store_count'];
            }
            $cartchecklist[$k]['goodstotal']=number_format($v['goods_num']*$v['goods_price'], 2);

        }
        return $cartchecklist;
    }

    public function checkcartlist($cartList)
    {
        foreach ($cartList as $cartKey => $cart) {
            //商品不存在或者已经下架
            if (empty($cart['goods']) || $cart['goods']['is_on_sale'] != 1) {
                $cart->delete();
                unset($cartList[$cartKey]);
                continue;
            }
            $this->getUserCartGoodsNum();//删除后，需要重新设置cookie值
            return $cartList;

        }

    }
    public function getUserCartGoodsNum(){
        if ($this->user_id) {
            $goods_num = Db::name('cart')->where(['user_id' => $this->user_id])->sum('goods_num');
        } else {
            $goods_num = Db::name('cart')->where(['session_id' => $this->session_id])->sum('goods_num');
        }
        $goods_num = empty($goods_num) ? 0 : $goods_num;
//        setcookie('cn', $goods_num, null, '/');
        return $goods_num;
    }

    //获取用户购物车商品总数
    public function getUserCartGoodsTypeNum()
    {
        if ($this->user_id) {
            $goods_num = Db::name('cart')->where(['user_id' => $this->user_id])->count();
        } else {
            $goods_num = Db::name('cart')->where(['session_id' => $this->session_id])->count();
        }
        return empty($goods_num) ? 0 : $goods_num;
    }

    //用户登录后 对购物车操作
    public function doUserLoginHandle(){
        if (empty($this->session_id) || empty($this->user_id)) {
            return;
        }
        //登录后将购物车的商品的 user_id 改为当前登录的id
        $cart = new Cart();
        $cart->save(['user_id' => $this->user_id], ['session_id' => $this->session_id, 'user_id' => 0]);
        // 查找购物车两件完全相同的商品
        $cart_id_arr = $cart->field('id')->where(['user_id' => $this->user_id])->group('goods_id,spec_key')->having('count(goods_id) > 1')->select();
        if (!empty($cart_id_arr)) {
            $cart_id_arr = get_arr_column($cart_id_arr, 'id');
            Db::name('cart')->delete($cart_id_arr); // 删除购物车完全相同的商品
        }
    }


    //返回用户选中的购物车商品的数量和总价格
    public function gettotalprice(){
        if ($this->user_id) {
            $where=['user_id'=>$this->user_id,'selected' => 1];
        }else{
            $where= ['session_id' => $this->session_id,'selected' => 1];
        }
        $field='goods_price,goods_num';
        $res = Db::name('cart')->where($where)->field($field)->select();
        $list['totalprice']=0;
        $list['count']=0;
        foreach ($res as $k=>$v){
            $list['totalprice']+=$v['goods_price']*$v['goods_num'];
            $list['count']=count($res);

        }

        return $list;
    }


}
