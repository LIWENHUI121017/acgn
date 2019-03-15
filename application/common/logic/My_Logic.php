<?php
namespace app\common\logic;


use think\Db;

class My_Logic{

    protected $table;



    //查询一条数据
    public function get_one($where = array(),$field = '*',$table=''){
        if ($table){
            $res = model($table)->field($field)->where($where)->find();
            if (!empty($res)){
                $res = $res->toArray();
            }
        }else{
            $res = model($this->table)->field($field)->where($where)->find();
            if (!empty($res)){
                $res = $res->toArray();
            }
        }
        return $res;
    }

    //查询多条数据
    public function get_all($where = array(),$field = '*',$table=''){
        if ($table){
            $res = model($table)->field($field)->where($where)->select();
            if (!empty($res)){
                $res = $res->toArray();
            }
        }else{
            $res = model($this->table)->field($field)->where($where)->select();
            if (!empty($res)){
                $res = $res->toArray();
            }
        }
        return $res;
    }

    //添加一条数据
    public function add($data,$table=''){
        if ($table){
            $res = Db::name($table)->insertGetId($data);
        }else{
            $res = Db::name($this->table)->insertGetId($data);
        }
        return $res;
    }

    //添加多条数据
    public function addall($data,$table=''){
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
    public function edit($where = array(),$data,$table=''){
        if ($table){
            $res = Db::name($table)->where($where)->update($data);
            return $res;
        }else{
            $res = Db::name($this->table)->where($where)->update($data);
            return $res;
        }

    }

    //查询总数
    public function get_count($w=array()){
        $res = Db::name($this->table)->where($w)->count();
        return $res;
    }

}
