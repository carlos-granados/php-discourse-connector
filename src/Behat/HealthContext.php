<?php

declare(strict_types=1);

namespace App\Behat;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Behat\Step\When;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final readonly class HealthContext implements Context
{
    public function __construct(private KernelBrowser $client)
    {
    }

    #[When('I request the health endpoint')]
    public function iRequestTheHealthEndpoint(): void
    {
        $this->client->request('GET', '/healthz');
    }

    #[Then('the service reports it is healthy')]
    public function theServiceReportsItIsHealthy(): void
    {
        $response = $this->client->getResponse();

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(\sprintf('Expected HTTP 200, got %d: %s', $response->getStatusCode(), (string) $response->getContent()));
        }

        /** @var array{status?: string} $data */
        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        if ('ok' !== ($data['status'] ?? null)) {
            throw new \RuntimeException(\sprintf('Service is not healthy: %s', (string) $response->getContent()));
        }
    }
}
