## 支付宝RSA密钥配置教程

**阅读此文档之前，要仔细分清【公】和【私】这2个字，如果你连这2个字都分不清的话就不要往下看了！！！**
配置前确保已经[签约支付产品](https://b.alipay.com/page/product-mall/all-product)，如果没有签约，即使是配置了密钥也无法发起支付！

1、进入[支付宝开放平台](https://open.alipay.com/develop/manage)页面，选择某个应用（签约后会自动生成一个**基础应用**，不需要自己创建应用），点击 **详情**，进入 应用详情页，左上方可以复制该应用的**APPID**，然后进入 **开发设置** > **接口加签方式（密钥/证书）**> **设置。**
![img](https://blog.cccyun.cn/content/uploadfile/202503/24c0e0c2-f457-48aa-90ee-e980e5de2052.png)

2、设置加签方式，选择 **密钥** > **下一步**。
![img](https://blog.cccyun.cn/content/uploadfile/202503/b820c471-f6dd-4a4e-a3b0-af71c3017b5b.png)

3、下载[支付宝开放平台密钥工具](https://ideservice.alipay.com/ide/getPluginUrl.htm?clientType=assistant&platform=win&channelType=WEB)，安装完后打开。

4、加签方式和加密算法都保持默认，点击【生成密钥】，可以在结果页中看到生成的【应用公钥】、【应用私钥】
![QQ截图20250318103756.png](https://blog.cccyun.cn/content/uploadfile/202503/QQ%E6%88%AA%E5%9B%BE20250318103756.png)

![QQ截图20250318103953.png](https://blog.cccyun.cn/content/uploadfile/202503/QQ%E6%88%AA%E5%9B%BE20250318103953.png)
复制【应用私钥】填写到网站后台。

5、将刚才在软件里面生成的【应用公钥】填写到下方输入框内。确认上传后，点击忽略IP白名单设置，就会显示【支付宝公钥】了。
![img](https://blog.cccyun.cn/content/uploadfile/202503/QQ%E6%88%AA%E5%9B%BE20250318102024.png)

![QQ截图20200402195831.png](http://blog.cccyun.cn/content/uploadfile/202004/QQ%E6%88%AA%E5%9B%BE20200402195831.png)
复制【支付宝公钥】填写到网站后台。

6、如果需要用到当面付JS支付或支付宝快捷登录，还需要配置**授权回调地址**，直接填写网站首页的URL即可。
![QQ截图20250318104841.png](https://blog.cccyun.cn/content/uploadfile/202503/QQ%E6%88%AA%E5%9B%BE20250318104841.png)



提醒：网站后台需要用到的是【支付宝公钥】和【应用私钥】，千万不能搞混了！【应用公钥】和【应用私钥】是一一对应的，也就是填写到网站后台的【应用私钥】和提交到支付宝那边的【应用公钥】是同一对才可以！

## 支付宝RSA公钥证书配置教程

若使用单笔转账到支付宝、现金红包等出资类接口，则必须使用公钥证书模式，否则提交转账时就会出现“接口已升级”的错误提示。
1、进入[支付宝开放平台](https://open.alipay.com/develop/manage)页面，选择某个应用，点击 **详情**，进入 应用详情页，左上方可以复制该应用的**APPID**，然后进入 **开发设置** > **接口加签方式（密钥/证书）**> **设置。**
![img](https://blog.cccyun.cn/content/uploadfile/202503/24c0e0c2-f457-48aa-90ee-e980e5de2052.png)

2、设置加签方式，选择 **证书** > **下一步。**
![1695263606769-205f426b-c597-4742-af22-0149471e6ea1.png](https://blog.cccyun.cn/content/uploadfile/202503/1695263606769-205f426b-c597-4742-af22-0149471e6ea1.png)
3、打开密钥工具，进入 **生成密钥** 功能，
加签方式选择 **证书**，
填写 **组织/公司**，必须与支付宝主账号名称完全相同，
点击 **生成CSR文件**，可以点击 **打开文件位置** 查看生成的应用私钥、应用公钥和 CSR文件。
![img](https://gw.alipayobjects.com/mdn/rms_d00b4e/afts/img/A*QlB_TqHKE7EAAAAAAAAAAAAAARQnAQ)
4、打开“**应用私钥RSA2048-敏感数据，请妥善保管.txt**”文件，将里面的内容复制到网站后台【应用私钥】输入框。
5、回到开放平台控制台，上传生成的 CSR 文件，设置安全联系人信息。点击 **确认上传**。
![img](https://blog.cccyun.cn/content/uploadfile/202503/QQ%E6%88%AA%E5%9B%BE20250318111204.png)
6、将生成的**应用公钥证书**、**支付宝公钥证书**、**支付宝根证书** 全部下载至本地，并上传到网站指定目录内，这3个文件均须保持原本的文件名。