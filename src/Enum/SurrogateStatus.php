<?php

declare(strict_types=1);

namespace App\Enum;

enum SurrogateStatus: string
{
    case Pending = 'pending';
    case SubscribeSent = 'subscribe_sent';
    case Confirming = 'confirming';
    case Subscribed = 'subscribed';
    case Unsubscribing = 'unsubscribing';
    case Unsubscribed = 'unsubscribed';
    case Disabled = 'disabled';
}
