<?php

declare(strict_types=1);

namespace Tests\Performance;

use PHPUnit\Framework\TestCase;

/**
 * 前端效能測試.
 */
class FrontendPerformanceTest extends TestCase
{
    private string $baseUrl = 'http://nginx';

    private array $performanceResults = [];

    protected function tearDown(): void
    {
        // 輸出效能報告
        $this->outputPerformanceReport();
    }

    /**
     * 測試靜態資源載入效能.
     */
    public function testStaticResourceLoadTime(): void
    {
        $resources = [
            '/api-docs.json' => 'API 文件 JSON',
            '/api-docs.yaml' => 'API 文件 YAML',
            '/index.php' => '首頁',
        ];

        foreach ($resources as $path => $name) {
            $startTime = microtime(true);
            $response = $this->makeHttpRequest($path);
            $loadTime = microtime(true) - $startTime;

            $this->performanceResults['resource_load_times'][] = [
                'resource' => $name,
                'path' => $path,
                'load_time' => round($loadTime * 1000, 2), // ms
                'content_length' => strlen($response['body'] ?? ''),
                'status_code' => $response['status_code'] ?? 0,
            ];

            // 目標：靜態資源載入時間 < 500ms
            $this->assertLessThan(0.5, $loadTime, "{$name} 載入時間應該少於 500ms");
        }
    }

    /**
     * 測試 HTTP 快取標頭.
     */
    public function testHttpCacheHeaders(): void
    {
        $testCases = [
            [
                'path' => '/api-docs.json',
                'expected_headers' => ['Cache-Control', 'ETag'],
                'name' => 'JSON API 文件',
            ],
            [
                'path' => '/api-docs.yaml',
                'expected_headers' => ['Cache-Control', 'ETag'],
                'name' => 'YAML API 文件',
            ],
        ];

        foreach ($testCases as $testCase) {
            $response = $this->makeHttpRequest($testCase['path'], [], true);
            $headers = $response['headers'] ?? [];

            foreach ($testCase['expected_headers'] as $expectedHeader) {
                $this->assertArrayHasKey(
                    strtolower($expectedHeader),
                    array_change_key_case($headers, CASE_LOWER),
                    "{$testCase['name']} 應該包含 {$expectedHeader} 標頭",
                );
            }

            $this->performanceResults['cache_headers'][] = [
                'resource' => $testCase['name'],
                'path' => $testCase['path'],
                'headers_present' => array_intersect(
                    $testCase['expected_headers'],
                    array_keys(array_change_key_case($headers, CASE_LOWER)),
                ),
                'all_headers' => array_keys($headers),
            ];
        }
    }

    /**
     * 測試 Gzip 壓縮.
     */
    public function testGzipCompression(): void
    {
        $compressibleResources = [
            '/api-docs.json' => 'JSON API 文件',
            '/api-docs.yaml' => 'YAML API 文件',
        ];

        foreach ($compressibleResources as $path => $name) {
            // 請求壓縮版本
            $compressedResponse = $this->makeHttpRequest($path, ['Accept-Encoding' => 'gzip'], true);
            $compressedHeaders = array_change_key_case($compressedResponse['headers'] ?? [], CASE_LOWER);

            // 請求未壓縮版本
            $uncompressedResponse = $this->makeHttpRequest($path, [], true);

            $compressedSize = strlen($compressedResponse['body'] ?? '');
            $uncompressedSize = strlen($uncompressedResponse['body'] ?? '');

            $this->performanceResults['compression'][] = [
                'resource' => $name,
                'path' => $path,
                'compressed_size' => $compressedSize,
                'uncompressed_size' => $uncompressedSize,
                'compression_ratio' => $uncompressedSize > 0 ? round((1 - $compressedSize / $uncompressedSize) * 100, 2) : 0,
                'content_encoding_header' => $compressedHeaders['content-encoding'] ?? 'none',
            ];

            // 目標：可壓縮資源應該有壓縮標頭
            if ($uncompressedSize > 1024) { // 只測試大於 1KB 的檔案
                $this->assertArrayHasKey(
                    'content-encoding',
                    $compressedHeaders,
                    "{$name} 應該支援壓縮",
                );
            }
        }
    }

    /**
     * 測試首次內容繪製時間 (TTFB + 處理時間).
     */
    public function testTimeToFirstByte(): void
    {
        $endpoints = [
            '/' => '首頁',
            '/api/docs' => 'API 文件',
            '/api/docs/ui' => 'Swagger UI',
        ];

        foreach ($endpoints as $path => $name) {
            $startTime = microtime(true);
            $response = $this->makeHttpRequest($path);
            $ttfb = microtime(true) - $startTime;

            $this->performanceResults['ttfb'][] = [
                'endpoint' => $name,
                'path' => $path,
                'ttfb' => round($ttfb * 1000, 2), // ms
                'status_code' => $response['status_code'] ?? 0,
            ];

            // 目標：TTFB < 200ms
            $this->assertLessThan(0.2, $ttfb, "{$name} TTFB 應該少於 200ms");
        }
    }

