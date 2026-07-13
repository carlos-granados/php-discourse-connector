<?php

declare(strict_types=1);

namespace App\Behat;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Behat\Step\When;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

final readonly class WebhookContext implements Context
{
    public function __construct(
        private KernelBrowser $client,
        #[Autowire(service: 'messenger.transport.async')]
        private TransportInterface $asyncTransport,
        #[Autowire(env: 'DISCOURSE_WEBHOOK_SECRET')]
        private string $webhookSecret,
        private MessageBusInterface $bus,
    ) {
    }

    #[When('the queued messages are processed')]
    public function theQueuedMessagesAreProcessed(): void
    {
        if (!$this->asyncTransport instanceof InMemoryTransport) {
            throw new \LogicException('The async transport is expected to be in-memory in the test environment.');
        }

        foreach ($this->asyncTransport->getSent() as $envelope) {
            // ReceivedStamp makes the bus handle the message synchronously
            // instead of re-queueing it
            $this->bus->dispatch($envelope->getMessage(), [new ReceivedStamp('async')]);
        }

        $this->asyncTransport->reset();
    }

    #[When('Discourse sends a signed :event webhook')]
    public function discourseSendsASignedWebhook(string $event): void
    {
        $body = $this->payloadFor($event);

        $this->postWebhook($event, $body, $this->sign($body));
    }

    #[When('Discourse sends a :event webhook with an invalid signature')]
    public function discourseSendsAWebhookWithAnInvalidSignature(string $event): void
    {
        $this->postWebhook($event, $this->payloadFor($event), 'sha256='.str_repeat('0', 64));
    }

    #[When('Discourse sends a signed :event webhook with a broken body')]
    public function discourseSendsASignedWebhookWithABrokenBody(string $event): void
    {
        $body = 'this is not json';

        $this->postWebhook($event, $body, $this->sign($body));
    }

    #[Then('the response should be successful')]
    public function theResponseShouldBeSuccessful(): void
    {
        $this->assertResponseStatus(200);
    }

    #[Then('the response should be unauthorized')]
    public function theResponseShouldBeUnauthorized(): void
    {
        $this->assertResponseStatus(401);
    }

    #[Then('the response should be bad request')]
    public function theResponseShouldBeBadRequest(): void
    {
        $this->assertResponseStatus(400);
    }

    #[Then('a :messageClass message should be queued')]
    public function aMessageShouldBeQueued(string $messageClass): void
    {
        $queued = $this->queuedMessages();

        if (1 !== \count($queued)) {
            throw new \RuntimeException(\sprintf('Expected exactly 1 queued message, found %d.', \count($queued)));
        }

        $fqcn = 'App\\Message\\'.$messageClass;

        if (!$queued[0] instanceof $fqcn) {
            throw new \RuntimeException(\sprintf('Expected a queued %s, got %s.', $fqcn, $queued[0]::class));
        }
    }

    #[Then('no message should be queued')]
    public function noMessageShouldBeQueued(): void
    {
        $queued = $this->queuedMessages();

        if ([] !== $queued) {
            throw new \RuntimeException(\sprintf('Expected no queued messages, found %d (first: %s).', \count($queued), $queued[0]::class));
        }
    }

    /**
     * A realistic payload for the given Discourse event type.
     */
    private function payloadFor(string $event): string
    {
        $payload = match ($event) {
            'user_created', 'user_updated', 'user_destroyed' => [
                'user' => [
                    'id' => 42,
                    'username' => 'jane_doe',
                    // user_updated carries a changed name so scenarios can observe the sync
                    'name' => 'user_updated' === $event ? 'Jane D.' : 'Jane Doe',
                    'email' => 'jane.doe@example.com',
                ],
            ],
            'post_created' => [
                'post' => [
                    'id' => 7,
                    'topic_id' => 3,
                    'user_id' => 42,
                    'category_id' => 5,
                    'via_email' => false,
                    'raw' => 'Hello list',
                ],
            ],
            default => ['topic' => ['id' => 3]],
        };

        return json_encode($payload, \JSON_THROW_ON_ERROR);
    }

    private function assertResponseStatus(int $status): void
    {
        $actual = $this->client->getResponse()->getStatusCode();

        if ($status !== $actual) {
            throw new \RuntimeException(\sprintf('Expected HTTP %d, got %d: %s', $status, $actual, (string) $this->client->getResponse()->getContent()));
        }
    }

    private function sign(string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $body, $this->webhookSecret);
    }

    private function postWebhook(string $event, string $body, string $signature): void
    {
        $this->client->request('POST', '/webhook/discourse', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DISCOURSE_EVENT' => $event,
            'HTTP_X_DISCOURSE_EVENT_SIGNATURE' => $signature,
        ], content: $body);
    }

    /**
     * @return list<object>
     */
    private function queuedMessages(): array
    {
        if (!$this->asyncTransport instanceof InMemoryTransport) {
            throw new \LogicException('The async transport is expected to be in-memory in the test environment.');
        }

        return array_values(array_map(
            static fn (\Symfony\Component\Messenger\Envelope $envelope): object => $envelope->getMessage(),
            $this->asyncTransport->getSent(),
        ));
    }
}
