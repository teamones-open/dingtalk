# Teamones DingTalk 3.0.0 — PHP 8.5 compatibility fork

基于 `mingyoung/dingtalk` **2.3.1** 的维护分支，保留 `EasyDingTalk\Application`
及原部门、用户、考勤、消息等客户端 API。不是上游未完成的 `3.x-dev`。

## 要求

- PHP **8.5.x**（Composer `~8.5.0`）
- curl、json、openssl、simplexml 扩展
- Guzzle 7、Promises 2、PSR-7 2
- Symfony Cache / HttpFoundation 6.4 或 7.4；Monolog 2 或 3
- 支持 PSR Simple Cache 1 / 2 / 3（已验证 1 和 3 组合）

## 安装与迁移

包名为 `teamones/dingtalk`，PHP 8.5 维护版本从 **3.0.0** 起步。
版本由 Git tag 提供，不在 composer.json 写死 version。
没有确认 Packagist 收录时，使用 VCS 仓库配置：

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/teamones-open/dingtalk"}
    ],
    "require": {
        "teamones/dingtalk": "^3.0"
    }
}
```

在应用测试分支中移除 `mingyoung/dingtalk` 的 require，加入新包约束，再执行
受控的 Composer update。两包共享 `EasyDingTalk` 命名空间，明确 conflict，
**不能同时安装**。不要用 replace 冒充完全二进制兼容的原包。

```php
use EasyDingTalk\Application;

$app = new Application([
    'corp_id' => 'your-corp-id',
    'app_key' => 'your-app-key',
    'app_secret' => 'your-app-secret',
]);

$departments = $app->department->list(null, true);
$users = $app->user->getDetailedUsers(42, 0, 100);
$schedules = $app->attendance->schedules('2026-10-23');
```

业务 URL、参数名、数组响应保留 2.3.1 行为。包本身不改变钉钉旧 API 的服务端
生命周期；旧接口是否仍对企业应用开放必须在授权测试环境核验。

## 兼容变更

- 移除 `overtrue/http 1.1.3` 依赖；基于同版源码维护私有
  `EasyDingTalk\Internal\Http` 兼容层，避免全局 Overtrue 命名空间冲突。
- 旧 `config`/`collection` helper 的具体类改为私有命名空间；外部若显式
  type-hint `Overtrue\Http\Support\Collection`，需要调整。
- 修 nullable、ArrayAccess、Serializable 魔术方法和异常 code 的 PHP 8.5 兼容。
- 修复嵌套配置 dotGet/dotSet 和上传资源处理；HTTP 栈保留配置的底层 handler。
- JSON/XML/raw/collection 响应转换有离线回归；XML 拒绝 DTD/实体声明。
- 保留原 Token 过期响应处理边界，**未新增写请求自动重放**。重试策略变更需
  单独做幂等性评审。
- 未加载全局 `Overtrue\Http` 别名，不修改其他 SDK。

## 测试

```text
composer install
composer test:strict
php tools/verify-im-contract.php /path/to/im/app/common/service/DingTalkService.php
```

测试引导显式开启 `E_ALL`，不会依赖容器对弃用的屏蔽。HTTP 测试使用 Guzzle
MockHandler，不需要 app_secret，不向真实钉钉发送消息。

3.0.0 的本地验证：现代与 PSR 1.x 两套依赖矩阵各 111 tests / 338 assertions；
IM 原服务类离线桥接各 12 项断言。真实钉钉 API 和完整 IM 业务接入未验收。
本地生成的详细报告位于 `output/php85-compatibility.html`（output 不入库）。

## 上游与许可

- EasyDingTalk 2.3.1：`74d12baff001494985e6f7c2fba875702c6a9535`
- 内部 HTTP 1.1.3：`6374e7486d637cd7f8dfa2b8a2f4d5ab055110b4`
- MIT；保留作者版权和许可，详见 `LICENSE`、`THIRD_PARTY_NOTICES.md` 和 `licenses/`。
