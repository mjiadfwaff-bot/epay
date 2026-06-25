<?php

class EpusdtCore
{
    private $pid;
    private $key;
    private $submit_url;
    private $sign_type = 'MD5';

    public function __construct($config)
    {
        $this->pid = $config['pid'];
        $this->key = $config['key'];
        $this->submit_url = rtrim($config['apiurl'], '/') . '/submit.php';
    }

    public function pagePay($param_tmp, $button = '正在跳转')
    {
        $param = $this->buildRequestParam($param_tmp);

        $html = '<form id="dopay" action="' . $this->submit_url . '" method="post">';
        foreach ($param as $k => $v) {
            $html .= '<input type="hidden" name="' . htmlspecialchars($k, ENT_QUOTES) . '" value="' . htmlspecialchars($v, ENT_QUOTES) . '"/>';
        }
        $html .= '<input type="submit" value="' . htmlspecialchars($button, ENT_QUOTES) . '"></form><script>document.getElementById("dopay").submit();</script>';

        return $html;
    }

    public function getPayLink($param_tmp)
    {
        $param = $this->buildRequestParam($param_tmp);
        return $this->submit_url . '?' . http_build_query($param);
    }

    public function verifyNotify()
    {
        if (empty($_GET)) return false;

        return $this->getSign($_GET) === $_GET['sign'];
    }

    public function verifyReturn()
    {
        if (empty($_GET)) return false;

        return $this->getSign($_GET) === $_GET['sign'];
    }

    private function buildRequestParam($param)
    {
        $param['pid'] = $this->pid;
        $param['sign'] = $this->getSign($param);
        $param['sign_type'] = $this->sign_type;

        return $param;
    }

    private function getSign($param)
    {
        ksort($param);
        reset($param);

        $signstr = '';
        foreach ($param as $k => $v) {
            if ($k != 'sign' && $k != 'sign_type' && $v !== '') {
                $signstr .= $k . '=' . $v . '&';
            }
        }
        $signstr = substr($signstr, 0, -1);
        $signstr .= $this->key;

        return md5($signstr);
    }
}
