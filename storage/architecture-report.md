# 專案架構分析報告（基於 Context7 MCP 最新技術）

**生成時間**: 2025-08-29 12:04:43

## 📊 程式碼品質指標

| 指標 | 數值 | 狀態 |
|------|------|------|
| 總類別數 | 186 | - |
| 介面與類別比例 | 22.04% | ✅ 良好 |
| 平均依賴數/類別 | 0.00 | ✅ 良好 |
| 現代 PHP 採用率 | 56.45% | ✅ 良好 |
| PSR-4 合規率 | 82.50% | ❌ 需修正 |
| DDD 結構完整性 | 80.00% | ✅ 良好 |

## 🎯 DDD 邊界上下文分析

### Attachment 上下文

| 組件類型 | 數量 | 項目 |
|----------|------|------|
| 實體 | 0 | - |
| 值物件 | 0 | - |
| 聚合 | 0 | - |
| 儲存庫 | 2 | AttachmentRepository, AttachmentRepositoryInterface |
| 領域服務 | 4 | AttachmentService, FileSecurityService, FileSecurityServiceInterface... |
| 領域事件 | 0 | - |

### Auth 上下文

| 組件類型 | 數量 | 項目 |
|----------|------|------|
| 實體 | 0 | - |
| 值物件 | 16 | JwtPayload, TokenPair, DeviceInfo... |
| 聚合 | 0 | - |
| 儲存庫 | 4 | UserRepository, AuthServiceProvider, SimpleAuthServiceProvider... |
| 領域服務 | 8 | PwnedPasswordService, PasswordManagementService, SessionSecurityService... |
| 領域事件 | 0 | - |

### Post 上下文

| 組件類型 | 數量 | 項目 |
|----------|------|------|
| 實體 | 0 | - |
| 值物件 | 0 | - |
| 聚合 | 0 | - |
| 儲存庫 | 3 | PostService, PostRepository, PostRepositoryInterface |
| 領域服務 | 4 | RichTextProcessorService, PostCacheKeyService, ContentModerationService... |
| 領域事件 | 0 | - |

### Security 上下文

| 組件類型 | 數量 | 項目 |
|----------|------|------|
| 實體 | 0 | - |
| 值物件 | 0 | - |
| 聚合 | 0 | - |
| 儲存庫 | 8 | ActivityLoggingService, IpService, SuspiciousActivityDetector... |
| 領域服務 | 14 | SecurityTestService, SecurityHeaderService, CsrfProtectionService... |
| 領域事件 | 0 | - |

### storage 上下文

| 組件類型 | 數量 | 項目 |
|----------|------|------|
| 實體 | 0 | - |
| 值物件 | 0 | - |
| 聚合 | 0 | - |
| 儲存庫 | 0 | - |
| 領域服務 | 0 | - |
| 領域事件 | 0 | - |

## 🚀 現代 PHP 特性使用情況

| 特性 | 使用次數 | 描述 |
|------|----------|------|
| Match 表達式 (PHP 8.0+) | 154 | ✅ 更安全的條件分支 |
| 唯讀屬性 (PHP 8.1+) | 101 | ✅ 提升資料不變性 |
| 屬性標籤 (PHP 8.0+) | 74 | ✅ 現代化 metadata |
| 空安全運算子 (PHP 8.0+) | 55 | ✅ 防止 null 指標異常 |
| 建構子屬性提升 (PHP 8.0+) | 21 | ✅ 減少樣板程式碼 |
| 聯合型別 (PHP 8.0+) | 17 | ✅ 更靈活的型別定義 |
| 列舉型別 (PHP 8.1+) | 5 | ✅ 型別安全的常數 |

## 📁 目錄結構

