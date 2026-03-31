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

enum SchemaDetail: string
{
    case SUMMARY = 'summary';
    case COLUMNS = 'columns';
    case FULL = 'full';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $detail): string => $detail->value,
            self::cases()
        );
    }

    public static function tryFromInput(string $input): ?self
    {
        return self::tryFrom(strtolower(trim($input)));
    }
}
