# CLAUDE.md

此文件为 Claude Code (claude.ai/code) 在此代码库中工作时提供指导。

## 项目概述

这是一个基于 **ThinkPHP 5.1 LTS 框架** 构建的 **支付处理系统 (PayV2)**。该系统处理支付交易，支持多种支付渠道、商户管理，以及用于 Cash App 通知的 Microsoft Outlook 集成。

## 开发命令

### Composer
```bash
composer install          # 安装依赖包
composer update           # 更新依赖包
```

### ThinkPHP 控制台
```bash
php think                 # 列出可用命令
php think run             # 启动内置 PHP 服务器（开发环境）
php think clear           # 清除缓存和日志
php think make:controller # 生成控制器
php think make:model      # 生成模型
```

### 自定义命令
```bash
php think syncEmailMicrosoft    # 同步 Microsoft Outlook 邮件
php think syncEmail            # 通用邮件同步
```

### 代码质量
```bash
phpstan analyse           # 运行静态分析（配置在 phpstan.neon）
```

## 架构

### 框架结构
- **框架**: ThinkPHP 5.1 LTS（需要 PHP ≥7.3）
- **模式**: 模块化 MVC 架构
- **数据库**: MySQL，表前缀 `pay_`，数据库名 `pay_v2`
- **入口文件**: `public/index.php`

### 模块结构
```
application/
├── api/          # 支付处理、通知、Microsoft Graph、Telegram
├── shop/         # 管理界面（商户、订单、设置）
├── index/        # 基础控制器和公共页面
├── common/       # 共享模型、服务和工具
└── command/      # 控制台命令
```

### 核心组件

#### API 模块 (`application/api/`)
- 支付处理接口
- 支付通知回调处理器
- Microsoft Graph API 集成
- Telegram 机器人功能
- 定时任务处理器

#### Shop 模块 (`application/shop/`)
- 商户管理界面
- 订单管理
- 系统配置
- 管理员认证

#### 公共服务 (`application/common/`)
- **模型**: 支付、商户、管理员模型
- **服务**: 支付处理、邮件处理、余额管理
- **辅助类**: AES 加密、二维码生成、Telegram 集成

## 配置

### 数据库
配置文件位于 `config/database.php`，使用 MySQL 连接 `pay_v2` 数据库。

### 环境
使用 `.env` 文件进行环境特定设置。主要应用配置查看 `config/app.php`。

### 第三方集成
- **Microsoft Graph**: 用于 Outlook 邮件同步
- **阿里云 OSS**: 文件存储服务
- **Telegram Bot**: 通知系统
- **二维码库**: 支付二维码生成

## 核心依赖

`composer.json` 中的必需包：
- `microsoft/microsoft-graph`: Microsoft Graph API 集成
- `webklex/php-imap`: 邮件处理
- `guzzlehttp/guzzle`: HTTP 客户端
- `aliyuncs/oss-sdk-php`: 阿里云对象存储
- `bacon/bacon-qr-code`: 二维码生成

## 开发说明

### 测试
未配置自动化测试套件，需要手动测试更改。

### 代码生成
使用 ThinkPHP 内置生成器：
- 控制器: `php think make:controller moduleName/ControllerName`
- 模型: `php think make:model ModelName`

### 安全特性
- `application/common/helpers/` 中的 AES 加密辅助类
- SQL 注入防护的参数过滤
- 基于令牌的 API 认证
- CORS 头处理

### 文件结构约定
- 控制器: 遵循 ThinkPHP 命名约定（驼峰命名法）
- 模型: 位于 `application/common/model/`
- 服务: 位于 `application/common/service/`
- 路由: 定义在 `route/route.php`

## Microsoft 邮件集成

系统包含专门的 Microsoft Outlook 邮件同步功能，特别用于 Cash App 支付通知。关键文件：
- `application/api/controller/Microsoft.php`: Microsoft Graph API 接口
- 自定义邮件同步控制台命令
- IMAP 和 Graph API 集成配置