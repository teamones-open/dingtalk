<?php
/** Offline bridge against an unchanged IM DingTalkService source file. */
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(static function ($level, $message, $file, $line) {
    if (error_reporting() & $level) { throw new ErrorException($message, 0, $level, $file, $line); }
    return false;
});
require getenv('SDK_VENDOR_AUTOLOAD') ?: dirname(__DIR__).'/vendor/autoload.php';
$source = $argv[1] ?? '';
if (!is_file($source)) { throw new InvalidArgumentException('Pass the IM DingTalkService.php path'); }
require $source;

// Only these two helpers are used by the tested read-only code paths.
function S($key, ...$values) {
    static $cache = [];
    if ($values) { $cache[$key] = $values[0]; return true; }
    return $cache[$key] ?? null;
}
function get_format_date($timestamp, $type) { return date($type === 0 ? 'Y-m-d' : 'Y-m-d H:i:s', $timestamp); }

$history = [];
$response = static fn (array $data) => new GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], json_encode($data));
$mock = new GuzzleHttp\Handler\MockHandler([
    $response(['errcode' => 0, 'result' => ['schedules' => []]]),
    $response(['errcode' => 0, 'userlist' => [['userid' => 'test-1']], 'hasMore' => true]),
    $response(['errcode' => 0, 'userlist' => [['userid' => 'test-2']], 'hasMore' => false]),
    $response(['errcode' => 0, 'department' => [['id' => 42]]]),
    $response(['errcode' => 0, 'userlist' => [['userid' => 'test-3', 'mobile' => 'test-mobile']], 'hasMore' => false]),
]);
$stack = GuzzleHttp\HandlerStack::create($mock);
$stack->push(GuzzleHttp\Middleware::history($history));
$service = new common\service\DingTalkService();
$service->setConfig(['corp_id' => 'test-corp', 'app_key' => 'test-key', 'app_secret' => 'test-secret', 'http' => ['handler' => $stack]]);
$service->init();
$appProperty = new ReflectionProperty($service, 'dingTalkApp');
$app = $appProperty->getValue($service);
$cache = new Symfony\Component\Cache\Psr16Cache(new Symfony\Component\Cache\Adapter\ArrayAdapter());
$cache->set('access_token.test-key', ['access_token' => 'test-token', 'expires_in' => 7200]);
$app['cache'] = $cache;
$assertions = 0;
$check = static function ($condition, $message) use (&$assertions) {
    ++$assertions;
    if (!$condition) { throw new RuntimeException($message); }
};
$check($app instanceof EasyDingTalk\Application, 'Application construction failed');
$check($service->getSchedulingTime('2026-10-23')['errcode'] === 0, 'Scheduling return format changed');
$method = new ReflectionMethod($service, 'getDepartmentUserList');
$users = [];
$args = [&$users, 42, 0, 100];
$method->invokeArgs($service, $args);
$check(array_column($users, 'userid') === ['test-1', 'test-2'], 'Pagination behavior changed');
parse_str($history[2]['request']->getUri()->getQuery(), $pageQuery);
$check($pageQuery['offset'] === '100', 'Next pagination offset changed');
$map = $service->generateDingTalkCache();
$check($map['test-mobile']['userid'] === 'test-3', 'User cache shape changed');
$check($service->generateDingTalkCache() === $map, 'Same-day cache behavior changed');
$check(count($history) === 5, 'Unexpected request count or cache miss');
foreach ($history as $item) {
    parse_str($item['request']->getUri()->getQuery(), $query);
    $check(($query['access_token'] ?? '') === 'test-token', 'Access token missing');
}
$result = ['php' => PHP_VERSION, 'assertions' => $assertions, 'status' => 'PASS',
    'source_sha256' => hash_file('sha256', $source),
    'checks' => ['unchanged IM setConfig/init', 'scheduling response', 'user pagination', 'user cache shape', 'same-day cache', 'access-token middleware'],
    'scope' => 'Mock HTTP, in-memory cache; no application bootstrap/database/real DingTalk request'];
$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
if (isset($argv[2])) { file_put_contents($argv[2], $json); }
echo $json;
