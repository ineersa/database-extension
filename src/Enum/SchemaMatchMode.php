<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Enum;

enum SchemaMatchMode: string
{
    case CONTAINS = 'contains';
    case PREFIX = 'prefix';
    case EXACT = 'exact';
    case GLOB = 'glob';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $mode): string => $mode->value,
            self::cases()
        );
    }

    public static function tryFromInput(string $input): ?self
    {
        return self::tryFrom(strtolower(trim($input)));
    }
}
