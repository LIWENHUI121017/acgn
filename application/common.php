<?php
use think\Db;


//获取商品一二三级分类

function get_goods_category_tree(){
    $tree = $arr = $result = array();
    $cat_list = Db::name('goods_category')->where(['is_show' => 1])->order('sort_order')->select();//所有分类
    if($cat_list){
        foreach ($cat_list as $val){
            if($val['level'] == 2){
                $arr[$val['pid']][] = $val;
            }
            if($val['level'] == 3){
                $crr[$val['pid']][] = $val;
            }
            if($val['level'] == 1){
                $tree[] = $val;
            }
        }

        foreach ($arr as $k=>$v){
            foreach ($v as $kk=>$vv){
                $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
            }
        }

        foreach ($tree as $val){
            $val['tmenu'] = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
            $result[$val['id']] = $val;
        }
    }
    return $result;
}

//面包屑导航条获取
function navigate_goods($id,$type=0){
        $catid = $id;
        if ($type==1){
            $catid = Db::name('goods')->where(['id'=>$id])->column('goods_type_id');
        }
        $catelist = Db::name('goods_category')->column('id,pid,name', 'id');

        foreach ($catelist as $v){
            $a[0] = $catelist[$catid[0]]['pid'];
           if ($a > 0 && array_key_exists($a[0],$catelist)){
               $arr[$a[0]] = $catelist[$a[0]]['name'];
           }else{
               break;
           }

        }
         $arr[$catid[0]] = $catelist[$catid[0]]['name'];
        return $arr;

}

function get_arr_column($arr, $key_name)
{
    $arr2 = array();
    foreach($arr as $key => $val){
        $arr2[] = $val[$key_name];
    }
    return $arr2;
}

//根据中文获取拼音首字母
function get_letter($string) {
    $charlist = mb_str_split($string);
    return implode(array_map("getfirstchar", $charlist));
}
function mb_str_split($string) {
    // Split at all position not after the start: ^
    // and not before the end: $
    return preg_split('/(?<!^)(?!$)/u', $string);
}
function getfirstchar($s0) {
    $fchar = ord(substr($s0, 0, 1));
    if (($fchar >= ord("a") and $fchar <= ord("z"))or($fchar >= ord("A") and $fchar <= ord("Z"))) return strtoupper(chr($fchar));
    $s = iconv("UTF-8", "GBK", $s0);
    $asc = ord($s{0}) * 256 + ord($s{1})-65536;
    if ($asc >= -20319 and $asc <= -20284)return "A";
    if ($asc >= -20283 and $asc <= -19776)return "B";
    if ($asc >= -19775 and $asc <= -19219)return "C";
    if ($asc >= -19218 and $asc <= -18711)return "D";
    if ($asc >= -18710 and $asc <= -18527)return "E";
    if ($asc >= -18526 and $asc <= -18240)return "F";
    if ($asc >= -18239 and $asc <= -17923)return "G";
    if ($asc >= -17922 and $asc <= -17418)return "H";
    if ($asc >= -17417 and $asc <= -16475)return "J";
    if ($asc >= -16474 and $asc <= -16213)return "K";
    if ($asc >= -16212 and $asc <= -15641)return "L";
    if ($asc >= -15640 and $asc <= -15166)return "M";
    if ($asc >= -15165 and $asc <= -14923)return "N";
    if ($asc >= -14922 and $asc <= -14915)return "O";
    if ($asc >= -14914 and $asc <= -14631)return "P";
    if ($asc >= -14630 and $asc <= -14150)return "Q";
    if ($asc >= -14149 and $asc <= -14091)return "R";
    if ($asc >= -14090 and $asc <= -13319)return "S";
    if ($asc >= -13318 and $asc <= -12839)return "T";
    if ($asc >= -12838 and $asc <= -12557)return "W";
    if ($asc >= -12556 and $asc <= -11848)return "X";
    if ($asc >= -11847 and $asc <= -11056)return "Y";
    if ($asc >= -11055 and $asc <= -10247)return "Z";
    return null;
}


 //将数据库中查出的列表以指定的 id 作为数组的键名 数组指定列为元素 的一个数组
function get_id_val($arr, $key_name,$key_name2){
    $arr2 = array();
    foreach ($arr as $k => $v){
        $arr2[$v[$key_name]] = $v[$key_name2];
    }
    return $arr2;
}
/**
 * 多个数组的笛卡尔积
 *
 * @param unknown_type $data
 */
function combineDika() {
    $data = func_get_args();
    $data = current($data);
    $cnt = count($data);
    $result = array();
    $arr1 = array_shift($data);
    foreach($arr1 as $key=>$item)
    {
        $result[] = array($item);
    }

    foreach($data as $key=>$item)
    {
        $result = combineArray($result,$item);
    }
    return $result;
}
/**
 * 两个数组的笛卡尔积
 * @param unknown_type $arr1
 * @param unknown_type $arr2
 */
function combineArray($arr1,$arr2) {
    $result = array();
    foreach ($arr1 as $item1)
    {
        foreach ($arr2 as $item2)
        {
            $temp = $item1;
            $temp[] = $item2;
            $result[] = $temp;
        }
    }
    return $result;
}

/**
 * 将model查询出来的对象转换为数组
 * @param array $array
 * @return array
 */
function acgnModeltoarray($array){
    $datarray = array();
    if (empty($array) || !count($array)) {
        return false;
    }
    foreach ($array as $value) {
        $datarray[] = collection($value)->toArray();
    }
    return $datarray;
}

/**
 * 手机号码隐藏中间四位
 */
function hidtel($phone){
    $IsWhat = preg_match('/(0[0-9]{2,3}[\-]?[2-9][0-9]{6,7}[\-]?[0-9]?)/i',$phone); //固定电话
    if($IsWhat == 1){
        return preg_replace('/(0[0-9]{2,3}[\-]?[2-9])[0-9]{3,4}([0-9]{3}[\-]?[0-9]?)/i','$1****$2',$phone);
    }else{
        return preg_replace('/(1[358]{1}[0-9])[0-9]{4}([0-9]{4})/i','$1****$2',$phone);
    }
}
/**
 * 检查手机号码格式
 * @param $mobile 手机号码
 */
function check_mobile($mobile){
    if(preg_match('/1[34578]\d{9}$/',$mobile))
        return true;
    return false;
}


