<?php

declare(strict_types=1);

/**
 * 前端效能分析工具
 * 
 * 分析當前前端資源的載入效能，檢測優化機會
 */

class FrontendPerformanceAnalyzer
{
    private string $publicPath;
    private array $analysisResults = [];

    public function __construct(?string $publicPath = null)
    {
        $this->publicPath = $publicPath ?? __DIR__ . '/../public';
    }

    /**
     * 執行完整的前端效能分析
     */
    public function runFullAnalysis(): array
    {
        echo "🔍 開始前端效能分析...\n\n";

        $this->analysisResults = [
            'static_resources' => $this->analyzeStaticResources(),
            'http_headers' => $this->analyzeHttpHeaders(),
            'compression' => $this->analyzeCompression(),
            'caching_strategy' => $this->analyzeCachingStrategy(),
            'performance_metrics' => $this->measurePerformance(),
            'optimization_suggestions' => $this->generateOptimizationSuggestions(),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->generateReport();

        return $this->analysisResults;
    }

    /**
     * 分析靜態資源
     */
    private function analyzeStaticResources(): array
    {
        echo "📦 分析靜態資源...\n";

        $resources = [];

        // 檢查 public 目錄結構
        $publicFiles = $this->scanDirectory($this->publicPath);

        foreach ($publicFiles as $file) {
            $filePath = $this->publicPath . '/' . $file;
            if (!is_file($filePath)) continue;

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $size = filesize($filePath);
            $lastModified = filemtime($filePath);

            $resources[] = [
                'file' => $file,
                'type' => $this->getResourceType($extension),
                'extension' => $extension,
                'size_bytes' => $size,
                'size_human' => $this->formatBytes($size),
                'last_modified' => date('Y-m-d H:i:s', $lastModified),
                'can_compress' => $this->canCompress($extension),
                'can_minify' => $this->canMinify($extension),
                'cache_suitable' => $this->isCacheSuitable($extension)
            ];
        }

        // 統計資訊
        $totalSize = array_sum(array_column($resources, 'size_bytes'));
        $compressibleSize = array_sum(array_map(
            fn($r) => $r['can_compress'] ? $r['size_bytes'] : 0,
            $resources
        ));

        return [
            'files' => $resources,
            'total_count' => count($resources),
            'total_size' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
            'compressible_size' => $compressibleSize,
            'compression_potential' => $totalSize > 0 ? round($compressibleSize / $totalSize * 100, 2) : 0
        ];
    }

    /**
     * 分析 HTTP 標頭
     */
    private function analyzeHttpHeaders(): array
    {
        echo "🌐 分析 HTTP 標頭配置...\n";

        $headers = [];
        $recommendations = [];

        // 檢查 Nginx 配置（如果存在）
        $nginxConfigPath = __DIR__ . '/../docker/nginx/default.conf';
        $nginxConfig = file_exists($nginxConfigPath) ? file_get_contents($nginxConfigPath) : '';

        // 分析快取標頭
        $cacheHeaders = [
            'Cache-Control' => $this->checkCacheControl($nginxConfig),
            'Expires' => $this->checkExpires($nginxConfig),
            'ETag' => $this->checkETag($nginxConfig),
            'Last-Modified' => $this->checkLastModified($nginxConfig)
        ];

        // 分析壓縮標頭
        $compressionHeaders = [
            'Content-Encoding' => $this->checkCompression($nginxConfig),
            'Vary' => $this->checkVaryHeader($nginxConfig)
        ];

        // 分析安全標頭
        $securityHeaders = [
            'X-Content-Type-Options' => $this->checkSecurityHeader($nginxConfig, 'nosniff'),
            'X-Frame-Options' => $this->checkSecurityHeader($nginxConfig, 'X-Frame-Options'),
            'X-XSS-Protection' => $this->checkSecurityHeader($nginxConfig, 'X-XSS-Protection')
        ];

        return [
            'cache_headers' => $cacheHeaders,
            'compression_headers' => $compressionHeaders,
            'security_headers' => $securityHeaders,
            'nginx_config_exists' => file_exists($nginxConfigPath),
            'optimization_needed' => $this->needsHeaderOptimization($cacheHeaders, $compressionHeaders)
        ];
    }

    /**
     * 分析壓縮策略
     */
    private function analyzeCompression(): array
    {
        echo "🗜️ 分析壓縮策略...\n";

        $compressionAnalysis = [
            'gzip_support' => function_exists('gzencode'),
            'brotli_support' => function_exists('brotli_compress'),
            'compressible_files' => [],
            'compression_savings' => []
        ];

        // 模擬壓縮測試
        if (isset($this->analysisResults['static_resources']['files'])) {
            foreach ($this->analysisResults['static_resources']['files'] as $file) {
                if ($file['can_compress']) {
                    $filePath = $this->publicPath . '/' . $file['file'];
                    $originalSize = $file['size_bytes'];

                    if (is_file($filePath) && $originalSize > 100) {
                        $content = file_get_contents($filePath);

                        // Gzip 壓縮測試
                        if ($compressionAnalysis['gzip_support']) {
                            $gzipContent = gzencode($content, 9);
                            $gzipSize = strlen($gzipContent);
                            $gzipSavings = round((1 - $gzipSize / $originalSize) * 100, 2);

                            $compressionAnalysis['compression_savings'][] = [
                                'file' => $file['file'],
                                'original_size' => $originalSize,
                                'gzip_size' => $gzipSize,
                                'gzip_savings' => $gzipSavings
                            ];
                        }
                    }
                }
            }
        }

        return $compressionAnalysis;
    }

    /**
     * 分析快取策略
     */
    private function analyzeCachingStrategy(): array
    {
        echo "🚀 分析快取策略...\n";

        return [
            'browser_cache' => [
                'static_assets_ttl' => '1 year recommended for versioned assets',
                'api_responses_ttl' => '5 minutes recommended',
                'html_ttl' => 'no-cache recommended'
            ],
            'cdn_ready' => [
                'static_assets' => true,
                'api_endpoints' => false,
                'versioning_strategy' => 'query parameter or hash-based naming'
            ],
            'cache_busting' => [
                'current_strategy' => 'none detected',
                'recommended_strategy' => 'file hash or timestamp in URL'
            ]
        ];
    }

    /**
     * 測量效能指標
     */
    private function measurePerformance(): array
    {
        echo "⚡ 測量效能指標...\n";

        $performanceMetrics = [];

        // 測試 index.php 回應時間
        $startTime = microtime(true);
        $indexPath = $this->publicPath . '/index.php';

        if (file_exists($indexPath)) {
            // 模擬檔案大小檢查而非執行 PHP
            $content = file_get_contents($indexPath);
            $loadTime = microtime(true) - $startTime;

            $performanceMetrics['index_load_time'] = round($loadTime * 1000, 2); // ms
            $performanceMetrics['content_size'] = strlen($content);
        }

        // 計算總載入時間估算
        $totalSize = $this->analysisResults['static_resources']['total_size'] ?? 0;
        $estimatedLoadTime = $this->estimateLoadTime($totalSize);

        return [
            'measured_metrics' => $performanceMetrics,
            'estimated_load_time' => $estimatedLoadTime,
            'performance_budget' => [
                'total_page_size' => '< 1MB',
                'first_contentful_paint' => '< 1.5s',
                'time_to_interactive' => '< 3s'
            ]
        ];
    }

    /**
     * 生成優化建議
     */
    private function generateOptimizationSuggestions(): array
    {
        $suggestions = [];

        // 基於靜態資源分析的建議
        if (isset($this->analysisResults['static_resources'])) {
            $resources = $this->analysisResults['static_resources'];

            if ($resources['compression_potential'] > 50) {
                $suggestions[] = [
                    'type' => 'compression',
                    'priority' => 'high',
                    'description' => '啟用 Gzip/Brotli 壓縮可節省 ' . $resources['compression_potential'] . '% 的頻寬',
                    'implementation' => 'Nginx 配置 gzip on; gzip_types text/css application/javascript;',
                    'estimated_improvement' => $resources['compression_potential'] . '% 載入速度提升'
                ];
            }
        }

        // HTTP 標頭優化建議
        $suggestions[] = [
            'type' => 'caching',
            'priority' => 'high',
            'description' => '設定適當的瀏覽器快取標頭',
            'implementation' => '為靜態資源設定長期快取，API 回應設定短期快取',
            'estimated_improvement' => '返回用戶 80% 載入速度提升'
        ];

        // CDN 建議
        $suggestions[] = [
            'type' => 'cdn',
            'priority' => 'medium',
            'description' => '使用 CDN 加速靜態資源載入',
            'implementation' => '將 CSS、JS、圖片等靜態資源部署到 CDN',
            'estimated_improvement' => '全球用戶 40-60% 載入速度提升'
        ];

        // 資源最小化建議
        $suggestions[] = [
            'type' => 'minification',
            'priority' => 'medium',
            'description' => '最小化 CSS 和 JavaScript 檔案',
            'implementation' => '使用工具移除空格、註解和未使用的程式碼',
            'estimated_improvement' => '20-30% 檔案大小減少'
        ];

        return $suggestions;
    }

    /**
     * 掃描目錄
     */
    private function scanDirectory(string $path): array
    {
        $files = [];
        if (is_dir($path)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace($path . '/', '', $file->getPathname());
                    $files[] = $relativePath;
                }
            }
        }
        return $files;
    }

    /**
     * 取得資源類型
     */
    private function getResourceType(string $extension): string
    {
        $types = [
            'css' => 'stylesheet',
            'js' => 'javascript',
            'php' => 'server-side',
            'html' => 'markup',
            'json' => 'data',
            'yaml' => 'data',
            'png' => 'image',
            'jpg' => 'image',
            'jpeg' => 'image',
            'gif' => 'image',
            'svg' => 'image',
            'ico' => 'icon',
            'pdf' => 'document',
            'txt' => 'text'
        ];

        return $types[$extension] ?? 'other';
    }

    /**
     * 檢查是否可壓縮
     */
    private function canCompress(string $extension): bool
    {
        $compressible = ['css', 'js', 'html', 'json', 'yaml', 'txt', 'xml', 'svg'];
        return in_array($extension, $compressible, true);
    }

    /**
     * 檢查是否可最小化
     */
    private function canMinify(string $extension): bool
    {
        $minifiable = ['css', 'js', 'html', 'json'];
        return in_array($extension, $minifiable, true);
    }

    /**
     * 檢查是否適合快取
     */
    private function isCacheSuitable(string $extension): bool
    {
        $cacheable = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'pdf'];
        return in_array($extension, $cacheable, true);
    }

    /**
     * 格式化位元組
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 檢查快取控制
     */
    private function checkCacheControl(string $config): array
    {
        return [
            'configured' => strpos($config, 'Cache-Control') !== false,
            'expires_configured' => strpos($config, 'expires') !== false,
            'recommendation' => 'max-age=31536000 for static assets, no-cache for HTML'
        ];
    }

    /**
     * 檢查 Expires 標頭
     */
    private function checkExpires(string $config): array
    {
        return [
            'configured' => strpos($config, 'expires') !== false,
            'recommendation' => '1y for static assets, -1 for dynamic content'
        ];
    }

    /**
     * 檢查 ETag
     */
    private function checkETag(string $config): array
    {
        return [
            'configured' => strpos($config, 'etag') !== false,
            'recommendation' => 'Enable for better cache validation'
        ];
    }

    /**
     * 檢查 Last-Modified
     */
    private function checkLastModified(string $config): array
    {
        return [
            'configured' => true, // Nginx default
            'recommendation' => 'Automatic with Nginx'
        ];
    }

    /**
     * 檢查壓縮
     */
    private function checkCompression(string $config): array
    {
        return [
            'gzip_enabled' => strpos($config, 'gzip on') !== false,
            'brotli_enabled' => strpos($config, 'brotli on') !== false,
            'recommendation' => 'Enable gzip and brotli compression'
        ];
    }

    /**
     * 檢查 Vary 標頭
     */
    private function checkVaryHeader(string $config): array
    {
        return [
            'configured' => strpos($config, 'Vary') !== false,
            'recommendation' => 'Add "Vary: Accept-Encoding" for compressed responses'
        ];
    }

    /**
     * 檢查安全標頭
     */
    private function checkSecurityHeader(string $config, string $header): array
    {
        return [
            'configured' => strpos($config, $header) !== false,
            'recommendation' => "Configure {$header} for security"
        ];
    }

    /**
     * 檢查是否需要標頭優化
     */
    private function needsHeaderOptimization(array $cacheHeaders, array $compressionHeaders): bool
    {
        return !$cacheHeaders['Cache-Control']['configured'] ||
            !$compressionHeaders['Content-Encoding']['gzip_enabled'];
    }

    /**
     * 估算載入時間
     */
    private function estimateLoadTime(int $totalSize): array
    {
        // 基於不同網路條件的載入時間估算
        $connections = [
            'Fast 3G' => 1.5 * 1024 * 1024 / 8, // 1.5 Mbps to bytes/sec
            '4G' => 10 * 1024 * 1024 / 8,        // 10 Mbps to bytes/sec
            'WiFi' => 50 * 1024 * 1024 / 8       // 50 Mbps to bytes/sec
        ];

        $estimates = [];
        foreach ($connections as $type => $speed) {
            $loadTime = $totalSize > 0 ? $totalSize / $speed : 0;
            $estimates[$type] = round($loadTime, 2) . 's';
        }

        return $estimates;
    }

    /**
     * 生成分析報告
     */
    private function generateReport(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📊 前端效能分析報告\n";
        echo str_repeat("=", 80) . "\n\n";

        // 靜態資源報告
        if (!empty($this->analysisResults['static_resources'])) {
            $resources = $this->analysisResults['static_resources'];
            echo "📦 靜態資源分析:\n";
            echo "  • 總檔案數: {$resources['total_count']}\n";
            echo "  • 總大小: {$resources['total_size_human']}\n";
            echo "  • 壓縮潛力: {$resources['compression_potential']}%\n\n";

            if (!empty($resources['files'])) {
                echo "  主要檔案:\n";
                $sortedFiles = $resources['files'];
                usort($sortedFiles, fn($a, $b) => $b['size_bytes'] <=> $a['size_bytes']);

                foreach (array_slice($sortedFiles, 0, 5) as $file) {
                    $compress = $file['can_compress'] ? '✅' : '❌';
                    $minify = $file['can_minify'] ? '✅' : '❌';
                    echo "    • {$file['file']} ({$file['size_human']}) - 壓縮:{$compress} 最小化:{$minify}\n";
                }
            }
            echo "\n";
        }

        // 壓縮分析報告
        if (!empty($this->analysisResults['compression']['compression_savings'])) {
            echo "🗜️ 壓縮效果分析:\n";
            foreach ($this->analysisResults['compression']['compression_savings'] as $saving) {
                echo "  • {$saving['file']}: {$saving['gzip_savings']}% 壓縮節省\n";
            }
            echo "\n";
        }

        // 效能指標報告
        if (!empty($this->analysisResults['performance_metrics'])) {
            $metrics = $this->analysisResults['performance_metrics'];
            echo "⚡ 效能指標:\n";
            if (isset($metrics['measured_metrics']['index_load_time'])) {
                echo "  • Index 載入時間: {$metrics['measured_metrics']['index_load_time']}ms\n";
            }
            if (isset($metrics['estimated_load_time'])) {
                echo "  • 預估載入時間:\n";
                foreach ($metrics['estimated_load_time'] as $type => $time) {
                    echo "    - {$type}: {$time}\n";
                }
            }
            echo "\n";
        }

        // 優化建議報告
        if (!empty($this->analysisResults['optimization_suggestions'])) {
            echo "💡 優化建議:\n";
            foreach ($this->analysisResults['optimization_suggestions'] as $suggestion) {
                $priority = match ($suggestion['priority']) {
                    'high' => '🔴',
                    'medium' => '🟡',
                    'low' => '🟢',
                    default => '⚪'
                };
                echo "  {$priority} {$suggestion['description']}\n";
                echo "     實作方式: {$suggestion['implementation']}\n";
                echo "     預期改善: {$suggestion['estimated_improvement']}\n\n";
            }
        }

        echo "✅ 分析完成！詳細報告已保存到 storage/logs/frontend_performance_analysis.json\n\n";

        // 保存詳細報告到檔案
        $reportPath = __DIR__ . '/../storage/logs/frontend_performance_analysis.json';
        $logDir = dirname($reportPath);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents($reportPath, json_encode($this->analysisResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

// 執行分析
if (basename($_SERVER['argv'][0] ?? '') === basename(__FILE__)) {
    try {
        $analyzer = new FrontendPerformanceAnalyzer();
        $results = $analyzer->runFullAnalysis();

        echo "🎯 下一步建議:\n";
        echo "1. 建立前端資源壓縮系統\n";
        echo "2. 配置 HTTP 快取標頭\n";
        echo "3. 啟用 Gzip/Brotli 壓縮\n";
        echo "4. 實作資源最小化工具\n";
        echo "5. 建立效能監控工具\n\n";
    } catch (Exception $e) {
        echo "❌ 分析失敗: " . $e->getMessage() . "\n";
        exit(1);
    }
}
