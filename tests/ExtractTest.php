<?php
/**
 * TLDExtract: Library for extraction of domain parts e.g. TLD. Domain parser that uses Public Suffix List.
 *
 * @link      https://github.com/layershifter/TLDExtract
 *
 * @copyright Copyright (c) 2016, Alexander Fedyashov
 * @license   https://raw.githubusercontent.com/layershifter/TLDExtract/master/LICENSE Apache 2.0 License
 */

namespace LayerShifter\TLDExtract\Tests;

use LayerShifter\TLDExtract\Exceptions\RuntimeException;
use LayerShifter\TLDExtract\Extract;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Extract class.
 */
class ExtractTest extends TestCase
{
    private Extract $extract;

    /**
     * Tests constructor() exception for result class that not implements result interface.
     */
    public function testConstructorNotImplements(): void
    {
        $this->expectException(RuntimeException::class);
        new Extract(null, Extract::class);
    }

    /**
     * Tests setExtractionMode() exception for invalid mode type.
     */
    public function testSetExtractionModeInvalidArgumentType(): void
    {
        $this->expectException(RuntimeException::class);

        $extract = new Extract();
        $extract->setExtractionMode('a');
    }

    /**
     * Tests setExtractionMode() exception for invalid mode value.
     */
    public function testSetExtractionModeInvalidArgumentValue(): void
    {
        $this->expectException(RuntimeException::class);

        $extract = new Extract();
        $extract->setExtractionMode(-10);
    }

    /**
     * Real world test case. Uses official test data.
     *
     * @see       http://mxr.mozilla.org/mozilla-central/source/netwerk/test/unit/data/test_psl.txt?raw=1
     * @copyright Public Domain. https://creativecommons.org/publicdomain/zero/1.0/
     */
    public function testParse(): void
    {
        // null input.

        $this->checkPublicDomain(null, null);

        // Mixed case.

        $this->checkPublicDomain('COM', null);
        $this->checkPublicDomain('example.COM', 'example.com');
        $this->checkPublicDomain('WwW.example.COM', 'example.com');

        // Long domains.

        $this->checkPublicDomain(
            sprintf(
                '%s.%s.%s.%s.com',
                str_repeat('a', 63),
                str_repeat('a', 63),
                str_repeat('a', 63),
                str_repeat('a', 57)
            ),  // 253 characters
            str_repeat('a', 57) . '.com'
        );
        $this->checkPublicDomain(
            sprintf(
                'http://%s.%s.%s.%s.com',
                str_repeat('a', 63),
                str_repeat('a', 63),
                str_repeat('a', 63),
                str_repeat('a', 57)
            ),  // 253 characters without schema
            str_repeat('a', 57) . '.com'
        );

        // Long and too short parts of domains domains.

        $this->checkPublicDomain('test..com', null);
        $this->checkPublicDomain(
            str_repeat('a', 64) . '.a.com',
            null
        );

        // Leading dot.

        $this->checkPublicDomain('.com', null);
        $this->checkPublicDomain('..com', null);
        $this->checkPublicDomain('.example', null);
        $this->checkPublicDomain('.example.com', null);
        $this->checkPublicDomain('.example.example', null);

        // Unlisted TLD.

        $this->checkPublicDomain('example', null);
        $this->checkPublicDomain('example.example', 'example.example');
        $this->checkPublicDomain('b.example.example', 'example.example');
        $this->checkPublicDomain('a.b.example.example', 'example.example');

        // TLD with only 1 rule.

        $this->checkPublicDomain('biz', null);
        $this->checkPublicDomain('domain.biz', 'domain.biz');
        $this->checkPublicDomain('b.domain.biz', 'domain.biz');
        $this->checkPublicDomain('a.b.domain.biz', 'domain.biz');

        // TLD with some 2-level rules.

        $this->checkPublicDomain('com', null);
        $this->checkPublicDomain('example.com', 'example.com');
        $this->checkPublicDomain('b.example.com', 'example.com');
        $this->checkPublicDomain('a.b.example.com', 'example.com');
        $this->checkPublicDomain('uk.com', null);
        $this->checkPublicDomain('example.uk.com', 'example.uk.com');
        $this->checkPublicDomain('b.example.uk.com', 'example.uk.com');
        $this->checkPublicDomain('a.b.example.uk.com', 'example.uk.com');
        $this->checkPublicDomain('test.ac', 'test.ac');

        // TLD with only 1 (wildcard) rule.

        $this->checkPublicDomain('mm', null);
        $this->checkPublicDomain('c.mm', null);
        $this->checkPublicDomain('b.c.mm', 'b.c.mm');
        $this->checkPublicDomain('a.b.c.mm', 'b.c.mm');

        // More complex TLD.

        $this->checkPublicDomain('jp', null);
        $this->checkPublicDomain('test.jp', 'test.jp');
        $this->checkPublicDomain('www.test.jp', 'test.jp');
        $this->checkPublicDomain('ac.jp', null);
        $this->checkPublicDomain('test.ac.jp', 'test.ac.jp');
        $this->checkPublicDomain('www.test.ac.jp', 'test.ac.jp');
        $this->checkPublicDomain('kyoto.jp', null);
        $this->checkPublicDomain('test.kyoto.jp', 'test.kyoto.jp');
        $this->checkPublicDomain('ide.kyoto.jp', null);
        $this->checkPublicDomain('b.ide.kyoto.jp', 'b.ide.kyoto.jp');
        $this->checkPublicDomain('a.b.ide.kyoto.jp', 'b.ide.kyoto.jp');
        $this->checkPublicDomain('c.kobe.jp', null);
        $this->checkPublicDomain('b.c.kobe.jp', 'b.c.kobe.jp');
        $this->checkPublicDomain('a.b.c.kobe.jp', 'b.c.kobe.jp');
        $this->checkPublicDomain('city.kobe.jp', 'city.kobe.jp');
        $this->checkPublicDomain('www.city.kobe.jp', 'city.kobe.jp');

        // TLD with a wildcard rule and exceptions.

        $this->checkPublicDomain('ck', null);
        $this->checkPublicDomain('test.ck', null);
        $this->checkPublicDomain('b.test.ck', 'b.test.ck');
        $this->checkPublicDomain('a.b.test.ck', 'b.test.ck');
        $this->checkPublicDomain('www.ck', 'www.ck');
        $this->checkPublicDomain('www.www.ck', 'www.ck');

        // US K12.

        $this->checkPublicDomain('us', null);
        $this->checkPublicDomain('test.us', 'test.us');
        $this->checkPublicDomain('www.test.us', 'test.us');
        $this->checkPublicDomain('ak.us', null);
        $this->checkPublicDomain('test.ak.us', 'test.ak.us');
        $this->checkPublicDomain('www.test.ak.us', 'test.ak.us');
        $this->checkPublicDomain('k12.ak.us', null);
        $this->checkPublicDomain('test.k12.ak.us', 'test.k12.ak.us');
        $this->checkPublicDomain('www.test.k12.ak.us', 'test.k12.ak.us');
    }

