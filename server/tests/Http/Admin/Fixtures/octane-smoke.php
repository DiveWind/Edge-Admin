<?php

/*
|--------------------------------------------------------------------------
| 常驻内存（Octane）下的冒烟请求：检查请求之间的状态泄露（设计文档 §10.3、§10.5）
|--------------------------------------------------------------------------
|
| php tests/Http/Admin/Fixtures/octane-smoke.php worker   在本进程中用 Octane 的 Worker 处理全部请求（同一个应用实例，
|                                                          每个请求一个沙箱，与 FrankenPHP / RoadRunner 的 worker 相同）
| php tests/Http/Admin/Fixtures/octane-smoke.php serve    用 octane:start 起 FrankenPHP（2 个 worker），经 HTTP 发同一组请求；
|                                                          需要 frankenphp 可执行文件（server/frankenphp 或 PATH 中）
|
| 两种方式都在临时目录中建一个 SQLite 库（子进程执行 setup：迁移、core:sync 与测试账号），结束后删除；不读取 .env 中的数据库。
| 输出 JSON：{ passed: [检查名], failed: [{ check, detail }] }，有失败时退出码为 1。
|
| 检查的是“上一个请求留下的东西不能影响下一个请求”：登录状态、数据范围、授权变更立即生效（含其他 worker）、语言、
| 请求 ID（Context）、响应后写入的操作日志（defer）、停用账号立即失效，以及 spatie 的权限集合在请求之间被清空。
|
*/

use App\Core\System\Models\{Role, User};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Sleep;
use Laravel\Octane\{ApplicationFactory, RequestContext, Worker};
use Laravel\Octane\Testing\Fakes\FakeClient;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Process\Process;

$basePath = dirname(__DIR__, 4);

require $basePath.'/vendor/autoload.php';

const ORIGIN = 'http://localhost:5999';

$mode = $argv[1] ?? '';

/*
|--------------------------------------------------------------------------
| 数据（setup 子进程）
|--------------------------------------------------------------------------
*/

if ($mode === 'setup') {
    $app = require $basePath.'/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    if ($kernel->call('migrate:fresh', ['--seed' => true, '--force' => true]) !== 0) {
        fwrite(STDERR, $kernel->output());

        exit(1);
    }

    $grant = fn (Role $role): Role => $role->givePermissionTo(['system', 'system.user']);
    $self = $grant(Role::factory()->create(['name' => 'viewer', 'data_scope' => 'self']));
    $all = $grant(Role::factory()->create(['name' => 'all-viewer', 'data_scope' => 'all']));

    $alice = User::factory()->create(['username' => 'alice'])->assignRole($self);
    User::factory()->create(['username' => 'bob'])->assignRole($all);
    User::factory()->create(['username' => 'carol', 'created_by' => $alice->id]);
    User::factory()->create(['username' => 'dave']);

    echo json_encode(['viewer' => $self->id, 'bob' => User::query()->where('username', 'bob')->value('id')]);

    exit(0);
}

/*
|--------------------------------------------------------------------------
| 环境：本脚本与它启动的进程共用
|--------------------------------------------------------------------------
*/

$temporary = (string) tempnam(sys_get_temp_dir(), 'octane-smoke-');
$database = $temporary.'.sqlite';
touch($database);

$env = [
    'APP_ENV' => 'local',                  // 不是 testing：CSRF 校验照常生效
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'APP_LOCALE' => 'en',
    'APP_TIMEZONE' => 'UTC',
    'BCRYPT_ROUNDS' => '4',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $database,
    'DB_URL' => '',
    'CACHE_STORE' => 'database',           // 多个 worker 共享（spatie 的权限缓存、限流）
    'SESSION_DRIVER' => 'database',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'LOG_CHANNEL' => 'stderr',
    'CORE_APPS' => 'admin',
    'CORE_ADMIN_PREFIX' => 'api/admin',
    'CORE_ADMIN_RATE_LIMIT' => '1000',
    'CORE_AUTH_LOGIN_RATE_LIMIT' => '100',
    'SANCTUM_STATEFUL_DOMAINS' => 'localhost:5999',
    'OCTANE_STATE_FILE' => $database.'.octane.json',
];