- `app`
- `app/Application`
- `app/Application/Controllers`
- `app/Application/Controllers/Health`
- `app/Application/Controllers/Health/.`
- `app/Application/Controllers/Health/..`
- `app/Application/Controllers/Security`
- `app/Application/Controllers/Security/.`
- `app/Application/Controllers/Security/..`
- `app/Application/Controllers/.`
- `app/Application/Controllers/Web`
- `app/Application/Controllers/Web/.`
- `app/Application/Controllers/Web/..`
- `app/Application/Controllers/..`
- `app/Application/Controllers/Api`
- `app/Application/Controllers/Api/V1`
- `app/Application/Controllers/Api/V1/.`
- `app/Application/Controllers/Api/V1/..`
- `app/Application/Controllers/Api/.`
- `app/Application/Controllers/Api/..`
- `app/Application/.`
- `app/Application/..`
- `app/Application/Middleware`
- `app/Application/Middleware/.`
- `app/Application/Middleware/..`
- `app/Shared`
- `app/Shared/Helpers`
- `app/Shared/Helpers/.`
- `app/Shared/Helpers/..`
- `app/Shared/OpenApi`
- `app/Shared/OpenApi/.`
- `app/Shared/OpenApi/..`
- `app/Shared/Schemas`
- `app/Shared/Schemas/.`
- `app/Shared/Schemas/..`
- `app/Shared/.`
- `app/Shared/Config`
- `app/Shared/Config/.`
- `app/Shared/Config/..`
- `app/Shared/Exceptions`
- `app/Shared/Exceptions/.`
- `app/Shared/Exceptions/..`
- `app/Shared/Exceptions/Validation`
- `app/Shared/Exceptions/Validation/.`
- `app/Shared/Exceptions/Validation/..`
- `app/Shared/..`
- `app/Shared/DTOs`
- `app/Shared/DTOs/.`
- `app/Shared/DTOs/..`
- `app/Shared/Contracts`
- `app/Shared/Contracts/.`
- `app/Shared/Contracts/..`
- `app/Shared/Validation`
- `app/Shared/Validation/.`
- `app/Shared/Validation/..`
- `app/Shared/Validation/Factory`
- `app/Shared/Validation/Factory/.`
- `app/Shared/Validation/Factory/..`
- `app/Shared/Http`
- `app/Shared/Http/.`
- `app/Shared/Http/..`
- `app/.`
- `app/Domains`
- `app/Domains/Attachment`
- `app/Domains/Attachment/Models`
- `app/Domains/Attachment/Models/.`
- `app/Domains/Attachment/Models/..`
- `app/Domains/Attachment/Services`
- `app/Domains/Attachment/Services/.`
- `app/Domains/Attachment/Services/..`
- `app/Domains/Attachment/Repositories`
- `app/Domains/Attachment/Repositories/.`
- `app/Domains/Attachment/Repositories/..`
- `app/Domains/Attachment/.`
- `app/Domains/Attachment/..`
- `app/Domains/Attachment/DTOs`
- `app/Domains/Attachment/DTOs/.`
- `app/Domains/Attachment/DTOs/..`
- `app/Domains/Attachment/Contracts`
- `app/Domains/Attachment/Contracts/.`
- `app/Domains/Attachment/Contracts/..`
- `app/Domains/Attachment/Enums`
- `app/Domains/Attachment/Enums/.`
- `app/Domains/Attachment/Enums/..`
- `app/Domains/Security`
- `app/Domains/Security/Models`
- `app/Domains/Security/Models/.`
- `app/Domains/Security/Models/..`
- `app/Domains/Security/Services`
- `app/Domains/Security/Services/Advanced`
- `app/Domains/Security/Services/Advanced/.`
- `app/Domains/Security/Services/Advanced/..`
- `app/Domains/Security/Services/Headers`
- `app/Domains/Security/Services/Headers/.`
- `app/Domains/Security/Services/Headers/..`
- `app/Domains/Security/Services/Core`
- `app/Domains/Security/Services/Core/.`
- `app/Domains/Security/Services/Core/..`
- `app/Domains/Security/Services/Error`
- `app/Domains/Security/Services/Error/.`
- `app/Domains/Security/Services/Error/..`
- `app/Domains/Security/Services/Activity`
- `app/Domains/Security/Services/Activity/.`
- `app/Domains/Security/Services/Activity/..`
- `app/Domains/Security/Services/Logging`
- `app/Domains/Security/Services/Logging/.`
- `app/Domains/Security/Services/Logging/..`
- `app/Domains/Security/Services/.`
- `app/Domains/Security/Services/..`
- `app/Domains/Security/Services/Secrets`
- `app/Domains/Security/Services/Secrets/.`
- `app/Domains/Security/Services/Secrets/..`
- `app/Domains/Security/Services/Content`
- `app/Domains/Security/Services/Content/.`
- `app/Domains/Security/Services/Content/..`
- `app/Domains/Security/Entities`
- `app/Domains/Security/Entities/.`
- `app/Domains/Security/Entities/..`
- `app/Domains/Security/Repositories`
- `app/Domains/Security/Repositories/.`
- `app/Domains/Security/Repositories/..`
- `app/Domains/Security/Providers`
- `app/Domains/Security/Providers/.`
- `app/Domains/Security/Providers/..`
- `app/Domains/Security/.`
- `app/Domains/Security/..`
- `app/Domains/Security/DTOs`
- `app/Domains/Security/DTOs/.`
- `app/Domains/Security/DTOs/..`
- `app/Domains/Security/Contracts`
- `app/Domains/Security/Contracts/.`
- `app/Domains/Security/Contracts/..`
- `app/Domains/Security/Enums`
- `app/Domains/Security/Enums/.`
- `app/Domains/Security/Enums/..`
- `app/Domains/Post`
- `app/Domains/Post/Models`
- `app/Domains/Post/Models/.`
- `app/Domains/Post/Models/..`
- `app/Domains/Post/Services`
- `app/Domains/Post/Services/.`
- `app/Domains/Post/Services/..`
- `app/Domains/Post/Repositories`
- `app/Domains/Post/Repositories/.`
- `app/Domains/Post/Repositories/..`
- `app/Domains/Post/.`
- `app/Domains/Post/Exceptions`
- `app/Domains/Post/Exceptions/.`
- `app/Domains/Post/Exceptions/..`
- `app/Domains/Post/..`
- `app/Domains/Post/DTOs`
- `app/Domains/Post/DTOs/.`
- `app/Domains/Post/DTOs/..`
- `app/Domains/Post/Contracts`
- `app/Domains/Post/Contracts/.`
- `app/Domains/Post/Contracts/..`
- `app/Domains/Post/Enums`
- `app/Domains/Post/Enums/.`
- `app/Domains/Post/Enums/..`
- `app/Domains/Post/Validation`
- `app/Domains/Post/Validation/.`
- `app/Domains/Post/Validation/..`
- `app/Domains/.`
- `app/Domains/..`
- `app/Domains/Auth`
- `app/Domains/Auth/Models`
- `app/Domains/Auth/Models/.`
- `app/Domains/Auth/Models/..`
- `app/Domains/Auth/ValueObjects`
- `app/Domains/Auth/ValueObjects/.`
- `app/Domains/Auth/ValueObjects/..`
- `app/Domains/Auth/Services`
- `app/Domains/Auth/Services/Advanced`
- `app/Domains/Auth/Services/Advanced/.`
- `app/Domains/Auth/Services/Advanced/..`
- `app/Domains/Auth/Services/.`
- `app/Domains/Auth/Services/..`
- `app/Domains/Auth/Entities`
- `app/Domains/Auth/Entities/.`
- `app/Domains/Auth/Entities/..`
- `app/Domains/Auth/Repositories`
- `app/Domains/Auth/Repositories/.`
- `app/Domains/Auth/Repositories/..`
- `app/Domains/Auth/Providers`
- `app/Domains/Auth/Providers/.`
- `app/Domains/Auth/Providers/..`
- `app/Domains/Auth/.`
- `app/Domains/Auth/Exceptions`
- `app/Domains/Auth/Exceptions/.`
- `app/Domains/Auth/Exceptions/..`
- `app/Domains/Auth/..`
- `app/Domains/Auth/DTOs`
- `app/Domains/Auth/DTOs/.`
- `app/Domains/Auth/DTOs/..`
- `app/Domains/Auth/Contracts`
- `app/Domains/Auth/Contracts/.`
- `app/Domains/Auth/Contracts/..`
- `app/..`
- `app/Infrastructure`
- `app/Infrastructure/Services`
- `app/Infrastructure/Services/.`
- `app/Infrastructure/Services/..`
- `app/Infrastructure/Cache`
- `app/Infrastructure/Cache/Providers`
- `app/Infrastructure/Cache/Providers/.`
- `app/Infrastructure/Cache/Providers/..`
- `app/Infrastructure/Cache/.`
- `app/Infrastructure/Cache/..`
- `app/Infrastructure/OpenApi`
- `app/Infrastructure/OpenApi/.`
- `app/Infrastructure/OpenApi/..`
- `app/Infrastructure/Database`
- `app/Infrastructure/Database/.`
- `app/Infrastructure/Database/..`
- `app/Infrastructure/.`
- `app/Infrastructure/Config`
- `app/Infrastructure/Config/.`
- `app/Infrastructure/Config/..`
- `app/Infrastructure/..`
- `app/Infrastructure/Routing`
- `app/Infrastructure/Routing/Cache`
- `app/Infrastructure/Routing/Cache/.`
- `app/Infrastructure/Routing/Cache/..`
- `app/Infrastructure/Routing/Core`
- `app/Infrastructure/Routing/Core/.`
- `app/Infrastructure/Routing/Core/..`
- `app/Infrastructure/Routing/Providers`
- `app/Infrastructure/Routing/Providers/.`
- `app/Infrastructure/Routing/Providers/..`
- `app/Infrastructure/Routing/.`
- `app/Infrastructure/Routing/Exceptions`
- `app/Infrastructure/Routing/Exceptions/.`
- `app/Infrastructure/Routing/Exceptions/..`
- `app/Infrastructure/Routing/..`
- `app/Infrastructure/Routing/Contracts`
- `app/Infrastructure/Routing/Contracts/.`
- `app/Infrastructure/Routing/Contracts/..`
- `app/Infrastructure/Routing/Middleware`
- `app/Infrastructure/Routing/Middleware/.`
- `app/Infrastructure/Routing/Middleware/..`
- `app/Infrastructure/Auth`
- `app/Infrastructure/Auth/Repositories`
- `app/Infrastructure/Auth/Repositories/.`
- `app/Infrastructure/Auth/Repositories/..`
- `app/Infrastructure/Auth/.`
- `app/Infrastructure/Auth/..`
- `app/Infrastructure/Auth/Jwt`
- `app/Infrastructure/Auth/Jwt/.`
- `app/Infrastructure/Auth/Jwt/..`
- `app/Infrastructure/Http`
- `app/Infrastructure/Http/.`
- `app/Infrastructure/Http/..`
- `scripts`
- `scripts/consolidated`
- `scripts/consolidated/.`
- `scripts/consolidated/..`
- `scripts/.`
- `scripts/lib`
- `scripts/lib/.`
- `scripts/lib/..`
- `scripts/..`
- `config`
- `config/routes`
- `config/routes/.`
- `config/routes/..`
- `config/.`
- `config/..`
- `database`
- `database/migrations`
- `database/migrations/.`
- `database/migrations/..`
- `database/seeds`
- `database/seeds/.`
- `database/seeds/..`
- `database/.`
- `database/..`
- `docs`
- `docs/archive`
- `docs/archive/.`
- `docs/archive/..`
- `docs/.`
- `docs/..`
- `.`
- `.github`
- `.github/workflows`
- `.github/workflows/.`
- `.github/workflows/..`
- `.github/.`
- `.github/..`
- `.github/chatmodes`
- `.github/chatmodes/.`
- `.github/chatmodes/..`
- `..`
- `ssl-data`
- `ssl-data/.`
- `ssl-data/..`
- `certbot-data`
- `certbot-data/.`
- `certbot-data/..`
- `examples`
- `examples/.`
- `examples/..`

## 🏷️ 命名空間分析

### `App\Application\Controllers\Health`
- app/Application/Controllers/Health/HealthController.php

### `App\Application\Controllers`
- app/Application/Controllers/TestController.php
- app/Application/Controllers/PostController.php
- app/Application/Controllers/BaseController.php

### `App\Application\Controllers\Security`
- app/Application/Controllers/Security/CSPReportController.php

### `App\Application\Controllers\Web`
- app/Application/Controllers/Web/SwaggerController.php

### `App\Application\Controllers\Api\V1`
- app/Application/Controllers/Api/V1/IpController.php
- app/Application/Controllers/Api/V1/PostController.php
- app/Application/Controllers/Api/V1/ActivityLogController.php
- app/Application/Controllers/Api/V1/AuthController.php
- app/Application/Controllers/Api/V1/AttachmentController.php

### `App\Application\Middleware`
- app/Application/Middleware/RateLimitMiddleware.php
- app/Application/Middleware/JwtAuthenticationMiddleware.php
- app/Application/Middleware/AuthorizationMiddleware.php
- app/Application/Middleware/AuthorizationResult.php
- app/Application/Middleware/JwtAuthorizationMiddleware.php

### `App`
- app/Application.php

### `App\Shared\Schemas`
- app/Shared/Schemas/PostSchema.php
- app/Shared/Schemas/AuthSchema.php
- app/Shared/Schemas/PostRequestSchema.php

### `App\Shared\Config`
- app/Shared/Config/JwtConfig.php

### `App\Shared\Exceptions`
- app/Shared/Exceptions/NotFoundException.php
- app/Shared/Exceptions/CsrfTokenException.php
- app/Shared/Exceptions/ValidationException.php
- app/Shared/Exceptions/StateTransitionException.php

### `App\Shared\Exceptions\Validation`
- app/Shared/Exceptions/Validation/RequestValidationException.php

### `App\Shared\DTOs`
- app/Shared/DTOs/BaseDTO.php

### `App\Shared\Contracts`
- app/Shared/Contracts/CacheInterface.php
- app/Shared/Contracts/CacheServiceInterface.php
- app/Shared/Contracts/OutputSanitizerInterface.php
- app/Shared/Contracts/ValidatorInterface.php
- app/Shared/Contracts/RepositoryInterface.php

