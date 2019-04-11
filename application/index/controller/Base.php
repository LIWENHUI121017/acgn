<?php
namespace app\index\controller;
use app\common\logic\My_Logic;
use think\Controller;
use think\Db;
use think\Session;
use think\Loader;

class Base extends Controller
{

	public $session_id;

	public function _initialize()
    {
        $this->session_id = session_id();
        header("Cache-control: private");  // history.back返回后输入框值丢失问题 参考文章 http://www.tp-shop.cn/article_id_1465.html  http://blog.csdn.net/qinchaoguang123456/article/details/29852881
        define('SESSION_ID',$this->session_id); //将当前的session_id保存为常量，供其它方法调用
        //获取导航栏
        $nav = get_goods_category_tree();
//        dump($nav);
        $logic = new My_Logic();
        //获取购物车数量
        if(session('?user')) {
            $user = session('user');
            $cartcount= $logic->get_count(['user_id'=>$user['id']],'Cart');
            $this->assign('cartcount',$cartcount);
        }else{
            $cartcount= $logic->get_count(['session_id'=>session_id()],'Cart');
            $this->assign('cartcount',$cartcount);

        }
        $this->assign('nav',$nav);

    }
}