#!/usr/bin/env php
<?php

// ── 日志控制（SHOW_LOG=true 才输出，默认静默）────────────────────────────────
$SHOW_LOG = in_array(strtolower(getenv('SHOW_LOG') ?: ''), ['1', 'true', 'yes']);

function logInfo(...$args): void {
    global $SHOW_LOG;
    if ($SHOW_LOG) {
        echo implode(' ', $args) . "\n";
        flush();
    }
}

function logError(...$args): void {
    global $SHOW_LOG;
    if ($SHOW_LOG) {
        fwrite(STDERR, implode(' ', $args) . "\n");
    }
}

// ── 下载路径 ──────────────────────────────────────────────────────────────────
$FILE_PATH = getenv('FILE_PATH') ?: '.';

// ── 架构检测 → 下载链接 ───────────────────────────────────────────────────────
const BASE_URL = 'https://github.com/dsadsadsss/cnet/releases/download/v1/';

function getBinaryUrl(): string {
    $plat = PHP_OS_FAMILY === 'BSD' ? 'freebsd' : 'linux';

    $machine = php_uname('m');
    $archMap = [
        'x86_64'  => 'amd64',
        'aarch64' => 'arm64',
        'arm64'   => 'arm64',
    ];

    if (!isset($archMap[$machine])) {
        fwrite(STDERR, "[cnet] 不支持的平台/架构: {$plat}/{$machine}\n");
        fwrite(STDERR, "[cnet] 支持: linux/freebsd × amd64/arm64\n");
        exit(1);
    }

    return BASE_URL . "cnet-{$plat}-{$archMap[$machine]}";
}

// ── 下载文件（跟随重定向）────────────────────────────────────────────────────
function download(string $url, string $dest): void {
    logInfo("[cnet] URL: $url");
    logInfo("[cnet] 目标: $dest");

    $ctx = stream_context_create([
        'http' => [
            'follow_location' => 1,
            'max_redirects'   => 5,
            'header'          => 'User-Agent: cnet-launcher/1.0',
        ],
    ]);

    $in = fopen($url, 'rb', false, $ctx);
    if (!$in) {
        fwrite(STDERR, "[cnet] 下载失败: $url\n");
        exit(1);
    }

    $out = fopen($dest, 'wb');
    if (!$out) {
        fwrite(STDERR, "[cnet] 无法写入: $dest\n");
        exit(1);
    }

    $received = 0;
    while (!feof($in)) {
        $chunk = fread($in, 65536);
        if ($chunk === false) break;
        fwrite($out, $chunk);
        $received += strlen($chunk);
        logInfo("\r[cnet] 下载中... {$received} bytes");
    }

    fclose($in);
    fclose($out);
    logInfo("\n");
}

// ── 构建子进程环境变量 ────────────────────────────────────────────────────────
function buildEnv(): array {
    $env = getenv();

    if (empty($env['SERVER_PORT']) && empty($env['PORT'])) {
        $env['SERVER_PORT'] = '7860';
    }

    $defaults = [
        'TOKEN'    => '123',
        'SUB_NAME' => '',
        'SUB_URL'  => '',
        'TOK'      => '',
        'DOM'      => '',
        'NSERVER'  => '',
        'NKEY'     => '',
        'APP_UUID' => '',
        'APP_TLS'  => 'true',
    ];

    foreach ($defaults as $key => $val) {
        if (empty($env[$key]) && $val !== '') {
            $env[$key] = $val;
        }
    }

    return $env;
}

// ── 将 env 数组转为 KEY=VALUE 字符串数组（proc_open 用）──────────────────────
function envToArray(array $env): array {
    $result = [];
    foreach ($env as $k => $v) {
        $result[] = "$k=$v";
    }
    return $result;
}

// ── 启动子进程 ────────────────────────────────────────────────────────────────
function startProcess(string $binaryPath, array $env): mixed {
    global $SHOW_LOG;

    $port = $env['SERVER_PORT'] ?? $env['PORT'] ?? '7860';
    $ts   = date('H:i:s');
    logInfo("[cnet] [$ts] 启动进程，PORT=$port");

    $desc = $SHOW_LOG
        ? [STDIN, STDOUT, STDERR]
        : [STDIN, ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']];

    $proc = proc_open([$binaryPath], $desc, $pipes, null, $env);

    if ($proc === false) {
        fwrite(STDERR, "[cnet] 启动失败: $binaryPath\n");
        exit(1);
    }

    return $proc;
}

// ── 检查进程是否存活 ──────────────────────────────────────────────────────────
function isAlive(mixed $proc): bool {
    if ($proc === null) return false;
    $status = proc_get_status($proc);
    return $status['running'] ?? false;
}

// ── 主流程 ────────────────────────────────────────────────────────────────────
function main(): void {
    global $FILE_PATH;

    if (!is_dir($FILE_PATH)) {
        mkdir($FILE_PATH, 0755, true);
    }

    $binaryPath = realpath($FILE_PATH) . '/cnet';

    // 下载二进制（若不存在）
    if (!file_exists($binaryPath)) {
        $url = getBinaryUrl();
        logInfo('[cnet] 二进制不存在，开始下载');
        download($url, $binaryPath);
        chmod($binaryPath, 0755);
        logInfo('[cnet] 下载完成，权限已设置');
    } else {
        logInfo("[cnet] 已存在: $binaryPath");
    }

    $env  = buildEnv();
    $proc = startProcess($binaryPath, $env);

    // 注册退出处理
    register_shutdown_function(function () use (&$proc) {
        if (isAlive($proc)) {
            logInfo("\n[cnet] 终止子进程");
            proc_terminate($proc);
            // 等待最多 5 秒
            $deadline = time() + 5;
            while (isAlive($proc) && time() < $deadline) {
                usleep(100000);
            }
            if (isAlive($proc)) {
                proc_terminate($proc, 9); // SIGKILL
            }
            proc_close($proc);
        }
    });

    // 主循环：每 60 秒检查一次，进程退出则重启
    $lastCheck = time();
    while (true) {
        sleep(1);

        if (time() - $lastCheck >= 60) {
            $lastCheck = time();
            if (!isAlive($proc)) {
                $ts = date('H:i:s');
                logInfo("[cnet] [$ts] 检测到进程已退出，正在重启...");
                proc_close($proc);
                $proc = startProcess($binaryPath, $env);
            }
        }
    }
}

main();
