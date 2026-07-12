<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where the email Message-ID of a mapped Discourse post originates from.
 */
enum MessageSource: string
{
    /** The post arrived from the mailing list via the Discourse mirror. */
    case List = 'list';

    /** The post was written in Discourse and emailed to the list by this connector. */
    case Connector = 'connector';
}
