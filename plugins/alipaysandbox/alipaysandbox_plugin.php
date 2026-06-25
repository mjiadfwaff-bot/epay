<?php

/**
 * 支付宝沙箱支付插件
 *
 * 说明：
 * 1. 本插件使用支付宝沙箱环境，用于开发测试
 * 2. 网关地址：https://openapi-sandbox.dl.alipaydev.com/gateway.do
 * 3. WebSocket服务地址：openchannel-sandbox.dl.alipaydev.com
 * 4. 沙箱账号申请：https://open.alipay.com/develop/sandbox/app
 * 5. 本插件复用官方alipay插件的所有代码逻辑，仅修改网关地址
 */

class alipaysandbox_plugin
{
	static public $info = [
		'name'        => 'alipaysandbox',
		'showname'    => '支付宝沙箱支付',
		'author'      => '支付宝',
		'link'        => 'https://open.alipay.com/develop/sandbox/app',
		'types'       => ['alipay'],
		'transtypes'  => ['alipay','bank'],
		'inputs' => [
			'appid' => [
				'name' => '沙箱应用APPID',
				'type' => 'input',
				'note' => '请在沙箱环境中获取',
			],
			'appkey' => [
				'name' => '支付宝公钥',
				'type' => 'textarea',
				'note' => '填错也可以支付成功但会无法回调，如果用公钥证书模式此处留空',
			],
			'appsecret' => [
				'name' => '应用私钥',
				'type' => 'textarea',
				'note' => '请妥善保管',
			],
			'appmchid' => [
				'name' => '卖家支付宝用户ID',
				'type' => 'input',
				'note' => '可留空，默认为商户签约账号',
			],
		],
		'select' => [
			'1' => '电脑网站支付',
			'2' => '手机网站支付',
			'3' => '当面付扫码',
			'4' => '当面付JS',
			'5' => '预授权支付',
			'6' => 'APP支付',
			'7' => 'JSAPI支付',
			'8' => '订单码支付',
		],
		'note' => '<p><font color="red">【沙箱环境专用】用于开发测试，不能用于生产环境！</font></p>
<p>沙箱网关：https://openapi-sandbox.dl.alipaydev.com/gateway.do</p>
<p>WebSocket服务：openchannel-sandbox.dl.alipaydev.com</p>
<p>选择可用的接口，只能选择已经签约的产品，否则会无法支付！</p>
<p>如果使用公钥证书模式，需将<font color="red">应用公钥证书、支付宝公钥证书、支付宝根证书</font>3个crt文件放置于<font color="red">/plugins/alipaysandbox/cert/</font>文件夹（或<font color="red">/plugins/alipaysandbox/cert/应用APPID/</font>文件夹）</p>',
		'bindwxmp' => false,
		'bindwxa' => false,
	];