### `App\Shared\Validation`
- app/Shared/Validation/Validator.php
- app/Shared/Validation/ValidationResult.php

### `App\Shared\Validation\Factory`
- app/Shared/Validation/Factory/ValidatorFactory.php

### `App\Shared\Http`
- app/Shared/Http/ApiResponse.php

### `App\Domains\Attachment\Models`
- app/Domains/Attachment/Models/Attachment.php

### `App\Domains\Attachment\Services`
- app/Domains/Attachment/Services/AttachmentService.php
- app/Domains/Attachment/Services/FileSecurityService.php

### `App\Domains\Attachment\Repositories`
- app/Domains/Attachment/Repositories/AttachmentRepository.php

### `App\Domains\Attachment\DTOs`
- app/Domains/Attachment/DTOs/CreateAttachmentDTO.php

### `App\Domains\Attachment\Contracts`
- app/Domains/Attachment/Contracts/FileSecurityServiceInterface.php
- app/Domains/Attachment/Contracts/AttachmentServiceInterface.php
- app/Domains/Attachment/Contracts/AttachmentRepositoryInterface.php

### `App\Domains\Attachment\Enums`
- app/Domains/Attachment/Enums/FileRules.php

### `App\Domains\Security\Models`
- app/Domains/Security/Models/IpList.php

### `App\Domains\Security\Services\Advanced`
- app/Domains/Security/Services/Advanced/SecurityTestService.php

### `App\Domains\Security\Services\Headers`
- app/Domains/Security/Services/Headers/SecurityHeaderService.php

### `App\Domains\Security\Services\Core`
- app/Domains/Security/Services/Core/CsrfProtectionService.php
- app/Domains/Security/Services/Core/XssProtectionService.php

### `App\Domains\Security\Services\Error`
- app/Domains/Security/Services/Error/ErrorHandlerService.php

### `App\Domains\Security\Services`
- app/Domains/Security/Services/ActivityLoggingService.php
- app/Domains/Security/Services/IpService.php
- app/Domains/Security/Services/SuspiciousActivityDetector.php

### `App\Domains\Security\Services\Activity`
- app/Domains/Security/Services/Activity/CachedActivityLoggingService.php

### `App\Domains\Security\Services\Logging`
- app/Domains/Security/Services/Logging/LoggingSecurityService.php

### `App\Domains\Security\Services\Secrets`
- app/Domains/Security/Services/Secrets/SecretsManager.php

### `App\Domains\Security\Services\Content`
- app/Domains/Security/Services/Content/XssProtectionExtensionService.php

### `App\Domains\Security\Entities`
- app/Domains/Security/Entities/ActivityLog.php

### `App\Domains\Security\Repositories`
- app/Domains/Security/Repositories/IpRepository.php
- app/Domains/Security/Repositories/ActivityLogRepository.php

### `App\Domains\Security\Providers`
- app/Domains/Security/Providers/SecurityServiceProvider.php

### `App\Domains\Security\DTOs`
- app/Domains/Security/DTOs/CreateActivityLogDTO.php
- app/Domains/Security/DTOs/SuspiciousActivityAnalysisDTO.php
- app/Domains/Security/DTOs/ActivityLogSearchDTO.php
- app/Domains/Security/DTOs/CreateIpRuleDTO.php

### `App\Domains\Security\Contracts`
- app/Domains/Security/Contracts/ActivityLogRepositoryInterface.php
- app/Domains/Security/Contracts/SuspiciousActivityDetectorInterface.php
- app/Domains/Security/Contracts/CsrfProtectionServiceInterface.php
- app/Domains/Security/Contracts/LoggingSecurityServiceInterface.php
- app/Domains/Security/Contracts/IpRepositoryInterface.php
- app/Domains/Security/Contracts/SecurityTestInterface.php
- app/Domains/Security/Contracts/SecretsManagerInterface.php
- app/Domains/Security/Contracts/XssProtectionServiceInterface.php
- app/Domains/Security/Contracts/SecurityHeaderServiceInterface.php
- app/Domains/Security/Contracts/ErrorHandlerServiceInterface.php
- app/Domains/Security/Contracts/ActivityLoggingServiceInterface.php

### `App\Domains\Security\Enums`
- app/Domains/Security/Enums/ActivitySeverity.php
- app/Domains/Security/Enums/ActivityCategory.php
- app/Domains/Security/Enums/ActivityType.php
- app/Domains/Security/Enums/ActivityStatus.php

### `App\Domains\Post\Models`
- app/Domains/Post/Models/Post.php

### `App\Domains\Post\Services`
- app/Domains/Post/Services/RichTextProcessorService.php
- app/Domains/Post/Services/PostService.php
- app/Domains/Post/Services/PostCacheKeyService.php
- app/Domains/Post/Services/ContentModerationService.php

### `App\Domains\Post\Repositories`
- app/Domains/Post/Repositories/PostRepository.php

### `App\Domains\Post\Exceptions`
- app/Domains/Post/Exceptions/PostStatusException.php
- app/Domains/Post/Exceptions/PostValidationException.php
- app/Domains/Post/Exceptions/PostNotFoundException.php

### `App\Domains\Post\DTOs`
- app/Domains/Post/DTOs/CreatePostDTO.php
- app/Domains/Post/DTOs/UpdatePostDTO.php

### `App\Domains\Post\Contracts`
- app/Domains/Post/Contracts/PostServiceInterface.php
- app/Domains/Post/Contracts/PostRepositoryInterface.php

### `App\Domains\Post\Enums`
- app/Domains/Post/Enums/PostStatus.php

### `App\Domains\Post\Validation`
- app/Domains/Post/Validation/PostValidator.php

### `App\Domains\Auth\Models`
- app/Domains/Auth/Models/Role.php
- app/Domains/Auth/Models/Permission.php

### `AlleyNote\Domains\Auth\ValueObjects`
- app/Domains/Auth/ValueObjects/JwtPayload.php
- app/Domains/Auth/ValueObjects/TokenPair.php
- app/Domains/Auth/ValueObjects/DeviceInfo.php
- app/Domains/Auth/ValueObjects/TokenBlacklistEntry.php

### `AlleyNote\Domains\Auth\Services`
- app/Domains/Auth/Services/RefreshTokenService.php
- app/Domains/Auth/Services/AuthenticationService.php
- app/Domains/Auth/Services/TokenBlacklistService.php
- app/Domains/Auth/Services/JwtTokenService.php

### `App\Domains\Auth\Services`
- app/Domains/Auth/Services/AuthService.php
- app/Domains/Auth/Services/PasswordManagementService.php
- app/Domains/Auth/Services/SessionSecurityService.php
- app/Domains/Auth/Services/PasswordSecurityService.php
- app/Domains/Auth/Services/AuthorizationService.php

### `App\Domains\Auth\Services\Advanced`
- app/Domains/Auth/Services/Advanced/PwnedPasswordService.php

### `AlleyNote\Domains\Auth\Entities`
- app/Domains/Auth/Entities/RefreshToken.php

### `App\Domains\Auth\Repositories`
- app/Domains/Auth/Repositories/UserRepository.php

### `App\Domains\Auth\Providers`
- app/Domains/Auth/Providers/AuthServiceProvider.php
- app/Domains/Auth/Providers/SimpleAuthServiceProvider.php

### `AlleyNote\Domains\Auth\Exceptions`
- app/Domains/Auth/Exceptions/JwtException.php
- app/Domains/Auth/Exceptions/AuthenticationException.php
- app/Domains/Auth/Exceptions/TokenValidationException.php
- app/Domains/Auth/Exceptions/JwtConfigurationException.php
- app/Domains/Auth/Exceptions/RefreshTokenException.php
- app/Domains/Auth/Exceptions/TokenGenerationException.php
- app/Domains/Auth/Exceptions/InvalidTokenException.php
- app/Domains/Auth/Exceptions/TokenExpiredException.php
- app/Domains/Auth/Exceptions/TokenParsingException.php

### `App\Domains\Auth\Exceptions`
- app/Domains/Auth/Exceptions/ForbiddenException.php
- app/Domains/Auth/Exceptions/UnauthorizedException.php

### `App\Domains\Auth\DTOs`
- app/Domains/Auth/DTOs/RegisterUserDTO.php

### `AlleyNote\Domains\Auth\DTOs`
- app/Domains/Auth/DTOs/LoginResponseDTO.php
- app/Domains/Auth/DTOs/RefreshRequestDTO.php
- app/Domains/Auth/DTOs/LoginRequestDTO.php
- app/Domains/Auth/DTOs/LogoutRequestDTO.php
- app/Domains/Auth/DTOs/RefreshResponseDTO.php

### `App\Domains\Auth\Contracts`
- app/Domains/Auth/Contracts/PasswordSecurityServiceInterface.php
- app/Domains/Auth/Contracts/UserRepositoryInterface.php
- app/Domains/Auth/Contracts/SessionSecurityServiceInterface.php
- app/Domains/Auth/Contracts/AuthorizationServiceInterface.php

