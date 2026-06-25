<?php
/**
 * 日志功能测试脚本
 * 用于验证日志写入是否正常
 */

// 模拟配置
$aima_config = [
    'log_path' => __DIR__ . '/inc/log/',
    'pay_memberid' => 'M200025',
    'key' => 'd3659350381a2e6ee070a41430e7bc4d',
];

// 测试日志写入
function testWriteLog($text, $config){
    if(empty($config['log_path'])){
        echo "错误：log_path 为空\n";
        return;
    }

    if(!is_dir($config['log_path'])){
        echo "日志目录不存在，正在创建...\n";
        if(!@mkdir($config['log_path'], 0755, true)){
            echo "错误：无法创建日志目录\n";
            return;
        }
    }

    $logFile = $config['log_path'] . date('Y-m-d') . '.log';
    echo "日志文件路径：{$logFile}\n";

    $logContent = date('Y-m-d H:i:s') . ' ' . $text . "\n";

    if(file_put_contents($logFile, $logContent, FILE_APPEND) === false){
        echo "错误：写入日志失败\n";
    }else{
        echo "成功：日志已写入\n";
    }
}

echo "====== 艾玛支付日志测试 ======\n\n";

echo "1. 检查日志目录\n";
echo "   路径：{$aima_config['log_path']}\n";
echo "   存在：" . (is_dir($aima_config['log_path']) ? '是' : '否') . "\n";
echo "   可写：" . (is_writable(dirname($aima_config['log_path'])) ? '是' : '否') . "\n\n";

echo "2. 测试日志写入\n";
testWriteLog("测试日志 - " . time(), $aima_config);

echo "\n3. 检查日志文件\n";
$logFile = $aima_config['log_path'] . date('Y-m-d') . '.log';
if(file_exists($logFile)){
    echo "   文件存在：是\n";
    echo "   文件大小：" . filesize($logFile) . " 字节\n";
    echo "   最后几行内容：\n";
    echo "   " . str_repeat('-', 50) . "\n";
    $content = file_get_contents($logFile);
    $lines = explode("\n", trim($content));
    $lastLines = array_slice($lines, -5);
    foreach($lastLines as $line){
        echo "   {$line}\n";
    }
    echo "   " . str_repeat('-', 50) . "\n";
}else{
    echo "   文件存在：否\n";
}

echo "\n4. 测试多次写入\n";
for($i = 1; $i <= 3; $i++){
    testWriteLog("测试写入 #{$i}", $aima_config);
}

echo "\n====== 测试完成 ======\n";
