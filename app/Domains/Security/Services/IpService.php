<?php

declare(strict_types=1);

namespace App\Domains\Security\Services;

use App\Domains\Security\Contracts\ActivityLoggingServiceInterface;
use App\Domains\Security\Contracts\IpRepositoryInterface;
use App\Domains\Security\DTOs\CreateActivityLogDTO;
use App\Domains\Security\DTOs\CreateIpRuleDTO;
use App\Domains\Security\Enums\ActivityStatus;
use App\Domains\Security\Enums\ActivityType;
use App\Domains\Security\Models\IpList;
use App\Shared\Contracts\ValidatorInterface;
use InvalidArgumentException;
use RuntimeException;

class IpService
{
    public function __construct(
        private IpRepositoryInterface $repository,
        private ActivityLoggingServiceInterface $activityLogger,
        private ValidatorInterface $validator,
    ) {}

    public function createIpRule(CreateIpRuleDTO $dto): IpList
    {
        // DTO 已經在建構時驗證過資料，這裡直接轉換為陣列
        $data = $dto->toArray();
        $action = $data['action'];

        // 轉換 action 為內部使用的 type 欄位
        $data['type'] = $data['action'] === 'allow' ? 1 : 0; // 1=白名單，0=黑名單
        unset($data['action']); // 移除 action 欄位

        $result = $this->repository->create($data);
        if (!$result instanceof IpList) {
            throw new RuntimeException('建立 IP 規則失敗');
        }

        // 記錄 IP 規則建立活動
        $this->logIpRuleActivity($result, $action, 'created');

        return $result;
    }

    public function isIpAllowed(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('無效的 IP 位址格式');
        }

        // 檢查是否在白名單中
        if ($this->repository->isWhitelisted($ip)) {
            $this->logIpAccessActivity($ip, 'allowed', 'whitelisted');

            return true;
        }

        // 檢查是否在黑名單中
        if ($this->repository->isBlacklisted($ip)) {
            $this->logIpAccessActivity($ip, 'blocked', 'blacklisted');

            return false;
        }

        // 預設允許存取
        $this->logIpAccessActivity($ip, 'allowed', 'default');

        return true;
    }

    public function blockIp(string $ip, string $reason = 'manual', ?int $userId = null): IpList
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('無效的 IP 位址格式');
        }

        $dto = new CreateIpRuleDTO($this->validator, [
            'ip_address' => $ip,
            'action' => 'block',
            'reason' => "IP 封鎖 - 原因: {$reason}",
            'created_by' => $userId ?? 1, // 預設系統使用者
        ]);

        $result = $this->createIpRule($dto);

        // 記錄 IP 封鎖事件
        $this->activityLogger->log(
            new CreateActivityLogDTO(
                actionType: ActivityType::IP_BLOCKED,
                userId: $userId,
                status: ActivityStatus::SUCCESS,
                targetType: 'ip_address',
                targetId: $ip,
                description: "IP 位址 {$ip} 已被封鎖",
                metadata: [
                    'ip_address' => $ip,
                    'reason' => $reason,
                    'block_type' => 'manual',
                    'rule_id' => $result->getId(),
                ],
            ),
        );

        return $result;
    }

    public function unblockIp(string $ip, ?int $userId = null): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('無效的 IP 位址格式');
        }

        // 找到並刪除黑名單規則
        $rule = $this->repository->findByIpAddress($ip);
        if ($rule && $rule->isBlacklist()) {
            $this->repository->delete($rule->getId());

            // 記錄 IP 解封事件
            $this->activityLogger->log(
                new CreateActivityLogDTO(
                    actionType: ActivityType::IP_UNBLOCKED,
                    userId: $userId,
                    status: ActivityStatus::SUCCESS,
                    targetType: 'ip_address',
                    targetId: $ip,
                    description: "IP 位址 {$ip} 已解除封鎖",
                    metadata: [
                        'ip_address' => $ip,
                        'unblock_type' => 'manual',
                    ],
                ),
            );
        }
    }

    public function getRulesByType(int $type): array
    {
        if (!in_array($type, [0, 1], true)) {
            throw new InvalidArgumentException('無效的名單類型，必須是 0（黑名單）或 1（白名單）');
        }

        return $this->repository->getByType($type);
    }

    /**
     * 記錄 IP 規則相關活動.
     */
    private function logIpRuleActivity(IpList $rule, string $action, string $operation): void
    {
        $activityType = match ($action) {
            'allow' => ActivityType::IP_UNBLOCKED, // 使用現有的類型
            'block' => ActivityType::IP_BLOCKED,
            default => ActivityType::SUSPICIOUS_ACTIVITY_DETECTED,
        };

        $this->activityLogger->log(
            new CreateActivityLogDTO(
                actionType: $activityType,
                status: ActivityStatus::SUCCESS,
                targetType: 'ip_rule',
                targetId: (string) $rule->getId(),
                description: "IP 規則 {$operation}: {$rule->getIpAddress()} ({$action})",
                metadata: [
                    'ip_address' => $rule->getIpAddress(),
                    'rule_type' => $action,
                    'rule_id' => $rule->getId(),
                    'operation' => $operation,
                    'description' => $rule->getDescription() ?? '',
                ],
            ),
        );
    }

    /**
     * 記錄 IP 存取活動.
     */
    private function logIpAccessActivity(string $ip, string $result, string $reason): void
    {
        // 簡化，使用現有的類型
        $activityType = match ($result) {
            'blocked' => ActivityType::IP_BLOCKED,
            default => ActivityType::SUSPICIOUS_ACTIVITY_DETECTED, // 通用安全事件
        };

        $status = match ($result) {
            'allowed' => ActivityStatus::SUCCESS,
            'blocked' => ActivityStatus::BLOCKED,
            default => ActivityStatus::ERROR,
        };

        // 只記錄被阻擋的 IP 存取，避免日誌過多
        if ($result === 'blocked') {
            $this->activityLogger->log(
                new CreateActivityLogDTO(
                    actionType: $activityType,
                    status: $status,
                    targetType: 'ip_address',
                    targetId: $ip,
                    description: "IP 存取被阻擋: {$ip}",
                    metadata: [
                        'ip_address' => $ip,
                        'access_result' => $result,
                        'reason' => $reason,
                        'timestamp' => date('c'),
                    ],
                ),
            );
        }
    }
}
