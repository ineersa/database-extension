<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Tests\Capability;

use MatesOfMate\DatabaseExtension\Capability\DatabaseTool;
use PHPUnit\Framework\TestCase;

class DatabaseToolTest extends TestCase
{
    public function testReturnsValidJson(): void
    {
        $tool = new DatabaseTool();

        $result = $tool->execute();

        $this->assertJson($result);
    }

    public function testContainsExpectedKeys(): void
    {
        $tool = new DatabaseTool();

        $result = json_decode($tool->execute(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('hint', $result);
    }
}
