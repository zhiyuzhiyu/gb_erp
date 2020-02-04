<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/1/19
 * Time: 10:55
 */
namespace app\index\service;
use app\index\model\gb_order;

class GbtcgroupService
{
    public function __construct()
    {
        $this->model = new gb_order();
    }

    public function index($arr){
        return $this->model->index($arr);
    }
    public function addUser($arr){
        return $this->model->addUser($arr);
    }

    public function addCon($arr){
        unset($arr['@contractlist']);
        return $this->model->addCon($arr);
    }
    public function has_order($order_sn){
        return $this->model->has_order($order_sn);
    }
    public function getOrderId(){
        $order =  $this->model->getOrderId();
        return !empty($order) ? $order[0]['order_id'] : 0;
    }
}