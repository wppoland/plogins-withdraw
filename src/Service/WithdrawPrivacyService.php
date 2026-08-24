<?php

declare(strict_types=1);

namespace Withdraw\Service;

use Withdraw\Contract\HasHooks;

defined('ABSPATH') || exit;

/**
 * Personal data exporter and eraser for withdrawal requests.
 */
final class WithdrawPrivacyService implements HasHooks
{
    private const PAGE_SIZE = 100;

    public function __construct(
        private readonly RequestRepository $repository,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('wp_privacy_personal_data_exporters', [$this, 'registerExporters']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'registerErasers']);
    }

    /**
     * @param array<string, array<string, mixed>> $exporters
     * @return array<string, array<string, mixed>>
     */
    public function registerExporters(array $exporters): array
    {
        $exporters['withdraw-declarations'] = [
            'exporter_friendly_name' => __('Withdrawal Declarations', 'plogins-withdraw'),
            'callback'               => [$this, 'exportWithdrawals'],
        ];

        return $exporters;
    }

    /**
     * @param array<string, array<string, mixed>> $erasers
     * @return array<string, array<string, mixed>>
     */
    public function registerErasers(array $erasers): array
    {
        $erasers['withdraw-declarations'] = [
            'eraser_friendly_name' => __('Withdrawal Declarations', 'plogins-withdraw'),
            'callback'             => [$this, 'eraseWithdrawals'],
        ];

        return $erasers;
    }

    /**
     * @return array{data: list<array<string, mixed>>, done: bool}
     */
    public function exportWithdrawals(string $email, int $page = 1): array
    {
        $page   = max(1, $page);
        $offset = ($page - 1) * self::PAGE_SIZE;

        $items = [];
        $rows  = $this->repository->findByEmail($email, self::PAGE_SIZE, $offset);

        foreach ($rows as $r) {
            $items[] = [
                'group_id'    => 'withdraw-declarations',
                'group_label' => __('Withdrawal Declarations', 'plogins-withdraw'),
                'item_id'     => 'withdrawal-' . $r->id,
                'data'        => [
                    ['name' => __('Order ID', 'plogins-withdraw'), 'value' => (string) $r->order_id],
                    ['name' => __('Status', 'plogins-withdraw'), 'value' => (string) $r->status],
                    ['name' => __('Reason', 'plogins-withdraw'), 'value' => (string) ($r->reason ?? '')],
                    ['name' => __('Date', 'plogins-withdraw'), 'value' => (string) $r->created_at],
                ],
            ];
        }

        return [
            'data' => $items,
            'done' => count($rows) < self::PAGE_SIZE,
        ];
    }

    /**
     * @return array{items_removed: int, items_retained: int, messages: list<string>, done: bool}
     */
    public function eraseWithdrawals(string $email, int $page = 1): array
    {
        $anonymized = $this->repository->anonymizeByEmail($email);

        return [
            'items_removed'  => $anonymized,
            'items_retained' => $anonymized,
            'messages'       => $anonymized > 0
                ? [__('Customer personal email and reason cleared from withdrawal declarations; order references retained for statutory bookkeeping.', 'plogins-withdraw')]
                : [],
            'done'           => true,
        ];
    }
}
