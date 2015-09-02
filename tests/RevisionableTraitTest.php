<?php

namespace Spira\Revisionable\Tests;

use Venturecraft\Revisionable\RevisionableTrait;

class RevisionableTraitTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function shouldGetMockForTrait()
    {
        $mock = $this->getMockForTrait(RevisionableTrait::class);

        $this->assertContains('RevisionableTrait', get_class($mock));
    }
}
