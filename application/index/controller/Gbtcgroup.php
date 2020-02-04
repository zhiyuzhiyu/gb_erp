<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/1/19
 * Time: 10:38
 */
namespace app\index\controller;

use app\index\service\GbtcgroupService;
use think\facade\Env;
use think\response\Redirect;
use think\Session;

class Gbtcgroup extends Base
{

    protected $session = "";
    protected $user_ord = 0;
    public function initialize()
    {
        parent::initialize();
        $this->session =  session('session');
        if(empty($this->session)){
            $this->login();
        }
        $this->service = new GbtcgroupService();
    }

    public function index(){
        set_time_limit(0);
        $order_id = $this->service->getOrderId();
        $res =  http_post("https://zwcap.zhaowoce.com:4433/gb/order/getGbOrderInfo",['order_id'=>$order_id]);
        $data = json_decode($res,true);
        if($data['code'] == 0){
            $datas = $data['data'];
            if(empty($datas)){
                return false;
            }
            foreach($datas as $k=>$v){
                //判断订单最近有没有处理过
                $order_sn = $v['order_sn'];
                $has_order = $this->service->has_order($order_sn);
                if(!empty($has_order)){
                    continue;
                }
                $res = $this->addGbOrder($v);
                if($res){
                    $this->service->index(array('order_sn'=>$v['order_sn'],'order_id'=>$v['order_id']));
                    log_by_type("GB","存储订单".$v['order_sn']."成功！");
                }else{
                    $this->service->index(array('order_sn'=>$v['order_sn'],'status'=>0,'order_id'=>$v['order_id']));
                    log_by_type("GB","存储订单".$v['order_sn']."失败，订单内商品不匹配");
                }
            }
        }else{
            //最近10分钟没有订单
        }

    }

    //执行
    public function addGbOrder($v){
        //判断商品是否存在
        $goods = $v['goods_info'];
        $goods_ords = [];
        foreach($goods as $g_k=>$g_v){
            $goods_sn = $g_v['goods_sn'];
            $goods_ord = $this->goodsList($goods_sn);
            if(!empty($goods_ord)){
                $goods_ords []= $goods_ord;
            }else{
                return false;
            }
        }

        //判断用户
        $consignee = $v['consignee'];
        $user_mobile = $v['mobile'];
        $user_ord = $this->userList($consignee);
        if(empty($user_ord)){
            //新建用户
            $user_ord = $this->addUser($consignee,$user_mobile);
        }
        //添加商品
        foreach($goods_ords as $g_k=>$g_ord){
            $this->goodInCon($user_ord,$g_ord);
        }
        //添加合同
        $address = $v['province_name'].$v['city_name'].$v['district_name'].$v['address'];
        $money_paid = $v['money_paid'];
        $this->addContract($user_ord,$consignee,$money_paid,$address);
        return true;
    }

    //login 登录
    //userList 获取用户 ord 判断用户是否已存在  返回ord    ==0 不存在
    //goodsList 获取商品 ord   返回ord    ==0 不存在
    //newOrd 分配客户id 返回 ord(唯一标识) khid（客户编号）   addUser 接口里有调用

    //addUser 添加客户  未调通
    //addContract  添加合同 未调通
    //goodInCon 产品存入合同  未调通

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


    //获取客户列表
    public function userList($name = ""){
//        $name = '金码头测试1' ;   //用户名
//        $mobile = '17633312736';
        $ch = curl_init();
        $data = [
            'session'=>$this->session,
            'cmdkey'=>'refresh',
            'datas'=>[
                ['id'=>'datatype','val'=>''],
                ['id'=>'stype','val'=>'1'],
                ['id'=>'remind','val'=>'147'],
                ['id'=>'name','val'=>$name],  //判断用户是否存在  南京正三角化学试剂有限公司
                ['id'=>'tjly','val'=>''],
                ['id'=>'tdate1','val'=>''],
                ['id'=>'tdate2','val'=>''],
                ['id'=>'checktype','val'=>'radio'],
                ['id'=>'telsort','val'=>''],
                ['id'=>'a_cateid','val'=>''],
                ['id'=>'catetype','val'=>'1'],
                ['id'=>'pagesize','val'=>'20'],
                ['id'=>'pageindex','val'=>'1']
            ]
        ];
        $json = json_encode($data);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, 'http://172.18.111.5/SYSA/mobilephone/salesmanage/custom/list.asp');
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
            $ord = $table['rows'][0][7];
        }else{
            $ord = 0;
        }
        return $ord;
