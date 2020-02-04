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

class gb_goods extends Model
{

    public function addGoods($arr){
        $res = Db::table('gb_goods')
            ->insert($arr);
        return  $res;
    }

    public function hasGoods($goods_id){
        $res = Db::query('select goods_id from gb_goods where goods_id = ? ',[$goods_id]);
        return $res;
    }

    public function getGoodsNum(){
        $res = Db::query('select count(*) num from gb_goods');
        return $res;
    }

    public function editGoods($goods_id){
        $res = Db::table('gb_goods')->where('goods_id',$goods_id)->update(['status'=>1]);
        return $res;
    }

    public function getGoods(){
        $res = Db::query('select * from gb_goods where status = ? ',[0]);
        return $res;
    }
}