### `AlleyNote\Domains\Auth\Contracts`
- app/Domains/Auth/Contracts/AuthenticationServiceInterface.php
- app/Domains/Auth/Contracts/TokenBlacklistRepositoryInterface.php
- app/Domains/Auth/Contracts/JwtProviderInterface.php
- app/Domains/Auth/Contracts/JwtTokenServiceInterface.php
- app/Domains/Auth/Contracts/RefreshTokenRepositoryInterface.php

### `App\Infrastructure\Services`
- app/Infrastructure/Services/CacheService.php
- app/Infrastructure/Services/RateLimitService.php
- app/Infrastructure/Services/OutputSanitizer.php

### `App\Infrastructure\Cache\Providers`
- app/Infrastructure/Cache/Providers/CacheServiceProvider.php

### `App\Infrastructure\Cache`
- app/Infrastructure/Cache/CacheKeys.php
- app/Infrastructure/Cache/RedisCache.php
- app/Infrastructure/Cache/CacheManager.php

### `App\Infrastructure\OpenApi`
- app/Infrastructure/OpenApi/OpenApiSpec.php

### `App\Infrastructure\Database`
- app/Infrastructure/Database/DatabaseConnection.php

### `App\Infrastructure\Config`
- app/Infrastructure/Config/ContainerFactory.php

### `App\Infrastructure\Routing\Cache`
- app/Infrastructure/Routing/Cache/FileRouteCache.php
- app/Infrastructure/Routing/Cache/RouteCacheFactory.php
- app/Infrastructure/Routing/Cache/MemoryRouteCache.php
- app/Infrastructure/Routing/Cache/RedisRouteCache.php

### `App\Infrastructure\Routing\Core`
- app/Infrastructure/Routing/Core/Router.php
- app/Infrastructure/Routing/Core/Route.php
- app/Infrastructure/Routing/Core/RouteCollection.php

### `App\Infrastructure\Routing`
- app/Infrastructure/Routing/RouteDispatcher.php
- app/Infrastructure/Routing/ControllerResolver.php
- app/Infrastructure/Routing/RouteLoader.php
- app/Infrastructure/Routing/RouteValidator.php
- app/Infrastructure/Routing/ClosureRequestHandler.php

### `App\Infrastructure\Routing\Providers`
- app/Infrastructure/Routing/Providers/RoutingServiceProvider.php

### `App\Infrastructure\Routing\Exceptions`
- app/Infrastructure/Routing/Exceptions/RouteConfigurationException.php

### `App\Infrastructure\Routing\Contracts`
- app/Infrastructure/Routing/Contracts/RouterInterface.php
- app/Infrastructure/Routing/Contracts/MiddlewareManagerInterface.php
- app/Infrastructure/Routing/Contracts/MiddlewareInterface.php
- app/Infrastructure/Routing/Contracts/RequestHandlerInterface.php
- app/Infrastructure/Routing/Contracts/MiddlewareDispatcherInterface.php
- app/Infrastructure/Routing/Contracts/RouteMatchResult.php
- app/Infrastructure/Routing/Contracts/RouteCacheInterface.php
- app/Infrastructure/Routing/Contracts/RouteCollectionInterface.php
- app/Infrastructure/Routing/Contracts/RouteInterface.php

### `App\Infrastructure\Routing\Middleware`
- app/Infrastructure/Routing/Middleware/MiddlewareDispatcher.php
- app/Infrastructure/Routing/Middleware/MiddlewareResolver.php
- app/Infrastructure/Routing/Middleware/MiddlewareManager.php
- app/Infrastructure/Routing/Middleware/AbstractMiddleware.php
- app/Infrastructure/Routing/Middleware/RouteParametersMiddleware.php
- app/Infrastructure/Routing/Middleware/RouteInfoMiddleware.php

### `AlleyNote\Infrastructure\Auth\Repositories`
- app/Infrastructure/Auth/Repositories/RefreshTokenRepository.php
- app/Infrastructure/Auth/Repositories/TokenBlacklistRepository.php

### `App\Infrastructure\Auth\Jwt`
- app/Infrastructure/Auth/Jwt/FirebaseJwtProvider.php

### `App\Infrastructure\Http`
- app/Infrastructure/Http/ServerRequest.php
- app/Infrastructure/Http/Stream.php
- app/Infrastructure/Http/Uri.php
- app/Infrastructure/Http/Response.php
- app/Infrastructure/Http/ServerRequestFactory.php

