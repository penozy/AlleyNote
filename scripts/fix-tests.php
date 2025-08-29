<?php

declare(strict_types=1);

/**
 * 批量修復測試檔案中的建構子參數問題
 */

$testDir = __DIR__ . '/../tests';
$errors = [];

// 需要修復的建構子映射
$constructorFixes = [
    'PostController' => [
        'old_args' => 3,
        'new_args' => 4,
        'add_mock' => 'ActivityLoggingServiceInterface',
        'import' => 'App\Domains\Security\Contracts\ActivityLoggingServiceInterface',
    ],
    'AttachmentService' => [
        'old_args' => 4,
        'new_args' => 5,
        'add_mock' => 'ActivityLoggingServiceInterface',
        'import' => 'App\Domains\Security\Contracts\ActivityLoggingServiceInterface',
    ],
    'AuthController' => [
        'constructor_issue' => 'parameter_order',
    ],
];

function scanTestFiles($dir)
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), 'Test.php')) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

function analyzeTestFile($filePath)
{
    $content = file_get_contents($filePath);
    $issues = [];

    // 檢查是否有 ArgumentCountError 相關問題
    if (strpos($content, 'PostController::__construct()') !== false) {
        if (strpos($content, 'new PostController(') !== false) {
            $issues[] = 'PostController constructor needs ActivityLoggingService';
        }
    }

    if (strpos($content, 'AttachmentService::__construct()') !== false) {
        if (strpos($content, 'new AttachmentService(') !== false) {
            $issues[] = 'AttachmentService constructor needs ActivityLoggingService';
        }
    }

    return $issues;
}

function reportAnalysis()
{
    $testFiles = scanTestFiles(__DIR__ . '/../tests');

    echo "🔍 掃描測試檔案...\n";
    echo "找到 " . count($testFiles) . " 個測試檔案\n\n";

    $totalIssues = 0;
    foreach ($testFiles as $file) {
        $issues = analyzeTestFile($file);
        if (!empty($issues)) {
            $relativePath = str_replace(__DIR__ . '/../', '', $file);
            echo "📄 {$relativePath}\n";
            foreach ($issues as $issue) {
                echo "  ⚠️  {$issue}\n";
                $totalIssues++;
            }
            echo "\n";
        }
    }

    echo "📊 總計發現 {$totalIssues} 個問題\n";

    if ($totalIssues > 0) {
        echo "\n💡 建議的修復步驟：\n";
        echo "1. 為每個有建構子問題的測試類別添加 ActivityLoggingServiceInterface mock\n";
        echo "2. 更新建構子呼叫以包含新的依賴項\n";
        echo "3. 添加適當的 mock 預期\n";
        echo "4. 更新導入語句\n";
    }

    return $totalIssues;
}

// 執行分析
$issueCount = reportAnalysis();
exit($issueCount > 0 ? 1 : 0);