    /**
     * Tests parsing result.
     *
     * @param null|string $hostname       Hostname for parsing
     * @param null|string $expectedResult Expected result of parsing
     */
    private function checkPublicDomain(?string $hostname, ?string $expectedResult): void
    {
        self::assertEquals($expectedResult, $this->extract->parse($hostname)->getRegistrableDomain());
    }

    /**
     * Real world test case for IDN. Uses official test data.
     *
     * @see       http://mxr.mozilla.org/mozilla-central/source/netwerk/test/unit/data/test_psl.txt?raw=1
     * @copyright Public Domain. https://creativecommons.org/publicdomain/zero/1.0/
     */
    public function testParseIdn(): void
    {
        // IDN labels.

        $this->checkPublicDomain('食狮.com.cn', '食狮.com.cn');
        $this->checkPublicDomain('食狮.公司.cn', '食狮.公司.cn');
        $this->checkPublicDomain('www.食狮.公司.cn', '食狮.公司.cn');
        $this->checkPublicDomain('shishi.公司.cn', 'shishi.公司.cn');
        $this->checkPublicDomain('公司.cn', null);
        $this->checkPublicDomain('食狮.中国', '食狮.中国');
        $this->checkPublicDomain('www.食狮.中国', '食狮.中国');
        $this->checkPublicDomain('shishi.中国', 'shishi.中国');
        $this->checkPublicDomain('中国', null);

        // Same as above, but punycoded.

        $this->checkPublicDomain('xn--85x722f.com.cn', 'xn--85x722f.com.cn');
        $this->checkPublicDomain('xn--85x722f.xn--55qx5d.cn', 'xn--85x722f.xn--55qx5d.cn');
        $this->checkPublicDomain('www.xn--85x722f.xn--55qx5d.cn', 'xn--85x722f.xn--55qx5d.cn');
        $this->checkPublicDomain('shishi.xn--55qx5d.cn', 'shishi.xn--55qx5d.cn');
        $this->checkPublicDomain('xn--55qx5d.cn', null);
        $this->checkPublicDomain('xn--85x722f.xn--fiqs8s', 'xn--85x722f.xn--fiqs8s');
        $this->checkPublicDomain('www.xn--85x722f.xn--fiqs8s', 'xn--85x722f.xn--fiqs8s');
        $this->checkPublicDomain('shishi.xn--fiqs8s', 'shishi.xn--fiqs8s');
        $this->checkPublicDomain('xn--fiqs8s', null);
    }

