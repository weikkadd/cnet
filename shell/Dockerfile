FROM alpine:3.19

WORKDIR /app

ENV FILE_PATH=/app \
    SHOW_LOG=false \
    SERVER_PORT=7860 \
    TOKEN=123 \
    SUB_NAME= \
    SUB_URL= \
    TOK= \
    DOM= \
    NSERVER= \
    NKEY= \
    APP_UUID= \
    APP_TLS=true

EXPOSE 7860

RUN cat > /app/start.sh << 'SCRIPT'
#!/bin/sh
set -e

# ── 日志控制 ──────────────────────────────────────────────────────────────────
show_log() {
  case "$SHOW_LOG" in
    1|true|yes|TRUE|YES) return 0 ;;
    *) return 1 ;;
  esac
}

log() {
  show_log && echo "[cnet] $*"
}

# ── 架构检测 → 下载链接 ───────────────────────────────────────────────────────
BASE="https://github.com/dsadsadsss/cnet/releases/download/v1"

get_binary_url() {
  PLAT="$(uname -s | tr '[:upper:]' '[:lower:]')"   # linux / freebsd
  ARCH="$(uname -m)"                                 # x86_64 / aarch64

  case "$ARCH" in
    x86_64)          ARCH="amd64" ;;
    aarch64|arm64)   ARCH="arm64" ;;
    *)
      echo "[cnet] 不支持的架构: $ARCH" >&2
      exit 1
      ;;
  esac

  case "$PLAT" in
    linux|freebsd) ;;
    *)
      echo "[cnet] 不支持的平台: $PLAT" >&2
      exit 1
      ;;
  esac

  echo "${BASE}/cnet-${PLAT}-${ARCH}"
}

# ── 下载文件（跟随重定向）────────────────────────────────────────────────────
download() {
  URL="$1"
  DEST="$2"
  log "URL: $URL"
  log "目标: $DEST"
  wget -q --show-progress -O "$DEST" "$URL" 2>&1 | (show_log && cat || cat > /dev/null)
  # 兜底：wget 不带 --show-progress 时静默
  if [ ! -s "$DEST" ]; then
    wget -q -O "$DEST" "$URL"
  fi
}

# ── 设置默认环境变量 ──────────────────────────────────────────────────────────
: "${SERVER_PORT:=${PORT:-7860}}"
: "${TOKEN:=123}"
: "${APP_TLS:=false}"

export SERVER_PORT TOKEN SUB_NAME SUB_URL TOK DOM NSERVER NKEY APP_UUID APP_TLS

# ── 确保目录存在 ──────────────────────────────────────────────────────────────
mkdir -p "${FILE_PATH:-.}"
BINARY="${FILE_PATH:-.}/cnet"

# ── 下载二进制（若不存在）────────────────────────────────────────────────────
if [ ! -f "$BINARY" ]; then
  log "二进制不存在，开始下载"
  URL="$(get_binary_url)"
  download "$URL" "$BINARY"
  chmod +x "$BINARY"
  log "下载完成，权限已设置"
else
  log "已存在: $BINARY"
fi

# ── 守护函数 ──────────────────────────────────────────────────────────────────
CHILD_PID=""

is_alive() {
  [ -n "$CHILD_PID" ] && kill -0 "$CHILD_PID" 2>/dev/null
}

start_process() {
  TS="$(date +%H:%M:%S)"
  log "[$TS] 启动进程，PORT=${SERVER_PORT}"
  if show_log; then
    "$BINARY" &
  else
    "$BINARY" > /dev/null 2>&1 &
  fi
  CHILD_PID=$!
}

# ── 信号处理 ──────────────────────────────────────────────────────────────────
cleanup() {
  log "收到终止信号，停止子进程"
  if is_alive; then
    kill "$CHILD_PID" 2>/dev/null
    wait "$CHILD_PID" 2>/dev/null
  fi
  exit 0
}
trap cleanup INT TERM

# ── 首次启动 ──────────────────────────────────────────────────────────────────
start_process

# ── 守护循环（每 60 秒检查一次）──────────────────────────────────────────────
while true; do
  sleep 60 &
  wait $!   # 可被信号中断
  if ! is_alive; then
    TS="$(date +%H:%M:%S)"
    log "[$TS] 检测到进程已退出（PID=${CHILD_PID}），正在重启..."
    start_process
  fi
done
SCRIPT

RUN chmod +x /app/start.sh

CMD ["/app/start.sh"]
