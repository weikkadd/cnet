#!/usr/bin/env python3
import os
import sys
import time
import platform
import stat
import urllib.request
import subprocess
import threading

# ── 日志控制（SHOW_LOG=true 才输出，默认静默）────────────────────────────────
SHOW_LOG = os.environ.get('SHOW_LOG', '').lower() in ('1', 'true', 'yes')

def log(*args):
    if SHOW_LOG:
        print(*args, flush=True)

def loge(*args):
    if SHOW_LOG:
        print(*args, file=sys.stderr, flush=True)

# ── 下载路径 ──────────────────────────────────────────────────────────────────
FILE_PATH = os.environ.get('FILE_PATH', '.')

# ── 架构检测 → 下载链接 ───────────────────────────────────────────────────────
BASE = 'https://github.com/dsadsadsss/cnet/releases/download/v1/'

def get_binary_url():
    plat = sys.platform          # 'linux' | 'freebsd' | ...
    arch = platform.machine()    # 'x86_64' | 'aarch64' | 'arm64' | ...

    plat_map = {'linux': 'linux', 'freebsd': 'freebsd'}
    arch_map  = {'x86_64': 'amd64', 'aarch64': 'arm64', 'arm64': 'arm64'}

    p = plat_map.get(plat)
    a = arch_map.get(arch)

    if not p or not a:
        print(f'[cnet] 不支持的平台/架构: {plat}/{arch}', file=sys.stderr)
        print('[cnet] 支持: linux/freebsd × amd64/arm64', file=sys.stderr)
        sys.exit(1)

    return f'{BASE}cnet-{p}-{a}'

# ── 下载文件（跟随重定向）────────────────────────────────────────────────────
def download(url, dest):
    log(f'[cnet] URL: {url}')
    log(f'[cnet] 目标: {dest}')

    req = urllib.request.Request(url, headers={'User-Agent': 'cnet-launcher/1.0'})
    with urllib.request.urlopen(req) as resp:
        total = int(resp.headers.get('Content-Length', 0))
        received = 0
        with open(dest, 'wb') as f:
            while True:
                chunk = resp.read(65536)
                if not chunk:
                    break
                f.write(chunk)
                received += len(chunk)
                if total and SHOW_LOG:
                    pct = received / total * 100
                    print(f'\r[cnet] 下载中... {pct:.1f}%', end='', flush=True)
    if SHOW_LOG:
        print()

# ── 构建子进程环境变量 ，这里添加参数或环境变量添加────────────────────────────────────────────────────────
def build_env():
    env = os.environ.copy()

    if not env.get('SERVER_PORT') and not env.get('PORT'):
        env['SERVER_PORT'] = '7860'

    defaults = {
        'TOKEN':    '123',
        'SUB_NAME': '',
        'SUB_URL':  '',
        'TOK':      '',
        'DOM':      '',
        'NSERVER':  '',
        'NKEY':     '',
        'APP_UUID': '',
        'APP_TLS':  'true',
    }

    for key, val in defaults.items():
        if not env.get(key) and val != '':
            env[key] = val

    return env

# ── 检查进程是否存活 ──────────────────────────────────────────────────────────
def is_alive(proc):
    if proc is None:
        return False
    return proc.poll() is None

# ── 启动子进程 ────────────────────────────────────────────────────────────────
def start_process(binary_path, env):
    port = env.get('SERVER_PORT') or env.get('PORT') or '7860'
    ts = time.strftime('%H:%M:%S')
    log(f'[cnet] [{ts}] 启动进程，PORT={port}')

    stdio = None if SHOW_LOG else subprocess.DEVNULL
    proc = subprocess.Popen(
        [binary_path],
        env=env,
        stdout=stdio,
        stderr=stdio,
    )
    return proc

# ── 守护线程 ──────────────────────────────────────────────────────────────────
child = None
child_lock = threading.Lock()

def guard(binary_path, env):
    global child
    with child_lock:
        if not is_alive(child):
            ts = time.strftime('%H:%M:%S')
            if child is not None:
                log(f'[cnet] [{ts}] 检测到进程已退出（PID={child.pid}），正在重启...')
            child = start_process(binary_path, env)

def watchdog(binary_path, env):
    while True:
        time.sleep(60)
        guard(binary_path, env)

# ── 主流程 ────────────────────────────────────────────────────────────────────
def main():
    os.makedirs(FILE_PATH, exist_ok=True)

    binary_path = os.path.realpath(os.path.join(FILE_PATH, 'cnet'))

    # 下载二进制（若不存在）
    if not os.path.exists(binary_path):
        url = get_binary_url()
        log('[cnet] 二进制不存在，开始下载')
        download(url, binary_path)
        os.chmod(binary_path, os.stat(binary_path).st_mode | stat.S_IXUSR | stat.S_IXGRP | stat.S_IXOTH)
        log('[cnet] 下载完成，权限已设置')
    else:
        log(f'[cnet] 已存在: {binary_path}')

    env = build_env()

    # 首次启动
    guard(binary_path, env)

    # 守护线程（每 60 秒检查一次）
    t = threading.Thread(target=watchdog, args=(binary_path, env), daemon=True)
    t.start()

    # 主线程等待，处理终止信号
    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        log('\n[cnet] 收到中断信号，终止子进程并退出')
        with child_lock:
            if child and is_alive(child):
                child.terminate()
                try:
                    child.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    child.kill()
        sys.exit(0)

if __name__ == '__main__':
    main()
