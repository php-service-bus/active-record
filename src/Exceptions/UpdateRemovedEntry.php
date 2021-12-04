<?php

/**
 * Active record implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Storage\ActiveRecord\Exceptions;

final class UpdateRemovedEntry extends \RuntimeException
{
}
