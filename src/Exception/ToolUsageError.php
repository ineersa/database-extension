<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Exception;

class ToolUsageError extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $hint = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getHint(): ?string
    {
        return $this->hint;
    }
}
