<?php
require_once dirname( __FILE__ ) . '/../vendor/autoload.php';
use Wikimedia\DeadlinkChecker\CheckIfDead;

class CheckIfDeadTest extends PHPUnit_Framework_TestCase {

	/**
	 * Test a single dead link via HTTP
	 */
	public function testDeadlinkTrueHTTP() {
		$obj = new CheckIfDead();
		$url = 'http://worldchiropracticalliance.org/resources/greens/green4.htm';
		$result = $obj->isLinkDead( $url );
		$this->assertTrue( $result );
	}

	/**
	 * Test a single dead link via HTTPS
	 */
	public function testDeadlinkTrueHTTPS() {
		$obj = new CheckIfDead();
		$url = 'https://en.wikipedia.org/nothing';
		$result = $obj->isLinkDead( $url );
		$this->assertTrue( $result );
	}

	/**
	 * Test a single dead link with a protocal relative URL
	 */
	public function testDeadlinkTrueNoProtocol() {
		$obj = new CheckIfDead();
		$url = '//en.wikipedia.org/nothing';
		$result = $obj->isLinkDead( $url );
		$this->assertTrue( $result );
	}

	/**
	 * Test a single live link via HTTPS
	 */
	public function testDeadlinkFalseHTTPS() {
		$obj = new CheckIfDead();
		$url = 'https://en.wikipedia.org';
		$result = $obj->isLinkDead( $url );
		$this->assertFalse( $result );
	}

	/**
	 * Test a single live link with a protocal relative URL
	 */
	public function testDeadlinkFalseNoProtocol() {
		$obj = new CheckIfDead();
		$url = '//en.wikipedia.org/wiki/Main_Page';
		$result = $obj->isLinkDead( $url );
		$this->assertFalse( $result );
	}

	/**
	 * Test a single live link via FTP
	 */
	public function testDeadlinkFalseFTP() {
		$obj = new CheckIfDead();
		$url = 'ftp://ftp.rsa.com/pub/pkcs/ascii/layman.asc';
		$result = $obj->isLinkDead( $url );
		$this->assertFalse( $result );
	}

	/**
	 * Test an array of dead links
	 */
	public function testDeadlinksTrue() {
		$obj = new CheckIfDead();
		// @codingStandardsIgnoreStart Line exceeds 100 characters
		$urls = [
			'https://en.wikipedia.org/nothing',
			'http://www.copart.co.uk/c2/specialSearch.html?_eventId=getLot&execution=e1s2&lotId=10543580',
			'http://forums.lavag.org/Industrial-EtherNet-EtherNet-IP-t9041.html',
			'http://203.221.255.21/opacs/TitleDetails?displayid=137394&collection=all&displayid=0&fieldcode=2&from=BasicSearch&genreid=0&ITEMID=$VARS.getItemId()&original=$VARS.getOriginal()&pageno=1&phrasecode=1&searchwords=Lara%20Saint%20Paul%20&status=2&subjectid=0&index='
		];
		// @codingStandardsIgnoreEnd
		$result = $obj->areLinksDead( $urls );
		$expected = [ true, true, true, true ];
		$this->assertEquals( $expected, array_values( $result ) );
	}

	/**
	 * Test an array of live links
	 */
	public function testDeadlinksFalse() {
		$obj = new CheckIfDead();
		// @codingStandardsIgnoreStart Line exceeds 100 characters
		$urls = [
			'https://en.wikipedia.org/wiki/Main_Page',
			'https://en.wikipedia.org/w/index.php?title=Republic_of_India',
			'https://astraldynamics.com',
			'http://news.bbc.co.uk/2/hi/uk_news/england/coventry_warwickshire/6236900.stm',
			'http://napavalleyregister.com/news/napa-pipe-plant-loads-its-final-rail-car/article_695e3e0a-8d33-5e3b-917c-07a7545b3594.html',
			'http://content.onlinejacc.org/cgi/content/full/41/9/1633',
			'http://flysunairexpress.com/#about',
			'ftp://ftp.rsa.com/pub/pkcs/ascii/layman.asc'
		];
		// @codingStandardsIgnoreEnd
		$result = $obj->areLinksDead( $urls );
		$expected = [ false, false, false, false, false, false, false, false ];
		$this->assertEquals( $expected, array_values( $result ) );
	}

	/**
	 * Test the URL cleaning function
	 */
	public function testCleanUrl() {
		$obj = new CheckIfDead();
		$this->assertEquals( $obj->cleanUrl( 'http://google.com?q=blah' ), 'google.com?q=blah' );
		$this->assertEquals( $obj->cleanUrl( 'https://www.google.com/' ), 'google.com' );
		$this->assertEquals( $obj->cleanUrl( 'ftp://google.com/#param=1' ), 'google.com' );
		$this->assertEquals( $obj->cleanUrl( '//google.com' ), 'google.com' );
		$this->assertEquals( $obj->cleanUrl( 'www.google.www.com' ), 'google.www.com' );
	}

}