foreach ($env as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $_SERVER[$name] = $value;
}

$cleanup = function () use ($temporary): void {
    foreach (glob($temporary.'*') ?: [] as $file) {
        new Filesystem()->delete($file);
    }
};

$setup = new Process([PHP_BINARY, __FILE__, 'setup'], $basePath, $env, null, 120);
$setup->run();

if (! $setup->isSuccessful()) {
    fwrite(STDERR, $setup->getOutput().$setup->getErrorOutput());
    $cleanup();

    exit(1);
}

['viewer' => $viewerId, 'bob' => $bobId] = json_decode($setup->getOutput(), true);

/*
|--------------------------------------------------------------------------
| 发送请求：worker（进程内）或 HTTP（FrankenPHP）
|--------------------------------------------------------------------------
|
| send() 返回 { status, headers（小写名 => 值）, json, cookies（名 => 值，null 为已删除） }。
|
*/

$server = null;
$worker = null;

if ($mode === 'worker') {
    // 与 RoadRunner / FrankenPHP 的 worker 相同：不是命令行环境（spatie 的 Octane 监听等按此注册）
    putenv('APP_RUNNING_IN_CONSOLE=false');
    $_ENV['APP_RUNNING_IN_CONSOLE'] = $_SERVER['APP_RUNNING_IN_CONSOLE'] = 'false';

    $client = new FakeClient([]);
    $worker = new Worker(new ApplicationFactory($basePath), $client);
    $worker->boot();

    $send = function (string $method, string $uri, array $cookies, array $headers, ?string $body) use ($worker, $client): array {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $server['CONTENT_TYPE'] = 'application/json';

        $request = Request::create('http://localhost'.$uri, $method, [], $cookies, [], $server, $body);
        $worker->handle($request, new RequestContext(['request' => $request]));

        if ($client->errors !== []) {
            throw new RuntimeException(implode("\n", $client->errors));
        }

        $response = array_pop($client->responses);
        $jar = [];
        foreach ($response->headers->getCookies() as $cookie) {
            /** @var Cookie $cookie */
            $jar[$cookie->getName()] = $cookie->isCleared() ? null : $cookie->getValue();
        }

        return [
            'status' => $response->getStatusCode(),
            'headers' => array_map(fn (array $values): string => (string) end($values), $response->headers->all()),
            'json' => json_decode((string) $response->getContent(), true),
            'cookies' => $jar,
        ];
    };
} elseif ($mode === 'serve') {
    $port = (int) (getenv('OCTANE_SMOKE_PORT') ?: 8199);
    $server = new Process(
        [PHP_BINARY, 'artisan', 'octane:start', '--server=frankenphp', '--host=127.0.0.1', "--port={$port}", '--admin-port='.($port + 1), '--workers=2', '--max-requests=1000'],
        $basePath, $env, null, null,
    );
    $server->start();

    $ready = false;
    for ($deadline = microtime(true) + 60; microtime(true) < $deadline && $server->isRunning();) {
        if (@file_get_contents("http://127.0.0.1:{$port}/up") !== false) {
            $ready = true;
            break;
        }
        Sleep::usleep(200_000);
    }

    if (! $ready) {
        fwrite(STDERR, "Octane did not start:\n".$server->getOutput().$server->getErrorOutput());
        $server->stop(5);
        $cleanup();

        exit(1);
    }

    $send = function (string $method, string $uri, array $cookies, array $headers, ?string $body) use ($port): array {
        $lines = ['Content-Type: application/json'];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        if ($cookies !== []) {
            $lines[] = 'Cookie: '.implode('; ', array_map(fn (string $name, string $value): string => $name.'='.rawurlencode($value), array_keys($cookies), $cookies));
        }

        $responseHeaders = [];
        $jar = [];
        $curl = curl_init("http://127.0.0.1:{$port}{$uri}");
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$responseHeaders, &$jar): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = array_map(trim(...), explode(':', $line, 2));
                    $name = strtolower($name);
                    $responseHeaders[$name] = $value;

                    if ($name === 'set-cookie') {
                        $cookie = Cookie::fromString($value, decode: true);
                        $jar[$cookie->getName()] = $cookie->isCleared() ? null : $cookie->getValue();
                    }
                }

                return strlen($line);
            },
        ]);
        $content = curl_exec($curl);

        return [
            'status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
            'headers' => $responseHeaders,
            'json' => json_decode(is_string($content) ? $content : '', true),
            'cookies' => $jar,
        ];
    };
} else {
    fwrite(STDERR, "Usage: php octane-smoke.php worker|serve\n");
    $cleanup();

    exit(2);
}

