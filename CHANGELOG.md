# Changelog

## 3.0.0 — 2026-10-23

Teamones PHP 8.5 维护版本从 3.0.0 起步，与其他 8.5 包版本线保持一致。
这不是 mingyoung/dingtalk 上游未完成的 3.x-dev 实现。

### 基线

- EasyDingTalk 官方 2.3.1，commit `74d12baff001494985e6f7c2fba875702c6a9535`。
- 内部 HTTP 层基于 overtrue/http 1.1.3，commit `6374e7486d637cd7f8dfa2b8a2f4d5ab055110b4`。
- 保留 EasyDingTalk 命名空间、Application 及业务客户端 URL、参数形式。

### 兼容改造

- 要求 PHP 8.5.x，HTTP 依赖升级为 Guzzle 7 / Promises 2 / PSR-7 2。
- 移除 overtrue/http 依赖；维护 SDK 私有 HTTP 兼容层，不接管全局 Overtrue 类。
- 支持 Symfony 6.4 / 7.4、Monolog 2 / 3、PSR Simple Cache 1 / 2 / 3。
- 修 nullable、ArrayAccess、序列化、嵌套配置、上传资源和 HTTP handler 注入。
- 补 XML 转换，拒绝 DTD / 实体声明；保留版权与 MIT 许可。
- 修正上游测试中的调用错误，增加 IM 接口、Token、OAuth、回调和机器人离线回归。

### 升级边界

- 包名为 teamones/dingtalk；与 mingyoung/dingtalk 冲突，不可同时安装。
- 显式引用 Overtrue Collection 具体类型的代码需要调整为 SDK 私有类型。
- 未新增写请求自动重放，不改变旧 Token 过期返回边界。
- IM 全依赖升级会联动 Symfony 等包，必须另行做应用集成验收。
- 本地两套依赖矩阵各 111 用例 / 338 断言通过；IM 桥接各 12 断言通过。
- 未调用真实钉钉 API；不保证旧服务端接口仍对所有应用开放。
