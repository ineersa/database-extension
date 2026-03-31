<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\ReadOnly;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;

class ReadOnlyConnection extends AbstractConnectionMiddleware
{
}