/*
|--------------------------------------------------------------------------
| 浏览器：每个“浏览器”有自己的 Cookie（会话、XSRF-TOKEN），与 tests/Http/Admin/VbenClient.php 的做法相同
|--------------------------------------------------------------------------
*/

$jars = [];

$call = function (string $browser, string $method, string $path, ?array $data = null, array $headers = []) use (&$jars, $send): array {
    $jars[$browser] ??= [];
    $headers = ['Accept' => 'application/json', 'Referer' => ORIGIN.'/', ...$headers];

    if ($method !== 'GET' && isset($jars[$browser]['XSRF-TOKEN'])) {
        $headers['X-XSRF-TOKEN'] = $jars[$browser]['XSRF-TOKEN'];
    }

    $response = $send($method, '/api/admin/'.$path, array_filter($jars[$browser], is_string(...)), $headers, $data === null ? null : (string) json_encode($data));

    foreach ($response['cookies'] as $name => $value) {
        $jars[$browser][$name] = $value;
    }

    return $response;
};

$login = function (string $browser, string $username) use ($call): int {
    $call($browser, 'GET', 'sanctum/csrf-cookie');

    return $call($browser, 'POST', 'login', ['username' => $username, 'password' => 'password'])['status'];
};

/*
|--------------------------------------------------------------------------
| 检查
|--------------------------------------------------------------------------
*/

$passed = [];
$failed = [];

$check = function (string $name, bool $ok, mixed $detail = null) use (&$passed, &$failed): void {
    if ($ok) {
        $passed[] = $name;
    } else {
        $failed[] = ['check' => $name, 'detail' => $detail];
    }
};

$usernames = fn (array $response): array => array_column($response['json']['data']['items'] ?? [], 'username');

