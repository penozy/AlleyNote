#!/bin/bash

# 資料庫還原腳本
# 用途：從備份檔案還原 SQLite 資料庫

set -e

# 顯示使用說明
show_help() {
    echo "用途: 從備份檔案還原 SQLite 資料庫"
    echo "使用方法:"
    echo "  $0 [backup_file] [target_db_path]"
    echo ""
    echo "範例:"
    echo "  $0 ./database/backups/backup_20240829_143000.db ./database/alleynote.sqlite3"
    echo "  $0 ./database/backups/backup_20240829_143000.db"
    echo "  BACKUP_FILE=./backups/latest.db $0"
    echo ""
    echo "環境變數:"
    echo "  DB_PATH     - 目標資料庫路徑 (預設: ./database/alleynote.sqlite3)"
    echo "  BACKUP_FILE - 要還原的備份檔案"
    echo "  BACKUP_DIR  - 備份目錄 (預設: ./database/backups)"
}

# 解析參數
if [[ $# -eq 2 ]]; then
    # 兩個參數：備份檔案路徑 和 目標資料庫路徑
    BACKUP_FILE="$1"
    DB_PATH="$2"
elif [[ $# -eq 1 ]]; then
    # 一個參數：備份檔案路徑
    BACKUP_FILE="$1"
    DB_PATH="${DB_PATH:-./database/alleynote.sqlite3}"
elif [[ $# -eq 0 ]]; then
    # 無參數：使用環境變數或預設值
    DB_PATH="${DB_PATH:-./database/alleynote.sqlite3}"
    BACKUP_FILE="${BACKUP_FILE:-}"
    BACKUP_DIR="${BACKUP_DIR:-./database/backups}"
else
    show_help
    exit 1
fi

# 如果沒有指定備份檔案，使用最新的
if [[ -z "$BACKUP_FILE" ]]; then
    if [[ -d "$BACKUP_DIR" ]]; then
        BACKUP_FILE=$(ls -t "$BACKUP_DIR"/backup_*.db 2>/dev/null | head -n1)
        if [[ -z "$BACKUP_FILE" ]]; then
            echo "錯誤: 在 $BACKUP_DIR 中找不到備份檔案" >&2
            exit 1
        fi
        echo "使用最新的備份檔案: $BACKUP_FILE"
    else
        echo "錯誤: 備份目錄不存在: $BACKUP_DIR" >&2
        exit 1
    fi
fi

# 檢查備份檔案是否存在
if [[ ! -f "$BACKUP_FILE" ]]; then
    echo "錯誤: 備份檔案不存在: $BACKUP_FILE" >&2
    exit 1
fi

# 檢查備份檔案是否為有效的 SQLite 資料庫
if ! file "$BACKUP_FILE" | grep -q "SQLite"; then
    echo "錯誤: 指定的檔案不是有效的 SQLite 資料庫: $BACKUP_FILE" >&2
    exit 1
fi

# 建立目標資料庫目錄
DB_DIR=$(dirname "$DB_PATH")
mkdir -p "$DB_DIR"

# 如果目標資料庫存在，先做備份
if [[ -f "$DB_PATH" ]]; then
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    CURRENT_BACKUP="$DB_DIR/restore_backup_$TIMESTAMP.db"
    echo "備份目前的資料庫: $DB_PATH -> $CURRENT_BACKUP"
    cp "$DB_PATH" "$CURRENT_BACKUP"
fi

# 執行還原
echo "正在還原資料庫: $BACKUP_FILE -> $DB_PATH"
cp "$BACKUP_FILE" "$DB_PATH"

if [[ $? -eq 0 ]]; then
    echo "資料庫還原完成: $DB_PATH"
    
    # 驗證還原的資料庫
    if sqlite3 "$DB_PATH" "SELECT count(*) FROM sqlite_master;" >/dev/null 2>&1; then
        echo "資料庫完整性檢查通過"
    else
        echo "警告: 還原的資料庫可能有問題" >&2
    fi
else
    echo "錯誤: 資料庫還原失敗" >&2
    exit 1
fi