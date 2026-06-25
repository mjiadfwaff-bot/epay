<?php
/**
 * 艾玛支付签名测试脚本
 * 用于验证签名算法是否正确
 */

// 测试参数
$params = [
    'pay_memberid' => 'M200025',
    'pay_orderid' => 'TEST20240101120000',
    'pay_applydate' => '2024-01-01 12:00:00',
    'pay_bankcode' => 'ALIPAY',
    'pay_notifyurl' => 'http://example.com/notify',
    'pay_callbackurl' => 'http://example.com/return',
    'pay_amount' => '100.00',
];

$key = 'd3659350381a2e6ee070a41430e7bc4d';

// 生成签名
function make_sign($params, $key){
    $filtered = [];
    foreach($params as $k => $v){
        if($k !== 'pay_md5sign' && $k !== 'sign' && $v !== '' && $v !== null){
            $filtered[$k] = $v;
        }
    }
    ksort($filtered);
    $signStr = '';
    foreach($filtered as $k => $v){
        $signStr .= $k.'='.$v.'&';
    }
    $signStr .= 'key='.$key;

    echo "签名前字符串:\n";
    echo $signStr . "\n\n";

    $sign = strtoupper(md5($signStr));

    echo "MD5签名结果:\n";
    echo $sign . "\n\n";

    return $sign;
}

$sign = make_sign($params, $key);

echo "完整请求参数:\n";
$params['pay_md5sign'] = $sign;
print_r($params);
