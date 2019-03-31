<?php
namespace app\common\validate;

use think\Validate;
use think\Db;

class UserAddress extends Validate
{
    // 验证规则
    protected $rule = [
        'consignee' => 'require|max:60',
        'province' => 'require|gt:0',
        'city' => 'require|gt:0',
        'district' => 'require|gt:0',
        'address' => 'require|max:255',
        'phone' => 'require|number',
        'zipcode' => 'require',
    ];
    //错误信息
    protected $message = [
        'consignee.require' => '收货人不能为空',
        'consignee.max' => '收货人长度不得超过60字符',
        'province.require' => '省份必须选择',
        'city.require' => '市必须选择',
        'district.require' => '镇/区必须选择',
        'province.gt' => '请选择省',
        'city.gt' => '请选择市',
        'district.gt' => '请选择镇/区',
        'address.require' => '地址不能为空',
        'address.max' => '地址名称最多不能超过255个字符',
        'phone.require' => '手机号不能为空',
        'zipcode' => 'zipcode格式错误',
    ];



}