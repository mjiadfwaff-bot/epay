<?php
$aima_config = array (
	//网关地址
	'apiUrl' => rtrim($channel['appurl'], '/'),

	//商户号
	'pay_memberid' => trim($channel['appid']),

	//商户密钥
	'key' => trim($channel['appkey']),

	//回调IP白名单（多个IP用逗号分隔，留空则不验证）
	'notify_ip' => trim($channel['appsecret']),

	//支付宝产品编码
	'alipay_code' => trim($channel['appmchid']),

	//微信产品编码
	'wxpay_code' => trim($channel['appswitch']),

	//日志记录位置（留空则不记录日志）
	'log_path' => dirname(__FILE__).'/log/',
);
