<?php


namespace app\index\controller;


use app\index\service\GoodsService;

class Goods extends Base
{
    protected $session = "";
    public function initialize()
    {
        parent::initialize();
        $this->session =  session('session');
        if(empty($this->session)){
            $this->login();
        }
        $this->service = new GoodsService();
    }

    //登录接口
    public function login(){
        $ch = curl_init();
        $data = [
            'session' =>'admin_login',
            'datas' =>[
                ['id'=>'user','val'=>'txt:admin'],
                ['id'=>'password','val'=>'txt:88888888'],
                ['id'=>'serialnum','val'=>'txt:123456abcdef'],
                ['id'=>'rndcode','val'=>'']
            ]
        ];
        $json = json_encode($data);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, 'http://172.18.111.5/SYSA/mobilephone/login.asp');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/zsml; charset=utf-8',	 /*接口规定content-type值必须为application/zsml。*/
                'Content-Length: '.strlen($json))
        );
        ob_start();
        curl_exec($ch);  /*输出返回结果*/
        $b=ob_get_contents();
        $res = (array)json_decode($b);
        $header = (array)$res['header'];
        session('session',$header['session']);
        $this->session = $header['session'];
//        echo $b;
    }

    //获取商品列表  返回商品id  392   1803
    public function goodsList($page = 1){
//        $cpbh = 'GSB04-1752-2004d-100ml';
        $ch = curl_init();
        $data = [
            'session'=>$this->session,
            'cmdkey'=>"refresh",
            'datas'=>[
                ['id'=>'listadd','val'=>"1"],  	/*列表模式*/
                ['id'=>'company','val'=>"0"],  /*客户ID*/
                ['id'=>'repairOrder','val'=>"0"],  /*维修单ID*/
                ['id'=>'secpro','val'=>"0"],  /*是否选择产品*/
                ['id'=>'fromtype','val'=>""],  	/**/
                ['id'=>'proSort','val'=>""],   	/*产品分类*/
                ['id'=>'cpname','val'=>""],    /*产品名称*/
                ['id'=>'cpbh','val'=>''],      /*产品编号*/
//                ['id'=>'cpxh','val'=>""],      /*产品型号*/
//                ['id'=>'kcxx_0','val'=>""],    /*库存下限上限*/
//                ['id'=>'kcxx_1','val'=>""],    /*库存下限下限*/
//                ['id'=>'kcsx_0','val'=>""],    /*库存上限上限*/
//                ['id'=>'kcsx_1','val'=>""],    /*库存上限下限*/
//                ['id'=>'cateid','val'=>""],    /*人员选择*/
//                ['id'=>'adddate','val'=>""],   	/*添加日期*/
//                ['id'=>'searchKey','val'=>""], 	 /*快速检索条件*/
                ['id'=>'pagesize','val'=>"20"],     /*每页记录数*/
                ['id'=>'pageindex','val'=>$page],     /*数据页标*/
                ['id'=>'_rpt_sort','val'=>"2"],  /*排序字段*/    //2 按时间正序
            ]
        ];
        $json = json_encode($data);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, 'http://172.18.111.5/SYSA/mobilephone/salesmanage/product/billlist.asp');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/zsml; charset=utf-8',	 /*接口规定content-type值必须为application/zsml。*/
                'Content-Length: '.strlen($json))
        );
        ob_start();
        curl_exec($ch);  /*输出返回结果*/
        $b=ob_get_contents();
        ob_end_clean();


        $res = (array)json_decode($b);
        $body = (array)$res['body'];
        $source = (array)$body['source'];
        $table = (array)$source['table'];

        if(!empty( $table['rows'])){
            return $table['rows'];
        }else{
           return [];
        }
    }

    //国标商品存入中间库
    public function saveGoods(){
        set_time_limit(0);
        $goods_num = $this->service->getGoodsNum();
        $page = floor($goods_num/20) + 1;
        $goods_list = self::goodsList($page);
        if(!empty($goods_list)){
            foreach($goods_list as $k=>$v){
                $goods_id = $v[0];
                //判断是否已同步
                $has_goods = $this->service->hasGoods($goods_id);
                if(empty($has_goods)){
                    $arr = [];
                    $arr['goods_id'] = $v[0];
                    $arr['cpname'] = $v[1];
                    $arr['cpbh'] = $v[2];
                    $arr['cpxh'] = $v[3];
                    $arr['proSort'] = $v[7];
                    $arr['unit'] = $v[5];
                    $arr['price'] = $v[6];
                    $save = $this->service->addGoods($arr);
                }
            }
            //查询未同步商品
            $goods = $this->service->getGoods();
            if(!empty($goods)){
                foreach ($goods as $k=>$v){
                    $cursor = [];
                    $cursor['goods_id'] = $v['goods_id'];
                    $cursor['goods_name'] = $v['cpname'];
                    $cursor['goods_sn'] = $v['cpbh'];
                    $cursor['shop_price'] = $v['price'];
                    $cursor['shop_cat_name'] = $v['proSort'];
                    $cursor['unit'] = $v['unit'];
                    $cursor['goods_xh'] = $v['cpxh'];
                    $save =  http_post("https://zwcap.zhaowoce.com:4433/gb/goods/saveGoods",$cursor);  //
                    $data = json_decode($save,true);
                    if($data['code'] === "0"){
                        $this->service->editGoods($cursor['goods_id']);
                    }
                }
            }
//            echo "<pre>";
//            print_r($goods_list);
//            echo "</pre>";
        }else{
            echo "没有需要同步的商品了";
        }
        exit;
    }

}