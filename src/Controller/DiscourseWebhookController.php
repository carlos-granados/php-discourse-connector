<?php

declare(strict_types=1);

namespace App\Controller;

use App\Message\ProvisionSurrogate;
use App\Message\RelayPost;
use App\Message\RetireSurrogate;
use App\Message\SyncSurrogate;
use App\Security\WebhookSignatureVerifier;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class DiscourseWebhookController
{
    public function __construct(
        private WebhookSignatureVerifier $signatureVerifier,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    #[Route('/webhook/discourse', name: 'discourse_webhook', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->headers->get('X-Discourse-Event-Signature');

        if (null === $signature || !$this->signatureVerifier->isValid($rawBody, $signature)) {
            $this->logger->warning('Discourse webhook rejected: invalid or missing signature');

            return new JsonResponse(['error' => 'invalid signature'], 401);
        }

        try {
            $payload = json_decode($rawBody, true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'invalid JSON'], 400);
        }

        if (!\is_array($payload)) {
            return new JsonResponse(['error' => 'invalid JSON'], 400);
        }

        $event = $request->headers->get('X-Discourse-Event', '');

        /** @var array<string, mixed> $payload */
        $message = $this->mapEventToMessage($event, $payload);

        if (null === $message) {
            return new JsonResponse(['status' => 'ignored']);
        }

        $this->bus->dispatch($message);

        return new JsonResponse(['status' => 'queued']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function mapEventToMessage(string $event, array $payload): ?object
    {
        return match ($event) {
            'user_created' => $this->userMessage($payload, ProvisionSurrogate::class),
            'user_updated' => $this->userMessage($payload, SyncSurrogate::class),
            'user_destroyed' => $this->retireMessage($payload),
            'post_created' => $this->relayMessage($payload),
            default => null,
        };
    }

    /**
     * @template T of ProvisionSurrogate|SyncSurrogate
     *
     * @param array<string, mixed> $payload
     * @param class-string<T>      $messageClass
     *
     * @return T|null
     */
    private function userMessage(array $payload, string $messageClass): ?object
    {
        $user = $this->arrayField($payload, 'user');
        $id = null !== $user ? $this->intField($user, 'id') : null;
        $username = null !== $user ? $this->stringField($user, 'username') : null;

        if (in_array(null, [$user, $id, $username], true)) {
            $this->logger->warning('Discourse user webhook with unusable payload dropped');

            return null;
        }

        return new $messageClass(
            $id,
            $username,
            $this->stringField($user, 'name'),
            $this->stringField($user, 'email'),
            $this->boolField($user, 'staged'),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function retireMessage(array $payload): ?RetireSurrogate
    {
        $user = $this->arrayField($payload, 'user');
        $id = null !== $user ? $this->intField($user, 'id') : null;

        if (null === $id) {
            $this->logger->warning('Discourse user_destroyed webhook with unusable payload dropped');

            return null;
        }

        return new RetireSurrogate($id);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function relayMessage(array $payload): ?RelayPost
    {
        $post = $this->arrayField($payload, 'post');
        $postId = null !== $post ? $this->intField($post, 'id') : null;
        $topicId = null !== $post ? $this->intField($post, 'topic_id') : null;
        $userId = null !== $post ? $this->intField($post, 'user_id') : null;

        if (in_array(null, [$post, $postId, $topicId, $userId], true)) {
            $this->logger->warning('Discourse post_created webhook with unusable payload dropped');

            return null;
        }

        return new RelayPost(
            $postId,
            $topicId,
            $userId,
            $this->intField($post, 'category_id'),
            $this->boolField($post, 'via_email'),
        );
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>|null
     */
    private function arrayField(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return \is_array($value) ? $value : null;
    }

    /**
     * @param array<mixed> $data
     */
    private function intField(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return \is_int($value) ? $value : null;
    }

    /**
     * @param array<mixed> $data
     */
    private function stringField(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * True only when the key holds the literal boolean true; missing or any
     * other value is treated as false.
     *
     * @param array<mixed> $data
     */
    private function boolField(array $data, string $key): bool
    {
        return true === ($data[$key] ?? null);
    }
}
