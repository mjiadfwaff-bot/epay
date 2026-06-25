<?php
$adi_config = array (
	//接口地址
	'apiUrl' => rtrim($channel['appurl'], '/'),

	//商户号
	'mchNo' => trim($channel['appid']),

	//商户密钥
	'key' => trim($channel['appkey']),

	//支付宝产品编码(productId)
	'alipay_productId' => trim($channel['appmchid']),

	//微信产品编码(productId)
	'wxpay_productId' => trim($channel['appswitch']),

	//日志记录位置（留空则不记录日志）
	'log_path' => dirname(__FILE__).'/log/',
);
