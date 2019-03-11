<?php
namespace app\index\controller;
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

    }
}