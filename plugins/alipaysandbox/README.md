# 支付宝沙箱支付插件使用说明

## 📋 插件概述

**插件名称**：支付宝沙箱支付 (alipaysandbox)
**用途**：用于开发测试环境的支付宝支付功能
**网关地址**：`https://openapi-sandbox.dl.alipaydev.com/gateway.do`
**WebSocket地址**：`openchannel-sandbox.dl.alipaydev.com`

⚠️ **重要提示**：本插件仅用于开发测试，不能用于生产环境！

---

## 🚀 快速开始

### 1. 申请沙箱账号

访问支付宝开放平台沙箱页面：
```
https://open.alipay.com/develop/sandbox/app
```

### 2. 获取沙箱配置信息

登录后可以获取：
- **沙箱APPID**：如 `2021000122600001`
- **支付宝公钥**：RSA2公钥字符串
- **应用私钥**：您自己生成的RSA2私钥
- **沙箱账号**：买家账号和卖家账号（用于测试支付）

### 3. 配置插件

在后台添加支付通道，选择"支付宝沙箱支付"，填写：

| 配置项 | 说明 | 示例 |
|--------|------|------|
| 沙箱应用APPID | 沙箱环境的APPID | 2021000122600001 |
| 支付宝公钥 | 沙箱支付宝公钥 | MIIBIjANBgkqhkiG9w0... |
| 应用私钥 | 您生成的应用私钥 | MIIEvQIBADANBgkqhk... |
| 卖家支付宝用户ID | 可留空 | - |

### 4. 选择支付方式

勾选您需要测试的支付方式：
- ✅ 电脑网站支付
- ✅ 手机网站支付
- ✅ 当面付扫码
- ✅ 当面付JS
- ✅ 预授权支付
- ✅ APP支付
- ✅ JSAPI支付
- ✅ 订单码支付

---

## 🔑 RSA密钥生成

### 方法1：使用支付宝密钥工具（推荐）

1. 下载支付宝密钥生成工具：
   - Windows: https://ideservice.alipay.com/ide/getPluginUrl.htm?clientType=assistant&platform=win
   - Mac: https://ideservice.alipay.com/ide/getPluginUrl.htm?clientType=assistant&platform=mac

2. 运行工具，选择 **RSA2(SHA256)密钥**

3. 点击"生成密钥"，会生成：
   - **应用私钥**（保存到本地，填入"应用私钥"配置项）
   - **应用公钥**（上传到沙箱应用配置）

4. 上传应用公钥后，从沙箱页面复制**支付宝公钥**，填入配置

### 方法2：使用OpenSSL命令行

```bash
# 生成私钥
openssl genrsa -out app_private_key.pem 2048

# 从私钥生成公钥
openssl rsa -in app_private_key.pem -pubout -out app_public_key.pem

# 查看私钥（去掉头尾和换行，填入"应用私钥"）
cat app_private_key.pem

# 查看公钥（去掉头尾和换行，上传到沙箱应用）
cat app_public_key.pem
```

---

## 🧪 测试支付

### 沙箱买家账号

登录沙箱页面可以看到测试买家账号：
- **买家账号**：如 `xxxyyy@sandbox.com`
- **登录密码**：111111
- **支付密码**：111111
- **账户余额**：通常为 100,000 元（虚拟金额）

### 测试流程

1. 在您的系统中发起支付（如充值10元）
2. 跳转到支付宝沙箱收银台
3. 使用沙箱买家账号登录
4. 输入支付密码完成支付
5. 查看回调和订单状态

### 常见测试场景

| 场景 | 操作 | 预期结果 |
|------|------|----------|
| 支付成功 | 正常完成支付 | 订单状态变为"已支付" |
| 支付失败 | 关闭支付窗口 | 订单状态保持"未支付" |
| 异步通知 | 完成支付后 | 系统收到notify回调 |
| 同步返回 | 完成支付后 | 跳转到return页面 |

---

## 📁 公钥证书模式（可选）

如果使用公钥证书模式，需要：

### 1. 下载证书文件

从沙箱应用页面下载3个证书文件：
- `appCertPublicKey_<APPID>.crt` - 应用公钥证书
- `alipayCertPublicKey_RSA2.crt` - 支付宝公钥证书
- `alipayRootCert.crt` - 支付宝根证书

