<?php

declare(strict_types=1);

namespace EonX\EasyWebhook\Bridge\Laravel\Commands;

use Carbon\Carbon;
use EonX\EasyPagination\Data\StartSizeData;
use EonX\EasyWebhook\Exceptions\InvalidDateTimeException;
use EonX\EasyWebhook\Interfaces\Stores\SendAfterStoreInterface;
use EonX\EasyWebhook\Interfaces\Stores\StoreInterface;
use EonX\EasyWebhook\Interfaces\WebhookClientInterface;
use Illuminate\Console\Command;

final class SendDueWebhooksCommand extends Command
{
    public function __construct()
    {
        $this->signature = \sprintf(
            'easy-webhooks:send-due-webhooks
            {--bulk= : How many webhooks to process at once}
            {--sendAfter= : DateTime to start fetching due webhooks from, in "%s" format}
            {--timezone= : The timezone of sendAfter DateTimes}
            ',
            StoreInterface::DATETIME_FORMAT
        );

        $this->description = 'Send "sendAfter" webhooks which are due';

        parent::__construct();
    }

    public function handle(StoreInterface $store, WebhookClientInterface $client): int
    {
        if ($store instanceof SendAfterStoreInterface === false) {
            $this->error(\sprintf(
                'Store "%s" does not implement "%s", cannot proceed.',
                \get_class($store),
                SendAfterStoreInterface::class
            ));

            return 1;
        }

        $sendAfter = null;
        $page = 1;
        $perPage = $this->getIntOption('bulk') ?? 15;
        $sendAfterString = $this->getStringOption('sendAfter');
        $timezone = $this->getStringOption('timezone');

        if ($sendAfterString !== null) {
            $sendAfter = Carbon::createFromFormat(StoreInterface::DATETIME_FORMAT, $sendAfterString);

            if ($sendAfter instanceof Carbon === false) {
                throw new InvalidDateTimeException(\sprintf('Invalid DateTime provided, "%s"', $sendAfterString));
            }
        }

        do {
            $dueWebhooks = $store->findDueWebhooks(new StartSizeData($page, $perPage), $sendAfter, $timezone);

            foreach ($dueWebhooks->getItems() as $webhook) {
                $client->sendWebhook($webhook);
            }

            $page++;
        } while ($dueWebhooks->hasNextPage());

        $this->output->success(\sprintf('Sent %d due webhooks', $dueWebhooks->getTotalItems()));

        return 0;
    }

    private function getIntOption(string $name): ?int
    {
        $option = $this->option($name);

        return \is_array($option) || \is_bool($option) ? null : (int)$option;
    }

    private function getStringOption(string $name): ?string
    {
        $option = $this->option($name);

        return \is_array($option) || \is_bool($option) ? null : (string)$option;
    }
}