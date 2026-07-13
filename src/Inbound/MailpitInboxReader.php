<?php

declare(strict_types=1);

namespace App\Inbound;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads the "catch-all mailbox" from Mailpit's HTTP API: every message
 * addressed to the surrogate domain. Local/dev only — production uses an
 * IMAP-based reader against the real catch-all mailbox.
 */
final readonly class MailpitInboxReader implements InboxReader
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'MAILPIT_API_URL')]
        private string $apiUrl,
        #[Autowire(env: 'SURROGATE_DOMAIN')]
        private string $surrogateDomain,
    ) {
    }

    public function fetchUnread(): iterable
    {
        $response = $this->httpClient->request('GET', $this->apiUrl.'/api/v1/search', [
            'query' => [
                'query' => \sprintf('is:unread to:"@%s"', $this->surrogateDomain),
                'limit' => '100',
            ],
        ]);

        /** @var array{messages?: list<array{ID?: string, From?: array{Address?: string}, To?: list<array{Address?: string}>, ReplyTo?: list<array{Address?: string}>, Subject?: string}>} $data */
        $data = $response->toArray();
        $readIds = [];

        foreach ($data['messages'] ?? [] as $summary) {
            $id = $summary['ID'] ?? null;

            if (null === $id) {
                continue;
            }

            $readIds[] = $id;

            $raw = $this->httpClient->request('GET', \sprintf('%s/api/v1/message/%s/raw', $this->apiUrl, $id))->getContent();

            yield new InboundMessage(
                from: $summary['From']['Address'] ?? '',
                to: $summary['To'][0]['Address'] ?? '',
                replyTo: $summary['ReplyTo'][0]['Address'] ?? null,
                subject: $summary['Subject'] ?? '',
                raw: $raw,
            );
        }

        if ([] !== $readIds) {
            $this->httpClient->request('PUT', $this->apiUrl.'/api/v1/messages', [
                'json' => ['IDs' => $readIds, 'Read' => true],
            ]);
        }
    }
}
