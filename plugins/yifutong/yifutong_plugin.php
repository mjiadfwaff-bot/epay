<?php

class yifutong_plugin
{
	static public $info = [
		'name'        => 'yifutong', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '易付通', //支付插件显示名称
		'author'      => '易付通', //支付插件作者
		'link'        => '', //支付插件作者链接
		'types'       => ['alipay','wxpay'], //支付插件支持的支付方式
		'transtypes'  => ['alipay','wxpay'], //支持的转账方式
		'inputs' => [
			'appurl' => [
				'name' => '接口地址',
				'type' => 'input',
				'note' => '必须以https://开头，以/结尾。默认：https://cqapi.cqepay.com/',
			],
			'appid' => [
				'name' => '商户号(mchNo)',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '商户密钥',
				'type' => 'input',
				'note' => '',
			],
			'appsecret' => [
				'name' => '通道ID(appId)',
				'type' => 'input',
				'note' => '',
			],
			'appmchid' => [
				'name' => '预留信息(apiInfo)',
				'type' => 'input',
				'note' => '',
			],
		],
		'select' => null,
		'note' => '请在易付通管理平台获取商户号、通道ID和商户密钥', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	//MD5签名生成（参数按ASCII排序，拼接&key=密钥，MD5后转大写）
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
			global $yft_config;
			$config = $yft_config;
		}
		if(empty($config['log_path'])) return;
		if(!is_dir($config['log_path'])){
			@mkdir($config['log_path'], 0755, true);
		}
		$logFile = $config['log_path'] . date('Y-m-d') . '.log';
		file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $text . "\n", FILE_APPEND);
	}

	//发送API请求
	static private function api_post($url, $params, $config = null){
		$jsonParams = json_encode($params, JSON_UNESCAPED_UNICODE);
		self::writeLog("请求: {$url}\n参数: {$jsonParams}", $config);

		$response = get_curl($url, $jsonParams, 0, 0, 0, 0, 0, ['Content-Type: application/json']);

		self::writeLog("响应: {$response}", $config);

		$result = json_decode($response, true);
		if(!$result){
			throw new Exception('接口返回数据解析失败，原始响应：'.substr($response, 0, 500));
		}
		if(!isset($result['code']) || $result['code'] != 0){
			$errMsg = '';
			if(isset($result['msg']) && $result['msg']){
				$errMsg = $result['msg'];
			}
			if(isset($result['data']['errMsg']) && $result['data']['errMsg']){
				$errMsg .= '['.$result['data']['errMsg'].']';
			}
			if(empty($errMsg)){
				$errMsg = '请求失败，返回：'.json_encode($result, JSON_UNESCAPED_UNICODE);
			}
			throw new Exception($errMsg);
		}
		return isset($result['data']) ? $result['data'] : null;
	}

	//商户统一下单（简易接口 infoPayOrderApi）
	static private function payOrder($ifCode){
		global $siteurl, $channel, $order, $ordername, $conf;

		require(PAY_ROOT."inc/config.php");

		$param = [
			'mchNo' => $yft_config['mchNo'],
			'amount' => intval(round($order['realmoney'] * 100)),
			'ifCode' => $ifCode,
			'apiInfo' => $yft_config['apiInfo'],
			'mchOrderNo' => TRADE_NO,
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'subject' => $ordername,
			'body' => $ordername,
		];

		$apiUrl = $yft_config['apiUrl'].'/api/pay/infoPayOrderApi';
		$config = $yft_config;

		return \lib\Payment::lockPayData(TRADE_NO, function() use($apiUrl, $param, $config){
			$data = self::api_post($apiUrl, $param, $config);

			//优先使用 qrUrl（真正的二维码链接）
			if(isset($data['qrUrl']) && !empty($data['qrUrl'])){
				return ['qrcode', $data['qrUrl']];
			}

			//处理 originalResponse 包裹的情况
			if(isset($data['originalResponse'])){
				$innerData = $data['originalResponse'];
			}else{
				$innerData = $data;
			}

			$payDataType = isset($innerData['payDataType']) ? $innerData['payDataType'] : '';
			$payData = isset($innerData['payData']) ? $innerData['payData'] : '';

			if(empty($payData)){
				$errMsg = isset($innerData['errMsg']) ? $innerData['errMsg'] : '未返回支付数据';
				throw new Exception($errMsg);
			}

			switch($payDataType){
				case 'payUrl':
					return ['jump', $payData];
				case 'form':
					return ['form', $payData];
				case 'codeUrl':
					return ['qrcode', $payData];
				case 'codeImgUrl':
					//二维码图片地址，直接跳转显示
					return ['qrcode_img', $payData];
				default:
					return ['jump', $payData];
			}
		});
	}

	//通道统一下单（完整接口 unifiedOrderApi，需要签名）
	static private function unifiedOrder($wayCode, $channelExtra = null){
		global $siteurl, $channel, $order, $ordername, $conf, $clientip;

		require(PAY_ROOT."inc/config.php");

		$param = [
			'mchNo' => $yft_config['mchNo'],
			'appId' => $yft_config['appId'],
			'mchOrderNo' => TRADE_NO,
			'wayCode' => $wayCode,
			'amount' => intval(round($order['realmoney'] * 100)),
			'currency' => 'cny',
			'clientIp' => $clientip,
			'subject' => $ordername,
			'body' => $ordername,
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'returnUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
			'reqTime' => strval(intval(microtime(true) * 1000)),
			'version' => '1.0',
			'signType' => 'MD5',
			'apiInfo' => $yft_config['apiInfo'],
		];
		if($channelExtra){
			$param['channelExtra'] = json_encode($channelExtra);
		}
		$param['sign'] = self::make_sign($param, $yft_config['key']);

		$apiUrl = $yft_config['apiUrl'].'/api/pay/unifiedOrderApi';
		$config = $yft_config;

		return \lib\Payment::lockPayData(TRADE_NO, function() use($apiUrl, $param, $config){
			$data = self::api_post($apiUrl, $param, $config);

			$payDataType = isset($data['payDataType']) ? $data['payDataType'] : '';
			$payData = isset($data['payData']) ? $data['payData'] : '';

			if(empty($payData)){
				$errMsg = isset($data['errMsg']) ? $data['errMsg'] : '未返回支付数据';
				throw new Exception($errMsg);
			}

			switch($payDataType){
				case 'payUrl':
					return ['jump', $payData];
				case 'form':
					return ['form', $payData];
				case 'codeUrl':
				case 'codeImgUrl':
					return ['qrcode', $payData];
				case 'wxapp':
				case 'aliapp':
					return ['jsapi', $payData];
				default:
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
		global $mdevice;

		try{
			list($method, $url) = self::payOrder('alipay');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝下单失败：'.$ex->getMessage()];
		}

		if($method == 'jump'){
			return ['type'=>'jump','url'=>$url];
		}elseif($method == 'form'){
			return ['type'=>'html','data'=>$url];
		}elseif($method == 'qrcode_img'){
			//二维码图片地址，直接展示图片
			return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$url];
		}else{
			//qrcode - 真正的二维码链接
			if(checkalipay() || $mdevice == 'alipay'){
				return ['type'=>'jump','url'=>$url];
			}else{
				return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$url];
			}
		}
	}

	//微信支付
	static public function wxpay(){
		global $device, $mdevice;

		try{
			list($method, $url) = self::payOrder('wxpay');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败：'.$ex->getMessage()];
		}

		if($method == 'jump'){
			return ['type'=>'jump','url'=>$url];
		}elseif($method == 'form'){
			return ['type'=>'html','data'=>$url];
		}elseif($method == 'qrcode_img'){
			//二维码图片地址
			if(checkmobile() || $device == 'mobile'){
				return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$url];
			}else{
				return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$url];
			}
		}else{
			//qrcode - 真正的二维码链接
			if(checkwechat() || $mdevice == 'wechat'){
				return ['type'=>'jump','url'=>$url];
			}elseif(checkmobile() || $device == 'mobile'){
				return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$url];
			}else{
				return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$url];
			}
		}
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		require(PAY_ROOT."inc/config.php");

		//获取回调数据（兼容 form-urlencoded 和 JSON 格式）
		$params = $_POST;
		if(empty($params)){
			$raw = file_get_contents('php://input');
			$params = json_decode($raw, true);
		}
		if(empty($params)){
			return ['type'=>'html','data'=>'fail'];
		}

		//验证签名
		if(!self::verify_sign($params, $yft_config['key'])){
			return ['type'=>'html','data'=>'fail'];
		}

		//检查支付状态 state=2 表示支付成功
		$state = isset($params['state']) ? intval($params['state']) : 0;
		if($state == 2){
			$mchOrderNo = isset($params['mchOrderNo']) ? $params['mchOrderNo'] : '';
			$payOrderId = isset($params['payOrderId']) ? $params['payOrderId'] : '';
			//回调金额单位是分，转为元进行比对
			$amount = isset($params['amount']) ? round($params['amount'] / 100, 2) : 0;

			if($mchOrderNo == TRADE_NO && $amount == round($order['realmoney'], 2)){
				processNotify($order, $payOrderId);
			}
			return ['type'=>'html','data'=>'success'];
		}

		return ['type'=>'html','data'=>'fail'];
	}

	//同步回调
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		require(PLUGIN_ROOT.'yifutong/inc/config.php');

		$param = [
			'mchNo' => $yft_config['mchNo'],
			'appId' => $yft_config['appId'],
			'mchOrderNo' => $order['trade_no'],
			'mchRefundNo' => $order['refund_no'],
			'refundAmount' => intval(round($order['refundmoney'] * 100)),
			'currency' => 'cny',
			'refundReason' => '商户退款',
			'reqTime' => strval(intval(microtime(true) * 1000)),
			'version' => '1.0',
			'signType' => 'MD5',
			'apiInfo' => $yft_config['apiInfo'],
		];
		if(!empty($order['api_trade_no'])){
			$param['payOrderId'] = $order['api_trade_no'];
		}
		$param['sign'] = self::make_sign($param, $yft_config['key']);

		$apiUrl = $yft_config['apiUrl'].'/api/refund/refundOrderApi';

		try{
			self::api_post($apiUrl, $param);
			return ['code'=>0];
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//转账
	static public function transfer($channel, $bizParam){
		global $conf, $clientip;
		if(empty($channel) || empty($bizParam))exit();

		require(PLUGIN_ROOT.'yifutong/inc/config.php');

		//确定入账方式
		if($bizParam['type'] == 'alipay'){
			$ifCode = 'alipay';
			$entryType = 'ALIPAY_CASH';
		}else{
			$ifCode = 'wxpay';
			$entryType = 'WX_CASH';
		}

		$param = [
			'mchNo' => $yft_config['mchNo'],
			'appId' => $yft_config['appId'],
			'mchOrderNo' => $bizParam['out_biz_no'],
			'ifCode' => $ifCode,
			'entryType' => $entryType,
			'amount' => intval(round($bizParam['money'] * 100)),
			'currency' => 'cny',
			'accountNo' => $bizParam['payee_account'],
			'accountName' => isset($bizParam['payee_real_name']) ? $bizParam['payee_real_name'] : '',
			'transferDesc' => isset($bizParam['transfer_desc']) ? $bizParam['transfer_desc'] : '转账',
			'clientIp' => $clientip,
			'notifyUrl' => $conf['localurl'].'pay/transfernotify/'.$channel['id'].'/',
			'reqTime' => strval(intval(microtime(true) * 1000)),
			'version' => '1.0',
			'signType' => 'MD5',
			'apiInfo' => $yft_config['apiInfo'],
		];
		$param['sign'] = self::make_sign($param, $yft_config['key']);

		$apiUrl = $yft_config['apiUrl'].'/api/transferOrder';

		try{
			$data = self::api_post($apiUrl, $param);
			$state = isset($data['state']) ? intval($data['state']) : 0;
			$transferId = isset($data['transferId']) ? $data['transferId'] : '';
			//state: 0-订单生成 1-转账中 2-转账成功 3-转账失败
			if($state == 2){
				return ['code'=>0, 'status'=>1, 'orderid'=>$transferId, 'paydate'=>date('Y-m-d H:i:s')];
			}elseif($state == 3){
				$errMsg = isset($data['errMsg']) ? $data['errMsg'] : '转账失败';
				return ['code'=>-1, 'msg'=>$errMsg];
			}else{
				return ['code'=>0, 'status'=>0, 'orderid'=>$transferId, 'paydate'=>date('Y-m-d H:i:s')];
			}
		}catch(Exception $ex){
			return ['code'=>-1, 'msg'=>$ex->getMessage()];
		}
	}

	//转账异步回调
	static public function transfernotify(){
		global $channel;

		require(PAY_ROOT."inc/config.php");

		//获取回调数据
		$params = $_POST;
		if(empty($params)){
			$raw = file_get_contents('php://input');
			$params = json_decode($raw, true);
		}
		if(empty($params)){
			return ['type'=>'html','data'=>'fail'];
		}

		//验证签名
		if(!self::verify_sign($params, $yft_config['key'])){
			return ['type'=>'html','data'=>'fail'];
		}

		$mchOrderNo = isset($params['mchOrderNo']) ? $params['mchOrderNo'] : '';
		$state = isset($params['state']) ? intval($params['state']) : 0;

		//state: 0-订单生成 1-转账中 2-转账成功 3-转账失败
		if($state == 2){
			processTransfer($mchOrderNo, 1, null);
			return ['type'=>'html','data'=>'success'];
		}elseif($state == 3){
			$errMsg = isset($params['errMsg']) ? $params['errMsg'] : '转账失败';
			processTransfer($mchOrderNo, 2, $errMsg);
			return ['type'=>'html','data'=>'success'];
		}

		return ['type'=>'html','data'=>'fail'];
	}
}
