<?php
$is_defend = true;
$nosession = true;
require './includes/common.php';

@header('Content-Type: text/html; charset=UTF-8');

$other=isset($_GET['other'])?true:false;
$trade_no=daddslashes($_GET['trade_no']);
$sitename=base64_decode(daddslashes($_GET['sitename']));
$row=$DB->getRow("SELECT * FROM pre_order WHERE trade_no='{$trade_no}' limit 1");
if(!$row)sysmsg('该订单号不存在，请返回来源地重新发起请求！');
if($row['status']==1)sysmsg('该订单已完成支付，请勿重复支付');
$gid = $DB->getColumn("SELECT gid FROM pre_user WHERE uid='{$row['uid']}' limit 1");
$paytype = \lib\Channel::getTypes($row['uid'], $gid);
$payMoney = $row['realmoney'] ? $row['realmoney'] : $row['money'];
$feeMoney = ($row['realmoney'] && $row['realmoney'] != $row['money']) ? round($row['realmoney'] - $row['money'], 2) : 0;

if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){
	$paytype = array_values($paytype);
	foreach($paytype as $i=>$s){
		if($s['name']=='wxpay'){
			$temp = $paytype[$i];
			$paytype[$i] = $paytype[0];
			$paytype[0] = $temp;
		}
	}
}
?>
<!DOCTYPE html>
<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=0" name="viewport">
<title>收银台 | <?php echo $sitename?$sitename:$conf['sitename']?> </title>
<link href="/assets/css/reset.css" rel="stylesheet" type="text/css">
<link href="/assets/css/main12.css?v=2" rel="stylesheet" type="text/css">
<style>
body.cashier-modern {
    min-height: 100vh;
    padding-bottom: 36px;
    background: #f4f6f8;
    color: #1f2937;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", Arial, sans-serif;
}
.cashier-modern .w1080 {
    width: min(720px, calc(100% - 28px));
    margin-left: auto;
    margin-right: auto;
}
.cashier-modern .navBD12 {
    border: 0;
    background: #ffffff;
    box-shadow: 0 1px 0 rgba(15, 23, 42, .08);
}
.cashier-modern .nav12 {
    height: 72px;
    padding: 0;
    display: flex;
    align-items: center;
    gap: 18px;
}
.cashier-modern .nav12-left,
.cashier-modern .nav12-right {
    float: none;
    height: auto;
    padding: 0;
    margin: 0;
}
.cashier-modern .nav12-left img {
    max-height: 38px;
    width: auto;
}
.cashier-modern .nav12-right {
    font-size: 24px;
    font-weight: 700;
    color: #111827;
}
.cashier-modern .checkout-card {
    background: #fff;
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 8px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, .06);
    box-sizing: border-box;
}
.cashier-modern .order-amount12 {
    width: min(720px, calc(100% - 28px));
    height: auto;
    padding: 0;
    margin-top: 20px;
    overflow: hidden;
}
.cashier-modern .order-summary-head {
    padding: 24px;
    background: #1f2937;
    color: #fff;
    text-align: center;
}
.cashier-modern .order-summary-head .label {
    display: block;
    color: rgba(255,255,255,.72);
    font-size: 13px;
    margin-bottom: 8px;
}
.cashier-modern .order-summary-head strong {
    font-size: 42px;
    line-height: 1;
    font-weight: 700;
    color: #fff;
}
.cashier-modern .order-summary-head small {
    margin-left: 6px;
    font-size: 16px;
    color: rgba(255,255,255,.8);
}
.cashier-modern .amount-note {
    margin-top: 10px;
    color: rgba(255,255,255,.7);
    font-size: 13px;
}
.cashier-modern .order-amount12-left {
    float: none;
    padding: 18px 24px 20px;
}
.cashier-modern .order-amount12-left li {
    display: grid;
    grid-template-columns: 86px 1fr;
    gap: 14px;
    margin: 0;
    padding: 8px 0;
}
.cashier-modern .order-amount12-left span {
    line-height: 1.45;
    font-size: 14px;
    color: #64748b;
    word-break: break-word;
}
.cashier-modern .order-amount12-left span:last-child {
    color: #1f2937;
}
.cashier-modern .PayMethod12 {
    width: min(720px, calc(100% - 28px));
    padding: 24px;
    margin-top: 16px;
    box-sizing: border-box;
}
.cashier-modern .PayMethod12 .row {
    margin: 0;
}
.cashier-modern .PayMethod12 h2 {
    height: auto;
    line-height: 1.3;
    margin-bottom: 16px;
    font-size: 18px;
    font-weight: 700;
    color: #111827;
}
.cashier-modern .PayMethod12 ul {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    overflow: visible;
}
.cashier-modern .PayMethod12 ul li {
    float: none;
    width: auto;
    height: 56px;
    margin: 0;
    padding: 0 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    border: 1px solid #dbe3ee;
    border-radius: 10px;
    background: #fff;
    box-sizing: border-box;
    cursor: pointer;
    transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
}
.cashier-modern .PayMethod12 ul li:hover {
    border-color: #93a4ba;
}
.cashier-modern .PayMethod12 ul li.active {
    border-color: #2563eb;
    background: #f8fbff;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
}
.cashier-modern .PayMethod12 ul li img {
    float: none;
    width: 26px;
    height: 26px;
    margin: 0;
    object-fit: contain;
}
.cashier-modern .PayMethod12 ul li span {
    float: none;
    color: #111827;
    font-size: 16px;
    font-weight: 600;
}
.cashier-modern .crypto-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    background: #26a17b;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0;
}
.cashier-modern .crypto-usdc {
    background: #2775ca;
}
.cashier-modern .crypto-trx {
    background: #ef0027;
}
.cashier-modern .empty-method {
    margin: 4px 0 0;
    padding: 18px;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    color: #64748b;
    text-align: center;
    font-size: 14px;
}
.cashier-modern .immediate-pay12 {
    width: min(720px, calc(100% - 28px));
    height: auto;
    margin-top: 16px;
    padding: 18px 24px;
    box-sizing: border-box;
}
.cashier-modern .immediate-pay12-right {
    float: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}
