#!/bin/bash

# Redis 快取管理腳本
# 提供快取暖機、監控和維護功能

set -e

# 顏色定義
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 配置
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
DOCKER_COMPOSE_CMD="sudo docker compose"

# 函數：顯示使用說明
show_help() {
    echo -e "${BLUE}Redis 快取管理工具${NC}"
    echo ""
    echo "使用方法: $0 [命令] [選項]"
    echo ""
    echo "可用命令:"
    echo -e "  ${GREEN}warmup${NC}      執行快取暖機"
    echo -e "  ${GREEN}status${NC}      檢查快取系統狀態"
    echo -e "  ${GREEN}stats${NC}       顯示快取統計資訊"
    echo -e "  ${GREEN}clear${NC}       清空所有快取"
    echo -e "  ${GREEN}test${NC}        執行快取連接測試"
    echo -e "  ${GREEN}monitor${NC}     即時監控快取效能"
    echo -e "  ${GREEN}report${NC}      產生快取分析報告"
    echo ""
    echo "範例:"
    echo "  $0 warmup           # 執行快取暖機"
    echo "  $0 stats            # 顯示統計資訊"
    echo "  $0 test             # 測試 Redis 連接"
}

# 函數：顯示狀態訊息
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 函數：檢查 Docker 和 Redis 狀態
check_prerequisites() {
    log_info "檢查系統前置條件..."
    
    # 檢查 Docker Compose
    if ! command -v docker &> /dev/null; then
        log_error "Docker 未安裝或無法存取"
        exit 1
    fi
    
    # 檢查容器是否運行
    if ! $DOCKER_COMPOSE_CMD ps web | grep -q "Up"; then
        log_error "Web 容器未運行，請先執行 'docker compose up'"
        exit 1
    fi
    
    # 檢查 Redis 容器
    if ! $DOCKER_COMPOSE_CMD ps redis | grep -q "Up"; then
        log_error "Redis 容器未運行，請檢查 docker-compose.yml"
        exit 1
    fi
    
    log_success "前置條件檢查完成"
}

