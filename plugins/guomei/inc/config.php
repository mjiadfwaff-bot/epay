<?php
$guomei_config = array (
	//接口地址
	'apiUrl' => rtrim($channel['appurl'], '/'),

	//商户ID（mchId）
	'mchId' => trim($channel['appid']),

	//商户密钥
	'key' => trim($channel['appkey']),

	//支付宝产品ID（productId）
	'alipay_productId' => trim($channel['appmchid']),

	//微信产品ID（productId）
	'wxpay_productId' => trim($channel['appswitch']),

	//日志记录位置（留空则不记录日志）
	'log_path' => dirname(__FILE__).'/log/',
);