.cashier-modern .immediate-pay12-right span {
    float: none;
    margin: 0;
    color: #64748b;
    font-size: 15px;
}
.cashier-modern .immediate-pay12-right span strong {
    font-size: 24px;
    color: #dc2626;
}
.cashier-modern .immediate_pay {
    width: 178px;
    height: 52px;
    line-height: 52px;
    border-radius: 8px;
    background: #2563eb;
    font-size: 17px;
    font-weight: 700;
}
.cashier-modern .immediate_pay:hover {
    color: #fff;
    background: #1d4ed8;
}
.cashier-modern .footer12 {
    margin-top: 34px;
    color: #a3aab7;
}
@media screen and (max-width: 640px) {
    .cashier-modern {
        padding-bottom: 120px;
    }
    .cashier-modern .nav12 {
        height: 64px;
    }
    .cashier-modern .nav12-left {
        margin-left: 14px;
    }
    .cashier-modern .nav12-right {
        font-size: 21px;
        margin-left: 0;
    }
    .cashier-modern .order-summary-head {
        padding: 20px;
    }
    .cashier-modern .order-summary-head strong {
        font-size: 36px;
    }
    .cashier-modern .order-amount12-left {
        padding: 14px 20px 16px;
    }
    .cashier-modern .order-amount12-left li {
        grid-template-columns: 78px 1fr;
        gap: 10px;
    }
    .cashier-modern .PayMethod12 {
        padding: 22px 20px;
    }
    .cashier-modern .PayMethod12 ul {
        grid-template-columns: 1fr;
    }
    .cashier-modern .immediate-pay12 {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 20;
        width: 100%;
        margin: 0;
        padding: 14px max(14px, env(safe-area-inset-left)) calc(14px + env(safe-area-inset-bottom)) max(14px, env(safe-area-inset-right));
        border-radius: 0;
        border-left: 0;
        border-right: 0;
        border-bottom: 0;
        box-shadow: 0 -10px 28px rgba(15, 23, 42, .1);
    }
    .cashier-modern .immediate-pay12-right {
        gap: 12px;
    }
    .cashier-modern .immediate-pay12-right span {
        font-size: 13px;
    }
    .cashier-modern .immediate-pay12-right span strong {
        font-size: 21px;
    }
    .cashier-modern .immediate_pay {
        width: 138px;
        height: 48px;
        line-height: 48px;
    }
    .cashier-modern .footer12 {
        display: none;
    }
}
</style>
</head>
<body class="cashier-modern">
<!--导航-->
<div class="w100 navBD12">
    <div class="w1080 nav12">
        <div class="nav12-left">
            <img src="/assets/img/logo.png">
        </div>
		<div class="nav12-right">
            收银台
        </div>

    </div>