### 2. 上传证书

将3个证书文件放到以下任一目录：

**方式1**：按APPID分类（推荐）
```
plugins/alipaysandbox/cert/2021000122600001/
├── appCertPublicKey_2021000122600001.crt
├── alipayCertPublicKey_RSA2.crt
└── alipayRootCert.crt
```

**方式2**：统一目录
```
plugins/alipaysandbox/cert/
├── appCertPublicKey_2021000122600001.crt
├── alipayCertPublicKey_RSA2.crt
└── alipayRootCert.crt
```

### 3. 配置说明

使用证书模式时：
- **支付宝公钥** 配置项可以留空
- **应用私钥** 配置项仍需填写

---

## 🔍 日志查看

日志文件位置：
```
plugins/alipaysandbox/inc/log/
```

日志文件按日期命名，如：
```
2026-02-10.log
```

查看最新日志：
```bash
tail -f plugins/alipaysandbox/inc/log/$(date +%Y-%m-%d).log
```

---

## ⚠️ 常见问题

### Q1: 提示"应用私钥格式错误"
**原因**：私钥包含了头尾标识或换行符
**解决**：
- 去掉 `-----BEGIN PRIVATE KEY-----` 和 `-----END PRIVATE KEY-----`
- 去掉所有换行符，合并为一行
- 只保留纯Base64字符串

### Q2: 提示"支付宝公钥格式错误"
**原因**：公钥包含了头尾标识或换行符
**解决**：
- 去掉 `-----BEGIN PUBLIC KEY-----` 和 `-----END PUBLIC KEY-----`
- 去掉所有换行符，合���为一行
- 只保留纯Base64字符串

### Q3: 支付成功但没有回调
**原因**：支付宝公钥配置错误，导致签名验证失败
**解决**：
1. 检查是否正确复制了沙箱页面的"支付宝公钥"
2. 确保不是"应用公钥"
3. 查看日志文件确认验证失败原因

### Q4: 提示"无效的应用授权令牌"
**原因**：使用了生产环境的APPID
**解决**：确保使用的是沙箱APPID（通常以2021或2016开头）

### Q5: 沙箱买家账号余额不足
**解决**：在沙箱页面可以"重置数据"恢复余额

---

## 🔄 从沙箱切换到生产

测试完成后切换到生产环境：

### 方式1：新建生产通道（推荐）
1. 在后台添加新支付通道
2. 选择"支付宝官方支付"（不是沙箱）
3. 填写生产环境的APPID和密钥
4. 启用生产通道，禁用沙箱通道

### 方式2：修改现有通道
1. 将通道插件切换为"支付宝官方支付"
2. 更新APPID和密钥为生产环境配置
3. 重新上传生产环境证书（如使用证书模式）

---

## 🛠️ 技术说明

### 网关地址差异

| 环境 | 网关地址 |
|------|----------|
| 沙箱 | https://openapi-sandbox.dl.alipaydev.com/gateway.do |
| 生产 | https://openapi.alipay.com/gateway.do |

### WebSocket地址差异

| 环境 | WebSocket地址 |
|------|---------------|
| 沙箱 | openchannel-sandbox.dl.alipaydev.com |
| 生产 | openchannel.alipay.com |

### 代码实现

本插件复用了 `alipay_plugin` 的所有代码逻辑，唯一区别是：
- 配置文件 `inc/config.php` 中的 `gateway_url` 使用沙箱地址
- 插件信息 `$info` 中的提示说明

---

## 📚 参考文档

- **沙箱环境使用说明**：https://opendocs.alipay.com/common/02kkv7
- **API文档**：https://opendocs.alipay.com/
- **密钥工具下载**：https://opendocs.alipay.com/common/02kipl

---

## 📞 技术支持

如遇到问题，请：
1. 查看日志文件：`plugins/alipaysandbox/inc/log/`
2. 检查沙箱应用配置：https://open.alipay.com/develop/sandbox/app
3. 参考支付宝开放平台文档

---

**最后更新**：2026-02-10
**版本**：v1.0
**适用系统**：易支付系统
