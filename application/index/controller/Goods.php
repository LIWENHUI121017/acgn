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

    //商品列表
    public function goodslist(){
        $cateid = input('id',0);//分类id
        $order = input('order','goods_time');//排序
        $search = urldecode(trim(input('search',''))); // 关键字搜索
        $status = input('status', 0); // 关键字搜索
        if ($status>0){
            empty($search) && $this->error('请输入搜索词');
        }
//        dump($order);
        $this->assign('order',$order);
        $this->assign('status',$status);
        $ec = input('ec','desc');
        $order.=" ".$ec;

        $logic = new GoodsLogic();
        //获取一级分类
        $where = ['is_show' => 1,'level'=>1];
        $catetree1 = $logic->get_all($where,'*','GoodsCategory','sort_order');
        //根据搜索框获取商品列表
        if ($search){
//            $strArray=preg_split('/(?<!^)(?!$)/u', $search );
//            $sear=join("%",$strArray);
            $searchs='%'.$search.'%';
            $goodslist= $logic->getgoodslistBysearch($searchs,$order);
            $page = $goodslist->render();
            $this->assign('search',$search);
        }else if($cateid>0){
            //获取族谱变成','字符串
            $res = $logic->getcatepath($cateid);
//            $catestring = strtr($res['pid_path'],'_',',');
            $arr = explode('_',$res['pid_path']);
            //该分类下的商品
            $goodslist = $logic->getgoodslistBycateid($cateid,$res['level'],$order);

            $page = $goodslist->render();//分页页码
            //获取二级分类
            $where = ['is_show' => 1,'level'=>2,'pid'=>$arr[1]];
            $catetree2 = $logic->get_all($where,'*','GoodsCategory','sort_order');
            if ($catetree2){
                $this->assign('cate2',$catetree2);
            }
            $this->assign('level',$res['level']);
            $this->assign('arr',$arr);
            $this->assign('page',$page);
            $this->assign('cate1',$catetree1);
            $this->assign('cateid',$cateid);
        }else{
            $goodslist = $logic->getgoodslistBycateid($cateid,0,$order);
            $page = $goodslist->render();//分页页码
            $this->assign('cate2','');
            $this->assign('cate1',$catetree1);
            $this->assign('arr',[0]);
            $this->assign('level',0);
            $this->assign('cateid',$cateid);
        }



        $this->assign('ec',$ec);
        $this->assign('page',$page);
        $this->assign('goodslist',$goodslist);



        return $this->fetch();
    }

    //商品详情
    public function goodsInfo(){
        $id = input('id');
        $userid = $this->user_id;
        $get = new GoodsLogic();
        $goodslist = $get->goodslist($id);
        //推荐商品
//        dump($goodslist);
//        die;
        $hotgoods = Db::name('goods')->where(['goods_type_id'=>$goodslist[0]['goods_type_id']])->select();
//        dump($hotgoods);
//        die;
        Session::set('goodsname',$goodslist[0]['goods_name']);
        $goodsinfo = array_reduce($goodslist, 'array_merge', array());
        $spec_goods_price = Db::name('spec_goods_price')->where("goods_id", $id)->column("key,item_id,price,store_count,price"); // 规格 对应 价格 库存表
        $collect = Db::name('goods_collect')->where(['goods_id'=>$id,'user_id'=>$userid])->find();
       //获取商品评价
        $comment = $get->get_all(['goods_id'=>$id],'*','Comment','add_time desc');
//        dump($comment);



        if (!$comment){
            $comment="";
        }else{
            foreach ($comment as $k=>$v){
                $comment[$k]['rank']  = round(($v['deliver_rank']+$v['goods_rank']+$v['service_rank'])/3);
            }
        }
//        dump($comment);
//        die;
        //获取商品相册
        $goods_images_list = $get->get_all(['goods_id'=>$id],'*','GoodsPicture');

        //获取商品属性
        $goods_attr = $get->get_all_goods_attr($id);
//        dump($goods_attr);
//        die;

        $this->assign('collect',$collect);
        $this->assign('goods',$goodslist);
        $this->assign('goodsinfo',$goodsinfo);
        $this->assign('hotgoods',$hotgoods);
        $this->assign('navigate',navigate_goods($id,$type=1));
        $this->assign('spec', $get->getspec($id));
        $this->assign('comment', $comment);
        $this->assign('goods_attr', $goods_attr);
        $this->assign('goods_images_list', $goods_images_list);
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

