<?php

declare(strict_types=1);

namespace App\Command;

use App\Mail\ListAddressResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Simulates the ezmlm-idx list server against Mailpit for local end-to-end
 * testing: answers subscribe/unsubscribe requests with confirmation
 * requests, and confirmation replies with WELCOME/GOODBYE messages.
 *
 * Registered only in the dev and test environments (never production) via the
 * #[When] attributes below, so the service simply does not exist in prod.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
#[AsCommand(
    name: 'app:fake-ezmlm',
    description: 'Simulate the ezmlm mailing list server against Mailpit (dev/test only)',
)]
final class FakeEzmlmCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly MailerInterface $mailer,
        private readonly ListAddressResolver $addresses,
        #[Autowire(env: 'MAILPIT_API_URL')]
        private readonly string $mailpitApiUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('watch', null, InputOption::VALUE_REQUIRED, 'Keep watching with the given interval in seconds', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $watch = $input->getOption('watch');
        $interval = \is_string($watch) ? max(1, (int) $watch) : null;

        do {
            $handled = $this->pass($io);
            $io->writeln(\sprintf('%d message(s) handled.', $handled));

            if (null !== $interval) {
                sleep($interval);
            }
        } while (null !== $interval);

        return Command::SUCCESS;
    }

    private function pass(SymfonyStyle $io): int
    {
        $handled = 0;

        // Subscription requests -> subscribe confirmation request
        foreach ($this->unread(\sprintf('to:"%s"', $this->addresses->subscribeAddress())) as $message) {
            $this->reply(
                from: $this->cookieAddress('sc'),
                to: $message['from'],
                subject: 'confirm subscribe to '.$this->addresses->listAddress(),
                body: "To confirm your subscription, reply to this message.\n",
            );
            $io->writeln('Subscribe request from '.$message['from'].' -> confirmation request sent');
            ++$handled;
        }

        // Unsubscription requests -> unsubscribe confirmation request
        foreach ($this->unread(\sprintf('to:"%s"', $this->addresses->unsubscribeAddress())) as $message) {
            $this->reply(
                from: $this->cookieAddress('uc'),
                to: $message['from'],
                subject: 'confirm unsubscribe from '.$this->addresses->listAddress(),
                body: "To confirm your unsubscription, reply to this message.\n",
            );
            $io->writeln('Unsubscribe request from '.$message['from'].' -> confirmation request sent');
            ++$handled;
        }

        // Replies to subscribe cookies -> WELCOME
        foreach ($this->unread(\sprintf('to:"%s+sc."', $this->addresses->listLocalPart())) as $message) {
            $this->reply(
                from: $this->addresses->listLocalPart().'-help@'.$this->addresses->listDomain(),
                to: $message['from'],
                subject: 'WELCOME to '.$this->addresses->listAddress(),
                body: "Welcome to the list!\n",
            );
            $io->writeln('Subscribe confirmed by '.$message['from'].' -> WELCOME sent');
            ++$handled;
        }

        // Replies to unsubscribe cookies -> GOODBYE
        foreach ($this->unread(\sprintf('to:"%s+uc."', $this->addresses->listLocalPart())) as $message) {
            $this->reply(
                from: $this->addresses->listLocalPart().'-help@'.$this->addresses->listDomain(),
                to: $message['from'],
                subject: 'GOODBYE from '.$this->addresses->listAddress(),
                body: "You have been removed from the list.\n",
            );
            $io->writeln('Unsubscribe confirmed by '.$message['from'].' -> GOODBYE sent');
            ++$handled;
        }

        return $handled;
    }

    /**
     * @return list<array{id: string, from: string}>
     */
    private function unread(string $query): array
    {
        $response = $this->httpClient->request('GET', $this->mailpitApiUrl.'/api/v1/search', [
            'query' => ['query' => 'is:unread '.$query, 'limit' => '100'],
        ]);

        /** @var array{messages?: list<array{ID?: string, From?: array{Address?: string}}>} $data */
        $data = $response->toArray();
        $messages = [];
        $ids = [];

        foreach ($data['messages'] ?? [] as $summary) {
            $id = $summary['ID'] ?? null;
            $from = $summary['From']['Address'] ?? null;

            if (null === $id || null === $from) {
                continue;
            }

            $ids[] = $id;
            $messages[] = ['id' => $id, 'from' => $from];
        }

        if ([] !== $ids) {
            $this->httpClient->request('PUT', $this->mailpitApiUrl.'/api/v1/messages', [
                'json' => ['IDs' => $ids, 'Read' => true],
            ]);
        }

        return $messages;
    }

    private function reply(string $from, string $to, string $subject, string $body): void
    {
        $this->mailer->send(
            new Email()
                ->from($from)
                ->to($to)
                ->subject($subject)
                ->text($body),
        );
    }

    private function cookieAddress(string $command): string
    {
        return \sprintf(
            '%s+%s.%d.%s@%s',
            $this->addresses->listLocalPart(),
            $command,
            time(),
            bin2hex(random_bytes(4)),
            $this->addresses->listDomain(),
        );
    }
}