</div>
<input type="hidden" name="trade_no" value="<?php echo $trade_no?>"/>
<!--订单金额-->
<?php if($other){?>
<div class="w1080 order-amount12" style="height: auto;">
    <h2><font style="color: red">当前支付方式暂时关闭维护，请更换其他方式支付</font></h2>
</div>
<?php if(in_array('qqpay',array_column($paytype,'name'))){?>
<div class="w1080 order-amount12" style="height: auto;">
    <h2 style="font-size:18px"><font style="color: green">如果您需要微信支付请将微信余额转到QQ再选择QQ钱包支付！</font></h2>
	<h3><a href="./wx.html" style="font-size:20px;color:blue">点击查看微信余额转到QQ钱包教程</a></h3>
</div>
<?php }}else{?>
<div class="w1080 order-amount12 checkout-card">
    <div class="order-summary-head">
        <span class="label">订单金额</span>
        <strong><?php echo $row['money']?></strong><small>元</small>
        <div class="amount-note">请确认订单信息后选择支付方式</div>
    </div>
    <ul class="order-amount12-left">
        <li>
            <span>商品名称：</span>
            <span><?php echo $row['name']?></span>
        </li>
        <li>
            <span>订单号：</span>
            <span><?php echo $trade_no?></span>
        </li>
		<li>
            <span>创建时间：</span>
            <span><?php echo $row['addtime']?></span>
        </li>
    </ul>
</div>
<?php }?>
<!--支付方式-->
<div class="w1080 PayMethod12 checkout-card">
    <div class="row">
        <h2>支付方式</h2>
        <?php if(empty($paytype)){?>
        <div class="empty-method">当前没有可用支付方式，请联系商户处理</div>
        <?php }else{?>
        <ul class="types">
		<?php foreach($paytype as $rows){?>
          <li class="pay_li" value="<?php echo $rows['id']?>">
             <?php
             $typeText = $rows['showname'].$rows['name'];
             if(preg_match('/USDC/i', $typeText)){
                echo '<i class="crypto-icon crypto-usdc">US</i>';
             }elseif(preg_match('/TRX|TRON/i', $typeText)){
                echo '<i class="crypto-icon crypto-trx">TR</i>';
             }elseif(preg_match('/USDT|加密|虚拟|Crypto/i', $typeText)){
                echo '<i class="crypto-icon">UT</i>';
             }else{
                echo '<img src="/assets/icon/'.$rows['name'].'.ico" onerror="this.style.display=\'none\'">';
             }
             ?>
             <span><?php echo $rows['showname']?></span>
          </li>
		<?php }?>
        </ul>
        <?php }?>
    </div>
</div>
<!--立即支付-->
<div class="w1080 immediate-pay12 checkout-card">
  <div class="immediate-pay12-right">
      <span>需支付：<strong><?php echo $payMoney?></strong>元<?php if($feeMoney)echo '（包含'.$feeMoney.'元手续费）';?></span>
        <a class="immediate_pay">立即支付</a>
    </div>
</div>
<div class="mt_agree">
  <div class="mt_agree_main">
    <h2>提示信息</h2>
    <p id="errorContent" style="text-align:center;line-height:36px;"></p>
    <a class="close_btn">确定</a>
  </div>
</div>
<!--底部-->
<div class="w1080 footer12">
    <p> <?php echo $sitename?$sitename:$conf['sitename']?></p>
</div>

<script src="<?php echo $cdnpublic?>jquery/1.12.4/jquery.min.js"></script>
<script type="text/javascript">
$(document).ready(function(){
	$(".types li").click(function(){
		$(".types li").each(function(){
			$(this).attr('class','');
		});
		$(this).attr('class','active');
	});
	$(document).on("click", ".immediate_pay", function () {
		var value = $(".types").find('.active').attr('value');
		var trade_no = $("input[name='trade_no']").val();
		if(!value){
			$("#errorContent").text("请先选择支付方式");
			$(".mt_agree").show();
			return false;
		}
		window.location.href='./submit2.php?typeid='+value+'&trade_no='+trade_no;
	});
	$(".close_btn").click(function(){
		$(".mt_agree").hide();
	});
	$(".types li:first").click();
})
</script>
</body>
</html>
