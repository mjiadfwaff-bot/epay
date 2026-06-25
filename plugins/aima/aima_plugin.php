<?php

class aima_plugin
{
	static public $info = [
		'name'        => 'aima', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '艾玛支付', //支付插件显示名称
		'author'      => '艾玛支付', //支付插件作者
		'link'        => '', //支付插件作者链接
		'types'       => ['alipay','wxpay'], //支付插件支持的支付方式
		'transtypes'  => [], //支持的转账方式（暂不支持）
		'inputs' => [
			'appurl' => [
				'name' => '网关地址',
				'type' => 'input',
				'note' => '必须以http://或https://开头，不要以/结尾。例如：http://xxx.itxt002.xyz',
			],
			'appid' => [
				'name' => '商户号(pay_memberid)',
				'type' => 'input',
				'note' => '例如：M200025',
			],
			'appkey' => [
				'name' => '商户秘钥',
				'type' => 'input',
				'note' => '用于签名验证',
			],
			'appsecret' => [
				'name' => '回调IP白名单',
				'type' => 'input',
				'note' => '多个IP用英文逗号分隔，留空则不验证。例如：34.92.216.115',
			],
			'appmchid' => [
				'name' => '支付宝产品编码(pay_bankcode)',
				'type' => 'input',
				'note' => '支付宝支付时使用的产品编码，请在商户后台查看',
			],
			'appswitch' => [
				'name' => '微信产品编码(pay_bankcode)',
				'type' => 'input',
				'note' => '微信支付时使用的产品编码，请在商户后台查看',
			],
		],
		'select' => null,
		'note' => '请在艾玛支付商户后台获取商户号和商户秘钥，并配置对应的支付产品编码',
		'bindwxmp' => false,
		'bindwxa' => false,
	];

	//MD5签名生成（只对文档指定的字段签名，按ASCII排序，拼接&key=密钥，MD5后转大写）
	static private function make_sign($params, $key){
		//下单参与签名的字段
		$signFields = [
			'pay_memberid', 'pay_orderid', 'pay_applydate',
			'pay_bankcode', 'pay_notifyurl', 'pay_callbackurl', 'pay_amount',
		];
		$filtered = [];
		foreach($params as $k => $v){
			if(in_array($k, $signFields) && $v !== '' && $v !== null){
				$filtered[$k] = $v;
			}
		}
		ksort($filtered);
		$signStr = '';
		foreach($filtered as $k => $v){
			$signStr .= $k.'='.$v.'&';
		}
		$signStr .= 'key='.$key;
		return strtoupper(md5($signStr));
	}

	//回调签名验证
	static private function verify_sign($params, $key){
		if(empty($params['sign'])) return false;
		$sign = $params['sign'];
		//回调参与签名的字段
		$signFields = [
			'memberid', 'orderid', 'amount',
			'transaction_id', 'datetime', 'returncode',
		];
		$filtered = [];
		foreach($params as $k => $v){
			if(in_array($k, $signFields) && $v !== '' && $v !== null){
				$filtered[$k] = $v;
			}
		}
		ksort($filtered);
		$signStr = '';
		foreach($filtered as $k => $v){
			$signStr .= $k.'='.$v.'&';
		}
		$signStr .= 'key='.$key;
		return $sign === strtoupper(md5($signStr));
	}

