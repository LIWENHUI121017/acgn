<?php
/**
 * Created by PhpStorm.
 * User: LEE
 * Date: 2019/1/29
 * Time: 16:29
 */

namespace app\admin\validate;

use think\Validate;

class Attribute extends Validate
{
    protected $rule = [
       "attr_name" =>  'require',
       "type_id" =>  'require|gt:0',
    ];


    protected $message = [
        "attr_name" =>  '商品属性的名称不能为空',
        "type_id" =>  '请选择模型',

    ];

}