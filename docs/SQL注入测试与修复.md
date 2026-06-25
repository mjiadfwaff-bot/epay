# SQL注入漏洞测试与修复指南

## 🔍 漏洞分析

### 漏洞位置
**文件**: `cashier.php` (第9-11行, 第14行)

**漏洞代码**:
```php
$trade_no=daddslashes($_GET['trade_no']);
$row=$DB->getRow("SELECT * FROM pre_order WHERE trade_no='{$trade_no}' limit 1");
```

### 漏洞原因
1. 使用 `addslashes()` 进行过滤（`daddslashes` 内部调用 `addslashes`）
2. 使用字符串拼接构造SQL（存在绕过风险）
3. 可能存在宽字节注入（GBK编码环境）

---

## 🧪 SQL注入测试POC

### POC 1: 基础测试（检测是否过滤）
```bash
# 测试单引号
curl "http://your-domain.com/cashier.php?trade_no=test' AND 1=1--&sitename=dGVzdA=="

# 测试OR逻辑
curl "http://your-domain.com/cashier.php?trade_no=test' OR '1'='1&sitename=dGVzdA=="

# 测试联合查询
curl "http://your-domain.com/cashier.php?trade_no=test' UNION SELECT 1,2,3,4,5--&sitename=dGVzdA=="
```

**预期结果**:
- 如果存在漏洞：返回异常数据或SQL错误
- 如果已修复：正常提示"订单不存在"

---

### POC 2: 宽字节注入（针对GBK编码）
```bash
# 利用 %df%27 绕过 addslashes
# addslashes 会将 ' 转义为 \'
# 但 %df\' 在GBK中会被解释为 運'，从而绕过转义

curl "http://your-domain.com/cashier.php?trade_no=test%df' OR 1=1--&sitename=dGVzdA=="
```

**原理**:
```
输入:  test%df'
经过addslashes: test%df\'
GBK解码: test運' (单引号未被转义！)
SQL语句: SELECT * FROM pre_order WHERE trade_no='test運' OR 1=1--'
```

---

### POC 3: 时间盲注测试
```bash
# 如果查询结果不可见，可以用时间盲注验证
curl "http://your-domain.com/cashier.php?trade_no=test' AND SLEEP(5)--&sitename=dGVzdA=="

# 如果响应延迟5秒，说明SQL被执行
```

---

### POC 4: 信息泄露（获取数据库版本）
```bash
# 使用联合查询获取数据库信息
curl "http://your-domain.com/cashier.php?trade_no=-1' UNION SELECT 1,VERSION(),USER(),4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20--&sitename=dGVzdA=="
```

---

## 🔧 修复方案

### 方案一: PDO 预处理（推荐✅）

**修改 cashier.php**:

```php
// 原代码（有漏洞）
$trade_no=daddslashes($_GET['trade_no']);
$row=$DB->getRow("SELECT * FROM pre_order WHERE trade_no='{$trade_no}' limit 1");

// 修复后（使用PDO预处理）
$trade_no = isset($_GET['trade_no']) ? trim($_GET['trade_no']) : '';
$stmt = $DB->prepare("SELECT * FROM pre_order WHERE trade_no=? LIMIT 1");
$stmt->execute([$trade_no]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
```

**修改第14行**:
```php
// 原代码
$gid = $DB->getColumn("SELECT gid FROM pre_user WHERE uid='{$row['uid']}' limit 1");

// 修复后
$stmt = $DB->prepare("SELECT gid FROM pre_user WHERE uid=? LIMIT 1");
$stmt->execute([$row['uid']]);
$gid = $stmt->fetchColumn();
```

---

### 方案二: 强类型转换（适用于整数）

如果 `trade_no` 是纯数字：

```php
$trade_no = intval($_GET['trade_no']);
$row=$DB->getRow("SELECT * FROM pre_order WHERE trade_no={$trade_no} LIMIT 1");
```

**注意**: 仅适用于确定是整数的字段！

---

### 方案三: 白名单验证

如果 `trade_no` 有固定格式（如：20位数字）：

```php
$trade_no = isset($_GET['trade_no']) ? $_GET['trade_no'] : '';

// 验证格式：只允许数字和字母，长度20
if(!preg_match('/^[a-zA-Z0-9]{20}$/', $trade_no)){
    sysmsg('订单号格式错误');
}

// 使用PDO预处理
$stmt = $DB->prepare("SELECT * FROM pre_order WHERE trade_no=? LIMIT 1");
$stmt->execute([$trade_no]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
```

---

## 🛠️ 完整修复代码

### 修复后的 cashier.php (前30行)

