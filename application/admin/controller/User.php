<?php
namespace app\admin\controller;



use app\common\logic\AttributeLogic;
use app\common\logic\SpecLogic;
use app\common\logic\UserLogic;
use think\Db;
use think\Loader;

class User extends Base
{

    public function _initialize()
    {
        return parent::_initialize();
    }

    public function index()
    {
        $logic = new UserLogic();
        $user = $logic-
        return $this->fetch();
    }


}
