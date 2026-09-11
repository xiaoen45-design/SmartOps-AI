#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")"

if command -v python3.12 >/dev/null 2>&1; then
  PYTHON_BIN="python3.12"
elif command -v python3 >/dev/null 2>&1; then
  PYTHON_BIN="python3"
else
  echo "Python 3 is not installed. Install Python 3.12, then run this file again."
  exit 1
fi

if ! "$PYTHON_BIN" - <<'PY'
import sys
if sys.version_info < (3, 10):
    raise SystemExit("Python 3.10 or newer is required")
if sys.version_info.releaselevel != "final":
    raise SystemExit("Use a stable Python release, preferably Python 3.12")
PY
then
  exit 1
fi

if [ ! -f .env ]; then
  cat > .env <<'ENV'
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASSWORD=
DB_NAME=smartops
DB_UNIX_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock
JSONL_PATH=../data/whole_pipeline_results.jsonl
AUTO_UPDATE_SQL_SNAPSHOT=true
MYSQLDUMP_PATH=/Applications/XAMPP/xamppfiles/bin/mysqldump
ENV
fi

PIDS="$(lsof -tiTCP:8001 -sTCP:LISTEN 2>/dev/null || true)"
if [ -n "$PIDS" ]; then
  for PID in $PIDS; do
    COMMAND="$(ps -p "$PID" -o command= 2>/dev/null || true)"
    if [[ "$COMMAND" == *uvicorn* ]] || [[ "$COMMAND" == *main:app* ]] || [[ "$COMMAND" == *smartops_fastapi* ]]; then
      echo "Stopping previous SmartOps FastAPI process $PID..."
      kill "$PID" 2>/dev/null || true
    else
      echo "Port 8001 is being used by another application: $COMMAND"
      echo "Close that application, then run this script again."
      exit 1
    fi
  done
  sleep 1
fi

SELECTED_VERSION="$($PYTHON_BIN -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")')"
if [ -d .venv ]; then
  VENV_VERSION="$(.venv/bin/python -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")' 2>/dev/null || true)"
  if [ "$VENV_VERSION" != "$SELECTED_VERSION" ]; then
    echo "Rebuilding the virtual environment for Python $SELECTED_VERSION..."
    rm -rf .venv
  fi
fi

[ -d .venv ] || "$PYTHON_BIN" -m venv .venv
source .venv/bin/activate
python -m pip install --disable-pip-version-check -q --upgrade pip
python -m pip install --disable-pip-version-check -q -r requirements.txt

if ! python - <<'PY'
from main import db, mysql_connection_options
connection = db()
try:
    with connection.cursor() as cursor:
        cursor.execute("SELECT DATABASE() AS db")
        row = cursor.fetchone()
    mode = "XAMPP Unix socket" if "unix_socket" in mysql_connection_options() else "TCP"
    print(f"Database connected: {row['db']} via {mode}")
finally:
    connection.close()
PY
then
  echo
  echo "FastAPI could not connect to XAMPP MySQL."
  echo "Confirm MySQL is started and smartops has been imported in phpMyAdmin."
  echo "The default macOS socket is /Applications/XAMPP/xamppfiles/var/mysql/mysql.sock"
  exit 1
fi

echo "Starting SmartOps FastAPI..."
echo "Health: http://127.0.0.1:8001/health"
echo "Docs:   http://127.0.0.1:8001/docs"
python -m uvicorn main:app --host 127.0.0.1 --port 8001 --loop asyncio
