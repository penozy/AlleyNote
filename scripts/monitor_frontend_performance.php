<?php

declare(strict_types=1);

/**
 * 前端效能監控工具
 * 
 * 定期監控前端效能指標
 */

class FrontendPerformanceMonitor
{
    private string $baseUrl;
    private array $endpoints;
    
    public function __construct(string $baseUrl = 'http://nginx')
    {
        $this->baseUrl = $baseUrl;
        $this->endpoints = [
            '/' => '首頁',
            '/api/docs' => 'API 文件',
            '/api/docs/ui' => 'Swagger UI',
            '/api-docs.json' => 'API JSON',
            '/api-docs.yaml' => 'API YAML'
        ];
    }
    
    /**
     * 執行效能監控
     */
    public function runMonitoring(): array
    {
        echo "🔍 開始前端效能監控...\n\n";
        
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'response_times' => $this->measureResponseTimes(),
            'availability' => $this->checkAvailability(),
            'cache_effectiveness' => $this->checkCacheEffectiveness(),
            'compression_status' => $this->checkCompressionStatus()
        ];
        
        $this->generateMonitoringReport($results);
        
        return $results;
    }
    
    /**
     * 測量回應時間
     */
    private function measureResponseTimes(): array
    {
        echo "⏱️ 測量回應時間...\n";
        
        $results = [];
        
        foreach ($this->endpoints as $path => $name) {
            $times = [];
            
            // 測量 3 次取平均
            for ($i = 0; $i < 3; $i++) {
                $startTime = microtime(true);
                $response = $this->makeRequest($path);
                $endTime = microtime(true);
                
                if ($response['success']) {
                    $times[] = ($endTime - $startTime) * 1000; // ms
                }
            }
            
            if (!empty($times)) {
                $avgTime = array_sum($times) / count($times);
                $results[] = [
                    'endpoint' => $name,
                    'path' => $path,
                    'avg_response_time' => round($avgTime, 2),
                    'min_time' => round(min($times), 2),
                    'max_time' => round(max($times), 2),
                    'status' => $avgTime < 500 ? 'good' : ($avgTime < 1000 ? 'warning' : 'critical')
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * 檢查可用性
     */
    private function checkAvailability(): array
    {
        echo "🔌 檢查服務可用性...\n";
        
        $results = [];
        
        foreach ($this->endpoints as $path => $name) {
            $response = $this->makeRequest($path);
            
            $results[] = [
                'endpoint' => $name,
                'path' => $path,
                'available' => $response['success'],
                'status_code' => $response['status_code'],
                'response_size' => $response['content_length']
            ];
        }
        
        return $results;
    }
    
    /**
     * 檢查快取效能
     */
    private function checkCacheEffectiveness(): array
    {
        echo "🚀 檢查快取效能...\n";
        
        $results = [];
        $cacheableEndpoints = ['/api-docs.json', '/api-docs.yaml'];
        
        foreach ($cacheableEndpoints as $path) {
            // 首次請求
            $firstResponse = $this->makeRequestWithHeaders($path, [], true);
            
            // 第二次請求 (應該命中快取)
            $secondResponse = $this->makeRequestWithHeaders($path, [], true);
            
            $results[] = [
                'endpoint' => $this->endpoints[$path] ?? $path,
                'path' => $path,
                'cache_control_header' => $firstResponse['headers']['cache-control'] ?? 'none',
                'etag_header' => $firstResponse['headers']['etag'] ?? 'none',
                'cache_headers_present' => isset($firstResponse['headers']['cache-control']) || isset($firstResponse['headers']['etag'])
            ];
        }
        
        return $results;
    }
    
    /**
     * 檢查壓縮狀態
     */
    private function checkCompressionStatus(): array
    {
        echo "🗜️ 檢查壓縮狀態...\n";
        
        $results = [];
        $compressibleEndpoints = ['/api-docs.json', '/api-docs.yaml'];
        
        foreach ($compressibleEndpoints as $path) {
            $response = $this->makeRequestWithHeaders($path, ['Accept-Encoding' => 'gzip'], true);
            
            $results[] = [
                'endpoint' => $this->endpoints[$path] ?? $path,
                'path' => $path,
                'compression_supported' => isset($response['headers']['content-encoding']),
                'compression_type' => $response['headers']['content-encoding'] ?? 'none',
                'vary_header' => $response['headers']['vary'] ?? 'none'
            ];
        }
        
        return $results;
    }
    
    /**
     * 發送請求
     */
    private function makeRequest(string $path): array
    {
        $url = $this->baseUrl . $path;
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        return [
            'success' => $content !== false,
            'status_code' => $this->getStatusCode($http_response_header ?? []),
            'content_length' => $content !== false ? strlen($content) : 0
        ];
    }
    
    /**
     * 發送帶標頭的請求
     */
    private function makeRequestWithHeaders(string $path, array $headers = [], bool $includeHeaders = false): array
    {
        $url = $this->baseUrl . $path;
        
        $headerString = [];
        foreach ($headers as $name => $value) {
            $headerString[] = "{$name}: {$value}";
        }
        
        $context = stream_context_create([
            'http' => [
                'header' => implode("\r\n", $headerString),
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);
        
        $content = @file_get_contents($url, false, $context);
        
        $result = [
            'success' => $content !== false,
            'status_code' => $this->getStatusCode($http_response_header ?? []),
            'content_length' => $content !== false ? strlen($content) : 0
        ];
        
        if ($includeHeaders && isset($http_response_header)) {
            $result['headers'] = $this->parseHeaders($http_response_header);
        }
        
        return $result;
    }
    
    /**
     * 解析狀態碼
     */
    private function getStatusCode(array $headers): int
    {
        if (empty($headers)) return 0;
        
        $statusLine = $headers[0];
        if (preg_match('/HTTP\/\d\.\d (\d{3})/', $statusLine, $matches)) {
            return (int)$matches[1];
        }
        
        return 0;
    }
    
    /**
     * 解析標頭
     */
    private function parseHeaders(array $headerLines): array
    {
        $headers = [];
        foreach ($headerLines as $line) {
            if (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }
        return $headers;
    }
    
    /**
     * 生成監控報告
     */
    private function generateMonitoringReport(array $results): void
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 前端效能監控報告\n";
        echo str_repeat("=", 60) . "\n\n";
        
        // 回應時間報告
        if (!empty($results['response_times'])) {
            echo "⏱️ 回應時間:\n";
            foreach ($results['response_times'] as $result) {
                $statusIcon = match($result['status']) {
                    'good' => '🟢',
                    'warning' => '🟡', 
                    'critical' => '🔴',
                    default => '⚪'
                };
                echo "  {$statusIcon} {$result['endpoint']}: {$result['avg_response_time']}ms (範圍: {$result['min_time']}-{$result['max_time']}ms)\n";
            }
            echo "\n";
        }
        
        // 可用性報告
        if (!empty($results['availability'])) {
            echo "🔌 服務可用性:\n";
            foreach ($results['availability'] as $result) {
                $statusIcon = $result['available'] ? '🟢' : '🔴';
                echo "  {$statusIcon} {$result['endpoint']}: " . ($result['available'] ? '可用' : '不可用') . " (HTTP {$result['status_code']})\n";
            }
            echo "\n";
        }
        
        // 快取效能報告
        if (!empty($results['cache_effectiveness'])) {
            echo "🚀 快取配置:\n";
            foreach ($results['cache_effectiveness'] as $result) {
                $statusIcon = $result['cache_headers_present'] ? '🟢' : '🔴';
                echo "  {$statusIcon} {$result['endpoint']}: " . ($result['cache_headers_present'] ? '已配置快取' : '未配置快取') . "\n";
            }
            echo "\n";
        }
        
        // 壓縮狀態報告
        if (!empty($results['compression_status'])) {
            echo "🗜️ 壓縮配置:\n";
            foreach ($results['compression_status'] as $result) {
                $statusIcon = $result['compression_supported'] ? '🟢' : '🔴';
                $compressionInfo = $result['compression_supported'] ? $result['compression_type'] : '未啟用';
                echo "  {$statusIcon} {$result['endpoint']}: {$compressionInfo}\n";
            }
            echo "\n";
        }
        
        echo "✅ 監控完成！時間: {$results['timestamp']}\n\n";
    }
}

// 執行監控
if (basename($_SERVER['argv'][0] ?? '') === basename(__FILE__)) {
    try {
        $monitor = new FrontendPerformanceMonitor();
        $results = $monitor->runMonitoring();
        
        echo "🎯 監控建議:\n";
        echo "1. 定期執行此監控檢查效能變化\n";
        echo "2. 設定自動告警當回應時間超過閾值\n";
        echo "3. 監控快取命中率提升效能\n";
        echo "4. 確保壓縮功能正常運作\n\n";
        
    } catch (Exception $e) {
        echo "❌ 監控失敗: " . $e->getMessage() . "\n";
        exit(1);
    }
}
