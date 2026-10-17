<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

interface PayPalOrdersClientInterface
{
    public function createOrder(array $body, string $requestId): array;
    public function getOrder(string $orderId): array;
    public function captureOrder(string $orderId, string $requestId): array;
    public function verifyWebhook(string $originalBody, array $headers, string $webhookId): bool;
}
