<?php
/**
 * Created by PhpStorm.
 * User: LEE
 * Date: 2019/1/29
 * Time: 16:29
 */

namespace app\admin\validate;

use think\Validate;

class AdminUpdateGoods extends Validate
{
    protected $rule = [
          "goods_name" => 'require',
//          "goods_sn" => 'require',
          "goods_inventory" => 'require|number',
          "goods_price" => 'require|number',
//          "goods_sale" => 'float',
//          "goods_abstract" => 'require',
          "cate1" => 'require|gt:0',
          "cate2" => 'require|gt:0',
          "cate3" => 'require',
          "goods_type_id" => 'require',
//          "goods_desc" => 'require',
    ];

    protected $message = [
        "goods_name" => '商品名称必须填写',
//          "goods_sn" => ,
          "goods_inventory.require" => '商品库存必须填写',
          "goods_inventory.number" => '商品库存必须填写数字',
          "goods_price.require" => '商品价格必须填写',
          "goods_price.number" => '商品价格必须填写数字',
//          "goods_sale" => '商品折扣必须是float类型',
//          "goods_abstract" => '商品简介不能为空',
          "cate1" =>  '商品类型1必须填写',
          "cate2" =>  '商品类型2还没选择',
          "cate3" =>  '商品类型3必须填写',
//          "goods_desc" =>  '商品详情必须填写',
            "goods_type_id" => '商品分类还没选择',
    ];

}