//        echo $b;
    }

    //分配新客户id
    public function newOrd(){
        $ch = curl_init();
        $data = [
            'session'=>$this->session,
            'datas'=>[
                ['id'=>'edit','val'=>''], /*修改模式*/
                ['id'=>'intsort','val'=>'2'], /*客户类型*/
                ['id'=>'datatype','val'=>''],
            ]
        ];
        $json = json_encode($data);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, 'http://172.18.111.5/SYSA/mobilephone/salesmanage/custom/add.asp?intsort=2');
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
        $bill = (array)$body['bill'];
        $groups = $bill['groups'];
        $groups_one = (array)$groups[0];
        $fields = $groups_one['fields'];

        $khid_arr = (array)$fields[2];

        $khid = $khid_arr['value'];
        $ord = $bill['value'];
        $arr['khid'] = $khid;
        $arr['ord'] = $ord;

        return $arr;

    }

    //添加客户
    public function addUser($name = '',$mobile = '')
    {
//        $name = "金码头测试1";
//        $mobile = "17633312736";
        $new_ord = self::newOrd();
        $ch = curl_init();

        $arr = [];
        $arr['ord'] = $new_ord['ord'];                                       //-------分配返回
        $arr['name'] = $name; /*客户名称*/
        $arr['pym'] = "";/*拼 音 码*/
        $arr['khid'] = $new_ord['khid'];/*客户编号*/                         //-------分配返回
        $arr['sort1'] = "3,7";/*客户分类*/
        $arr['ly'] = "173";/*客户来源*/
        $arr['jf2'] = "1";/*积分*/
        $arr['area'] = "219";/*客户区域*/
        $arr['trade'] = "139";	/*客户行业*/
        $arr['jz'] = "175";/*价值评估*/
        $arr['credit'] = "";/*信用等级*/
        $arr['url'] = ""; /*客户网址*/
        $arr['hk_xz'] = "" ;/*到款限制*/
        $arr['address'] = "";/*客户地址*/
        $arr['lng'] = "0";
        $arr['lat'] = "0";
        $arr['zip'] = ""; /* 邮  编 */
        $arr['sex'] = "" ; /* 性  别 */
        $arr['age'] = "";/* 年  龄 */
        $arr['year1'] = "";/* 生  日 */
        $arr['part1'] = "";/* 部  门 */
        $arr['job'] = ""; /* 职  务 */
        $arr['phone'] = "";/*办公电话*/
        $arr['fax'] = "" ; 	/* 传  真 */
        $arr['mobile'] = $mobile; /* 手  机 */
        $arr['phone2'] = "";/*家庭电话*/
        $arr['phone2'] = ""; /*电子邮件*/
        $arr['qq'] = "";
        $arr['weixinAcc'] = "";/*微  信*/
        $arr['msn'] = ""; /*   MSN  */
        $arr['jg'] = "";
        $arr['faren'] = ""; 	/*所在单位*/
        $arr['product'] = "" ; /*客户简介*/
        $arr['c2'] = ""; /*合作现状*/
        $arr['c3'] = ""; /*合作前景*/
        $arr['c4'] = ""; /*跟进策略*/
        $arr['intro'] = ""; /* 备  注 */

        $add_user = $this->service->addUser($arr);
        foreach($arr as $k=>$v){
            $datas[] = ['id'=>$k,'val'=>$v];
        }
        $data = [
            'session'=>$this->session,
            'cmdkey'=>'__sys_dosave',
            'datas'=>$datas
        ];

        $json = json_encode($data);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, 'http://172.18.111.5/SYSA/mobilephone/salesmanage/custom/add.asp?intsort=2');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/zsml; charset=utf-8',	 /*接口规定content-type值必须为application/zsml。*/
                'Content-Length: '.strlen($json))
        );
        ob_start();
        curl_exec($ch);  /*输出返回结果*/
        $b=ob_get_contents();
        ob_end_clean();
        $res = self::zhipai($new_ord['ord']);
        return $new_ord['ord'];
    }

    //客户指派
    public function zhipai($ord){
        $ch = curl_init();
        $data = [
            'session'=>$this->session,
            'cmdkey'=>'__sys_dosave',
            'datas'=>[
                ['id'=>'ord','val'=>$ord],
                ['id'=>'member1','val'=>'1'],
                ['id'=>'member2','val'=>'63']
            ]
        ];
        $json = json_encode($data);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, 'http://172.18.111.5/SYSA/mobilephone/systemmanage/order.asp?datatype=tel');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/zsml; charset=utf-8',	 /*接口规定content-type值必须为application/zsml。*/
                'Content-Length: '.strlen($json))
        );
        ob_start();
        curl_exec($ch);  /*输出返回结果*/
        $b=ob_get_contents();
        ob_end_clean();
        return true;
