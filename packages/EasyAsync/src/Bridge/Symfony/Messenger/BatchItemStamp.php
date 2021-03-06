<?php

declare(strict_types=1);

namespace EonX\EasyAsync\Bridge\Symfony\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class BatchItemStamp implements StampInterface
{
    /**
     * @var int
     */
    private $attempts;

    /**
     * @var string
     */
    private $batchItemId;

    public function __construct(string $batchItemId, int $attempts)
    {
        $this->batchItemId = $batchItemId;
        $this->attempts = $attempts;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getBatchItemId(): string
    {
        return $this->batchItemId;
    }
}
