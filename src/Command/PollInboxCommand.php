<?php

declare(strict_types=1);

namespace App\Command;

use App\Inbound\InboundEmailProcessor;
use App\Inbound\InboxReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:inbox:poll',
    description: 'Process unread emails from the catch-all mailbox of the surrogate domain',
)]
final class PollInboxCommand extends Command
{
    public function __construct(
        private readonly InboxReader $inboxReader,
        private readonly InboundEmailProcessor $processor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('watch', null, InputOption::VALUE_REQUIRED, 'Keep polling with the given interval in seconds', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $watch = $input->getOption('watch');
        $interval = \is_string($watch) ? max(1, (int) $watch) : null;

        do {
            $processed = 0;

            foreach ($this->inboxReader->fetchUnread() as $message) {
                $this->processor->process($message);
                ++$processed;
                $io->writeln(\sprintf('Processed: %s -> %s (%s)', $message->from, $message->to, $message->subject), OutputInterface::VERBOSITY_VERBOSE);
            }

            $io->writeln(\sprintf('%d message(s) processed.', $processed));

            if (null !== $interval) {
                sleep($interval);
            }
        } while (null !== $interval);

        return Command::SUCCESS;
    }
}