    /**
     * Custom tests for URL's parsing.
     */
    public function testParseUrls(): void
    {
        // Base tests.

        $this->checkPublicSuffix('com', null);
        $this->checkPublicSuffix('http://www.bbc.co.uk/news/business', 'co.uk');
        $this->checkPublicSuffix('http://ru.wikipedia.org/', 'org');
        $this->checkPublicSuffix('http://example.com/?foo=bar', 'com');
        $this->checkPublicSuffix('http://example.com?foo=bar', 'com');
        $this->checkPublicSuffix('bcc.bccbcc', 'bccbcc');
        $this->checkPublicSuffix('svadba.ru', 'ru');
        $this->checkPublicSuffix('us.example.com', 'com');
        $this->checkPublicSuffix('us.example.org', 'org');

        // Test number sign.

        $this->checkPublicSuffix('#test.com', null);
        $this->checkPublicSuffix('test.com#test_test', 'com');
    }

    /**
     * Tests parsing result.
     *
     * @param string $hostname       Hostname for parsing
     * @param null|string $expectedResult Expected result of parsing
     */
    private function checkPublicSuffix(string $hostname, ?string $expectedResult): void
    {
        self::assertEquals($expectedResult, $this->extract->parse($hostname)->getSuffix());
    }

    /**
     * Custom tests for IP's parsing.
     */
    public function testParseIp(): void
    {
        // Test IPv4.

        $this->checkHost('http://192.168.1.1/', '192.168.1.1');
        $this->checkHost('http://127.0.0.1:443', '127.0.0.1');

        // Test IPv6.

        $this->checkHost('http://[2001:0:9d38:6abd:3431:eb:3cbd:22ba]/', '2001:0:9d38:6abd:3431:eb:3cbd:22ba');
        $this->checkHost('https://[2001:0:9d38:6abd:3431:eb:3cbd:22ba]:443/', '2001:0:9d38:6abd:3431:eb:3cbd:22ba');

        // Test local.

        $this->checkHost('http://[fe80::3%25eth0]', 'fe80::3%25eth0');
        $this->checkHost('http://[fe80::1%2511]', 'fe80::1%2511');
    }

    /**
     * Tests parsing result.
     *
     * @param string $hostname       Hostname for parsing
     * @param string $expectedResult Expected result of parsingvoid
     */
    private function checkHost(string $hostname, string $expectedResult): void
    {
        self::assertEquals($expectedResult, $this->extract->parse($hostname)->getHostname());
    }

    /**
     * Test for parse() withExtract::MODE_ALLOW_ICANN | Extract::MODE_ALLOW_PRIVATE options.
     */
    public function testParseOnlyExisting(): void
    {
        $extract = new Extract(null, null, Extract::MODE_ALLOW_ICANN | Extract::MODE_ALLOW_PRIVATE);

        self::assertNull($extract->parse('example.example')->getSuffix());
        self::assertNull($extract->parse('a.example.example')->getSuffix());
        self::assertNull($extract->parse('a.b.example.example')->getSuffix());
        self::assertNull($extract->parse('localhost')->getSuffix());
        self::assertNull($extract->parse('example.localhost')->getSuffix());

        self::assertEquals('com', $extract->parse('example.com')->getSuffix());
        self::assertEquals('com', $extract->parse('a.example.com')->getSuffix());
        self::assertEquals('example.com', $extract->parse('a.example.com')->getRegistrableDomain());
    }