### `後添加
            if (preg_match('/^namespace [^`
- scripts/fix-phpunit-deprecations.php

### `= trim($matches[1])`
- scripts/scan-project-architecture.php

### `AlleyNote\Scripts\Consolidated`
- scripts/consolidated/ConsolidatedTestManager.php
- scripts/consolidated/ConsolidatedDeployer.php
- scripts/consolidated/ConsolidatedMaintainer.php
- scripts/consolidated/DefaultScriptAnalyzer.php
- scripts/consolidated/ScriptManager.php
- scripts/consolidated/DefaultScriptExecutor.php
- scripts/consolidated/ConsolidatedAnalyzer.php
- scripts/consolidated/DefaultScriptConfiguration.php
- scripts/consolidated/ConsolidatedErrorFixer.php


## 🏗️ DDD 架構分析

### Application 層
**子目錄**: Controllers, Controllers/Health, Controllers/Health/., Controllers/Health/.., Controllers/Security, Controllers/Security/., Controllers/Security/.., Controllers/., Controllers/Web, Controllers/Web/., Controllers/Web/.., Controllers/.., Controllers/Api, Controllers/Api/V1, Controllers/Api/V1/., Controllers/Api/V1/.., Controllers/Api/., Controllers/Api/.., .., Middleware, Middleware/., Middleware/..
**檔案數量**: 16

### Domains 層
**子目錄**: Attachment, Attachment/Models, Attachment/Models/., Attachment/Models/.., Attachment/Services, Attachment/Services/., Attachment/Services/.., Attachment/Repositories, Attachment/Repositories/., Attachment/Repositories/.., Attachment/., Attachment/.., Attachment/DTOs, Attachment/DTOs/., Attachment/DTOs/.., Attachment/Contracts, Attachment/Contracts/., Attachment/Contracts/.., Attachment/Enums, Attachment/Enums/., Attachment/Enums/.., Security, Security/Models, Security/Models/., Security/Models/.., Security/Services, Security/Services/Advanced, Security/Services/Advanced/., Security/Services/Advanced/.., Security/Services/Headers, Security/Services/Headers/., Security/Services/Headers/.., Security/Services/Core, Security/Services/Core/., Security/Services/Core/.., Security/Services/Error, Security/Services/Error/., Security/Services/Error/.., Security/Services/Activity, Security/Services/Activity/., Security/Services/Activity/.., Security/Services/Logging, Security/Services/Logging/., Security/Services/Logging/.., Security/Services/., Security/Services/.., Security/Services/Secrets, Security/Services/Secrets/., Security/Services/Secrets/.., Security/Services/Content, Security/Services/Content/., Security/Services/Content/.., Security/Entities, Security/Entities/., Security/Entities/.., Security/Repositories, Security/Repositories/., Security/Repositories/.., Security/Providers, Security/Providers/., Security/Providers/.., Security/., Security/.., Security/DTOs, Security/DTOs/., Security/DTOs/.., Security/Contracts, Security/Contracts/., Security/Contracts/.., Security/Enums, Security/Enums/., Security/Enums/.., Post, Post/Models, Post/Models/., Post/Models/.., Post/Services, Post/Services/., Post/Services/.., Post/Repositories, Post/Repositories/., Post/Repositories/.., Post/., Post/Exceptions, Post/Exceptions/., Post/Exceptions/.., Post/.., Post/DTOs, Post/DTOs/., Post/DTOs/.., Post/Contracts, Post/Contracts/., Post/Contracts/.., Post/Enums, Post/Enums/., Post/Enums/.., Post/Validation, Post/Validation/., Post/Validation/.., .., Auth, Auth/Models, Auth/Models/., Auth/Models/.., Auth/ValueObjects, Auth/ValueObjects/., Auth/ValueObjects/.., Auth/Services, Auth/Services/Advanced, Auth/Services/Advanced/., Auth/Services/Advanced/.., Auth/Services/., Auth/Services/.., Auth/Entities, Auth/Entities/., Auth/Entities/.., Auth/Repositories, Auth/Repositories/., Auth/Repositories/.., Auth/Providers, Auth/Providers/., Auth/Providers/.., Auth/., Auth/Exceptions, Auth/Exceptions/., Auth/Exceptions/.., Auth/.., Auth/DTOs, Auth/DTOs/., Auth/DTOs/.., Auth/Contracts, Auth/Contracts/., Auth/Contracts/.., storage, storage/., storage/.., storage/cache, storage/cache/htmlpurifier, storage/cache/htmlpurifier/., storage/cache/htmlpurifier/.., storage/cache/., storage/cache/..
**檔案數量**: 106

### Infrastructure 層
**子目錄**: Services, Services/., Services/.., Cache, Cache/Providers, Cache/Providers/., Cache/Providers/.., Cache/., Cache/.., OpenApi, OpenApi/., OpenApi/.., Database, Database/., Database/.., Config, Config/., Config/.., .., Routing, Routing/Cache, Routing/Cache/., Routing/Cache/.., Routing/Core, Routing/Core/., Routing/Core/.., Routing/Providers, Routing/Providers/., Routing/Providers/.., Routing/., Routing/Exceptions, Routing/Exceptions/., Routing/Exceptions/.., Routing/.., Routing/Contracts, Routing/Contracts/., Routing/Contracts/.., Routing/Middleware, Routing/Middleware/., Routing/Middleware/.., Auth, Auth/Repositories, Auth/Repositories/., Auth/Repositories/.., Auth/., Auth/.., Auth/Jwt, Auth/Jwt/., Auth/Jwt/.., Http, Http/., Http/..
**檔案數量**: 48

### Shared 層
**子目錄**: Helpers, Helpers/., Helpers/.., OpenApi, OpenApi/., OpenApi/.., Schemas, Schemas/., Schemas/.., Config, Config/., Config/.., Exceptions, Exceptions/., Exceptions/.., Exceptions/Validation, Exceptions/Validation/., Exceptions/Validation/.., .., DTOs, DTOs/., DTOs/.., Contracts, Contracts/., Contracts/.., Validation, Validation/., Validation/.., Validation/Factory, Validation/Factory/., Validation/Factory/.., Http, Http/., Http/..
**檔案數量**: 21


## 📊 類別統計

- **類別總數**: 186
- **介面總數**: 41
- **Trait 總數**: 0

## ⚠️ 發現的架構問題

- ⚠️  可能的循環依賴: app/Application/Controllers/Health/HealthController.php -> App\Application\Controllers\BaseController
- ⚠️  可能的循環依賴: app/Application/Controllers/Api/V1/PostController.php -> App\Application\Controllers\BaseController
- ⚠️  可能的循環依賴: app/Application/Controllers/Api/V1/ActivityLogController.php -> App\Application\Controllers\BaseController
- ⚠️  可能的循環依賴: app/Application/Controllers/Api/V1/AuthController.php -> App\Application\Controllers\BaseController
- ❌ Domain層不應依賴Infrastructure層: app/Domains/Auth/Providers/AuthServiceProvider.php -> App\Infrastructure\Auth\Jwt\FirebaseJwtProvider
- ❌ Domain層不應依賴Infrastructure層: app/Domains/Auth/Providers/SimpleAuthServiceProvider.php -> App\Infrastructure\Auth\Jwt\FirebaseJwtProvider

## 🔑 重要類別清單

- **HealthController**: `app/Application/Controllers/TestController.php`
  - 實作: 
- **CSPReportController**: `app/Application/Controllers/Security/CSPReportController.php`
  - 實作: 
- **PostController**: `app/Application/Controllers/Api/V1/PostController.php`
  - 繼承: BaseController
  - 實作: 
- **SwaggerController**: `app/Application/Controllers/Web/SwaggerController.php`
  - 實作: 
- **BaseController**: `app/Application/Controllers/BaseController.php`
  - 實作: 
- **IpController**: `app/Application/Controllers/Api/V1/IpController.php`
  - 實作: 
- **ActivityLogController**: `app/Application/Controllers/Api/V1/ActivityLogController.php`
  - 繼承: BaseController
  - 實作: 
- **AuthController**: `app/Application/Controllers/Api/V1/AuthController.php`
  - 繼承: BaseController
  - 實作: 
- **AttachmentController**: `app/Application/Controllers/Api/V1/AttachmentController.php`
  - 實作: 
- **AttachmentService**: `app/Domains/Attachment/Services/AttachmentService.php`
  - 實作: AttachmentServiceInterface
- **FileSecurityService**: `app/Domains/Attachment/Services/FileSecurityService.php`
  - 實作: FileSecurityServiceInterface
- **AttachmentRepository**: `app/Domains/Attachment/Repositories/AttachmentRepository.php`
  - 實作: 
- **SecurityTestService**: `app/Domains/Security/Services/Advanced/SecurityTestService.php`
  - 實作: SecurityTestInterface
- **SecurityHeaderService**: `app/Domains/Security/Services/Headers/SecurityHeaderService.php`
  - 實作: SecurityHeaderServiceInterface
- **CsrfProtectionService**: `app/Domains/Security/Services/Core/CsrfProtectionService.php`
  - 實作: 
- **XssProtectionService**: `app/Domains/Security/Services/Core/XssProtectionService.php`
  - 實作: 
- **ErrorHandlerService**: `app/Domains/Security/Services/Error/ErrorHandlerService.php`
  - 實作: ErrorHandlerServiceInterface
- **ActivityLoggingService**: `app/Domains/Security/Services/ActivityLoggingService.php`
  - 實作: ActivityLoggingServiceInterface
- **CachedActivityLoggingService**: `app/Domains/Security/Services/Activity/CachedActivityLoggingService.php`
  - 實作: ActivityLoggingServiceInterface
- **LoggingSecurityService**: `app/Domains/Security/Services/Logging/LoggingSecurityService.php`
  - 實作: LoggingSecurityServiceInterface
- **SecretsManager**: `app/Domains/Security/Services/Secrets/SecretsManager.php`
  - 實作: SecretsManagerInterface
- **XssProtectionExtensionService**: `app/Domains/Security/Services/Content/XssProtectionExtensionService.php`
  - 實作: 
- **IpService**: `app/Domains/Security/Services/IpService.php`
  - 實作: 
- **SuspiciousActivityDetector**: `app/Domains/Security/Services/SuspiciousActivityDetector.php`
  - 實作: SuspiciousActivityDetectorInterface
- **IpRepository**: `app/Domains/Security/Repositories/IpRepository.php`
  - 實作: IpRepositoryInterface
- **ActivityLogRepository**: `app/Domains/Security/Repositories/ActivityLogRepository.php`
  - 實作: ActivityLogRepositoryInterface
- **SecurityServiceProvider**: `app/Domains/Security/Providers/SecurityServiceProvider.php`
  - 實作: 
- **RichTextProcessorService**: `app/Domains/Post/Services/RichTextProcessorService.php`
  - 實作: 
- **PostService**: `app/Domains/Post/Services/PostService.php`
  - 實作: PostServiceInterface
- **PostCacheKeyService**: `app/Domains/Post/Services/PostCacheKeyService.php`
  - 實作: 
- **ContentModerationService**: `app/Domains/Post/Services/ContentModerationService.php`
  - 實作: 
- **PostRepository**: `app/Domains/Post/Repositories/PostRepository.php`
  - 實作: PostRepositoryInterface
- **RefreshTokenService**: `app/Domains/Auth/Services/RefreshTokenService.php`
  - 實作: 
- **AuthService**: `app/Domains/Auth/Services/AuthService.php`
  - 實作: 
- **PwnedPasswordService**: `app/Domains/Auth/Services/Advanced/PwnedPasswordService.php`
  - 實作: 
- **PasswordManagementService**: `app/Domains/Auth/Services/PasswordManagementService.php`
  - 實作: 
- **SessionSecurityService**: `app/Domains/Auth/Services/SessionSecurityService.php`
  - 實作: SessionSecurityServiceInterface
- **AuthenticationService**: `app/Domains/Auth/Services/AuthenticationService.php`
  - 實作: AuthenticationServiceInterface
- **TokenBlacklistService**: `app/Domains/Auth/Services/TokenBlacklistService.php`
  - 實作: 
- **PasswordSecurityService**: `app/Domains/Auth/Services/PasswordSecurityService.php`
  - 實作: PasswordSecurityServiceInterface
- **AuthorizationService**: `app/Domains/Auth/Services/AuthorizationService.php`
  - 實作: AuthorizationServiceInterface
- **JwtTokenService**: `app/Domains/Auth/Services/JwtTokenService.php`
  - 實作: JwtTokenServiceInterface
- **UserRepository**: `app/Domains/Auth/Repositories/UserRepository.php`
  - 實作: 
- **AuthServiceProvider**: `app/Domains/Auth/Providers/AuthServiceProvider.php`
  - 實作: 
- **SimpleAuthServiceProvider**: `app/Domains/Auth/Providers/SimpleAuthServiceProvider.php`
  - 實作: 
- **CacheService**: `app/Infrastructure/Services/CacheService.php`
  - 實作: CacheServiceInterface
- **RateLimitService**: `app/Infrastructure/Services/RateLimitService.php`
  - 實作: 
- **OutputSanitizer**: `app/Infrastructure/Services/OutputSanitizer.php`
  - 實作: 
- **OutputSanitizerService**: `app/Infrastructure/Services/OutputSanitizer.php`
  - 實作: OutputSanitizerInterface
- **CacheServiceProvider**: `app/Infrastructure/Cache/Providers/CacheServiceProvider.php`
  - 實作: 
- **RoutingServiceProvider**: `app/Infrastructure/Routing/Providers/RoutingServiceProvider.php`
  - 實作: 
- **ControllerResolver**: `app/Infrastructure/Routing/ControllerResolver.php`
  - 實作: 
- **RefreshTokenRepository**: `app/Infrastructure/Auth/Repositories/RefreshTokenRepository.php`
  - 實作: RefreshTokenRepositoryInterface
- **TokenBlacklistRepository**: `app/Infrastructure/Auth/Repositories/TokenBlacklistRepository.php`
  - 實作: TokenBlacklistRepositoryInterface

## 🔌 介面實作分析

### ``
- HealthController (`app/Application/Controllers/TestController.php`)
- CSPReportController (`app/Application/Controllers/Security/CSPReportController.php`)
- PostController (`app/Application/Controllers/Api/V1/PostController.php`)
- SwaggerController (`app/Application/Controllers/Web/SwaggerController.php`)
- BaseController (`app/Application/Controllers/BaseController.php`)
- IpController (`app/Application/Controllers/Api/V1/IpController.php`)
- ActivityLogController (`app/Application/Controllers/Api/V1/ActivityLogController.php`)
- AuthController (`app/Application/Controllers/Api/V1/AuthController.php`)
- AttachmentController (`app/Application/Controllers/Api/V1/AttachmentController.php`)
- AuthorizationMiddleware (`app/Application/Middleware/AuthorizationMiddleware.php`)
- Application (`app/Application.php`)
- implements (`scripts/remaining-error-fixer.php`)
- OpenApiConfig (`app/Shared/OpenApi/OpenApiConfig.php`)
- PostSchema (`app/Shared/Schemas/PostSchema.php`)
- AuthSchema (`app/Shared/Schemas/AuthSchema.php`)
- PostRequestSchema (`app/Shared/Schemas/PostRequestSchema.php`)
- JwtConfig (`app/Shared/Config/JwtConfig.php`)
- NotFoundException (`app/Shared/Exceptions/NotFoundException.php`)
- CsrfTokenException (`app/Shared/Exceptions/CsrfTokenException.php`)
- RequestValidationException (`app/Shared/Exceptions/Validation/RequestValidationException.php`)
- ValidationException (`app/Shared/Exceptions/ValidationException.php`)
- StateTransitionException (`app/Shared/Exceptions/StateTransitionException.php`)
- ValidatorFactory (`app/Shared/Validation/Factory/ValidatorFactory.php`)
- ApiResponse (`app/Shared/Http/ApiResponse.php`)
- Attachment (`app/Domains/Attachment/Models/Attachment.php`)
- AttachmentRepository (`app/Domains/Attachment/Repositories/AttachmentRepository.php`)
- CreateAttachmentDTO (`app/Domains/Attachment/DTOs/CreateAttachmentDTO.php`)
- FileRules (`app/Domains/Attachment/Enums/FileRules.php`)
- CsrfProtectionService (`app/Domains/Security/Services/Core/CsrfProtectionService.php`)
- XssProtectionService (`app/Domains/Security/Services/Core/XssProtectionService.php`)
- XssProtectionExtensionService (`app/Domains/Security/Services/Content/XssProtectionExtensionService.php`)
- IpService (`app/Domains/Security/Services/IpService.php`)
- ActivityLog (`app/Domains/Security/Entities/ActivityLog.php`)
- SecurityServiceProvider (`app/Domains/Security/Providers/SecurityServiceProvider.php`)
- ActivityLogSearchDTO (`app/Domains/Security/DTOs/ActivityLogSearchDTO.php`)
- CreateIpRuleDTO (`app/Domains/Security/DTOs/CreateIpRuleDTO.php`)
- RichTextProcessorService (`app/Domains/Post/Services/RichTextProcessorService.php`)
- PostCacheKeyService (`app/Domains/Post/Services/PostCacheKeyService.php`)
- ContentModerationService (`app/Domains/Post/Services/ContentModerationService.php`)
- PostStatusException (`app/Domains/Post/Exceptions/PostStatusException.php`)
- PostValidationException (`app/Domains/Post/Exceptions/PostValidationException.php`)
- PostNotFoundException (`app/Domains/Post/Exceptions/PostNotFoundException.php`)
- CreatePostDTO (`app/Domains/Post/DTOs/CreatePostDTO.php`)
- UpdatePostDTO (`app/Domains/Post/DTOs/UpdatePostDTO.php`)
- PostValidator (`app/Domains/Post/Validation/PostValidator.php`)
- Role (`app/Domains/Auth/Models/Role.php`)
- Permission (`app/Domains/Auth/Models/Permission.php`)
- RefreshTokenService (`app/Domains/Auth/Services/RefreshTokenService.php`)
- AuthService (`app/Domains/Auth/Services/AuthService.php`)
- PwnedPasswordService (`app/Domains/Auth/Services/Advanced/PwnedPasswordService.php`)
- PasswordManagementService (`app/Domains/Auth/Services/PasswordManagementService.php`)
- TokenBlacklistService (`app/Domains/Auth/Services/TokenBlacklistService.php`)
- UserRepository (`app/Domains/Auth/Repositories/UserRepository.php`)
- AuthServiceProvider (`app/Domains/Auth/Providers/AuthServiceProvider.php`)
- SimpleAuthServiceProvider (`app/Domains/Auth/Providers/SimpleAuthServiceProvider.php`)
- JwtException (`app/Domains/Auth/Exceptions/JwtException.php`)
- AuthenticationException (`app/Domains/Auth/Exceptions/AuthenticationException.php`)
- TokenValidationException (`app/Domains/Auth/Exceptions/TokenValidationException.php`)
- ForbiddenException (`app/Domains/Auth/Exceptions/ForbiddenException.php`)
- UnauthorizedException (`app/Domains/Auth/Exceptions/UnauthorizedException.php`)
- JwtConfigurationException (`app/Domains/Auth/Exceptions/JwtConfigurationException.php`)
- RefreshTokenException (`app/Domains/Auth/Exceptions/RefreshTokenException.php`)
- TokenGenerationException (`app/Domains/Auth/Exceptions/TokenGenerationException.php`)
- InvalidTokenException (`app/Domains/Auth/Exceptions/InvalidTokenException.php`)
- TokenExpiredException (`app/Domains/Auth/Exceptions/TokenExpiredException.php`)
- TokenParsingException (`app/Domains/Auth/Exceptions/TokenParsingException.php`)
- RegisterUserDTO (`app/Domains/Auth/DTOs/RegisterUserDTO.php`)
- LoginResponseDTO (`app/Domains/Auth/DTOs/LoginResponseDTO.php`)
- RefreshRequestDTO (`app/Domains/Auth/DTOs/RefreshRequestDTO.php`)
- LoginRequestDTO (`app/Domains/Auth/DTOs/LoginRequestDTO.php`)
- LogoutRequestDTO (`app/Domains/Auth/DTOs/LogoutRequestDTO.php`)
- RefreshResponseDTO (`app/Domains/Auth/DTOs/RefreshResponseDTO.php`)
- RateLimitService (`app/Infrastructure/Services/RateLimitService.php`)
- OutputSanitizer (`app/Infrastructure/Services/OutputSanitizer.php`)
- CacheServiceProvider (`app/Infrastructure/Cache/Providers/CacheServiceProvider.php`)
- CacheKeys (`app/Infrastructure/Cache/CacheKeys.php`)
- CacheManager (`app/Infrastructure/Cache/CacheManager.php`)
- OpenApiSpec (`app/Infrastructure/OpenApi/OpenApiSpec.php`)
- DatabaseConnection (`app/Infrastructure/Database/DatabaseConnection.php`)
- ContainerFactory (`app/Infrastructure/Config/ContainerFactory.php`)
- RouteCacheFactory (`app/Infrastructure/Routing/Cache/RouteCacheFactory.php`)
- RouteDispatcher (`app/Infrastructure/Routing/RouteDispatcher.php`)
- RoutingServiceProvider (`app/Infrastructure/Routing/Providers/RoutingServiceProvider.php`)
- RouteConfigurationException (`app/Infrastructure/Routing/Exceptions/RouteConfigurationException.php`)
- ControllerResolver (`app/Infrastructure/Routing/ControllerResolver.php`)
- RouteLoader (`app/Infrastructure/Routing/RouteLoader.php`)
- RouteMatchResult (`app/Infrastructure/Routing/Contracts/RouteMatchResult.php`)
- MiddlewareResolver (`app/Infrastructure/Routing/Middleware/MiddlewareResolver.php`)
- RouteParametersMiddleware (`app/Infrastructure/Routing/Middleware/RouteParametersMiddleware.php`)
- RouteInfoMiddleware (`app/Infrastructure/Routing/Middleware/RouteInfoMiddleware.php`)
- RouteValidator (`app/Infrastructure/Routing/RouteValidator.php`)
- ServerRequestFactory (`app/Infrastructure/Http/ServerRequestFactory.php`)
- AdvancedPhpstanFixer (`scripts/advanced-phpstan-fixer.php`)
- PhpUnitDeprecationFixer (`scripts/fix-phpunit-deprecations.php`)
- AnonymousClassFixer (`scripts/anonymous-class-fixer.php`)
- PhpstanFixCommander (`scripts/phpstan-fix-commander.php`)
- ProjectArchitectureScanner (`scripts/scan-project-architecture.php`)
- ConsolidatedTestManager (`scripts/consolidated/ConsolidatedTestManager.php`)
- ConsolidatedDeployer (`scripts/consolidated/ConsolidatedDeployer.php`)
- ConsolidatedMaintainer (`scripts/consolidated/ConsolidatedMaintainer.php`)
- ScriptManager (`scripts/consolidated/ScriptManager.php`)
- ScriptResult (`scripts/consolidated/ScriptManager.php`)
- ProjectStatus (`scripts/consolidated/ScriptManager.php`)
- TestStatus (`scripts/consolidated/ScriptManager.php`)
- ArchitectureMetrics (`scripts/consolidated/ScriptManager.php`)
- ModernPhpAdoption (`scripts/consolidated/ScriptManager.php`)
- ErrorFixingConfig (`scripts/consolidated/ScriptManager.php`)
- TestingConfig (`scripts/consolidated/ScriptManager.php`)
- AnalysisConfig (`scripts/consolidated/ScriptManager.php`)
- DeploymentConfig (`scripts/consolidated/ScriptManager.php`)
- MaintenanceConfig (`scripts/consolidated/ScriptManager.php`)
- ConsolidatedAnalyzer (`scripts/consolidated/ConsolidatedAnalyzer.php`)
- ConsolidatedErrorFixer (`scripts/consolidated/ConsolidatedErrorFixer.php`)
- PhpGenericSyntaxFixer (`scripts/fix-php-generic-syntax.php`)
- PHPStanTypeFixer (`scripts/phpstan-type-fixer.php`)
- ConsoleOutput (`scripts/lib/ConsoleOutput.php`)
- EnhancedPhpstanFixer (`scripts/enhanced-phpstan-fixer.php`)
- BulkPHPStanFixer (`scripts/bulk-phpstan-fixer.php`)
- RemainingErrorFixer (`scripts/remaining-error-fixer.php`)
- CommonErrorFixer (`scripts/common-error-fixer.php`)
- SpecificPhpstanFixer (`scripts/specific-phpstan-fixer.php`)
- InitialSchema (`database/migrations/20250823051608_initial_schema.php`)
- AddTokenHashToRefreshTokensTable (`database/migrations/20250826023305_add_token_hash_to_refresh_tokens_table.php`)
- CreateRefreshTokensTable (`database/migrations/20250825165731_create_refresh_tokens_table.php`)
- CreateUserActivityLogsTable (`database/migrations/20250829000000_create_user_activity_logs_table.php`)
- CreateTokenBlacklistTable (`database/migrations/20250825165750_create_token_blacklist_table.php`)
- UsersSeeder (`database/seeds/UsersSeeder.php`)
- UserActivityLogsSeeder (`database/seeds/UserActivityLogsSeeder.php`)

### `MiddlewareInterface`
- RateLimitMiddleware (`app/Application/Middleware/RateLimitMiddleware.php`)
- JwtAuthenticationMiddleware (`app/Application/Middleware/JwtAuthenticationMiddleware.php`)
- JwtAuthorizationMiddleware (`app/Application/Middleware/JwtAuthorizationMiddleware.php`)
- AbstractMiddleware (`app/Infrastructure/Routing/Middleware/AbstractMiddleware.php`)

### `JsonSerializable`
- AuthorizationResult (`app/Application/Middleware/AuthorizationResult.php`)
- BaseDTO (`app/Shared/DTOs/BaseDTO.php`)
- ValidationResult (`app/Shared/Validation/ValidationResult.php`)
- IpList (`app/Domains/Security/Models/IpList.php`)
- CreateActivityLogDTO (`app/Domains/Security/DTOs/CreateActivityLogDTO.php`)
- SuspiciousActivityAnalysisDTO (`app/Domains/Security/DTOs/SuspiciousActivityAnalysisDTO.php`)
- Post (`app/Domains/Post/Models/Post.php`)
- JwtPayload (`app/Domains/Auth/ValueObjects/JwtPayload.php`)
- TokenPair (`app/Domains/Auth/ValueObjects/TokenPair.php`)
- DeviceInfo (`app/Domains/Auth/ValueObjects/DeviceInfo.php`)
- TokenBlacklistEntry (`app/Domains/Auth/ValueObjects/TokenBlacklistEntry.php`)
- RefreshToken (`app/Domains/Auth/Entities/RefreshToken.php`)

### `ValidatorInterface`
- Validator (`app/Shared/Validation/Validator.php`)

### `AttachmentServiceInterface`
- AttachmentService (`app/Domains/Attachment/Services/AttachmentService.php`)

### `FileSecurityServiceInterface`
- FileSecurityService (`app/Domains/Attachment/Services/FileSecurityService.php`)

### `SecurityTestInterface`
- SecurityTestService (`app/Domains/Security/Services/Advanced/SecurityTestService.php`)

### `SecurityHeaderServiceInterface`
- SecurityHeaderService (`app/Domains/Security/Services/Headers/SecurityHeaderService.php`)

### `ErrorHandlerServiceInterface`
- ErrorHandlerService (`app/Domains/Security/Services/Error/ErrorHandlerService.php`)

### `ActivityLoggingServiceInterface`
- ActivityLoggingService (`app/Domains/Security/Services/ActivityLoggingService.php`)
- CachedActivityLoggingService (`app/Domains/Security/Services/Activity/CachedActivityLoggingService.php`)

### `LoggingSecurityServiceInterface`
- LoggingSecurityService (`app/Domains/Security/Services/Logging/LoggingSecurityService.php`)

### `SecretsManagerInterface`
- SecretsManager (`app/Domains/Security/Services/Secrets/SecretsManager.php`)

### `SuspiciousActivityDetectorInterface`
- SuspiciousActivityDetector (`app/Domains/Security/Services/SuspiciousActivityDetector.php`)

### `IpRepositoryInterface`
- IpRepository (`app/Domains/Security/Repositories/IpRepository.php`)

### `ActivityLogRepositoryInterface`
- ActivityLogRepository (`app/Domains/Security/Repositories/ActivityLogRepository.php`)

### `PostServiceInterface`
- PostService (`app/Domains/Post/Services/PostService.php`)

### `PostRepositoryInterface`
- PostRepository (`app/Domains/Post/Repositories/PostRepository.php`)

### `SessionSecurityServiceInterface`
- SessionSecurityService (`app/Domains/Auth/Services/SessionSecurityService.php`)

### `AuthenticationServiceInterface`
- AuthenticationService (`app/Domains/Auth/Services/AuthenticationService.php`)

### `PasswordSecurityServiceInterface`
- PasswordSecurityService (`app/Domains/Auth/Services/PasswordSecurityService.php`)

### `AuthorizationServiceInterface`
- AuthorizationService (`app/Domains/Auth/Services/AuthorizationService.php`)

### `JwtTokenServiceInterface`
- JwtTokenService (`app/Domains/Auth/Services/JwtTokenService.php`)

### `CacheServiceInterface`
- CacheService (`app/Infrastructure/Services/CacheService.php`)

### `OutputSanitizerInterface`
- OutputSanitizerService (`app/Infrastructure/Services/OutputSanitizer.php`)

### `CacheInterface`
- RedisCache (`app/Infrastructure/Cache/RedisCache.php`)

### `RouteCacheInterface`
- FileRouteCache (`app/Infrastructure/Routing/Cache/FileRouteCache.php`)
- MemoryRouteCache (`app/Infrastructure/Routing/Cache/MemoryRouteCache.php`)
- RedisRouteCache (`app/Infrastructure/Routing/Cache/RedisRouteCache.php`)

### `RouterInterface`
- Router (`app/Infrastructure/Routing/Core/Router.php`)

### `RouteInterface`
- Route (`app/Infrastructure/Routing/Core/Route.php`)

### `RouteCollectionInterface`
- RouteCollection (`app/Infrastructure/Routing/Core/RouteCollection.php`)

### `MiddlewareDispatcherInterface`
- MiddlewareDispatcher (`app/Infrastructure/Routing/Middleware/MiddlewareDispatcher.php`)

### `MiddlewareManagerInterface`
- MiddlewareManager (`app/Infrastructure/Routing/Middleware/MiddlewareManager.php`)

### `RequestHandlerInterface`
- ClosureRequestHandler (`app/Infrastructure/Routing/ClosureRequestHandler.php`)

### `RefreshTokenRepositoryInterface`
- RefreshTokenRepository (`app/Infrastructure/Auth/Repositories/RefreshTokenRepository.php`)

### `TokenBlacklistRepositoryInterface`
- TokenBlacklistRepository (`app/Infrastructure/Auth/Repositories/TokenBlacklistRepository.php`)

### `JwtProviderInterface`
- FirebaseJwtProvider (`app/Infrastructure/Auth/Jwt/FirebaseJwtProvider.php`)

### `ServerRequestInterface`
- ServerRequest (`app/Infrastructure/Http/ServerRequest.php`)

### `StreamInterface`
- Stream (`app/Infrastructure/Http/Stream.php`)

### `UriInterface`
- Uri (`app/Infrastructure/Http/Uri.php`)

### `ResponseInterface`
- Response (`app/Infrastructure/Http/Response.php`)

### `ScriptAnalyzerInterface`
- DefaultScriptAnalyzer (`scripts/consolidated/DefaultScriptAnalyzer.php`)

### `ScriptExecutorInterface`
- DefaultScriptExecutor (`scripts/consolidated/DefaultScriptExecutor.php`)

### `ScriptConfigurationInterface`
- DefaultScriptConfiguration (`scripts/consolidated/DefaultScriptConfiguration.php`)


## 🧪 測試覆蓋分析

- **有測試的類別**: 0 個
- **缺少測試的類別**: 186 個

### 缺少測試的重要類別


## 💉 依賴注入分析

### 依賴較多的類別 (≥3個依賴)
- **PostController** (4 個依賴)
  - `PostServiceInterface` $postService
  - `ValidatorInterface` $validator
  - `OutputSanitizerInterface` $sanitizer
  - `ActivityLoggingServiceInterface` $activityLogger

- **IpController** (3 個依賴)
  - `IpService` $service
  - `ValidatorInterface` $validator
  - `OutputSanitizerInterface` $sanitizer

- **AuthController** (5 個依賴)
  - `AuthService` $authService
  - `AuthenticationServiceInterface` $authenticationService
  - `JwtTokenServiceInterface` $jwtTokenService
  - `ValidatorInterface` $validator
  - `ActivityLoggingServiceInterface` $activityLoggingService

- **AttachmentService** (4 個依賴)
  - `AttachmentRepository` $attachmentRepo
  - `PostRepository` $postRepo
  - `AuthorizationService` $authService
  - `ActivityLoggingServiceInterface` $activityLogger

- **SecurityTestService** (7 個依賴)
  - `SessionSecurityServiceInterface` $sessionService
  - `AuthorizationServiceInterface` $authService
  - `FileSecurityServiceInterface` $fileService
  - `SecurityHeaderServiceInterface` $headerService
  - `ErrorHandlerServiceInterface` $errorService
  - `PasswordSecurityServiceInterface` $passwordService
  - `SecretsManagerInterface` $secretsManager

- **XssProtectionExtensionService** (3 個依賴)
  - `XssProtectionService` $baseXssProtection
  - `RichTextProcessorService` $richTextProcessor
  - `ContentModerationService` $contentModerator

- **IpService** (3 個依賴)
  - `IpRepositoryInterface` $repository
  - `ActivityLoggingServiceInterface` $activityLogger
  - `ValidatorInterface` $validator

- **SuspiciousActivityDetector** (3 個依賴)
  - `ActivityLogRepositoryInterface` $repository
  - `ActivityLoggingServiceInterface` $activityLogger
  - `LoggerInterface` $logger

- **ActivityLog** (3 個依賴)
  - `ActivityType` $actionType
  - `ActivityStatus` $status
  - `DateTimeImmutable` $occurredAt

- **CreateActivityLogDTO** (3 個依賴)
  - `ActivityType` $actionType
  - `ActivityStatus` $status
  - `DateTimeImmutable` $occurredAt

- **ActivityLogSearchDTO** (6 個依賴)
  - `ActivityType` $actionType
  - `ActivityCategory` $actionCategory
  - `ActivityStatus` $status
  - `ActivitySeverity` $minSeverity
  - `DateTime` $startDate
  - `DateTime` $endDate

- **PostRepository** (3 個依賴)
  - `PDO` $db
  - `CacheServiceInterface` $cache
  - `LoggingSecurityServiceInterface` $logger

- **JwtPayload** (3 個依賴)
  - `DateTimeImmutable` $iat
  - `DateTimeImmutable` $exp
  - `DateTimeImmutable` $nbf

- **RefreshTokenService** (4 個依賴)
  - `JwtTokenServiceInterface` $jwtTokenService
  - `RefreshTokenRepositoryInterface` $refreshTokenRepository
  - `TokenBlacklistRepositoryInterface` $blacklistRepository
  - `LoggerInterface` $logger

- **AuthService** (3 個依賴)
  - `UserRepository` $userRepository
  - `PasswordSecurityServiceInterface` $passwordService
  - `JwtTokenServiceInterface` $jwtTokenService

- **AuthenticationService** (3 個依賴)
  - `JwtTokenServiceInterface` $jwtTokenService
  - `RefreshTokenRepositoryInterface` $refreshTokenRepository
  - `UserRepositoryInterface` $userRepository

- **JwtTokenService** (4 個依賴)
  - `JwtProviderInterface` $jwtProvider
  - `RefreshTokenRepositoryInterface` $refreshTokenRepository
  - `TokenBlacklistRepositoryInterface` $blacklistRepository
  - `JwtConfig` $config

- **RefreshToken** (6 個依賴)
  - `DateTime` $expiresAt
  - `DeviceInfo` $deviceInfo
  - `DateTime` $revokedAt
  - `DateTime` $lastUsedAt
  - `DateTime` $createdAt
  - `DateTime` $updatedAt

- **RouteDispatcher** (4 個依賴)
  - `RouterInterface` $router
  - `ControllerResolver` $controllerResolver
  - `MiddlewareDispatcher` $middlewareDispatcher
  - `ContainerInterface` $container

- **ScriptManager** (3 個依賴)
  - `ScriptConfigurationInterface` $config
  - `ScriptExecutorInterface` $executor
  - `ScriptAnalyzerInterface` $analyzer

- **ScriptResult** (3 個依賴)
  - `ScriptConfigurationInterface` $config
  - `ScriptExecutorInterface` $executor
  - `ScriptAnalyzerInterface` $analyzer

- **ProjectStatus** (3 個依賴)
  - `ScriptConfigurationInterface` $config
  - `ScriptExecutorInterface` $executor
  - `ScriptAnalyzerInterface` $analyzer

- **TestStatus** (3 個依賴)
  - `ScriptConfigurationInterface` $config
  - `ScriptExecutorInterface` $executor
  - `ScriptAnalyzerInterface` $analyzer

- **ArchitectureMetrics** (3 個依賴)
  - `ScriptConfigurationInterface` $config
  - `ScriptExecutorInterface` $executor
  - `ScriptAnalyzerInterface` $analyzer

- **ModernPhpAdoption** (3 個依賴)
  - `ScriptConfigurationInterface` $config
  - `ScriptExecutorInterface` $executor
  - `ScriptAnalyzerInterface` $analyzer

- **ErrorFixingConfig** (3 個依賴)
  - `ScriptConfigurationInterface` $config
  - `ScriptExecutorInterface` $executor
  - `ScriptAnalyzerInterface` $analyzer

- **TestingConfig** (3 個依賴)
  - `ScriptConfigurationInterface` $config
  - `ScriptExecutorInterface` $executor
  - `ScriptAnalyzerInterface` $analyzer

- **AnalysisConfig** (3 個依賴)
  - `ScriptConfigurationInterface` $config
  - `ScriptExecutorInterface` $executor
  - `ScriptAnalyzerInterface` $analyzer

- **DeploymentConfig** (3 個依賴)
  - `ScriptConfigurationInterface` $config
  - `ScriptExecutorInterface` $executor
  - `ScriptAnalyzerInterface` $analyzer

- **MaintenanceConfig** (3 個依賴)
  - `ScriptConfigurationInterface` $config
  - `ScriptExecutorInterface` $executor
  - `ScriptAnalyzerInterface` $analyzer


## ❓ 可能的問題引用

- ❓ 找不到類別/介面: App\Domains\Security\Enums\ActivityType (在 app/Application/Controllers/Api/V1/PostController.php 中使用)
- ❓ 找不到類別/介面: App\Domains\Security\Enums\ActivityCategory (在 app/Application/Controllers/Api/V1/ActivityLogController.php 中使用)
- ❓ 找不到類別/介面: App\Domains\Security\Enums\ActivityType (在 app/Application/Controllers/Api/V1/ActivityLogController.php 中使用)
- ❓ 找不到類別/介面: ValueError (在 app/Application/Controllers/Api/V1/ActivityLogController.php 中使用)
- ❓ 找不到類別/介面: App\Domains\Security\Enums\ActivityType (在 app/Application/Controllers/Api/V1/AuthController.php 中使用)
- ❓ 找不到類別/介面: App\Domains\Security\Enums\ActivityType (在 app/Application/Middleware/RateLimitMiddleware.php 中使用)
- ❓ 找不到類別/介面: Throwable (在 app/Application/Middleware/RateLimitMiddleware.php 中使用)
- ❓ 找不到類別/介面: DI\ContainerBuilder (在 app/Application.php 中使用)
- ❓ 找不到類別/介面: Throwable (在 app/Shared/Exceptions/ValidationException.php 中使用)
- ❓ 找不到類別/介面: the first error from ValidationResult
        if (empty($message)) {
            $message = $validationResult->getFirstError() ?? '驗證失敗' (在 app/Shared/Exceptions/ValidationException.php 中使用)
- ... 還有 142 個