	//写日志（和yifutong完全一致）
	static private function writeLog($text, $config = null){
		if($config === null){
			global $aima_config;
			$config = $aima_config;
		}
		if(empty($config['log_path'])) return;
		if(!is_dir($config['log_path'])){
			@mkdir($config['log_path'], 0755, true);
		}
		$logFile = $config['log_path'] . date('Y-m-d') . '.log';
		file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $text . "\n", FILE_APPEND);
	}

	//发送API请求（POST form-urlencoded）
	static private function api_post($url, $params, $config = null){
		$postData = http_build_query($params);
		self::writeLog("[请求] URL: {$url}", $config);
		self::writeLog("[请求] 参数: ".json_encode($params, JSON_UNESCAPED_UNICODE), $config);

		$response = get_curl($url, $postData, 0, 0, 0, 0, 0, ['Content-Type: application/x-www-form-urlencoded']);

		self::writeLog("[响应] {$response}", $config);

		$result = json_decode($response, true);
		if(!$result){
			self::writeLog("[错误] 响应解析失败", $config);
			throw new Exception('接口返回数据解析失败，原始响应：'.substr($response, 0, 500));
		}
		return $result;
	}

	//创建支付订单（和yifutong的payOrder结构完全一致）
	static private function createOrder($pay_bankcode){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/config.php");

		self::writeLog("====== 创建订单 pay_bankcode={$pay_bankcode} ======", $aima_config);

		$param = [
			'pay_memberid' => $aima_config['pay_memberid'],
			'pay_orderid' => TRADE_NO,
			'pay_applydate' => date('Y-m-d H:i:s'),
			'pay_bankcode' => $pay_bankcode,
			'pay_notifyurl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'pay_callbackurl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'pay_amount' => sprintf('%.2f', $order['realmoney']),
			'pay_productname' => $ordername,
			'pay_ip' => $clientip,
		];

		//生成签名（只对参与签名的字段签名）
		$param['pay_md5sign'] = self::make_sign($param, $aima_config['key']);

		$apiUrl = rtrim($aima_config['apiUrl'], '/').'/Pay_Index.html';
		$config = $aima_config;

		return \lib\Payment::lockPayData(TRADE_NO, function() use($apiUrl, $param, $config){
			$data = self::api_post($apiUrl, $param, $config);

			//检查返回状态
			if(!isset($data['status']) || $data['status'] != 1){
				$errMsg = isset($data['msg']) ? $data['msg'] : '下单失败';
				throw new Exception($errMsg);
			}

			//获取支付链接（优先h5_url，其次pay_url，最后sdk_url）
			$payUrl = '';
			if(!empty($data['h5_url'])){
				$payUrl = $data['h5_url'];
			}elseif(!empty($data['pay_url'])){
				$payUrl = $data['pay_url'];
			}elseif(!empty($data['sdk_url'])){
				$payUrl = $data['sdk_url'];
			}

			if(empty($payUrl)){
				throw new Exception('未返回支付链接');
			}

			//判断返回类型
			if(strpos($payUrl, '<form') !== false || strpos($payUrl, '<html') !== false){
				return ['form', $payUrl];
			}elseif(strpos($payUrl, 'qr.alipay.com') !== false || strpos($payUrl, 'qpay.qq.com') !== false){
				return ['qrcode', $payUrl];
			}else{
				return ['jump', $payUrl];
			}
		});
	}

	//跳转支付入口
	static public function submit(){
		global $order;
		return ['type'=>'jump','url'=>'/pay/'.$order['typename'].'/'.TRADE_NO.'/'];
	}

	//API接口支付入口
	static public function mapi(){
		global $order;
		$typename = $order['typename'];
		return self::$typename();
	}

	//支付宝支付（和yifutong的alipay结构完全一致）
	static public function alipay(){
		global $mdevice, $channel;

		//从channel直接获取支付宝编码
		$pay_bankcode = trim($channel['appmchid']);
		if(empty($pay_bankcode)){
			return ['type'=>'error','msg'=>'请先配置支付宝产品编码(pay_bankcode)'];
		}

		try{
			list($method, $url) = self::createOrder($pay_bankcode);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败：'.$ex->getMessage()];
		}

		if($method == 'jump'){
			return ['type'=>'jump','url'=>$url];
		}elseif($method == 'form'){
			return ['type'=>'html','data'=>$url];
		}else{
			if(checkalipay() || $mdevice == 'alipay'){
				return ['type'=>'jump','url'=>$url];
			}else{
				return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$url];
			}
		}
	}

	//微信支付（和yifutong的wxpay结构完全一致）
	static public function wxpay(){
		global $device, $mdevice, $channel;

		//从channel直接获取微信编码
		$pay_bankcode = trim($channel['appswitch']);
		if(empty($pay_bankcode)){
			return ['type'=>'error','msg'=>'请先配置微信产品编码(pay_bankcode)'];
		}

		try{
			list($method, $url) = self::createOrder($pay_bankcode);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败：'.$ex->getMessage()];
		}

		if($method == 'jump'){
			return ['type'=>'jump','url'=>$url];
		}elseif($method == 'form'){
			return ['type'=>'html','data'=>$url];
		}else{
			if(checkwechat() || $mdevice == 'wechat'){
				return ['type'=>'jump','url'=>$url];
			}elseif(checkmobile() || $device == 'mobile'){
				return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$url];
			}else{
				return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$url];
			}
		}
	}

	//异步回调（和yifutong的notify结构完全一致）
	static public function notify(){
		global $channel, $order;

		require(PAY_ROOT."inc/config.php");

		self::writeLog("====== 收到支付回调 ======", $aima_config);

		//获取回调数据
		$params = $_POST;
		if(empty($params)){
			self::writeLog("[回调] 未接收到POST数据", $aima_config);
			return ['type'=>'html','data'=>'FAIL'];
		}

		self::writeLog("[回调] 数据: ".json_encode($params, JSON_UNESCAPED_UNICODE), $aima_config);

		//验证回调IP
		if(!empty($aima_config['notify_ip'])){
			$allowedIps = array_map('trim', explode(',', $aima_config['notify_ip']));
			$clientIp = $_SERVER['REMOTE_ADDR'];
			if(!in_array($clientIp, $allowedIps)){
				self::writeLog("[回调] IP验证失败: {$clientIp}", $aima_config);
				return ['type'=>'html','data'=>'FAIL'];
			}
		}

		//验证签名
		if(!self::verify_sign($params, $aima_config['key'])){
			self::writeLog("[回调] 签名验证失败", $aima_config);
			return ['type'=>'html','data'=>'FAIL'];
		}

		//检查支付状态 returncode=00 表示支付成功
		$returncode = isset($params['returncode']) ? $params['returncode'] : '';
		if($returncode == '00'){
			$orderid = isset($params['orderid']) ? $params['orderid'] : '';
			$transaction_id = isset($params['transaction_id']) ? $params['transaction_id'] : '';
			$amount = isset($params['amount']) ? floatval($params['amount']) : 0;

			if($orderid == TRADE_NO && $amount == round($order['realmoney'], 2)){
				processNotify($order, $transaction_id);
				self::writeLog("[回调] 支付成功: 订单={$orderid}, 金额={$amount}, 流水={$transaction_id}", $aima_config);
			}else{
				self::writeLog("[回调] 订单不匹配: 回调订单={$orderid}, 系统订单=".TRADE_NO.", 回调金额={$amount}, 系统金额=".round($order['realmoney'], 2), $aima_config);
			}
			return ['type'=>'html','data'=>'OK'];
		}

		self::writeLog("[回调] 状态异常: returncode={$returncode}", $aima_config);
		return ['type'=>'html','data'=>'FAIL'];
	}

	//同步回调
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款（暂不支持）
	static public function refund($order){
		return ['code'=>-1, 'msg'=>'艾玛支付暂不支持退款'];
	}
}
