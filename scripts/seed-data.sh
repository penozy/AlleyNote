#!/bin/bash

# 資料庫 Seeder 執行腳本
# 用途：執行所有測試資料 Seeder

set -e

echo "🌱 開始執行測試資料 Seeder..."

# 執行使用者資料 Seeder
echo "👤 執行使用者資料 Seeder..."
./vendor/bin/phinx seed:run -s UsersSeeder

# 執行活動記錄資料 Seeder
echo "📊 執行活動記錄資料 Seeder..."
./vendor/bin/phinx seed:run -s UserActivityLogsSeeder

echo "✅ 所有測試資料 Seeder 執行完成！"

# 顯示資料統計
echo ""
echo "📈 資料統計："

php -r "
\$pdo = new PDO('sqlite:./database/alleynote.sqlite3');

// 使用者數量
\$stmt = \$pdo->query('SELECT COUNT(*) as count FROM users');
\$userCount = \$stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo \"- 使用者數量: \$userCount\n\";

// 活動記錄數量
\$stmt = \$pdo->query('SELECT COUNT(*) as count FROM user_activity_logs');
\$activityCount = \$stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo \"- 活動記錄數量: \$activityCount\n\";

// 活動類型分佈
echo \"- 活動類型分佈:\n\";
\$stmt = \$pdo->query('SELECT action_category, COUNT(*) as count FROM user_activity_logs GROUP BY action_category ORDER BY count DESC');
while (\$row = \$stmt->fetch(PDO::FETCH_ASSOC)) {
    echo \"  * {\$row['action_category']}: {\$row['count']} 筆\n\";
}
"

echo ""
echo "🔗 可用的測試帳號："
echo "- 管理員: admin / admin123"
echo "- 使用者1: john_doe / password123" 
echo "- 使用者2: jane_smith / securepass456"
echo "- 測試帳號1: test_user1 / testpass1"
echo "- 測試帳號2: test_user2 / testpass2"