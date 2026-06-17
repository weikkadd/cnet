# cnet 启动器

ech 代理 + Nezha 监控 Agent + Cloudflare Tunnel 三合一启动器，支持 Node.js / Python / Shell 三个版本，自动下载对应架构二进制并守护运行。

ech代理软件可以使用这个 https://github.com/byJoey/ech-wk 

---

## 环境变量

### 基础设置

| 变量 | 默认值 | 说明 |
|---|---|---|
| `FILE_PATH` | `.`（当前目录） | 二进制文件下载保存路径 |
| `SHOW_LOG` | `false` | 是否显示日志，设为 `true` 开启 |

### WebSocket 代理

| 变量 | 默认值 | 说明 |
|---|---|---|
| `SERVER_PORT` | `7860` | 监听端口，优先于 `PORT` |
| `PORT` | `7860` | 监听端口（备用，`SERVER_PORT` 未设时生效） |
| `TOKEN` | `123` | ech密钥 |
| `SUB_NAME` | —— | 订阅节点名称，不需要上传节点可以不填 |
| `SUB_URL` | —— | 订阅上传地址，无服务器可以不填 |
| `DOM` | —— |  隧道域名，同样可以不填 |

### Nezha 监控 Agent

| 变量 | 默认值 | 说明 |
|---|---|---|
| `NSERVER` | —— | Nezha 服务端地址，如 `nezha.example.com:5555` |
| `NKEY` | —— | Nezha Agent 密钥（client_secret） |
| `APP_UUID` | —— | Agent UUID，不填则自动生成，可以直接复制哪吒面板上的改一下 |
| `APP_TLS` | `true` | 是否启用 TLS 连接 Nezha，设为 `false` 开启 |

### Cloudflare Tunnel

| 变量 | 默认值 | 说明 |
|---|---|---|
| `TOK` | —— | Cloudflare Tunnel Token（`不填则不启动隧道` ） |

---

## 支持架构

| 平台 | 架构 | 二进制 |
|---|---|---|
| Linux | x86_64 (amd64) | `cnet-linux-amd64` |
| Linux | aarch64 (arm64) | `cnet-linux-arm64` |
| FreeBSD | x86_64 (amd64) | `cnet-freebsd-amd64` |
| FreeBSD | aarch64 (arm64) | `cnet-freebsd-arm64` |

启动时自动检测当前平台和架构并下载对应版本，已存在则跳过下载。

---

## 快速启动

### Node.js

```bash
node index.js
```

### Python

```bash
python3 index.py
```

### Shell

```bash
chmod +x start.sh
./start.sh
```

### Docker

```bash
# Shell 版（最小镜像）
docker build -f Dockerfile -t cnet .
docker run -d -p 7860:7860 cnet

# Node.js 版
docker build -f Dockerfile -t cnet .
docker run -d -p 7860:7860 cnet

# Python 版
docker build -f Dockerfile -t cnet .
docker run -d -p 7860:7860 cnet
```

---

## 使用示例

### 仅 WebSocket 代理

```bash
SERVER_PORT=8080 TOKEN=mysecret node index.js
```

### WebSocket 代理 + Nezha 监控

```bash
SERVER_PORT=7860 \
TOKEN=mysecret \
NSERVER=nezha.example.com:5555 \
NKEY=your-agent-key \
APP_TLS=true \
python3 app.py
```

### 全功能（代理 + Nezha + Cloudflare Tunnel）

```bash
SERVER_PORT=7860 \
TOKEN=mysecret \
SUB_NAME=mynode \
SUB_URL=https://example.com/upload \
NSERVER=nezha.example.com:5555 \
NKEY=your-agent-key \
APP_TLS=true \
TOK=eyJhIjoixxxx... \
SHOW_LOG=true \
./start.sh
```

### Docker 全功能

```bash
docker run -d \
  -e SERVER_PORT=7860 \
  -e TOKEN=mysecret \
  -e SUB_NAME=mynode \
  -e SUB_URL=https://example.com/upload \
  -e NSERVER=nezha.example.com:5555 \
  -e NKEY=your-agent-key \
  -e APP_TLS=true \
  -e TOK=eyJhIjoixxxx... \
  -e SHOW_LOG=true \
  -v cnet-data:/app \
  -p 7860:7860 \
  cnet
```

---

## 守护机制

启动器内置守护逻辑，无需 systemd / supervisor：

- 启动时检查进程是否存在，不存在则启动
- 每 **60 秒**自动检查一次进程状态
- 进程意外退出后自动重启
- 收到 `SIGINT` / `SIGTERM` 时优雅终止子进程后退出
