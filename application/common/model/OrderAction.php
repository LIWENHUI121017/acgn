<?php
namespace app\common\model;

use think\Model;

class OrderAction extends Model{
    public function orderaction_admin(){
        $res = $this
            ->alias('a')
            ->join('admin s','a.action_user=s.id')
            ->field('')
            ->select()
            ->toArray();
        return $res;
    }
    public function orderaction_user(){
        $res = $this
            ->alias('a')
            ->join('user s','a.action_user=s.id')
            ->field('s.user_name ')
            ->select()
            ->toArray();
        return $res;
    }
}