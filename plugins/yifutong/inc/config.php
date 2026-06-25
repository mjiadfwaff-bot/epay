<?php
$yft_config = array (
	//接口地址
	'apiUrl' => rtrim($channel['appurl'], '/') ?: 'https://cqapi.cqepay.com',

	//商户号
	'mchNo' => trim($channel['appid']),

	//商户密钥
	'key' => trim($channel['appkey']),

	//通道ID
	'appId' => trim($channel['appsecret']),

	//预留信息
	'apiInfo' => trim($channel['appmchid']),

	//日志记录位置（留空则不记录日志）
	'log_path' => dirname(__FILE__).'/log/',
);