    /**
     * Test for parse() with Extract::MODE_ALLOW_ICANN | Extract::MODE_ALLOW_PRIVATE options.
     */
    public function testParseDisablePrivate(): void
    {
        $extract = new Extract(null, null, Extract::MODE_ALLOW_ICANN | Extract::MODE_ALLOW_NOT_EXISTING_SUFFIXES);

        self::assertEquals('example', $extract->parse('example.example')->getSuffix());
        self::assertEquals('example', $extract->parse('a.example.example')->getSuffix());
        self::assertEquals('example', $extract->parse('a.b.example.example')->getSuffix());
        self::assertEquals('localhost', $extract->parse('example.localhost')->getSuffix());
        self::assertNull($extract->parse('localhost')->getSuffix());

        self::assertEquals('com', $extract->parse('example.com')->getSuffix());
        self::assertEquals('com', $extract->parse('a.example.com')->getSuffix());
        self::assertEquals('example.com', $extract->parse('a.example.com')->getRegistrableDomain());

        self::assertEquals('com', $extract->parse('a.blogspot.com')->getSuffix());
        self::assertEquals('com', $extract->parse('a.b.blogspot.com')->getSuffix());
        self::assertEquals('blogspot.com', $extract->parse('a.blogspot.com')->getRegistrableDomain());
    }

    /**
     * Test for parse() with MODE_ALLOW_ICANN option.
     */
    public function testParseICANNOption(): void
    {
        $extract = new Extract(null, null, Extract::MODE_ALLOW_ICANN);

        self::assertNull($extract->parse('example.example')->getSuffix());
        self::assertNull($extract->parse('a.example.example')->getSuffix());
        self::assertNull($extract->parse('a.b.example.example')->getSuffix());
        self::assertNull($extract->parse('localhost')->getSuffix());
        self::assertNull($extract->parse('example.localhost')->getSuffix());

        self::assertEquals('com', $extract->parse('example.com')->getSuffix());
        self::assertEquals('com', $extract->parse('a.example.com')->getSuffix());
        self::assertEquals('example.com', $extract->parse('a.example.com')->getRegistrableDomain());
        self::assertEquals('com', $extract->parse('a.blogspot.com')->getSuffix());
        self::assertEquals('com', $extract->parse('a.b.blogspot.com')->getSuffix());
        self::assertEquals('blogspot.com', $extract->parse('a.blogspot.com')->getRegistrableDomain());
    }

    /**
     * Test for fixQueryPart() method.
     */
    public function fixQueryPart(): void
    {
        $method = new \ReflectionMethod(Extract::class, 'fixQueryPart');
        $method->setAccessible(true);

        self::assertEquals('http://example.com/?query', $method->invoke($this->extract), 'http://example.com/?query');
        self::assertEquals('http://example.com/?query', $method->invoke($this->extract), 'http://example.com?query');

        self::assertEquals('http://example.com/#hash', $method->invoke($this->extract), 'http://example.com/#hash');
        self::assertEquals('http://example.com/#hash', $method->invoke($this->extract), 'http://example.com#hash');

        self::assertEquals('http://example.com/?query#hash', $method->invoke($this->extract),
            'http://example.com?query#hash');
    }

    /**
     * Test for subdomain with underscore.
     */
    public function testParseUnderscore(): void
    {
        self::assertEquals('com', $this->extract->parse('dkim._domainkey.example.com')->getSuffix());
        self::assertEquals('example', $this->extract->parse('dkim._domainkey.example.com')->getHostname());
        self::assertEquals('dkim._domainkey', $this->extract->parse('dkim._domainkey.example.com')->getSubdomain());
        self::assertEquals(array('dkim', '_domainkey'),
            $this->extract->parse('dkim._domainkey.example.com')->getSubdomains());

        self::assertEquals('com', $this->extract->parse('_spf.example.com')->getSuffix());
        self::assertEquals('example', $this->extract->parse('_spf.example.com')->getHostname());
        self::assertEquals('_spf', $this->extract->parse('_spf.example.com')->getSubdomain());

        self::assertEquals('com', $this->extract->parse('foo_.example.com')->getSuffix());
        self::assertEquals('example', $this->extract->parse('foo_.example.com')->getHostname());
        self::assertEquals('foo_', $this->extract->parse('foo_.example.com')->getSubdomain());

        self::assertEquals('com', $this->extract->parse('bar.foo_.example.com')->getSuffix());
        self::assertEquals('example', $this->extract->parse('bar.foo_.example.com')->getHostname());
        self::assertEquals('bar.foo_', $this->extract->parse('bar.foo_.example.com')->getSubdomain());
        self::assertEquals(array('bar', 'foo_'), $this->extract->parse('bar.foo_.example.com')->getSubdomains());
    }

    protected function setUp(): void
    {
        $this->extract = new Extract();

        parent::setUp();
    }
}
