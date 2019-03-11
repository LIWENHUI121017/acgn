<?php
/**
 * Created by PhpStorm.
 * User: LEE
 * Date: 2019/1/29
 * Time: 16:29
 */

namespace app\admin\validate;

use think\Validate;

class Spec extends Validate
{
    protected $rule = [
       "name" =>  'require',
       "tid" =>  'require|gt:0',
       "item" =>  'require',


    ];


    protected $message = [
        "name" =>  '商品规格的名称不能为空',
        "tid" =>  '请选择模型',
        "item" =>  '规格项不能为空',
    ];

}