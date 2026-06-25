<?php

class adi_plugin
{
	static public $info = [
		'name'        => 'adi', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '啊Di支付', //支付插件显示名称
		'author'      => '啊Di支付', //支付插件作者
		'link'        => '', //支付插件作者链接
		'types'       => ['alipay','wxpay'], //支付插件支持的支付方式
		'transtypes'  => [], //支持的转账方式（暂不支持）
		'inputs' => [
			'appurl' => [
				'name' => '接口地址',
				'type' => 'input',
				'note' => '必须以http://或https://开头，不要以/结尾',
			],
			'appid' => [
				'name' => '商户号(mchNo)',
				'type' => 'input',
				'note' => '例如：M17066050245',
			],
			'appkey' => [
				'name' => '商户密钥',
				'type' => 'input',
				'note' => '用于签名验证',
			],
			'appmchid' => [
				'name' => '支付宝产品编码(productId)',
				'type' => 'input',
				'note' => '支付宝支付时使用的产品编码',
			],
			'appswitch' => [
				'name' => '微信产品编码(productId)',
				'type' => 'input',
				'note' => '微信支付时使用的产品编码',
			],
		],
		'select' => null,
		'note' => '请在啊Di支付商户后台获取商户号和商户密钥，并配置对应的产品编码',
		'bindwxmp' => false,
		'bindwxa' => false,
	];

