![](https://box.kancloud.cn/5a0aaa69a5ff42657b5c4715f3d49221) 

ThinkPHP 5.1（LTS版本） —— 12载初心，你值得信赖的PHP框架
===============

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/top-think/framework/badges/quality-score.png?b=5.1)](https://scrutinizer-ci.com/g/top-think/framework/?branch=5.1)
[![Build Status](https://travis-ci.org/top-think/framework.svg?branch=master)](https://travis-ci.org/top-think/framework)
[![Total Downloads](https://poser.pugx.org/topthink/framework/downloads)](https://packagist.org/packages/topthink/framework)
[![Latest Stable Version](https://poser.pugx.org/topthink/framework/v/stable)](https://packagist.org/packages/topthink/framework)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D5.6-8892BF.svg)](http://www.php.net/)
[![License](https://poser.pugx.org/topthink/framework/license)](https://packagist.org/packages/topthink/framework)

ThinkPHP5.1对底层架构做了进一步的改进，减少依赖，其主要特性包括：

 + 采用容器统一管理对象
 + 支持Facade
 + 注解路由支持
 + 路由跨域请求支持
 + 配置和路由目录独立
 + 取消系统常量
 + 助手函数增强
 + 类库别名机制
 + 增加条件查询
 + 改进查询机制
 + 配置采用二级
 + 依赖注入完善
 + 支持`PSR-3`日志规范
 + 中间件支持（V5.1.6+）
 + Swoole/Workerman支持（V5.1.18+）


> ThinkPHP5的运行环境要求PHP5.6以上。

## 安装

使用composer安装

~~~
composer create-project topthink/think tp
~~~

启动服务

~~~
cd tp
php think run
~~~

然后就可以在浏览器中访问

~~~
http://localhost:8000
~~~

更新框架
~~~
composer update topthink/framework
~~~


## 在线手册

+ [完全开发手册](https://www.kancloud.cn/manual/thinkphp5_1/content)
+ [升级指导](https://www.kancloud.cn/manual/thinkphp5_1/354155) 

## 目录结构

初始的目录结构如下：

~~~
www  WEB部署目录（或者子目录）
├─application           应用目录
│  ├─common             公共模块目录（可以更改）
│  ├─module_name        模块目录
│  │  ├─common.php      模块函数文件
│  │  ├─controller      控制器目录
│  │  ├─model           模型目录
│  │  ├─view            视图目录
│  │  └─ ...            更多类库目录
│  │
│  ├─command.php        命令行定义文件
│  ├─common.php         公共函数文件
│  └─tags.php           应用行为扩展定义文件
│
├─config                应用配置目录
│  ├─module_name        模块配置目录
│  │  ├─database.php    数据库配置
│  │  ├─cache           缓存配置
│  │  └─ ...            
│  │
│  ├─app.php            应用配置
│  ├─cache.php          缓存配置
│  ├─cookie.php         Cookie配置
│  ├─database.php       数据库配置
│  ├─log.php            日志配置
│  ├─session.php        Session配置
│  ├─template.php       模板引擎配置
│  └─trace.php          Trace配置
│
├─route                 路由定义目录
│  ├─route.php          路由定义
│  └─...                更多
│
├─public                WEB目录（对外访问目录）
│  ├─index.php          入口文件
│  ├─router.php         快速测试文件
│  └─.htaccess          用于apache的重写
│
├─thinkphp              框架系统目录
│  ├─lang               语言文件目录
│  ├─library            框架类库目录
│  │  ├─think           Think类库包目录
│  │  └─traits          系统Trait目录
│  │
│  ├─tpl                系统模板目录
│  ├─base.php           基础定义文件
│  ├─console.php        控制台入口文件
│  ├─convention.php     框架惯例配置文件
│  ├─helper.php         助手函数文件
│  ├─phpunit.xml        phpunit配置文件
│  └─start.php          框架入口文件
│
├─extend                扩展类库目录
├─runtime               应用的运行时目录（可写，可定制）
├─vendor                第三方类库目录（Composer依赖库）
├─build.php             自动生成定义文件（参考）
├─composer.json         composer 定义文件
├─LICENSE.txt           授权说明文件
├─README.md             README 文件
├─think                 命令行入口文件
~~~

> 可以使用php自带webserver快速测试
> 切换到根目录后，启动命令：php think run

## 命名规范

`ThinkPHP5`遵循PSR-2命名规范和PSR-4自动加载规范，并且注意如下规范：

### 目录和文件

*   目录不强制规范，驼峰和小写+下划线模式均支持；
*   类库、函数文件统一以`.php`为后缀；
*   类的文件名均以命名空间定义，并且命名空间的路径和类库文件所在路径一致；
*   类名和类文件名保持一致，统一采用驼峰法命名（首字母大写）；

### 函数和类、属性命名

*   类的命名采用驼峰法，并且首字母大写，例如 `User`、`UserType`，默认不需要添加后缀，例如`UserController`应该直接命名为`User`；
*   函数的命名使用小写字母和下划线（小写字母开头）的方式，例如 `get_client_ip`；
*   方法的命名使用驼峰法，并且首字母小写，例如 `getUserName`；
*   属性的命名使用驼峰法，并且首字母小写，例如 `tableName`、`instance`；
*   以双下划线"__"打头的函数或方法作为魔法方法，例如 `__call` 和 `__autoload`；

### 常量和配置

*   常量以大写字母和下划线命名，例如 `APP_PATH`和 `THINK_PATH`；
*   配置参数以小写字母和下划线命名，例如 `url_route_on` 和`url_convert`；

### 数据表和字段

*   数据表和字段采用小写加下划线方式命名，并注意字段名不要以下划线开头，例如 `think_user` 表和 `user_name`字段，不建议使用驼峰和中文作为数据表字段命名。

## 参与开发

请参阅 [ThinkPHP5 核心框架包](https://github.com/top-think/framework)。

## 版权信息

ThinkPHP遵循Apache2开源协议发布，并提供免费使用。

本项目包含的第三方源码和二进制文件之版权信息另行标注。

版权所有Copyright © 2006-2018 by ThinkPHP (http://thinkphp.cn)

All rights reserved。

ThinkPHP® 商标和著作权所有者为上海顶想信息科技有限公司。

更多细节参阅 [LICENSE.txt](LICENSE.txt)

# Microsoft Outlook IMAP 同步工具

这个工具使用 [Webklex/php-imap](https://github.com/Webklex/php-imap) 库通过 OAuth 2.0 认证连接到 Microsoft Outlook 邮箱，同步 Cash App 相关邮件。

## 特性

- ✅ 支持 OAuth 2.0 认证（无需密码）
- ✅ 使用 Webklex/php-imap 库（不依赖 php-imap 扩展）
- ✅ 自动处理 Cash App 支付通知
- ✅ Telegram 机器人通知
- ✅ Redis 缓存和任务锁

## 依赖要求

### 库依赖
项目已包含以下依赖，如需更新可运行：

```bash
composer require webklex/php-imap:^6.2
```

### OAuth 2.0 配置

1. **Microsoft Azure 应用注册**
   - 在 [Azure Portal](https://portal.azure.com) 创建应用
   - 配置 IMAP.AccessAsUser.All 权限
   - 获取 access token

2. **Access Token 获取**
   - 使用 Microsoft Graph API 获取 access token
   - 确保包含 `IMAP.AccessAsUser.All` 权限
   - Token 有效期通常为 1 小时

## 使用方法

### 运行同步命令

```bash
php think syncEmailMicrosoft
```

### 配置说明

在 `application/command/SyncEmailMicrosoft.php` 中配置：

```php
// Microsoft Outlook 邮箱配置
$username = 'your-email@outlook.com';
$accessToken = 'your-oauth-access-token';
```

### 配置参数

| 参数 | 说明 | 示例 |
|------|------|------|
| username | Outlook 邮箱地址 | user@outlook.com |
| accessToken | OAuth 2.0 访问令牌 | eyJ0eXAiOiJKV1Q... |

## 工作流程

1. **连接验证**：使用 OAuth 2.0 token 连接 Outlook IMAP
2. **邮件获取**：获取收件箱中的新邮件
3. **过滤处理**：只处理来自 `cash@square.com` 的邮件
4. **内容解析**：提取金额、订单号、状态等信息
5. **业务处理**：更新支付订单状态
6. **通知发送**：发送 Telegram 通知

## OAuth 2.0 认证流程

### 1. 获取 Authorization Code

访问 Microsoft 授权端点：
```
https://login.microsoftonline.com/{tenant-id}/oauth2/v2.0/authorize?
client_id={client-id}&
response_type=code&
redirect_uri={redirect-uri}&
scope=https://outlook.office365.com/IMAP.AccessAsUser.All offline_access&
response_mode=query
```

### 2. 获取 Access Token

使用 authorization code 获取 access token：
```bash
curl -X POST https://login.microsoftonline.com/{tenant-id}/oauth2/v2.0/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "client_id={client-id}" \
  -d "client_secret={client-secret}" \
  -d "code={authorization-code}" \
  -d "redirect_uri={redirect-uri}" \
  -d "grant_type=authorization_code"
```

## 错误处理

### 常见问题

1. **连接失败**
   - 检查 access token 是否有效
   - 确认 IMAP 权限是否正确配置
   - 查看日志了解详细错误信息

2. **Token 过期**
   - Access token 通常 1 小时过期
   - 需要实现自动刷新机制
   - 或定期手动更新 token

3. **权限不足**
   - 确保应用有 `IMAP.AccessAsUser.All` 权限
   - 检查用户是否已授权

### 日志查看

系统会记录详细的连接和处理日志：
- 连接尝试和结果
- 邮件处理状态
- 错误信息和堆栈跟踪

## 安全注意事项

1. **Token 安全**
   - 不要在代码中硬编码 access token
   - 考虑使用环境变量或安全配置文件
   - 定期轮换 token

2. **网络安全**
   - 使用 SSL/TLS 加密连接
   - 验证服务器证书（生产环境）

3. **日志安全**
   - 避免在日志中记录敏感信息
   - 使用 `StrHelper::hideEmail()` 隐藏邮箱地址

## 更新和维护

### 更新库版本

```bash
composer update webklex/php-imap
```

### 检查兼容性

不同版本的 API 可能有变化，更新时注意：
- 配置参数格式
- 方法调用方式
- 错误处理机制

## 参考文档

- [Webklex/php-imap GitHub](https://github.com/Webklex/php-imap)
- [Microsoft Graph IMAP 文档](https://docs.microsoft.com/en-us/graph/api/resources/mail-api-overview)
- [OAuth 2.0 for IMAP](https://docs.microsoft.com/en-us/exchange/client-developer/legacy-protocols/how-to-authenticate-an-imap-pop-smtp-application-by-using-oauth)
