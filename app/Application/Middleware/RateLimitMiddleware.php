<?php

namespace App\Application\Middleware;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\DTOs\CreateActivityLogDTO;
use App\Domains\Security\Enums\ActivityType;
use App\Infrastructure\Routing\Contracts\MiddlewareInterface;
use App\Infrastructure\Routing\Contracts\RequestHandlerInterface;
use App\Infrastructure\Services\RateLimitService;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class RateLimitMiddleware implements MiddlewareInterface
{
    private RateLimitService $rateLimitService;

    private array $config;

    public function __construct(
        RateLimitService $rateLimitService,
        private ActivityLoggingServiceInterface $activityLogger,
        array $config = [],
    ) {
        $this->rateLimitService = $rateLimitService;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    public function process(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri()->getPath();

        // 檢查是否需要跳過速率限制
        if (in_array($uri, $this->config['skip_paths'], true)) {
            return $handler->handle($request);
        }

        // 取得真實客戶端 IP
        $ip = $this->getRealClientIP($request->getServerParams());

        // 判斷操作類型
        $action = $this->determineAction($request);

        // 取得使用者 ID（如果已登入）
        $userId = $this->getUserId($request);

        // 檢查速率限制
        $maxRequests = $this->config['max_requests'] ?? 60;
        $timeWindow = $this->config['time_window'] ?? 60;
        $result = $this->rateLimitService->checkLimit($ip, $maxRequests, $timeWindow);

        if (!$result['allowed']) {
            // 記錄速率限制違規
            $this->logRateLimitViolation($request, $ip, $userId, $result);

            return $this->createRateLimitResponse($result, $request);
        }

        // 設定速率限制標頭
        $response = $handler->handle($request);

        return $this->addRateLimitHeaders($response, $result);
    }

    /**
     * 判斷請求的操作類型.
     */
    private function determineAction(Request $request): string
    {
        $uri = $request->getUri()->getPath();
        $method = $request->getMethod();

        // 登入相關 (最優先判斷)
        if (strpos($uri, '/auth/login') !== false) {
            return 'login';
        }

        if (strpos($uri, '/auth/register') !== false) {
            return 'register';
        }

        if (strpos($uri, '/auth/password-reset') !== false) {
            return 'password_reset';
        }

        // API 路由
        if (strpos($uri, '/api/') === 0) {
            return 'api';
        }

        // 內容建立
        if ($method === 'POST' && strpos($uri, '/posts') !== false) {
            return 'post_create';
        }

        // 預設
        return 'default';
    }

    /**
     * 取得使用者 ID.
     */
    private function getUserId(Request $request): ?int
    {
        // 從 request attributes 中取得使用者 ID
        $userId = $request->getAttribute('user_id');

        return $userId ? (int) $userId : null;
    }

    /**
     * 建立速率限制回應.
     */
    private function createRateLimitResponse(array $result, Request $request): ResponseInterface
    {
        // 判斷回應格式
        $acceptHeader = $request->getHeaderLine('Accept');
        $isJsonRequest = strpos($acceptHeader, 'application/json') !== false
            || strpos($request->getUri()->getPath(), '/api/') === 0;

        if ($isJsonRequest) {
            $body = json_encode([
                'error' => 'Rate limit exceeded',
                'message' => '請求過於頻繁，請稍後再試',
                'limit' => $result['limit'],
                'remaining' => $result['remaining'],
                'reset' => $result['reset'],
                'retry_after' => $result['reset'] - time(),
            ]) ?: '';

            $response = new Response(429, ['Content-Type' => 'application/json'], $body);
        } else {
            $body = $this->generateRateLimitHtml($result);
            $response = new Response(429, ['Content-Type' => 'text/html; charset=utf-8'], $body);
        }

        return $response
            ->withHeader('Retry-After', (string) ($result['reset'] - time()))
            ->withHeader('X-RateLimit-Limit', (string) $result['limit'])
            ->withHeader('X-RateLimit-Remaining', '0')
            ->withHeader('X-RateLimit-Reset', (string) $result['reset']);
    }

    /**
     * 記錄速率限制違規.
     */
    private function logRateLimitViolation(Request $request, string $ip, ?int $userId, array $result): void
    {
        try {
            $dto = CreateActivityLogDTO::securityEvent(
                actionType: ActivityType::API_RATE_LIMIT_EXCEEDED,
                description: '速率限制超出',
                ipAddress: $ip,
                userAgent: $request->getHeaderLine('User-Agent') ?: null,
                metadata: [
                    'request_method' => $request->getMethod(),
                    'request_uri' => $request->getUri()->getPath(),
                    'rate_limit' => $result['limit'],
                    'requests_made' => $result['limit'] - $result['remaining'] + 1,
                    'time_window' => $this->config['time_window'] ?? 60,
                    'user_id' => $userId,
                    'reset_time' => $result['reset'],
                ],
            );

            if ($userId) {
                $dto = $dto->withUserId($userId);
            }

            $this->activityLogger->log($dto);
        } catch (Throwable $e) {
            // 記錄失敗不應該影響請求處理
            error_log('Failed to log rate limit violation: ' . $e->getMessage());
        }
    }

    /**
     * 添加速率限制標頭.
     */
    private function addRateLimitHeaders(ResponseInterface $response, array $result): ResponseInterface
    {
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $result['limit'])
            ->withHeader('X-RateLimit-Remaining', (string) $result['remaining'])
            ->withHeader('X-RateLimit-Reset', (string) $result['reset']);
    }

    /**
     * 產生速率限制 HTML 頁面.
     */
    private function generateRateLimitHtml(array $result): string
    {
        $retryAfter = $result['reset'] - time();
        $retryTime = date('H:i:s', $result['reset']);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="zh-TW">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>請求過於頻繁 - AlleyNote</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                           background: #f5f5f5; margin: 0; padding: 20px; }
                    .container { max-width: 600px; margin: 50px auto; background: white; 
                                border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 40px; }
                    .icon { text-align: center; font-size: 64px; margin-bottom: 20px; }
                    h1 { color: #e74c3c; text-align: center; margin-bottom: 20px; }
                    .message { text-align: center; color: #666; margin-bottom: 30px; line-height: 1.6; }
                    .info { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; 
                            padding: 20px; margin: 20px 0; }
                    .retry-info { text-align: center; margin-top: 30px; }
                    .countdown { font-size: 24px; font-weight: bold; color: #e74c3c; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="icon">⏱️</div>
                    <h1>請求過於頻繁</h1>
                    <div class="message">
                        您的請求過於頻繁，為了確保服務的穩定性，我們暫時限制了您的存取。<br>
                        請稍候再試，感謝您的理解。
                    </div>
                    <div class="info">
                        <strong>限制資訊：</strong><br>
                        • 每分鐘最多 {$result['limit']} 次請求<br>
                        • 剩餘配額：{$result['remaining']} 次<br>
                        • 重置時間：{$retryTime}
                    </div>
                    <div class="retry-info">
                        <div>請在 <span class="countdown" id="countdown">{$retryAfter}</span> 秒後重試</div>
                    </div>
                </div>
                <script>
                    let countdown = {$retryAfter};
                    const element = document.getElementById('countdown');
                    const timer = setInterval(() => {
                        countdown--;
                        element.textContent = countdown;
                        if (countdown <= 0) {
                            clearInterval(timer);
                            location.reload();
                        }
                    }, 1000);
                </script>
            </body>
            </html>
            HTML;
    }

    /**
     * 預設設定.
     */
    private function getDefaultConfig(): array
    {
        return [
            'skip_paths' => [
                '/health',
                '/status',
                '/favicon.ico',
            ],
        ];
    }

    /**
     * 取得真實的客戶端 IP 位址.
     */
    private function getRealClientIP(array $serverParams): string
    {
        // 檢查代理伺服器的標頭
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
        ];

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ips = explode(',', $serverParams[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function getPriority(): int
    {
        return 10; // 中等優先級
    }

    public function getName(): string
    {
        return 'rate-limit';
    }

    public function shouldProcess(Request $request): bool
    {
        $uri = $request->getUri()->getPath();

        // 檢查是否需要跳過速率限制
        return !in_array($uri, $this->config['skip_paths'], true);
    }
}
