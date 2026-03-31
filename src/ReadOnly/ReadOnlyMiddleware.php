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

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

class ReadOnlyMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new ReadOnlyDriver($driver);
    }
}