//        echo $b;
    }

    //合同列表  获取最后一个合同编号
    public function contractList(){
        $ch = curl_init();
        $data = [
            'session'=>$this->session,
            'cmdkey'=>'refresh',
            'datas'=>[
                ['id'=>'stype','val'=>"0"],                  /*列表模式*/
                ['id'=>'datatype','val'=>''],                /*列表模式*/
                ['id'=>'ord','val'=>''],                          /*列表数据检索条件*/
                ['id'=>'remind','val'=>"0"],              	/*提醒类型*/
                ['id'=>'ly','val'=>''],                           /**/
                ['id'=>'tdate1','val'=>''],                   	/**/
                ['id'=>'tdate2','val'=>''],               	    /**/
                ['id'=>'checktype','val'=>'radio'],        	/*关联单据选择模式*/
                ['id'=>'a_cateid','val'=>''],             	/**/
                ['id'=>'a_sort1','val'=>''],               	/**/
                ['id'=>'a_date_0','val'=>''],              	/**/
                ['id'=>'a_date_1','val'=>''],              	/**/
                ['id'=>'a_sort2','val'=>''],               	/**/
                ['id'=>'a_sort3','val'=>''],               	/**/
                ['id'=>'catetype','val'=>""],                    /*人员类型*/
                ['id'=>'cateid','val'=>''],               	         /*人员选择*/
                ['id'=>'bz','val'=>''],                           /*币种*/
                ['id'=>'htfl','val'=>''],                    /*合同分类*/
                ['id'=>'htzt','val'=>''],                    /*合同状态*/
                ['id'=>'title','val'=>''],                   /*合同主题*/
                ['id'=>'htbh','val'=>''],                    /*合同编号*/
                ['id'=>'khmc','val'=>''],                    /*客户名称*/
                ['id'=>'zdy3','val'=>''],                  	/*折扣*/
                ['id'=>'zdy4','val'=>''],                         /*备注*/
//                ['id'=>'htmoney_0','val'=>''],                    /*合同金额上限*/
//                ['id'=>'htmoney_1','val'=>''],                 /*合同金额下限*/
//                ['id'=>'moneyall_0','val'=>''],              	/*优惠后金额上限*/
//                ['id'=>'moneyall_1','val'=>''],              	/*优惠后金额下限*/
//                ['id'=>'dateQD','val'=>''],                	         /*签订日期*/
//                ['id'=>'dateKS','val'=>''],                	         /*开始日期*/
//                ['id'=>'dateZZ','val'=>''],                	         /*终止日期*/
                ['id'=>'searchKey','val'=>''],                    	/*快速检索条件*/
                ['id'=>'pagesize','val'=>"1"],               	/*每页记录数*/
                ['id'=>'pageindex','val'=>"1"],                  /*数据页标*/
//                ['id'=>'_rpt_sort','val'=>'ord']   	       	       /*排序字段*/
            ]
        ];
        $json = json_encode($data,JSON_UNESCAPED_UNICODE);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, 'http://172.18.111.5/SYSA/mobilephone/salesmanage/contract/billlist.asp');
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
            $con_sn = $table['rows'][0][1];
        }else{
            $con_sn = 0;
        }
        $arr = explode('GB_',$con_sn);
        $sn = 'GB_'.($arr[1]+1) ;
        return $sn;

    }

    //添加合同
    public  function addContract($user_ord = '',$consignee = '',$money_paid = '',$address = '',$htid = ''){
//        $user_ord = 7592;
//        $consignee = '金码头测试用户';
//        $money_paid = "0.00";
//        $address = "111";

        $year = date("Y");
        $month = intval(date("m"));
        $day = intval(date("d"));
        $today =  $year."-".$month."-".$day;

        if($day - 1 == 0){
            if($month - 1 == 0){
                $month = 12;
                $day = 31;
            }else{
                $year = $year + 1;
                $month = $month - 1;
                if(in_array($month,[1,3,5,7,8,10,12])){
                    $day = 31;
                }else if($month == 2){
                    if(($year%4 == 0 && $year%100 != 0) || ($year%400 == 0 )){
                        $day = 29;
                    }else{
                        $day = 28;
                    }
                }else{
                    $day = 30;
                }
            }
        }else{
            $year = $year + 1;
            $day = $day - 1;
        }
        $end_date =  $year."-".$month."-".$day;

        if($htid == ""){
            $htid = $this->contractList();
        }

//        echo $htid;
//        exit;

        $arr = [];
//        $arr['ord'] = '';  添加不传         /*数据唯一标识*/
        $arr['company'] = $user_ord;   //客户id       /*关联客户*/
        $arr['htid'] = $htid;     		/*合同编号*/
        $arr['title'] = $consignee.$htid;               /*合同主题*/
        $arr['date3'] = $today;         	/*签订日期*/
        $arr['date1'] = $today;         	/*开始日期*/
        $arr['date2'] = $end_date;         	/*结束日期*/
        $arr['cateid'] = "0";                 /*销售人员*/
        $arr['person2id'] = '0';              /*对方代表*/
        $arr['sort'] = '192';                 /*合同分类*/
        $arr['complete1'] = '195';           	/*合同状态*/
        $arr['bz'] = "14";                    /*币　　种*/
        $arr['premoney'] = $money_paid;           	/*合同总额*/
        $arr['yhtype'] = '0';                /*优惠方式*/
        $arr['money1'] = '0.00';              /*优惠后总额*/
        $arr['fqhk'] = "1";                     /*回款计划*/
        $arr['paybackMode'] = '2';           	/*收款类型*/
        $arr['invoicePlan'] = '2';           	/*开票计划*/
        $arr['invoiceMode'] = '2';           	/*开票类型*/
        $arr['extras'] = "0";                  /*运杂费*/
        $arr['repairOrderid'] = "0";         	 /*repairOrderid*/
        $arr['@contractlist'] = "";    		/**/
        $arr['jh_intro3'] = "";          	 /*付款方式*/
        $arr['jh_intro4'] = $address;	 /*交货地址*/
        $arr['jh_intro5'] = "";          	 /*交货方式*/
        $arr['jh_intro6'] = "";     	 /*交货时间*/
        $arr['jh_intro1'] = "";            	 /*配件*/
        $arr['jh_intro2'] = "";            	 /*备注*/
        $arr['yh_money'] = "0.00";           	 /*优惠金额*/
        $arr['yh_zk'] = "1.00";                 /*折扣*/
        $arr['invoicePlanType'] = "204";     		/*票据类型*/
        $arr['zdy3'] = "1.00";                    /*折扣*/
        $arr['zdy4'] = "";                      /*备注*/
        $arr['intro'] = "";                   /*合同概要*/

        $add_con = $this->service->addCon($arr);

        foreach($arr as $k=>$v){
            $datas[] = ['id'=>$k,'val'=>$v];
        }
        $session  = $this->session;
        $data = [
            'session'=>$session,
            'cmdkey'=>'__sys_dosave',
            'datas'=>$datas
        ];
        $json = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, 'http://172.18.111.5/SYSA/mobilephone/salesmanage/contract/add.asp');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/zsml; charset=utf-8',	 /*接口规定content-type值必须为application/zsml。*/
                'Content-Length: '.strlen($json))
        );
        ob_start();
        curl_exec($ch);  /*输出返回结果*/
        $b=ob_get_contents();
        ob_end_clean();
        echo $b;
        $res = (array)json_decode($b);
        if(!empty($res['body'])){
            $body = (array)$res['body'];
            if(!empty($body['message'])){
                $message = (array)$body['message'];
                if($message['text'] == '合同编号已被使用，不能保存！'){
                    $arr_n = explode('GB_',$htid);
                    $sn = 'GB_'.($arr_n[1]+1) ;
                    $this->addContract($user_ord,$consignee,$money_paid,$address,$sn);
                };
            }
        }
    }

    //产品存入合同
    public function goodInCon($user_ord,$goods_ord){
        $ch = curl_init();
        $arr['ord'] = $goods_ord;
        $arr['company'] = $user_ord;
        foreach($arr as $k=>$v){
            $datas[] = ['id'=>$k,'val'=>$v];
        }
        $data=[
            'session'=>$this->session,
            'cmdkey'=>'addToContract',
            'datas'=>$datas
        ];
        $json = json_encode($data);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, 'http://172.18.111.5/SYSA/mobilephone/salesmanage/contract/contractlist.asp');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/zsml; charset=utf-8',	 /*接口规定content-type值必须为application/zsml。*/
                'Content-Length: '.strlen($json))
        );
        ob_start();
        curl_exec($ch);  /*输出返回结果*/
        $b=ob_get_contents();
        ob_end_clean();
        return true;
    }

    //获取商品列表  返回商品id
    public function goodsList($cpbh = ""){
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
                ['id'=>'cpbh','val'=>$cpbh],      /*产品编号*/
//                ['id'=>'cpxh','val'=>""],      /*产品型号*/
//                ['id'=>'kcxx_0','val'=>""],    /*库存下限上限*/
//                ['id'=>'kcxx_1','val'=>""],    /*库存下限下限*/
//                ['id'=>'kcsx_0','val'=>""],    /*库存上限上限*/
//                ['id'=>'kcsx_1','val'=>""],    /*库存上限下限*/
//                ['id'=>'cateid','val'=>""],    /*人员选择*/
//                ['id'=>'adddate','val'=>""],   	/*添加日期*/
//                ['id'=>'searchKey','val'=>""], 	 /*快速检索条件*/
                ['id'=>'pagesize','val'=>"20"],     /*每页记录数*/
                ['id'=>'pageindex','val'=>"1"],     /*数据页标*/
//                ['id'=>'_rpt_sort','val'=>""],  /*排序字段*/
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
            $ord = $table['rows'][0][0];
        }else{
            $ord = 0;
        }
        return $ord;
//        echo $b;
    }

}
