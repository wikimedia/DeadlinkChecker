<?php

require_once __DIR__ . '/../vendor/autoload.php';
use Wikimedia\DeadlinkChecker\CheckIfDead;

class CheckIfDeadTest extends PHPUnit_Framework_TestCase {

	/**
	 * Test live links
	 */
	public function testIsLinkDeadFalse() {
		$obj = new CheckIfDead();
		// @codingStandardsIgnoreStart Line exceeds 100 characters
		$this->assertFalse( $obj->isLinkDead( 'https://en.wikipedia.org' ) );
		$this->assertFalse( $obj->isLinkDead( '//en.wikipedia.org/wiki/Main_Page' ) );
		$this->assertFalse( $obj->isLinkDead( 'https://en.wikipedia.org/w/index.php?title=Republic_of_India' ) );
		$this->assertFalse( $obj->isLinkDead( 'ftp://ftp.rsa.com/pub/pkcs/ascii/layman.asc' ) );
		$this->assertFalse( $obj->isLinkDead( 'http://www.discogs.com/Various-Kad-Jeknu-Dragačevske-Trube-2/release/1173051' ) );
		$this->assertFalse( $obj->isLinkDead( 'https://astraldynamics.com' ) );
		$this->assertFalse( $obj->isLinkDead( 'http://napavalleyregister.com/news/napa-pipe-plant-loads-its-final-rail-car/article_695e3e0a-8d33-5e3b-917c-07a7545b3594.html' ) );
		$this->assertFalse( $obj->isLinkDead( 'http://content.onlinejacc.org/cgi/content/full/41/9/1633' ) );
		$this->assertFalse( $obj->isLinkDead( 'http://flysunairexpress.com/#about' ) );
		if ( function_exists( 'idn_to_ascii' ) ) {
			$this->assertFalse( $obj->isLinkDead( 'http://кц.рф/ru/' ) );
		}
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Test dead links
	 */
	public function testIsLinkDeadTrue() {
		$obj = new CheckIfDead();
		// @codingStandardsIgnoreStart Line exceeds 100 characters
		$this->assertTrue( $obj->isLinkDead( 'https://en.wikipedia.org/nothing' ) );
		$this->assertTrue( $obj->isLinkDead( '//en.wikipedia.org/nothing' ) );
		$this->assertTrue( $obj->isLinkDead( 'http://worldchiropracticalliance.org/resources/greens/green4.htm' ) );
		$this->assertTrue( $obj->isLinkDead( 'http://www.copart.co.uk/c2/specialSearch.html?_eventId=getLot&execution=e1s2&lotId=10543580' ) );
		$this->assertTrue( $obj->isLinkDead( 'http://forums.lavag.org/Industrial-EtherNet-EtherNet-IP-t9041.html' ) );
		$this->assertTrue( $obj->isLinkDead( 'http://203.221.255.21/opacs/TitleDetails?displayid=137394&collection=all&displayid=0&fieldcode=2&from=BasicSearch&genreid=0&ITEMID=$VARS.getItemId()&original=$VARS.getOriginal()&pageno=1&phrasecode=1&searchwords=Lara%20Saint%20Paul%20&status=2&subjectid=0&index=' ) );
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Test an array of dead links
	 */
	public function testAreLinksDead() {
		$obj = new CheckIfDead();
		// @codingStandardsIgnoreStart Line exceeds 100 characters
		$urls = [
			'https://en.wikipedia.org/wiki/Main_Page',
			'https://en.wikipedia.org/nothing',
		];
		// @codingStandardsIgnoreEnd
		$result = $obj->areLinksDead( $urls );
		$expected = [ false, true ];
		$this->assertEquals( $expected, array_values( $result ) );
	}

	/**
	 * Test the URL cleaning function
	 */
	public function testCleanURL() {
		$obj = new CheckIfDead();
		// @codingStandardsIgnoreStart Line exceeds 100 characters
		$this->assertEquals( $obj->cleanURL( 'http://google.com?q=blah' ), 'google.com?q=blah' );
		$this->assertEquals( $obj->cleanURL( 'https://www.google.com/' ), 'google.com' );
		$this->assertEquals( $obj->cleanURL( 'ftp://google.com/#param=1' ), 'google.com' );
		$this->assertEquals( $obj->cleanURL( '//google.com' ), 'google.com' );
		$this->assertEquals( $obj->cleanURL( 'www.google.www.com' ), 'google.www.com' );
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Test the URL sanitizing function
	 */
	public function testSanitizeURL() {
		$obj = new CheckIfDead();
		// @codingStandardsIgnoreStart Line exceeds 100 characters
		$this->assertEquals( $obj->sanitizeURL( 'http://google.com?q=blah' ), 'http://google.com/?q=blah' );
		$this->assertEquals( $obj->sanitizeURL( '//google.com?q=blah' ), 'https://google.com/?q=blah' );
		$this->assertEquals( $obj->sanitizeURL( 'https://en.wikipedia.org/w/index.php?title=Bill_Gates&action=edit' ), 'https://en.wikipedia.org/w/index.php?title=Bill_Gates&action=edit' );
		$this->assertEquals( $obj->sanitizeURL( 'ftp://google.com/#param=1' ), 'ftp://google.com/#param=1' );
		$this->assertEquals( $obj->sanitizeURL( 'https://zh.wikipedia.org/wiki/猫' ), 'https://zh.wikipedia.org/wiki/%E7%8C%AB' );
		$this->assertEquals( $obj->sanitizeURL( 'http://www.discogs.com/Various-Kad-Jeknu-Dragačevske-Trube-2' ), 'http://www.discogs.com/Various-Kad-Jeknu-Draga%C4%8Devske-Trube-2' );
		if ( function_exists( 'idn_to_ascii' ) ) {
			$this->assertEquals( $obj->sanitizeURL( 'http://кц.рф/ru/' ), 'http://xn--j1ay.xn--p1ai/ru/' );
		}
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Test the URL parsing function
	 */
	public function testParseURL() {
		$obj = new CheckIfDead();
		// @codingStandardsIgnoreStart Line exceeds 100 characters
		$this->assertEquals( $obj->parseURL( 'http://кц.рф/ru/' ), array( 'scheme' => 'http', 'host' => 'кц.рф', 'path' => '/ru/' ) );
		$this->assertEquals( $obj->parseURL( 'http://www.discogs.com/Various-Kad-Jeknu-Dragačevske-Trube-2' ), array( 'scheme' => 'http', 'host' => 'www.discogs.com', 'path' => '/Various-Kad-Jeknu-Dragačevske-Trube-2' ) );
		// @codingStandardsIgnoreEnd
	}
}
