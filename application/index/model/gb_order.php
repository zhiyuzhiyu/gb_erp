<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/1/19
 * Time: 10:57
 */
namespace app\index\model;
use think\Db;
use think\Model;

class gb_order extends Model
{
    public function index($arr){
//            $res = Db::table('user')
//            ->select();

//        $arr = [
//            'ord'=>2
//        ];
        $res = Db::table('zwc_order')
            ->insert($arr);
        return  $res;
    }

    public function addUser($arr){
        $res = Db::table('user')
            ->insert($arr);
        return  $res;
    }

    public function addCon($arr){
        $res = Db::table('contract')
            ->insert($arr);
        return  $res;
    }

    public function has_order($order_sn){
        $res = Db::query('select id from zwc_order where order_sn = ? ',[$order_sn]);
        return $res;
    }

    public function getOrderId(){
        $res = Db::query('select order_id from zwc_order order by order_id desc limit 1');
        return $res;
    }

}