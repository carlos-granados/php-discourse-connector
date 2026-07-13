<?php

declare(strict_types=1);

namespace App\Behat;

use App\Discourse\FakeDiscourseApi;
use App\Entity\MessageMap;
use App\Enum\MessageSource;
use App\Mail\ListAddressResolver;
use App\Repository\MessageMapRepository;
use App\Repository\OutboundMessageRepository;
use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mime\Email;

final readonly class PostingContext implements Context
{
    public function __construct(
        private KernelBrowser $client,
        private FakeDiscourseApi $discourse,
        #[Autowire(service: 'mailer.message_logger_listener')]
        private MessageLoggerListener $messageLogger,
        private ListAddressResolver $listAddresses,
        private OutboundMessageRepository $outboundMessages,
        private MessageMapRepository $messageMaps,
        private EntityManagerInterface $entityManager,
        #[Autowire(env: 'DISCOURSE_WEBHOOK_SECRET')]
        private string $webhookSecret,
    ) {
    }

    #[BeforeScenario]
    public function resetFakeDiscourse(): void
    {
        $this->discourse->reset();
    }

    #[Given('Discourse post number :postNumber in topic :topicId resolves to post :postId')]
    public function discoursePostNumberResolvesTo(int $postNumber, int $topicId, int $postId): void
    {
        $this->discourse->setPostIdForNumber($topicId, $postNumber, $postId);
    }

    #[Given('Discourse post :postId was received from the mailing list with Message-ID :messageId')]
    public function discoursePostWasReceivedFromTheList(int $postId, string $messageId): void
    {
        $this->discourse->setListMessageId($postId, $messageId);
    }

    #[Given('a list message :messageId is mapped to Discourse post :postId in topic :topicId')]
    public function aListMessageIsMapped(string $messageId, int $postId, int $topicId): void
    {
        $this->entityManager->persist(
            new MessageMap()
                ->setDiscoursePostId($postId)
                ->setDiscourseTopicId($topicId)
                ->setEmailMessageId($messageId)
                ->setSource(MessageSource::List),
        );
        $this->entityManager->flush();
    }

    #[When('Discourse sends a signed post_created reply webhook')]
    public function discourseSendsAReplyWebhook(): void
    {
        $this->sendPostCreated([
            'id' => 8,
            'topic_id' => 3,
            'user_id' => 42,
            'category_id' => 5,
            'via_email' => false,
            'topic_title' => 'Hello list',
            'post_number' => 2,
            'reply_to_post_number' => 1,
        ]);
    }

    #[When('Discourse sends a signed post_created webhook that arrived via email')]
    public function discourseSendsAViaEmailWebhook(): void
    {
        $this->sendPostCreated([
            'id' => 7, 'topic_id' => 3, 'user_id' => 42, 'category_id' => 5,
            'via_email' => true, 'topic_title' => 'Hello list', 'post_number' => 1, 'reply_to_post_number' => null,
        ]);
    }

    #[When('Discourse sends a signed post_created webhook in an unmirrored category')]
    public function discourseSendsAnUnmirroredCategoryWebhook(): void
    {
        $this->sendPostCreated([
            'id' => 7, 'topic_id' => 3, 'user_id' => 42, 'category_id' => 999,
            'via_email' => false, 'topic_title' => 'Hello list', 'post_number' => 1, 'reply_to_post_number' => null,
        ]);
    }

    #[Then('an email should be relayed to the mailing list')]
    public function anEmailShouldBeRelayedToTheList(): void
    {
        $this->relayedEmail();
    }

    #[Then('the relayed email should be from :fromName')]
    public function theRelayedEmailShouldBeFrom(string $fromName): void
    {
        $actual = $this->relayedEmail()->getFrom()[0]->getName();

        if ($fromName !== $actual) {
            throw new \RuntimeException(\sprintf('Expected From name "%s", got "%s".', $fromName, $actual));
        }
    }

    #[Then('the relayed email subject should be :subject')]
    public function theRelayedEmailSubjectShouldBe(string $subject): void
    {
        $actual = $this->relayedEmail()->getSubject();

        if ($subject !== $actual) {
            throw new \RuntimeException(\sprintf('Expected subject "%s", got "%s".', $subject, (string) $actual));
        }
    }

    #[Then('the relayed email Message-ID should be :messageId')]
    public function theRelayedEmailMessageIdShouldBe(string $messageId): void
    {
        $this->assertHeaderId('Message-ID', $messageId);
    }

    #[Then('the relayed email should be in reply to :messageId')]
    public function theRelayedEmailShouldBeInReplyTo(string $messageId): void
    {
        $this->assertHeaderId('In-Reply-To', $messageId);
    }

    #[Then('the relayed email should not be a reply')]
    public function theRelayedEmailShouldNotBeAReply(): void
    {
        if ($this->relayedEmail()->getHeaders()->has('In-Reply-To')) {
            throw new \RuntimeException('Expected no In-Reply-To header, but one is present.');
        }
    }

    #[Then('an outbound message should be recorded for Discourse post :postId')]
    public function anOutboundMessageShouldBeRecorded(int $postId): void
    {
        if (!$this->outboundMessages->findByDiscoursePostId($postId) instanceof \App\Entity\OutboundMessage) {
            throw new \RuntimeException(\sprintf('No outbound message recorded for post %d.', $postId));
        }
    }

    #[Then('a list message mapping should be recorded for Discourse post :postId')]
    public function aListMessageMappingShouldBeRecorded(int $postId): void
    {
        $map = $this->messageMaps->findByDiscoursePostId($postId);

        if (!$map instanceof MessageMap || MessageSource::List !== $map->getSource()) {
            throw new \RuntimeException(\sprintf('No list message mapping recorded for post %d.', $postId));
        }
    }

    /**
     * @param array<string, mixed> $post
     */
    private function sendPostCreated(array $post): void
    {
        $body = json_encode(['post' => $post], \JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/webhook/discourse', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_DISCOURSE_EVENT' => 'post_created',
            'HTTP_X_DISCOURSE_EVENT_SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, $this->webhookSecret),
        ], content: $body);
    }

    private function assertHeaderId(string $header, string $expected): void
    {
        $value = $this->relayedEmail()->getHeaders()->get($header)?->getBodyAsString();
        $actual = null === $value ? null : trim($value, '<>');

        if ($expected !== $actual) {
            throw new \RuntimeException(\sprintf('Expected %s "%s", got "%s".', $header, $expected, (string) $actual));
        }
    }

    private function relayedEmail(): Email
    {
        $listAddress = $this->listAddresses->listAddress();

        foreach (array_reverse($this->messageLogger->getEvents()->getMessages()) as $message) {
            if (!$message instanceof Email) {
                continue;
            }

            foreach ($message->getTo() as $to) {
                if ($to->getAddress() === $listAddress) {
                    return $message;
                }
            }
        }

        throw new \RuntimeException(\sprintf('No email was relayed to the list address "%s".', $listAddress));
    }
}
