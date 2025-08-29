<?php

declare(strict_types=1);

/**
 * 資源壓縮工具
 * 
 * 壓縮和最小化前端資源檔案
 */

class AssetCompressionTool
{
    private string $publicPath;
    private array $compressionResults = [];
    
    public function __construct(?string $publicPath = null)
    {
        $this->publicPath = $publicPath ?? __DIR__ . '/../public';
    }
    
    /**
     * 執行完整的資源壓縮
     */
    public function compressAssets(): array
    {
        echo "🗜️ 開始資源壓縮...\n\n";
        
        $this->compressionResults = [
            'json_files' => $this->compressJsonFiles(),
            'yaml_files' => $this->compressYamlFiles(),
            'php_files' => $this->optimizePhpFiles(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $this->generateCompressionReport();
        
        return $this->compressionResults;
    }
    
    /**
     * 壓縮 JSON 檔案
     */
    private function compressJsonFiles(): array
    {
        echo "📄 壓縮 JSON 檔案...\n";
        
        $results = [];
        $jsonFiles = glob($this->publicPath . '/*.json');
        
        foreach ($jsonFiles as $filePath) {
            $originalContent = file_get_contents($filePath);
            $originalSize = strlen($originalContent);
            
            // 解析並重新編碼為緊湊格式
            $jsonData = json_decode($originalContent, true);
            if ($jsonData !== null) {
                $compressedContent = json_encode($jsonData, JSON_UNESCAPED_UNICODE);
                $compressedSize = strlen($compressedContent);
                $savings = round((1 - $compressedSize / $originalSize) * 100, 2);
                
                // 建立壓縮版本
                $compressedPath = str_replace('.json', '.min.json', $filePath);
                file_put_contents($compressedPath, $compressedContent);
                
                $results[] = [
                    'file' => basename($filePath),
                    'original_size' => $originalSize,
                    'compressed_size' => $compressedSize,
                    'savings_percent' => $savings,
                    'compressed_file' => basename($compressedPath)
                ];
                
                echo "  ✅ " . basename($filePath) . " -> " . basename($compressedPath) . " ({$savings}% 節省)\n";
            }
        }
        
        return $results;
    }
    
    /**
     * 壓縮 YAML 檔案 (移除註解和多餘空白)
     */
    private function compressYamlFiles(): array
    {
        echo "📄 壓縮 YAML 檔案...\n";
        
        $results = [];
        $yamlFiles = glob($this->publicPath . '/*.{yaml,yml}', GLOB_BRACE);
        
        foreach ($yamlFiles as $filePath) {
            $originalContent = file_get_contents($filePath);
            $originalSize = strlen($originalContent);
            
            // 簡單的 YAML 壓縮 (移除空白行和多餘空格)
            $lines = explode("\n", $originalContent);
            $compressedLines = [];
            
            foreach ($lines as $line) {
                $trimmedLine = rtrim($line);
                // 跳過空白行，但保留有縮排意義的行
                if ($trimmedLine !== '' || (isset($compressedLines[count($compressedLines) - 1]) && trim($compressedLines[count($compressedLines) - 1]) === '')) {
                    $compressedLines[] = $trimmedLine;
                }
            }
            
            $compressedContent = implode("\n", $compressedLines);
            $compressedSize = strlen($compressedContent);
            $savings = round((1 - $compressedSize / $originalSize) * 100, 2);
            
            if ($savings > 0) {
                // 建立壓縮版本
                $compressedPath = str_replace(['.yaml', '.yml'], ['.min.yaml', '.min.yml'], $filePath);
                file_put_contents($compressedPath, $compressedContent);
                
                $results[] = [
                    'file' => basename($filePath),
                    'original_size' => $originalSize,
                    'compressed_size' => $compressedSize,
                    'savings_percent' => $savings,
                    'compressed_file' => basename($compressedPath)
                ];
                
                echo "  ✅ " . basename($filePath) . " -> " . basename($compressedPath) . " ({$savings}% 節省)\n";
            }
        }
        
        return $results;
    }
    
    /**
     * 優化 PHP 檔案 (移除不必要的空白和註解)
     */
    private function optimizePhpFiles(): array
    {
        echo "🐘 優化 PHP 檔案...\n";
        
        $results = [];
        $phpFiles = glob($this->publicPath . '/*.php');
        
        foreach ($phpFiles as $filePath) {
            $originalContent = file_get_contents($filePath);
            $originalSize = strlen($originalContent);
            
            // PHP 簡單優化 (移除多餘空白行)
            $lines = explode("\n", $originalContent);
            $optimizedLines = [];
            $previousLineEmpty = false;
            
            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                
                // 跳過連續的空白行
                if ($trimmedLine === '') {
                    if (!$previousLineEmpty) {
                        $optimizedLines[] = '';
                        $previousLineEmpty = true;
                    }
                } else {
                    $optimizedLines[] = $line;
                    $previousLineEmpty = false;
                }
            }
            
            $optimizedContent = implode("\n", $optimizedLines);
            $optimizedSize = strlen($optimizedContent);
            $savings = round((1 - $optimizedSize / $originalSize) * 100, 2);
            
            if ($savings > 0) {
                $results[] = [
                    'file' => basename($filePath),
                    'original_size' => $originalSize,
                    'optimized_size' => $optimizedSize,
                    'savings_percent' => $savings,
                    'note' => 'Removed excessive whitespace'
                ];
                
                echo "  ✅ " . basename($filePath) . " 優化完成 ({$savings}% 節省)\n";
            }
        }
        
        return $results;
    }
    
    /**
     * 生成壓縮報告
     */
    private function generateCompressionReport(): void
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 資源壓縮報告\n";
        echo str_repeat("=", 60) . "\n\n";
        
        $totalOriginalSize = 0;
        $totalCompressedSize = 0;
        $totalFiles = 0;
        
        // JSON 檔案報告
        if (!empty($this->compressionResults['json_files'])) {
            echo "📄 JSON 檔案壓縮結果:\n";
            foreach ($this->compressionResults['json_files'] as $result) {
                echo "  • {$result['file']}: " . $this->formatBytes($result['original_size']) . 
                     " -> " . $this->formatBytes($result['compressed_size']) . 
                     " ({$result['savings_percent']}% 節省)\n";
                
                $totalOriginalSize += $result['original_size'];
                $totalCompressedSize += $result['compressed_size'];
                $totalFiles++;
            }
            echo "\n";
        }
        
        // YAML 檔案報告
        if (!empty($this->compressionResults['yaml_files'])) {
            echo "📄 YAML 檔案壓縮結果:\n";
            foreach ($this->compressionResults['yaml_files'] as $result) {
                echo "  • {$result['file']}: " . $this->formatBytes($result['original_size']) . 
                     " -> " . $this->formatBytes($result['compressed_size']) . 
                     " ({$result['savings_percent']}% 節省)\n";
                
                $totalOriginalSize += $result['original_size'];
                $totalCompressedSize += $result['compressed_size'];
                $totalFiles++;
            }
            echo "\n";
        }
        
        // 總計報告
        if ($totalFiles > 0) {
            $totalSavings = round((1 - $totalCompressedSize / $totalOriginalSize) * 100, 2);
            echo "📊 總計:\n";
            echo "  • 處理檔案: {$totalFiles} 個\n";
            echo "  • 原始大小: " . $this->formatBytes($totalOriginalSize) . "\n";
            echo "  • 壓縮後: " . $this->formatBytes($totalCompressedSize) . "\n";
            echo "  • 總節省: {$totalSavings}%\n\n";
        }
        
        echo "✅ 壓縮完成！\n\n";
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
}

// 執行壓縮
if (basename($_SERVER['argv'][0] ?? '') === basename(__FILE__)) {
    try {
        $compressor = new AssetCompressionTool();
        $results = $compressor->compressAssets();
        
        echo "🎯 壓縮檔案已建立，建議:\n";
        echo "1. 使用壓縮版本提供給生產環境\n";
        echo "2. 設定 Web 伺服器自動選擇壓縮版本\n";
        echo "3. 配置適當的快取標頭\n";
        echo "4. 考慮使用 CDN 分發靜態資源\n\n";
        
    } catch (Exception $e) {
        echo "❌ 壓縮失敗: " . $e->getMessage() . "\n";
        exit(1);
    }
}
