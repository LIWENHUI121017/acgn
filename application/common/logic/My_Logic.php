<?php
namespace app\common\logic;


use think\Db;

class My_Logic{

    protected $table;
    protected $search_field;


    //查询一条数据
    public function get_one($where = array(),$field = '*',$table){
        if ($table){
            $res = model($table)->field($field)->where($where)->find();
            if (!empty($res)){
                $res = $res->toArray();
            }
//            $res = Db::name($table)->field($field)->where($where)->find();
        }else{
            $res = model($this->table)->field($field)->where($where)->find()->toArray();
//            $res = Db::name($this->table)->field($field)->where($where)->find();
        }
        return $res;
    }

    //查询多条数据
    public function get_all($where = array(),$field = '*',$table){
        if ($table){
            $res = model($table)->field($field)->where($where)->select()->toArray();
//            $res = Db::name()->field($field)->where($where)->select();
        }else{
            $res = model($this->table)->field($field)->where($where)->select()->toArray();
//            $res = Db::name($this->table)->field($field)->where($where)->select();
        }
        return $res;
    }

    //添加一条数据
    public function add($data,$table){
        if ($table){
            $res = Db::name($table)->insertGetId($data);
        }else{
            $res = Db::name($this->table)->insertGetId($data);
        }
        return $res;
    }

    //添加多条数据
    public function addall($data,$table){
        if ($table){
            $res = model($table)->saveAll($data);
        }else{
            $res = model($this->table)->saveAll($data);
        }
        return $res;

        return $res;
    }
    //删除一条数据
    public function del($where=array()){
        $res = Db::name($this->table)->where($where)->delete();
        return $res;
    }

    //修改数据
    public function edit($where = array(),$data){
        $res = Db::name($this->table)->where($where)->update($data);
        return $res;
    }

    //查询总数
    public function get_count($w=array()){
        $res = Db::name($this->table)->where($w)->count();
        return $res;
    }

}
