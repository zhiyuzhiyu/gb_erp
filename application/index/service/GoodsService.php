<?php


namespace app\index\service;

use app\index\model\gb_goods;

class GoodsService
{
    public function __construct()
    {
        $this->model = new gb_goods();
    }

    public function addGoods($arr){
        return $this->model->addGoods($arr);
    }

    public function getGoodsNum(){
        $goods =  $this->model->getGoodsNum();
        return !empty($goods) ? $goods[0]['num'] : 0;
    }

    public function hasGoods($goods_id){
        $goods =  $this->model->hasGoods($goods_id);
        return $goods;
    }
    public function editGoods($goods_id){
        $goods =  $this->model->editGoods($goods_id);
        return $goods;
    }

    public function getGoods(){
        $goods =  $this->model->getGoods();
        return $goods;
    }
}