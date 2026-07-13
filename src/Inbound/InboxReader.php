<?php

declare(strict_types=1);

namespace App\Inbound;

/**
 * Source of unread emails from the catch-all mailbox of the surrogate domain.
 *
 * Local/dev reads from Mailpit; production will read the real catch-all
 * mailbox over IMAP (implementation planned for the deployment phase).
 */
interface InboxReader
{
    /**
     * Yields unread messages and marks them read at the source.
     *
     * @return iterable<InboundMessage>
     */
    public function fetchUnread(): iterable;
}
