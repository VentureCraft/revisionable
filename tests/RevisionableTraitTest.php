<?php

namespace Spira\Revisionable\Tests;

class RevisionableTraitTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function shouldGetMockForTrait()
    {
        $mock = $this->getMockForTrait('Venturecraft\Revisionable\RevisionableTrait');

        $this->assertContains('RevisionableTrait', get_class($mock));
    }
}