	//写日志
	static private function writeLog($text){
		$log_path = dirname(__FILE__).'/inc/log/';
		if(!is_dir($log_path)){
			@mkdir($log_path, 0755, true);
		}
		$logFile = $log_path . date('Y-m-d') . '.log';
		file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $text . "\n", FILE_APPEND);
	}

	//确保加载alipay插件
	static private function loadAlipayPlugin(){
		if(!class_exists('alipay_plugin')){
			$alipay_plugin_file = PLUGIN_ROOT.'alipay/alipay_plugin.php';
			if(file_exists($alipay_plugin_file)){
				require_once($alipay_plugin_file);
				self::writeLog("[加载] 成功加载 alipay_plugin.php");
			}else{
				self::writeLog("[错误] alipay_plugin.php 文件不存在: {$alipay_plugin_file}");
				throw new Exception('支付宝插件文件不存在，请确保已安装 alipay 插件');
			}
		}
	}

	static public function submit(){
		try{
			self::loadAlipayPlugin();
			self::writeLog("[调用] submit()");
			return alipay_plugin::submit();
		}catch(Exception $e){
			self::writeLog("[异常] submit() - ".$e->getMessage());
			return ['type'=>'error','msg'=>'支付宝沙箱插件调用失败：'.$e->getMessage()];
		}
	}

	static public function mapi(){
		try{
			self::loadAlipayPlugin();
			self::writeLog("[调用] mapi()");
			return alipay_plugin::mapi();
		}catch(Exception $e){
			self::writeLog("[异常] mapi() - ".$e->getMessage());
			return ['type'=>'error','msg'=>'支付宝沙箱插件调用失败：'.$e->getMessage()];
		}
	}

	static private function handleExtUser(&$bizContent){
		self::loadAlipayPlugin();
		return alipay_plugin::handleExtUser($bizContent);
	}

	static public function qrcodepc(){
		try{
			self::loadAlipayPlugin();
			self::writeLog("[调用] qrcodepc()");
			return alipay_plugin::qrcodepc();
		}catch(Exception $e){
			self::writeLog("[异常] qrcodepc() - ".$e->getMessage());
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
	}

	static public function submitpc(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::submitpc();
		}catch(Exception $e){
			self::writeLog("[异常] submitpc() - ".$e->getMessage());
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
	}

	static public function submitwap(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::submitwap();
		}catch(Exception $e){
			self::writeLog("[异常] submitwap() - ".$e->getMessage());
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
	}

	static public function qrcode(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::qrcode();
		}catch(Exception $e){
			self::writeLog("[异常] qrcode() - ".$e->getMessage());
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
	}

	static public function apppay(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::apppay();
		}catch(Exception $e){
			self::writeLog("[异常] apppay() - ".$e->getMessage());
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
	}

	static public function preauth(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::preauth();
		}catch(Exception $e){
			self::writeLog("[异常] preauth() - ".$e->getMessage());
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
	}

	static public function jspay(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::jspay();
		}catch(Exception $e){
			self::writeLog("[异常] jspay() - ".$e->getMessage());
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
	}

	static public function jsapipay(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::jsapipay();
		}catch(Exception $e){
			self::writeLog("[异常] jsapipay() - ".$e->getMessage());
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
	}

	static public function alipaymini(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::alipaymini();
		}catch(Exception $e){
			self::writeLog("[异常] alipaymini() - ".$e->getMessage());
			exit(json_encode(['code'=>-1, 'msg'=>$e->getMessage()]));
		}
	}

	static public function minipay(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::minipay();
		}catch(Exception $e){
			self::writeLog("[异常] minipay() - ".$e->getMessage());
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
	}

	static public function scanpay(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::scanpay();
		}catch(Exception $e){
			self::writeLog("[异常] scanpay() - ".$e->getMessage());
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
	}

	static public function ok(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::ok();
		}catch(Exception $e){
			self::writeLog("[异常] ok() - ".$e->getMessage());
			return ['type'=>'page','page'=>'ok'];
		}
	}

	static public function notify(){
		try{
			self::loadAlipayPlugin();
			self::writeLog("[回调] notify() - POST数据: ".json_encode($_POST, JSON_UNESCAPED_UNICODE));
			return alipay_plugin::notify();
		}catch(Exception $e){
			self::writeLog("[异常] notify() - ".$e->getMessage());
			return ['type'=>'html','data'=>'fail'];
		}
	}

	static public function return(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::return();
		}catch(Exception $e){
			self::writeLog("[异常] return() - ".$e->getMessage());
			return ['type'=>'error','msg'=>$e->getMessage()];
		}
	}

	static public function preauthnotify(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::preauthnotify();
		}catch(Exception $e){
			self::writeLog("[异常] preauthnotify() - ".$e->getMessage());
			return ['type'=>'html','data'=>'fail'];
		}
	}

	static public function refund($order){
		try{
			self::loadAlipayPlugin();
			self::writeLog("[退款] refund() - 订单号: ".$order['trade_no']);
			return alipay_plugin::refund($order);
		}catch(Exception $e){
			self::writeLog("[异常] refund() - ".$e->getMessage());
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	static public function close($order){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::close($order);
		}catch(Exception $e){
			self::writeLog("[异常] close() - ".$e->getMessage());
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	static public function transfer($channel, $bizParam){
		try{
			self::loadAlipayPlugin();
			self::writeLog("[转账] transfer() - 商户订单号: ".$bizParam['out_biz_no']);
			return alipay_plugin::transfer($channel, $bizParam);
		}catch(Exception $e){
			self::writeLog("[异常] transfer() - ".$e->getMessage());
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	static public function transfer_query($channel, $bizParam){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::transfer_query($channel, $bizParam);
		}catch(Exception $e){
			self::writeLog("[异常] transfer_query() - ".$e->getMessage());
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	static public function transfer_proof($channel, $bizParam){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::transfer_proof($channel, $bizParam);
		}catch(Exception $e){
			self::writeLog("[异常] transfer_proof() - ".$e->getMessage());
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	static public function balance_query($channel, $bizParam){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::balance_query($channel, $bizParam);
		}catch(Exception $e){
			self::writeLog("[异常] balance_query() - ".$e->getMessage());
			return ['code'=>-1, 'msg'=>$e->getMessage()];
		}
	}

	static public function signnotify(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::signnotify();
		}catch(Exception $e){
			self::writeLog("[异常] signnotify() - ".$e->getMessage());
			return ['type'=>'html','data'=>'check sign fail'];
		}
	}

	static public function appgw(){
		try{
			self::loadAlipayPlugin();
			return alipay_plugin::appgw();
		}catch(Exception $e){
			self::writeLog("[异常] appgw() - ".$e->getMessage());
			return ['type'=>'html','data'=>'check sign fail'];
		}
	}
}
