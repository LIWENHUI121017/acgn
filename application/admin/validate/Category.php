<?php
/**
 * Created by PhpStorm.
 * User: LEE
 * Date: 2019/1/29
 * Time: 16:29
 */

namespace app\admin\validate;

use think\Validate;

class Category extends Validate
{
    protected $rule = [
       "name" =>  'require',
       "pid" =>  'require',
       "is_show" =>  'require',
       "sort_order" =>  'require',

    ];

    protected $message = [
        "name" =>  '商品分类的名称不能为空',
        "pid" =>  '上级分类还没选',
        "sort_order" =>  'require',
    ];

}