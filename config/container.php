<?php

declare(strict_types=1);

/**
 * DI 容器配置檔案
 * 
 * 定義應用程式所有服務的依賴注入配置
 */

use App\Domains\Auth\Providers\SimpleAuthServiceProvider;
use App\Domains\Security\Providers\SecurityServiceProvider;
use App\Infrastructure\Cache\Providers\CacheServiceProvider;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\ServerRequest;
use App\Infrastructure\Http\ServerRequestFactory;
use App\Infrastructure\Http\Stream;
use App\Infrastructure\Routing\Providers\RoutingServiceProvider;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

return array_merge(
    // 基本 HTTP 服務
    [
        // PSR-7 HTTP 消息介面
        ResponseInterface::class => \DI\factory(function () {
            return new Response();
        }),

        ServerRequestInterface::class => \DI\factory(function () {
            return ServerRequestFactory::fromGlobals();
        }),

        StreamInterface::class => \DI\factory(function () {
            return new Stream();
        }),

        // HTTP 工廠
        ServerRequestFactory::class => \DI\create(ServerRequestFactory::class),

        // 資料庫連線
        PDO::class => \DI\factory(function (\Psr\Container\ContainerInterface $c) {
            $dbPath = $c->get('db.path');
            return new PDO('sqlite:' . $dbPath);
        }),
    ],

    // 路由系統服務
    RoutingServiceProvider::getDefinitions(),
    SecurityServiceProvider::getDefinitions(),
    CacheServiceProvider::getDefinitions(),

    // JWT 認證系統服務
    SimpleAuthServiceProvider::getDefinitions(),

    // 基本應用程式服務
    [
        // 環境配置
        'app.debug' => \DI\env('APP_DEBUG', false),
        'app.name' => \DI\env('APP_NAME', 'AlleyNote'),
        'app.version' => '1.0.0',

        // 資料庫配置（為將來準備）
        'db.path' => \DI\env('DB_PATH', __DIR__ . '/../database/alleynote.sqlite3'),
        'db.driver' => \DI\env('DB_DRIVER', 'sqlite'),

        // 日誌配置
        'log.path' => \DI\env('LOG_PATH', __DIR__ . '/../storage/logs/app.log'),
        'log.level' => \DI\env('LOG_LEVEL', 'info'),

        // 快取配置
        'cache.driver' => \DI\env('CACHE_DRIVER', 'file'),
        'cache.path' => \DI\env('CACHE_PATH', __DIR__ . '/../storage/cache'),

        // API 配置
        'api.base_url' => \DI\env('API_BASE_URL', 'http://localhost'),
        'api.version' => \DI\env('API_VERSION', 'v1'),

        // 安全配置
        'security.jwt_secret' => \DI\env('JWT_SECRET', 'your-secret-key'),
        'security.session_lifetime' => \DI\env('SESSION_LIFETIME', 3600),
    ],

    // 第三方服務配置（為將來擴展準備）
    [
        // Monolog Logger（如果需要）
        // Monolog\Logger::class => \DI\factory(function (ContainerInterface $c) {
        //     $logger = new \Monolog\Logger($c->get('app.name'));
        //     $logger->pushHandler(new \Monolog\Handler\StreamHandler($c->get('log.path')));
        //     return $logger;
        // }),

        // PDO 連線（如果需要）
        // PDO::class => \DI\factory(function (ContainerInterface $c) {
        //     return new PDO('sqlite:' . $c->get('db.path'));
        // }),
    ]
);
