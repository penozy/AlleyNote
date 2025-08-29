#!/bin/bash

# 資料庫備份腳本
# 用途：建立 SQLite 資料庫的備份

set -e

# 處理命令列參數
if [ $# -eq 2 ]; then
    # 如果提供了2個參數：源資料庫路徑 和 目標備份檔案路徑
    DB_PATH="$1"
    BACKUP_FILE="$2"
    BACKUP_DIR="$(dirname "$BACKUP_FILE")"
elif [ $# -eq 1 ]; then
    # 如果只提供了1個參數：源資料庫路徑
    DB_PATH="$1"
    BACKUP_DIR="${BACKUP_DIR:-./database/backups}"
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_FILE="$BACKUP_DIR/backup_$TIMESTAMP.db"
else
    # 預設設定
    DB_PATH="${DB_PATH:-./database/alleynote.sqlite3}"
    BACKUP_DIR="${BACKUP_DIR:-./database/backups}"
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_FILE="$BACKUP_DIR/backup_$TIMESTAMP.db"
fi

# 檢查資料庫檔案是否存在
if [ ! -f "$DB_PATH" ]; then
    echo "錯誤: 資料庫檔案不存在: $DB_PATH" >&2
    exit 1
fi

# 建立備份目錄
mkdir -p "$BACKUP_DIR"

# 執行備份
echo "正在備份資料庫: $DB_PATH -> $BACKUP_FILE"
cp "$DB_PATH" "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    echo "資料庫備份完成: $BACKUP_FILE"
    
    # 清理舊的備份檔案 (保留最新10個) - 僅當使用預設備份目錄時
    if [[ "$BACKUP_DIR" == *"database/backups"* ]]; then
        cd "$BACKUP_DIR" && ls -t backup_*.db 2>/dev/null | tail -n +11 | xargs -r rm --
        echo "備份檔案清理完成"
    fi
else
    echo "錯誤: 資料庫備份失敗" >&2
    exit 1
fi