```php
<?php
$is_defend = true;
$nosession = true;
require './includes/common.php';

@header('Content-Type: text/html; charset=UTF-8');

$other = isset($_GET['other']) ? true : false;

// 修复：使用预处理替代字符串拼接
$trade_no = isset($_GET['trade_no']) ? trim($_GET['trade_no']) : '';
$sitename_raw = isset($_GET['sitename']) ? $_GET['sitename'] : '';

// 验证 trade_no 格式（根据实际业务调整）
if(!preg_match('/^[a-zA-Z0-9]{10,30}$/', $trade_no)){
    sysmsg('订单号格式错误');
}

// 安全解码 sitename
$sitename = base64_decode($sitename_raw);
$sitename = htmlspecialchars($sitename, ENT_QUOTES, 'UTF-8'); // 防XSS

// 使用PDO预处理查询（防SQL注入）
$stmt = $DB->prepare("SELECT * FROM pre_order WHERE trade_no=? LIMIT 1");
$stmt->execute([$trade_no]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$row) sysmsg('该订单号不存在，请返回来源地重新发起请求！');
if($row['status'] == 1) sysmsg('该订单已完成支付，请勿重复支付');

// 修复第14行
$stmt = $DB->prepare("SELECT gid FROM pre_user WHERE uid=? LIMIT 1");
$stmt->execute([$row['uid']]);
$gid = $stmt->fetchColumn();

$paytype = \lib\Channel::getTypes($row['uid'], $gid);

if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false){
    $paytype = array_values($paytype);
    foreach($paytype as $i => $s){
        if($s['name'] == 'wxpay'){
            $temp = $paytype[$i];
            $paytype[$i] = $paytype[0];
            $paytype[0] = $temp;
        }
    }
}
?>
```

---

## 🧪 修复后的测试

### 测试1: 单引号注入（应该失败）
```bash
curl "http://your-domain.com/cashier.php?trade_no=test'--&sitename=dGVzdA=="
```
**预期结果**: 提示 "订单号格式错误" 或 "订单不存在"

### 测试2: 宽字节注入（应该失败）
```bash
curl "http://your-domain.com/cashier.php?trade_no=test%df'%20OR%201=1--&sitename=dGVzdA=="
```
**预期结果**: 提示 "订单号格式错误"

### 测试3: 联合查询（应该失败）
```bash
curl "http://your-domain.com/cashier.php?trade_no=test%27%20UNION%20SELECT%201,2,3--&sitename=dGVzdA=="
```
**预期结果**: 提示 "订单号格式错误"

### 测试4: 正常订单号（应该成功）
```bash
curl "http://your-domain.com/cashier.php?trade_no=2026020717570056178&sitename=dGVzdA=="
```
**预期结果**: 正常显示订单页面或提示"订单不存在"（如果订单号不在数据库）

---

## 📊 自动化测试脚本

### sqlmap 测试（专业工具）

```bash
# 安装 sqlmap
# pip install sqlmap

# 测试 trade_no 参数
sqlmap -u "http://your-domain.com/cashier.php?trade_no=test&sitename=dGVzdA==" \
       -p trade_no \
       --level=5 \
       --risk=3 \
       --batch

# 如果存在注入，sqlmap会自动检测
```

---

## 🔒 数据库层面防护

### 1. 使用最小权限账户
```sql
-- 创建只读账户（查询订单）
CREATE USER 'epay_readonly'@'localhost' IDENTIFIED BY '强密码';
GRANT SELECT ON epay_db.pre_order TO 'epay_readonly'@'localhost';
GRANT SELECT ON epay_db.pre_user TO 'epay_readonly'@'localhost';
FLUSH PRIVILEGES;
```

### 2. 启用SQL审计日志
```sql
-- MySQL 8.0+
INSTALL PLUGIN audit_log SONAME 'audit_log.so';
SET GLOBAL audit_log_policy = 'ALL';
```

---

## ✅ 验证修复清单

- [ ] 所有SQL查询使用PDO预处理
- [ ] 输入参数进行白名单验证
- [ ] XSS防护（htmlspecialchars）
- [ ] 运行POC测试，确认无法注入
- [ ] 使用sqlmap扫描，确认安全
- [ ] 代码审计，检查其他文件

---

## 🚨 紧急情况处理

如果发现已被攻击：

1. **立即备份**
```bash
mysqldump -u root -p epay_db > backup_$(date +%Y%m%d_%H%M%S).sql
```

2. **检查异常记录**
```sql
-- 查看最近创建的订单
SELECT * FROM pre_order ORDER BY id DESC LIMIT 100;

-- 查看异常订单号（包含特殊字符）
SELECT * FROM pre_order WHERE trade_no REGEXP '[^a-zA-Z0-9]';
```

3. **应用修复补丁**
4. **重置数据库密码**
5. **通知用户（如有数据泄露）**

---

## 📞 支持

如需技术支持或发现新的安全问题，请联系系统管理员。