# 函數：測試 Redis 連接
test_redis() {
    log_info "測試 Redis 連接..."
    
    result=$($DOCKER_COMPOSE_CMD exec -T web php -r "
        try {
            \$redis = new Redis();
            \$redis->connect('redis', 6379);
            \$response = \$redis->ping();
            if (\$response === true || \$response === '+PONG') {
                echo 'SUCCESS: Redis 連接正常';
            } else {
                echo 'ERROR: Redis 連接異常';
                exit(1);
            }
        } catch (Exception \$e) {
            echo 'ERROR: ' . \$e->getMessage();
            exit(1);
        }
    " 2>&1)
    
    if echo "$result" | grep -q "SUCCESS"; then
        log_success "Redis 連接測試通過"
        return 0
    else
        log_error "Redis 連接測試失敗: $result"
        return 1
    fi
}

# 函數：執行快取暖機
run_warmup() {
    log_info "開始執行快取暖機..."
    
    # 執行快取效能測試來觸發暖機邏輯
    $DOCKER_COMPOSE_CMD exec -T web ./vendor/bin/phpunit tests/Performance/CachePerformanceTest.php --no-output > /dev/null 2>&1
    
    log_success "快取暖機完成"
    
    # 顯示暖機後的快取狀態
    show_cache_stats
}

# 函數：顯示快取統計
show_cache_stats() {
    log_info "取得快取統計資訊..."
    
    result=$($DOCKER_COMPOSE_CMD exec -T web php -r "
        require_once '/var/www/html/vendor/autoload.php';
        
        use App\Infrastructure\Cache\Providers\AppRedisCache;
        
        try {
            \$cache = new AppRedisCache();
            \$redis = \$cache->getRedisInstance();
            \$info = \$redis->info();
            
            echo '快取伺服器資訊:' . PHP_EOL;
            echo '  Redis 版本: ' . (\$info['redis_version'] ?? 'Unknown') . PHP_EOL;
            echo '  記憶體使用: ' . (\$info['used_memory_human'] ?? 'Unknown') . PHP_EOL;
            echo '  連接數: ' . (\$info['connected_clients'] ?? 'Unknown') . PHP_EOL;
            echo '  命中率: ' . (isset(\$info['keyspace_hits']) && isset(\$info['keyspace_misses']) ? 
                round((\$info['keyspace_hits'] / (\$info['keyspace_hits'] + \$info['keyspace_misses'])) * 100, 2) : 'Unknown') . '%' . PHP_EOL;
            echo '  總鍵數: ' . (\$redis->dbSize() ?? 'Unknown') . PHP_EOL;
            
        } catch (Exception \$e) {
            echo 'ERROR: 無法取得快取統計: ' . \$e->getMessage() . PHP_EOL;
            exit(1);
        }
    " 2>&1)
    
    if echo "$result" | grep -q "ERROR"; then
        log_error "$result"
        return 1
    else
        echo "$result"
        return 0
    fi
}

# 函數：清空快取
clear_cache() {
    log_warning "即將清空所有快取資料..."
    read -p "確定要繼續嗎? (y/N): " -r
    echo
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        log_info "清空快取中..."
        
        result=$($DOCKER_COMPOSE_CMD exec -T web php -r "
            require_once '/var/www/html/vendor/autoload.php';
            
            use App\Infrastructure\Cache\Providers\AppRedisCache;
            
            try {
                \$cache = new AppRedisCache();
                if (\$cache->clear()) {
                    echo 'SUCCESS: 快取清空完成';
                } else {
                    echo 'ERROR: 快取清空失敗';
                    exit(1);
                }
            } catch (Exception \$e) {
                echo 'ERROR: ' . \$e->getMessage();
                exit(1);
            }
        " 2>&1)
        
        if echo "$result" | grep -q "SUCCESS"; then
            log_success "快取清空完成"
        else
            log_error "$result"
            return 1
        fi
    else
        log_info "取消清空快取操作"
    fi
}

# 函數：顯示系統狀態
show_status() {
    log_info "檢查快取系統狀態..."
    
    echo ""
    echo "=== 系統狀態 ==="
    
    # Docker 容器狀態
    echo ""
    echo "Docker 容器狀態:"
    $DOCKER_COMPOSE_CMD ps
    
    echo ""
    echo "=== Redis 連接測試 ==="
    if test_redis; then
        echo ""
        echo "=== 快取統計資訊 ==="
        show_cache_stats
    fi
}

# 函數：即時監控
run_monitor() {
    log_info "開始即時監控快取效能 (按 Ctrl+C 結束)..."
    
    while true; do
        clear
        echo -e "${BLUE}Redis 快取即時監控${NC} - $(date)"
        echo "=================================="
        
        show_cache_stats
        
        echo ""
        echo "下次更新: 5 秒後..."
        sleep 5
    done
}

# 函數：產生分析報告
generate_report() {
    log_info "產生快取分析報告..."
    
    report_file="storage/cache-report-$(date +%Y%m%d-%H%M%S).txt"
    
    {
        echo "Redis 快取系統分析報告"
        echo "產生時間: $(date)"
        echo "=========================="
        echo ""
        
        echo "=== 系統狀態 ==="
        $DOCKER_COMPOSE_CMD ps
        echo ""
        
        echo "=== Redis 資訊 ==="
        show_cache_stats
        echo ""
        
        echo "=== 效能測試結果 ==="
        $DOCKER_COMPOSE_CMD exec -T web ./vendor/bin/phpunit tests/Performance/CachePerformanceTest.php 2>&1 || true
        echo ""
        
    } > "$report_file"
    
    log_success "分析報告已儲存至: $report_file"
}

# 主要邏輯
main() {
    case "${1:-}" in
        "warmup")
            check_prerequisites
            run_warmup
            ;;
        "status")
            check_prerequisites
            show_status
            ;;
        "stats")
            check_prerequisites
            show_cache_stats
            ;;
        "clear")
            check_prerequisites
            clear_cache
            ;;
        "test")
            check_prerequisites
            test_redis
            ;;
        "monitor")
            check_prerequisites
            run_monitor
            ;;
        "report")
            check_prerequisites
            generate_report
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        "")
            log_error "請指定命令"
            echo ""
            show_help
            exit 1
            ;;
        *)
            log_error "未知命令: $1"
            echo ""
            show_help
            exit 1
            ;;
    esac
}

# 執行主函數
main "$@"