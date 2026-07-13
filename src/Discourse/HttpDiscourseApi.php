<?php

declare(strict_types=1);

namespace App\Discourse;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpDiscourseApi implements DiscourseApi
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'DISCOURSE_URL')]
        private string $discourseUrl,
        #[Autowire(env: 'DISCOURSE_API_KEY')]
        #[\SensitiveParameter]
        private string $apiKey,
        #[Autowire(env: 'DISCOURSE_API_USERNAME')]
        private string $apiUsername,
    ) {
    }

    public function fetchPostRaw(int $postId): string
    {
        $data = $this->get(\sprintf('/posts/%d.json', $postId));

        return \is_string($data['raw'] ?? null) ? $data['raw'] : '';
    }

    public function resolvePostId(int $topicId, int $postNumber): ?int
    {
        $data = $this->get(\sprintf('/posts/by_number/%d/%d.json', $topicId, $postNumber));

        return \is_int($data['id'] ?? null) ? $data['id'] : null;
    }

    public function fetchListMessageId(int $postId): ?string
    {
        $data = $this->get(\sprintf('/posts/%d/raw-email.json', $postId));
        $rawEmail = $data['raw_email'] ?? null;

        if (!\is_string($rawEmail) || '' === $rawEmail) {
            return null;
        }

        if (1 === preg_match('/^Message-ID:\s*<([^>]+)>/im', $rawEmail, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($this->discourseUrl, '/').$path, [
                'headers' => [
                    'Api-Key' => $this->apiKey,
                    'Api-Username' => $this->apiUsername,
                    'Accept' => 'application/json',
                ],
            ]);

            /** @var array<string, mixed> $data */
            $data = $response->toArray();

            return $data;
        } catch (HttpExceptionInterface $e) {
            if (404 === $e->getResponse()->getStatusCode()) {
                return [];
            }

            throw $e;
        }
    }
}
