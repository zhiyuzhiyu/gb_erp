<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

function http_post($url, $param)
{
    $oCurl = curl_init();
    if (stripos($url, "https://") !== FALSE) {
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
    }
    if (is_string($param)) {
        $strPOST = $param;
    } else {
        $aPOST = array();
        if(!empty($param)){
            foreach ($param as $key => $val) {
                $aPOST[] = $key . "=" . urlencode($val);
            }
        }
        $strPOST = join("&", $aPOST);
    }
    curl_setopt($oCurl, CURLOPT_URL, $url);
    curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
    if ($param != "") {
        curl_setopt($oCurl, CURLOPT_POST, true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);
    }
    $UserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.86 Safari/537.36';
    curl_setopt($oCurl, CURLOPT_USERAGENT, $UserAgent);
    $sContent = curl_exec($oCurl);
    $aStatus = curl_getinfo($oCurl);
    curl_close($oCurl);
    if (intval($aStatus["http_code"]) == 200) {
        return $sContent;
    } else {
        return false;
    }
}


/**
 * 功能描述：按类型记录日志
 */
function log_by_type($type, $msg, $path = '')
{
    $ip = $_SERVER["REMOTE_ADDR"];
    $pid = getmypid();
    $time = date('Y-m-d H:i:s', time());
    $MLogPath = \think\facade\Env::get('root_path')."log/";
    $MLogTitle = $type . "_" . date('Y-m-d') . ".txt";
    if ($path) {
        $arr = explode("/", $path);
        foreach ($arr as $name) {
            $MLogPath .= $name . "/";
            if (!file_exists($MLogPath)) {
                mkdir($MLogPath);
            }
        }
    }

    $pre_utf_header = "";
    if (!file_exists($MLogPath . $MLogTitle))
        $pre_utf_header = "\xEF\xBB\xBF";
    $fp = fopen($MLogPath . $MLogTitle, "a+");
    $msg = $pid . "\t" . $time . "\t" . $ip . "\t" . $msg . "\r\n";
    fwrite($fp, $pre_utf_header . $msg);
    fflush($fp);
    fclose($fp);
}