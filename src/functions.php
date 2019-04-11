<?php

/**
 * Active record implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\ActiveRecord;

use Ramsey\Uuid\Uuid;

/**
 * @noinspection PhpDocMissingThrowsInspection
 *
 * @internal
 *
 * Generate a version 4 (random) UUID.
 *
 * @return string
 */
function uuid(): string
{
    /** @noinspection PhpUnhandledExceptionInspection */
    return Uuid::uuid4()->toString();
}