	//MD5签名生成（所有非空参数按ASCII字典序排序，拼接&key=密钥，MD5后转大写）
	static private function make_sign($params, $key){
		$filtered = [];
		foreach($params as $k => $v){
			if($k !== 'sign' && $v !== '' && $v !== null){
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

	//验证签名
	static private function verify_sign($params, $key){
		if(empty($params['sign'])) return false;
		$sign = $params['sign'];
		return $sign === self::make_sign($params, $key);
	}

	//写日志
	static private function writeLog($text, $config = null){
		if($config === null){
			global $adi_config;
			$config = $adi_config;
		}
		if(empty($config['log_path'])) return;
		if(!is_dir($config['log_path'])){
			@mkdir($config['log_path'], 0755, true);
		}
		$logFile = $config['log_path'] . date('Y-m-d') . '.log';
		file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $text . "\n", FILE_APPEND);
	}

	//发送API请求（POST JSON）
	static private function api_post($url, $params, $config = null){
		$jsonParams = json_encode($params, JSON_UNESCAPED_UNICODE);
		self::writeLog("[请求] URL: {$url}", $config);
		self::writeLog("[请求] 参数: {$jsonParams}", $config);

		$response = get_curl($url, $jsonParams, 0, 0, 0, 0, 0, ['Content-Type: application/json']);

		self::writeLog("[响应] {$response}", $config);

		$result = json_decode($response, true);
		if(!$result){
			self::writeLog("[错误] 响应解析失败", $config);
			throw new Exception('接口返回数据解析失败，原始响应：'.substr($response, 0, 500));
		}
		if(!isset($result['code']) || $result['code'] != 0){
			$errMsg = isset($result['msg']) ? $result['msg'] : '请求失败';
			self::writeLog("[错误] {$errMsg}", $config);
			throw new Exception($errMsg);
		}
		return $result;
	}

	//统一下单
	static private function createOrder($productId){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/config.php");

		self::writeLog("====== 创建订单 productId={$productId} ======", $adi_config);

		$param = [
			'mchNo' => $adi_config['mchNo'],
			'mchOrderNo' => TRADE_NO,
			'productId' => $productId,
			'amount' => intval(round($order['realmoney'] * 100)),
			'clientIp' => $clientip,
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'returnUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'reqTime' => intval(microtime(true) * 1000),
		];

		//生成签名
		$param['sign'] = self::make_sign($param, $adi_config['key']);

		$apiUrl = $adi_config['apiUrl'].'/api/pay/unifiedOrder';
		$config = $adi_config;

		return \lib\Payment::lockPayData(TRADE_NO, function() use($apiUrl, $param, $config){
			$result = self::api_post($apiUrl, $param, $config);
			$data = isset($result['data']) ? $result['data'] : [];

			//检查订单状态：1=出码成功, 3=支付失败, 7=出码失败
			$orderState = isset($data['orderState']) ? intval($data['orderState']) : 0;
			if($orderState != 1){
				$errMsg = '出码失败(orderState='.$orderState.')';
				throw new Exception($errMsg);
			}

			//获取支付链接
			$payData = isset($data['payData']) ? $data['payData'] : '';
			if(empty($payData)){
				throw new Exception('未返回支付链接');
			}

			$payDataType = isset($data['payDataType']) ? $data['payDataType'] : 'payUrl';

			//判断返回类型
			if($payDataType == 'form'){
				return ['form', $payData];
			}elseif(strpos($payData, 'qr.alipay.com') !== false || strpos($payData, 'qpay.qq.com') !== false){
				return ['qrcode', $payData];
			}else{
				return ['jump', $payData];
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

	//支付宝支付
	static public function alipay(){
		global $mdevice, $channel;

		$productId = trim($channel['appmchid']);
		if(empty($productId)){
			return ['type'=>'error','msg'=>'请先配置支付宝产品编码(productId)'];
		}

		try{
			list($method, $url) = self::createOrder($productId);
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

	//微信支付
	static public function wxpay(){
		global $device, $mdevice, $channel;

		$productId = trim($channel['appswitch']);
		if(empty($productId)){
			return ['type'=>'error','msg'=>'请先配置微信产品编码(productId)'];
		}

		try{
			list($method, $url) = self::createOrder($productId);
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

	//异步回调通知
	static public function notify(){
		global $channel, $order;

		require(PAY_ROOT."inc/config.php");

		self::writeLog("====== 收到支付回调 ======", $adi_config);

		//获取回调数据（application/x-www-form-urlencoded）
		$params = $_POST;
		if(empty($params)){
			$raw = file_get_contents('php://input');
			//尝试解析为JSON（兼容）
			$jsonParams = json_decode($raw, true);
			if($jsonParams){
				$params = $jsonParams;
			}else{
				//尝试解析为form-urlencoded
				parse_str($raw, $params);
			}
		}
		if(empty($params)){
			self::writeLog("[回调] 未接收到数据", $adi_config);
			return ['type'=>'html','data'=>'fail'];
		}

		self::writeLog("[回调] 数据: ".json_encode($params, JSON_UNESCAPED_UNICODE), $adi_config);

		//验证签名
		if(!self::verify_sign($params, $adi_config['key'])){
			self::writeLog("[回调] 签名验证失败", $adi_config);
			return ['type'=>'html','data'=>'fail'];
		}

		//检查支付状态：state=2 或 state=5 均为支付成功
		$state = isset($params['state']) ? intval($params['state']) : 0;
		if($state == 2 || $state == 5){
			$mchOrderNo = isset($params['mchOrderNo']) ? $params['mchOrderNo'] : '';
			$payOrderId = isset($params['payOrderId']) ? $params['payOrderId'] : '';
			//回调金额单位是分，转为元进行比对
			$amount = isset($params['amount']) ? round($params['amount'] / 100, 2) : 0;

			if($mchOrderNo == TRADE_NO && $amount == round($order['realmoney'], 2)){
				processNotify($order, $payOrderId);
				self::writeLog("[回调] 支付成功: 订单={$mchOrderNo}, 金额={$amount}元, 流水={$payOrderId}", $adi_config);
			}else{
				self::writeLog("[回调] 订单不匹配: 回调订单={$mchOrderNo}, 系统订单=".TRADE_NO.", 回调金额={$amount}, 系统金额=".round($order['realmoney'], 2), $adi_config);
			}
			return ['type'=>'html','data'=>'success'];
		}

		self::writeLog("[回调] 状态异常: state={$state}", $adi_config);
		return ['type'=>'html','data'=>'fail'];
	}

	//同步回调
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款（文档未提供退款接口）
	static public function refund($order){
		return ['code'=>-1, 'msg'=>'啊Di支付暂不支持退款'];
	}
}
