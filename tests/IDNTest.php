<?php

namespace LayerShifter\TLDExtract\Tests;

use LayerShifter\TLDExtract\IDN;
use PHPUnit\Framework\TestCase;

/**
 * Tests for IDN class.
 */
class IDNTest extends TestCase
{

    /**
     * @var IDN Object for tests
     */
    private IDN $idn;

    /**
     * Method that setups test's environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->idn = new IDN();
    }

    /**
     * Tests toASCII() method.
     */
    public function testToASCII(): void
    {
        $this->assertEquals('xn--tst-qla.de', $this->idn->toASCII('täst.de'));
    }

    /**
     * Tests toUTF8() method.
     */
    public function testToUTF8(): void
    {
        $this->assertEquals('täst.de', $this->idn->toUTF8('xn--tst-qla.de'));
    }
}