try {
    foreach (['admin', 'alice', 'bob'] as $user) {
        $check("{$user} logs in", ($status = $login($user, $user)) === 200, $status);
    }

    // 登录状态：交替请求，每个请求只看到自己的用户；没有 Cookie 的请求是未登录（守卫不残留上一个请求的用户）
    foreach (['alice', 'bob', 'guest', 'admin', 'guest', 'alice'] as $browser) {
        $response = $call($browser, 'GET', 'user/info');
        $expected = $browser === 'guest' ? 401 : 200;
        $check("{$browser} gets only its own user info", $response['status'] === $expected
            && ($browser === 'guest' || ($response['json']['data']['username'] ?? null) === $browser), $response['status']);
    }

    // 数据范围：仅本人的 alice 只看到自己与自己创建的用户，范围为全部的 bob 看到全部，交替请求不串
    foreach (['alice', 'bob', 'alice', 'bob'] as $browser) {
        $names = $usernames($call($browser, 'GET', 'system/users?pageSize=100'));
        sort($names);
        $expected = $browser === 'alice' ? ['alice', 'carol'] : ['admin', 'alice', 'bob', 'carol', 'dave', 'disabled'];
        $check("{$browser} lists the users within its data scope", $names === $expected, $names);
    }

    // 语言：Accept-Language 只作用于本请求
    foreach (['zh-CN', null, 'zh-CN', null] as $index => $language) {
        $call('guest-'.$index, 'GET', 'sanctum/csrf-cookie');
        $response = $call('guest-'.$index, 'POST', 'login', [], $language === null ? [] : ['Accept-Language' => $language]);
        $message = (string) ($response['json']['message'] ?? '');
        $chinese = preg_match('/\p{Han}/u', $message) === 1;
        $check('the language of request '.$index.' is '.($language ?? 'the default'), $response['status'] === 422 && $chinese === ($language !== null), $message);
    }

    // 请求 ID（Context）：每个请求自己的 ID，传入的 ID 只用于本请求
    $first = $call('alice', 'GET', 'user/info', null, ['X-Request-Id' => 'smoke-request-0001'])['headers']['x-request-id'] ?? null;
    $second = $call('alice', 'GET', 'user/info')['headers']['x-request-id'] ?? null;
    $check('the request id is per request', $first === 'smoke-request-0001' && is_string($second) && $second !== $first, [$first, $second]);

    // 授权变更立即生效：收回 viewer 的 system.user 后，alice 的下一个请求（可能落在另一个 worker）就没有这个权限码
    $codes = $call('alice', 'GET', 'auth/codes')['json']['data'] ?? [];
    $check('alice has system.user before the change', in_array('system.user', $codes, true), $codes);

    $update = $call('admin', 'PUT', "system/roles/{$viewerId}", ['name' => 'viewer', 'title' => 'Viewer', 'permissions' => []]);
    $check('admin revokes the permissions of the viewer role', $update['status'] === 200, $update['json']);

    foreach (range(1, 4) as $attempt) {
        $codes = $call('alice', 'GET', 'auth/codes')['json']['data'] ?? null;
        $check("alice no longer has system.user (request {$attempt})", is_array($codes) && ! in_array('system.user', $codes, true), $codes);
        $check("alice cannot list users any more (request {$attempt})", $call('alice', 'GET', 'system/users')['status'] === 403);
    }

    // 操作日志：在响应发送之后写入（defer），带的是那个请求的 ID
    $logs = $call('admin', 'GET', 'monitor/operation-logs?filter[description]=admin.system.roles.update');
    $requestIds = array_column($logs['json']['data']['items'] ?? [], 'request_id');
    $check('the operation log is written after the response with its own request id', $requestIds === [$update['headers']['x-request-id'] ?? null], [$requestIds, $update['headers']['x-request-id'] ?? null]);

    // 停用账号立即失效（下一个请求即 401），其他人不受影响
    $disable = $call('admin', 'PUT', "system/users/{$bobId}/status", ['status' => 'disabled']);
    $check('admin disables bob', $disable['status'] === 200, $disable['json']);
    foreach (range(1, 2) as $attempt) {
        $check("bob is signed out (request {$attempt})", $call('bob', 'GET', 'user/info')['status'] === 401);
        $check("alice is still signed in (request {$attempt})", $call('alice', 'GET', 'user/info')['status'] === 200);
    }

    // spatie 的 PermissionRegistrar 是单例，启动时不解析（register_permission_check_method 为 false）：每个请求在自己的沙箱中
    // 解析一个、随沙箱丢弃。以后若在启动时解析（预热、启动时读取权限），它会常驻在 worker 中，这时由 spatie 的 Octane 监听
    // （config/permission.php 的 register_octane_reset_listener）在每个请求结束时清空内存中的集合：两种情况下 worker 中都不留集合
    if ($worker instanceof Worker) {
        $call('alice', 'GET', 'auth/codes');
        $app = $worker->application();
        $permissions = $app->resolved(PermissionRegistrar::class) ? (fn () => $this->permissions)->call($app->make(PermissionRegistrar::class)) : null;
        $check('the worker keeps no permission collection between requests', $permissions === null, gettype($permissions));
    }
} catch (Throwable $e) {
    $failed[] = ['check' => 'unexpected error', 'detail' => $e::class.': '.$e->getMessage()];
} finally {
    $worker?->terminate();
    $server?->stop(10);
    $cleanup();
}

echo json_encode(['passed' => $passed, 'failed' => $failed], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";

if ($failed !== [] && $server instanceof Process) {
    fwrite(STDERR, $server->getOutput().$server->getErrorOutput());
}

exit($failed === [] ? 0 : 1);
