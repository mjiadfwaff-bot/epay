<?php

class guomei_plugin
{
	static public $info = [
		'name'        => 'guomei',
		'showname'    => '国美聚合支付',
		'author'      => 'guomei',
		'link'        => '',
		'types'       => ['alipay','wxpay'],
		'transtypes'  => [],
		'inputs' => [
			'appurl' => [
				'name' => '接口地址',
				'type' => 'input',
				'note' => '必须以http://或https://开头，不要以/结尾。例如：http://pay.guomei.click',
			],
			'appid' => [
				'name' => '商户ID(mchId)',
				'type' => 'input',
				'note' => '例如：20001222',
			],
			'appkey' => [
				'name' => '商户密钥(key)',
				'type' => 'input',
				'note' => '用于签名与回调验签',
			],
			'appmchid' => [
				'name' => '支付宝产品ID(productId)',
				'type' => 'input',
				'note' => '支付宝支付对应产品ID，如 8000',
			],
			'appswitch' => [
				'name' => '微信产品ID(productId)',
				'type' => 'input',
				'note' => '微信支付对应产品ID，如 8001',
			],
		],
		'select' => null,
		'note' => '请按国美接口文档填写商户ID、商户密钥与产品ID',
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
		return strtoupper($sign) === self::make_sign($params, $key);
	}

	//写日志
	static private function writeLog($text, $config = null){
		if($config === null){
			global $guomei_config;
			$config = $guomei_config;
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
		if(!isset($result['retCode']) || strtoupper($result['retCode']) !== 'SUCCESS'){
			$errMsg = isset($result['retMsg']) ? $result['retMsg'] : '请求失败';
			self::writeLog("[错误] {$errMsg}", $config);
			throw new Exception($errMsg);
		}
		return $result;
	}

	//统一下单
	static private function createOrder($productId){
		global $siteurl, $order, $conf;

		require(PAY_ROOT."inc/config.php");

		self::writeLog("====== 创建订单 productId={$productId} ======", $guomei_config);

		$param = [
			'mchId' => $guomei_config['mchId'],
			'productId' => $productId,
			'mchOrderNo' => TRADE_NO,
			'amount' => intval(round($order['realmoney'] * 100)),
			'notifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
			'returnUrl' => $siteurl.'pay/return/'.TRADE_NO.'/',
		];

		//生成签名
		$param['sign'] = self::make_sign($param, $guomei_config['key']);

		$apiUrl = $guomei_config['apiUrl'].'/api/pay/create_order';
		$config = $guomei_config;

		return \lib\Payment::lockPayData(TRADE_NO, function() use($apiUrl, $param, $config) {
			$result = self::api_post($apiUrl, $param, $config);

			if(isset($result['sign']) && !self::verify_sign($result, $config['key'])){
				throw new Exception('响应签名验证失败');
			}

			$payOrderId = isset($result['payOrderId']) ? $result['payOrderId'] : '';
			if($payOrderId){
				\lib\Payment::updateOrder(TRADE_NO, $payOrderId);
			}

			$payParams = isset($result['payParams']) ? $result['payParams'] : null;
			if(is_string($payParams)){
				$tmp = json_decode($payParams, true);
				if(is_array($tmp)) $payParams = $tmp;
			}
			if(!is_array($payParams)){
				throw new Exception('未返回支付参数(payParams)');
			}

			$payUrl = isset($payParams['payUrl']) ? $payParams['payUrl'] : '';
			$payMethod = isset($payParams['payMethod']) ? strtolower($payParams['payMethod']) : '';
			if(empty($payUrl)){
				throw new Exception('未返回支付链接(payUrl)');
			}

			// codeImg/codeUrl 返回二维码，其余按跳转处理
			if($payMethod === 'codeimg' || $payMethod === 'codeurl'){
				return ['qrcode', $payUrl];
			}
			if(strpos($payUrl, '<form') !== false || strpos($payUrl, '<html') !== false){
				return ['form', $payUrl];
			}
			if(preg_match('/^https?:\/\//i', $payUrl)){
				return ['jump', $payUrl];
			}
			return ['qrcode', $payUrl];
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
			return ['type'=>'error','msg'=>'请先配置支付宝产品ID(productId)'];
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
			return ['type'=>'error','msg'=>'请先配置微信产品ID(productId)'];
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
		global $order;

		require(PAY_ROOT."inc/config.php");

		self::writeLog("====== 收到支付回调 ======", $guomei_config);

		//支持 application/json 与 x-www-form-urlencoded
		$params = [];
		$rawInput = file_get_contents('php://input');
		if(!empty($rawInput)){
			$jsonData = json_decode($rawInput, true);
			if(is_array($jsonData)){
				$params = $jsonData;
			}
		}
		if(empty($params)){
			$params = $_POST;
		}

		self::writeLog("[回调] 参数: ".json_encode($params, JSON_UNESCAPED_UNICODE), $guomei_config);

		if(empty($params) || !isset($params['mchOrderNo']) || !isset($params['status']) || !isset($params['sign'])){
			self::writeLog("[回调] 参数不完整", $guomei_config);
			return ['type'=>'html','data'=>'fail'];
		}

		if(!self::verify_sign($params, $guomei_config['key'])){
			self::writeLog("[回调] 签名验证失败", $guomei_config);
			return ['type'=>'html','data'=>'fail'];
		}

		$status = isset($params['status']) ? intval($params['status']) : 0;
		if($status == 2 || $status == 3){
			$mchOrderNo = isset($params['mchOrderNo']) ? $params['mchOrderNo'] : '';
			$payOrderId = isset($params['payOrderId']) ? $params['payOrderId'] : '';
			$amount = isset($params['amount']) ? round($params['amount'] / 100, 2) : 0;

			if($mchOrderNo == TRADE_NO && $amount == round($order['realmoney'], 2)){
				processNotify($order, $payOrderId);
				self::writeLog("[回调] 支付成功: 订单={$mchOrderNo}, 金额={$amount}元, 流水={$payOrderId}", $guomei_config);
			}else{
				self::writeLog("[回调] 订单不匹配: 回调订单={$mchOrderNo}, 系统订单=".TRADE_NO.", 回调金额={$amount}, 系统金额=".round($order['realmoney'], 2), $guomei_config);
			}
			return ['type'=>'html','data'=>'success'];
		}

		self::writeLog("[回调] 状态异常: status={$status}", $guomei_config);
		return ['type'=>'html','data'=>'fail'];
	}

	//同步回调
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款（文档未提供退款接口）
	static public function refund($order){
		return ['code'=>-1, 'msg'=>'国美聚合支付暂不支持退款'];
	}
}
