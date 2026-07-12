<?php

declare(strict_types=1);

namespace App\Enum;

enum InboundEmailKind: string
{
    case SubscribeConfirmation = 'subscribe_confirmation';
    case UnsubscribeConfirmation = 'unsubscribe_confirmation';
    case PostConfirmation = 'post_confirmation';
    case Bounce = 'bounce';
    case Other = 'other';
}
