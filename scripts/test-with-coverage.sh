#!/bin/bash

# 設定測試環境變數
export JWT_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC5/t5fzVEF1234
abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/=
-----END PRIVATE KEY-----"

export JWT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuf7eX81RBd1234abcdef
ghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/=QIDAQAB
-----END PUBLIC KEY-----"

echo "🔧 設定測試環境變數完成"
echo "📊 執行完整測試套件與覆蓋率分析..."

# 執行完整測試與覆蓋率
./vendor/bin/phpunit --coverage-text --coverage-html=coverage-reports --stop-on-error