<?php
require_once dirname( __FILE__ ) . '/../checkIfDead.php';

class checkIfDeadTest extends PHPUnit_Framework_TestCase {

	/**
	 * Test a single dead link
	 */
	public function testDeadlinkTrue() {
		$obj = new checkIfDead();
		$url = 'http://worldchiropracticalliance.org/resources/greens/green4.htm';
		$result = $obj->checkDeadlink( $url );
		$this->assertEquals( true, $result['result'] );
	}

	/**
	 * Test a single live link
	 */
	public function testDeadlinkFalse() {
		$obj = new checkIfDead();
		$url = 'https://en.wikipedia.org';
		$result = $obj->checkDeadlink( $url );
		$this->assertEquals( false, $result['result'] );
	}

	/**
	 * Test an array of dead links
	 */
	public function testDeadlinksTrue() {
		$obj = new checkIfDead();
		$urls = [
			'https://en.wikipedia.org/nothing',
			'http://www.copart.co.uk/c2/specialSearch.html?_eventId=getLot&execution=e1s2&lotId=10543580',
			'http://forums.lavag.org/Industrial-EtherNet-EtherNet-IP-t9041.html',
			'http://203.221.255.21/opacs/TitleDetails?displayid=137394&collection=all&displayid=0&fieldcode=2&from=BasicSearch&genreid=0&ITEMID=$VARS.getItemId()&original=$VARS.getOriginal()&pageno=1&phrasecode=1&searchwords=Lara%20Saint%20Paul%20&status=2&subjectid=0&index='
		];
		$result = $obj->checkDeadlinks( $urls );
		$expected = [true, true, true, true];
		$this->assertEquals( $expected, $result['results'] );
	}

	/**
	 * Test an array of live links
	 */
	public function testDeadlinksFalse() {
		$obj = new checkIfDead();
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
		$result = $obj->checkDeadlinks( $urls );
		$expected = [false, false, false, false, false, false, false, false];
		$this->assertEquals( $expected, $result['results'] );
	}

	/**
	 * Test the URL cleaning function
	 */
	public function testCleanUrl() {
		$obj = new checkIfDead();
		// workaround to make private function testable
		$reflection = new \ReflectionClass( get_class( $obj ) );
		$method = $reflection->getMethod( 'cleanUrl' );
		$method->setAccessible( true );
		$this->assertEquals( $method->invokeArgs( $obj, ['http://google.com?q=blah'] ), 'google.com?q=blah' );
		$this->assertEquals( $method->invokeArgs( $obj, ['https://www.google.com/'] ), 'google.com' );
		$this->assertEquals( $method->invokeArgs( $obj, ['ftp://google.com/#param=1'] ), 'google.com' );
		$this->assertEquals( $method->invokeArgs( $obj, ['//google.com'] ), 'google.com' );
		$this->assertEquals( $method->invokeArgs( $obj, ['www.google.www.com'] ), 'google.www.com' );
	}

}