    /**
     * 測試併發載入效能.
     */
    public function testConcurrentLoadPerformance(): void
    {
        $concurrentRequests = 5;
        $testPath = '/api-docs.json';

        $startTime = microtime(true);
        $responses = [];

        // 模擬併發請求 (簡化版)
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $responses[] = $this->makeHttpRequest($testPath);
        }

        $totalTime = microtime(true) - $startTime;
        $averageTime = $totalTime / $concurrentRequests;

        $this->performanceResults['concurrent_load'] = [
            'concurrent_requests' => $concurrentRequests,
            'total_time' => round($totalTime * 1000, 2), // ms
            'average_time' => round($averageTime * 1000, 2), // ms
            'successful_responses' => count(array_filter($responses, fn($r) => ($r['status_code'] ?? 0) === 200)),
        ];

        // 目標：併發請求平均回應時間 < 1s
        $this->assertLessThan(1.0, $averageTime, '併發請求平均回應時間應該少於 1 秒');
    }

    /**
     * 發送 HTTP 請求
     */
    private function makeHttpRequest(string $path, array $headers = [], bool $includeHeaders = false): array
    {
        $url = $this->baseUrl . $path;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $this->buildHeaderString($headers),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        $statusCode = 200; // 預設成功

        // 解析 HTTP 狀態碼
        if (isset($http_response_header)) {
            $statusLine = $http_response_header[0] ?? '';
            if (preg_match('/HTTP\/\d\.\d (\d{3})/', $statusLine, $matches)) {
                $statusCode = (int) $matches[1];
            }
        }

        $result = [
            'body' => $response !== false ? $response : '',
            'status_code' => $statusCode,
        ];

        if ($includeHeaders && isset($http_response_header)) {
            $result['headers'] = $this->parseHeaders($http_response_header);
        }

        return $result;
    }

    /**
     * 建立標頭字串.
     */
    private function buildHeaderString(array $headers): string
    {
        $headerStrings = [];
        foreach ($headers as $name => $value) {
            $headerStrings[] = "{$name}: {$value}";
        }

        return implode("\r\n", $headerStrings);
    }

    /**
     * 解析 HTTP 標頭.
     */
    private function parseHeaders(array $headerLines): array
    {
        $headers = [];
        foreach ($headerLines as $line) {
            if (strpos($line, ':') !== false) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim(strtolower($name))] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * 輸出效能報告.
     */
    private function outputPerformanceReport(): void
    {
        if (empty($this->performanceResults)) {
            return;
        }

        echo "\n" . str_repeat('=', 60) . "\n";
        echo "🎨 T4.3 前端效能測試報告\n";
        echo str_repeat('=', 60) . "\n";

        // 資源載入時間報告
        if (!empty($this->performanceResults['resource_load_times'])) {
            echo "\n📦 資源載入時間:\n";
            foreach ($this->performanceResults['resource_load_times'] as $result) {
                $status = $result['load_time'] < 500 ? '🟢' : ($result['load_time'] < 1000 ? '🟡' : '🔴');
                echo "  {$status} {$result['resource']}: {$result['load_time']}ms\n";
            }
        }

        // TTFB 報告
        if (!empty($this->performanceResults['ttfb'])) {
            echo "\n⚡ 首位元組時間 (TTFB):\n";
            foreach ($this->performanceResults['ttfb'] as $result) {
                $status = $result['ttfb'] < 200 ? '🟢' : ($result['ttfb'] < 500 ? '🟡' : '🔴');
                echo "  {$status} {$result['endpoint']}: {$result['ttfb']}ms\n";
            }
        }

        // 壓縮報告
        if (!empty($this->performanceResults['compression'])) {
            echo "\n🗜️ 壓縮效果:\n";
            foreach ($this->performanceResults['compression'] as $result) {
                $ratio = $result['compression_ratio'];
                $status = $ratio > 50 ? '🟢' : ($ratio > 20 ? '🟡' : '🔴');
                echo "  {$status} {$result['resource']}: {$ratio}% 壓縮\n";
            }
        }

        // 併發效能報告
        if (!empty($this->performanceResults['concurrent_load'])) {
            $result = $this->performanceResults['concurrent_load'];
            echo "\n🔄 併發載入效能:\n";
            echo "  • 併發請求數: {$result['concurrent_requests']}\n";
            echo "  • 總時間: {$result['total_time']}ms\n";
            echo "  • 平均時間: {$result['average_time']}ms\n";
            echo "  • 成功回應數: {$result['successful_responses']}\n";
        }

        echo "\n" . str_repeat('=', 60) . "\n\n";
    }
}
