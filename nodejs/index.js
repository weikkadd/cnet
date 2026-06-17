#!/usr/bin/env node
'use strict';

const https = require('https');
const fs = require('fs');
const path = require('path');
const os = require('os');
const { spawn } = require('child_process');

// ── 日志控制（SHOW_LOG=true 才输出，默认静默）────────────────────────────────
const SHOW_LOG = /^(1|true|yes)$/i.test(process.env.SHOW_LOG || '');
const log  = (...a) => SHOW_LOG && console.log(...a);
const loge = (...a) => SHOW_LOG && console.error(...a);

// ── 下载路径 ──────────────────────────────────────────────────────────────────
const FILE_PATH = process.env.FILE_PATH || '.';

// ── 架构检测 → 下载链接 ───────────────────────────────────────────────────────
const BASE = 'https://github.com/dsadsadsss/cnet/releases/download/v1/';

function getBinaryUrl() {
  const platform = os.platform();
  const arch = os.arch();

  const platMap = { linux: 'linux', freebsd: 'freebsd' };
  const archMap  = { x64: 'amd64', arm64: 'arm64' };

  const plat = platMap[platform];
  const arc  = archMap[arch];

  if (!plat || !arc) {
    console.error(`[cnet] 不支持的平台/架构: ${platform}/${arch}`);
    console.error('[cnet] 支持: linux/freebsd × amd64/arm64');
    process.exit(1);
  }

  return `${BASE}cnet-${plat}-${arc}`;
}

// ── 下载文件（跟随重定向）────────────────────────────────────────────────────
function download(url, dest) {
  return new Promise((resolve, reject) => {
    const follow = (u) => {
      https.get(u, { headers: { 'User-Agent': 'cnet-launcher/1.0' } }, (res) => {
        if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
          follow(res.headers.location);
          return;
        }
        if (res.statusCode !== 200) {
          reject(new Error(`HTTP ${res.statusCode} 下载失败: ${u}`));
          return;
        }
        const total = parseInt(res.headers['content-length'] || '0', 10);
        let received = 0;
        const out = fs.createWriteStream(dest);
        res.on('data', (chunk) => {
          received += chunk.length;
          if (total && SHOW_LOG) {
            const pct = ((received / total) * 100).toFixed(1);
            process.stdout.write(`\r[cnet] 下载中... ${pct}%`);
          }
        });
        res.pipe(out);
        out.on('finish', () => {
          if (SHOW_LOG) process.stdout.write('\n');
          resolve();
        });
        out.on('error', reject);
      }).on('error', reject);
    };
    follow(url);
  });
}

// ── 构建子进程环境变量 ────────────────────────────────────────────────────────
function buildEnv() {
  const env = Object.assign({}, process.env);

  if (!env.SERVER_PORT && !env.PORT) {
    env.SERVER_PORT = '7860';
  }

  const defaults = {
    TOKEN:    '123',
    SUB_NAME: '',
    SUB_URL:  '',
    TOK:      '',
    DOM:      '',
    NSERVER:  '',
    NKEY:     '',
    APP_UUID: '',
    APP_TLS:  'false',
  };

  for (const [key, def] of Object.entries(defaults)) {
    if (!env[key] && def !== '') {
      env[key] = def;
    }
  }

  return env;
}

// ── 检查进程是否存活 ──────────────────────────────────────────────────────────
function isAlive(pid) {
  if (!pid) return false;
  try {
    process.kill(pid, 0);
    return true;
  } catch {
    return false;
  }
}

// ── 启动子进程 ────────────────────────────────────────────────────────────────
function startProcess(binaryPath, env) {
  const port = env.SERVER_PORT || env.PORT || '7860';
  const ts = new Date().toLocaleTimeString();
  log(`[cnet] [${ts}] 启动进程，PORT=${port}`);

  const child = spawn(binaryPath, [], {
    env,
    stdio: SHOW_LOG ? 'inherit' : 'ignore',
    detached: false,
  });

  child.on('error', (err) => {
    loge(`[cnet] 启动失败: ${err.message}`);
  });

  child.on('exit', (code, signal) => {
    const ts2 = new Date().toLocaleTimeString();
    if (signal) {
      log(`[cnet] [${ts2}] 进程被信号终止: ${signal}`);
    } else {
      log(`[cnet] [${ts2}] 进程退出，code=${code}，等待下次守护检查重启`);
    }
  });

  return child;
}

// ── 主流程 ────────────────────────────────────────────────────────────────────
async function main() {
  fs.mkdirSync(FILE_PATH, { recursive: true });

  const binaryPath = path.resolve(path.join(FILE_PATH, 'cnet'));

  // 下载二进制（若不存在）
  if (!fs.existsSync(binaryPath)) {
    const url = getBinaryUrl();
    log(`[cnet] 二进制不存在，开始下载`);
    log(`[cnet] URL: ${url}`);
    log(`[cnet] 目标: ${binaryPath}`);
    await download(url, binaryPath);
    fs.chmodSync(binaryPath, 0o755);
    log('[cnet] 下载完成，权限已设置');
  } else {
    log(`[cnet] 已存在: ${binaryPath}`);
  }

  const env = buildEnv();
  let child = null;

  // ── 守护函数 ───────────────────────────────────────────────────────────────
  function guard() {
    if (!child || !isAlive(child.pid)) {
      const ts = new Date().toLocaleTimeString();
      if (child) log(`[cnet] [${ts}] 检测到进程已退出（PID=${child.pid}），正在重启...`);
      child = startProcess(binaryPath, env);
    }
  }

  // 首次启动
  guard();

  // 每 60 秒检查一次
  setInterval(guard, 60 * 1000);

  // 转发终止信号
  for (const sig of ['SIGINT', 'SIGTERM']) {
    process.on(sig, () => {
      log(`\n[cnet] 收到 ${sig}，终止子进程并退出`);
      if (child && isAlive(child.pid)) child.kill(sig);
      process.exit(0);
    });
  }
}

main().catch((err) => {
  loge('[cnet] 错误:', err.message);
  process.exit(1);
});
