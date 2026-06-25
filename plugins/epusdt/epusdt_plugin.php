<?php

class epusdt_plugin
{
    static public $info = [
        'name'        => 'epusdt',
        'showname'    => 'Epusdt',
        'author'      => 'GMWalletApp',
        'link'        => 'https://github.com/GMWalletApp/epusdt',
        'types'       => ['usdt', 'usdc', 'trx'],
        'inputs' => [
            'appurl' => [
                'name' => '接口地址',
                'type' => 'input',
                'note' => '填写到 EPay submit.php 所在目录，例如：http://43.128.19.181:18081/payments/epay/v1/order/create-transaction/',
            ],
            'appid' => [
                'name' => '商户ID',
                'type' => 'input',
                'note' => 'Epusdt 后台 EPay 兼容接入的商户ID',
            ],
            'appkey' => [
                'name' => '商户密钥',
                'type' => 'input',
                'note' => 'Epusdt 后台 EPay 兼容接入的密钥',
            ],
            'currency' => [
                'name' => '法币币种',
                'type' => 'input',
                'note' => '默认 cny',
            ],
            'usdc_network' => [
                'name' => 'USDC 网络',
                'type' => 'select',
                'options' => [
                    'tron' => 'TRON',
                    'bsc' => 'BSC',
                    'polygon' => 'Polygon',
                    'solana' => 'Solana',
                    'eth' => 'Ethereum',
                ],
            ],
        ],
        'select' => null,
        'note' => '支付方式调用值需分别添加为 usdt、usdc、trx。USDT/TRX 默认使用 TRON 网络，USDC 按本配置选择的网络传给 Epusdt。',
        'bindwxmp' => false,
        'bindwxa' => false,
    ];

    static public function submit()
    {
        global $siteurl, $channel, $order, $conf;

        require(PAY_ROOT . 'inc/epusdt.config.php');
        require(PAY_ROOT . 'inc/EpusdtCore.class.php');

        $asset = self::getAsset($order['typename'], $channel);
        if (!$asset) {
            return ['type' => 'error', 'msg' => 'Epusdt 暂不支持当前支付方式：' . $order['typename']];
        }

        $parameter = [
            'type' => $order['typename'],
            'notify_url' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
            'return_url' => $siteurl . 'pay/return/' . TRADE_NO . '/',
            'out_trade_no' => TRADE_NO,
            'name' => $order['name'],
            'money' => $order['realmoney'],
            'token' => $asset['token'],
            'network' => $asset['network'],
            'currency' => !empty($channel['currency']) ? strtolower($channel['currency']) : 'cny',
        ];

        $epusdt = new EpusdtCore($epusdt_config);
        if (is_https() && substr($epusdt_config['apiurl'], 0, 7) == 'http://') {
            return ['type' => 'jump', 'url' => $epusdt->getPayLink($parameter)];
        }

        return ['type' => 'html', 'data' => $epusdt->pagePay($parameter, '正在跳转')];
    }

    static public function mapi()
    {
        global $siteurl;

        return ['type' => 'jump', 'url' => $siteurl . 'pay/submit/' . TRADE_NO . '/'];
    }

    static public function notify()
    {
        global $channel, $order;

        require(PAY_ROOT . 'inc/epusdt.config.php');
        require(PAY_ROOT . 'inc/EpusdtCore.class.php');

        $epusdtNotify = new EpusdtCore($epusdt_config);
        if ($epusdtNotify->verifyNotify()) {
            $out_trade_no = $_GET['out_trade_no'];
            $trade_no = $_GET['trade_no'];
            $money = $_GET['money'];

            if ($_GET['trade_status'] == 'TRADE_SUCCESS') {
                if ($out_trade_no == TRADE_NO && round($money, 2) == round($order['realmoney'], 2)) {
                    processNotify($order, $trade_no);
                }
            }
            return ['type' => 'html', 'data' => 'success'];
        }

        return ['type' => 'html', 'data' => 'fail'];
    }

    static public function return()
    {
        global $channel, $order;

        require(PAY_ROOT . 'inc/epusdt.config.php');
        require(PAY_ROOT . 'inc/EpusdtCore.class.php');

        $epusdtNotify = new EpusdtCore($epusdt_config);
        if ($epusdtNotify->verifyReturn()) {
            $out_trade_no = $_GET['out_trade_no'];
            $trade_no = $_GET['trade_no'];
            $money = $_GET['money'];

            if ($_GET['trade_status'] == 'TRADE_SUCCESS') {
                if ($out_trade_no == TRADE_NO && round($money, 2) == round($order['realmoney'], 2)) {
                    processReturn($order, $trade_no);
                } else {
                    return ['type' => 'error', 'msg' => '订单信息校验失败'];
                }
            } else {
                return ['type' => 'error', 'msg' => 'trade_status=' . $_GET['trade_status']];
            }
        } else {
            return ['type' => 'error', 'msg' => '验证签名失败！'];
        }
    }

    static private function getAsset($typename, $channel)
    {
        if ($typename == 'usdt') {
            return ['token' => 'USDT', 'network' => 'tron'];
        }
        if ($typename == 'usdc') {
            return ['token' => 'USDC', 'network' => !empty($channel['usdc_network']) ? $channel['usdc_network'] : 'tron'];
        }
        if ($typename == 'trx') {
            return ['token' => 'TRX', 'network' => 'tron'];
        }

        return null;
    }
}
