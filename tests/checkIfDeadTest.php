<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Wikimedia\DeadlinkChecker\CheckIfDead;

class CheckIfDeadTest extends PHPUnit_Framework_TestCase {

	/**
	 * Test Links
	 *
	 * @param string $url URL
	 * @param bool $expect Expected link status
	 * @dataProvider provideIsLinkDead
	 */
	public function testIsLinkDead( $url, $expect ) {
		$obj = new CheckIfDead( 30, 60, false, true, true );
		$this->assertSame( $expect, $obj->isLinkDead( $url ) );
	}

	public function provideIsLinkDead() {
		// Invoke CheckIfDead to determine TOR readiness
		new CheckIfDead( 30, 60, false, true, true );

		// @codingStandardsIgnoreStart Line exceeds 100 characters
		$tests = [
			//[ 'http://mil.sagepub.com/content/17/2/227.short', false ],   // CloudFlare protection is preventing this URL from passing
			[ 'https://www.mobilitaet.bs.ch/gesamtverkehr/verkehrskonzepte/verkehrskonzept-innenstadt.html', false ],
			[ 'http://www.twirpx.com/file/1775048/', false ],
			[ 'https://ergebnisse2011.zensus2022.de/datenbank//online?operation=table&code=1000A-1008', false ],
			[ 'https://www.statistik.bs.ch/zahlen/tabellen/1-bevoelkerung/religionszugehoerigkeit.html', false ],
			[ 'https://www.archaeologie.bs.ch/vermitteln/archaeologische-publikationen.html', false ],
			[ 'https://www.gleichstellung.bs.ch/veranstaltungen/2019-oktober-luststreifen.html', false ],
			[ 'https://www.hs.fi/lehti/hsarchive/1970-01-08/14', false ],
			[ 'https://en.wikipedia.org', false ],
			[ '//en.wikipedia.org/wiki/Main_Page', false ],
			[ 'https://en.wikipedia.org/w/index.php?title=Republic_of_India', false ],
			[ 'ftp://ftp.cnpq.br/pub/doc/proantar/pab-12.pdf', false ],
			[ 'http://www.discogs.com/Various-Kad-Jeknu-Dragačevske-Trube-2/release/1173051', false ],
			[ 'https://astraldynamics.com', false ],
			[
				'http://napavalleyregister.com/news/napa-pipe-plant-loads-its-final-rail-car/article_695e3e0a-8d33-5e3b-917c-07a7545b3594.html',
				false
			],
			[ 'http://content.onlinejacc.org/cgi/content/full/41/9/1633', false ],
			[ 'http://flysunairexpress.com/#about', false ],
			[ 'http://www.palestineremembered.com/download/VillageStatistics/Table%20I/Haifa/Page-047.jpg', false ],
			[ 'http://archives.lse.ac.uk/TreeBrowse.aspx?src=CalmView.Catalog&field=RefNo&key=RICHARDS', false ],
			[
				'https://en.wikipedia.org/w/index.php?title=Wikipedia:Templates_for_discussion/Holding%20cell&action=edit',
				false
			],
			[ 'http://www.musicvf.com/Buck+Owens+%2526+Ringo+Starr.art', false ],
			[ 'http://www.beweb.chiesacattolica.it/diocesi/diocesi/503/Aosta', false ],
			[ 'http://www.dioceseoflascruces.org/', false ],
			[ 'http://www.worcesterdiocese.org/', false ],
			[ 'http://www.catholicdos.org/', false ],
			[ 'http://www.diocesitivoli.it/', false ],
			[ 'http://www.victoriadiocese.org/', false ],
			[ 'http://www.saginaw.org/', false ],
			[ 'http://www.dioceseofprovidence.org/', false ],
			[ 'http://www.rcdop.org.uk/', false ],
			[ 'mms://ier-w.latvijasradio.lv/pppy?20121202A121500130000', false ],
			[ 'mms://200.23.59.10/radiotam', true ],
			[ 'http://babel.hathitrust.org/cgi/pt?id=pst.000003356951;view=1up;seq=1', false ],
			[ 'http://parlinfo.aph.gov.au/parlInfo/search/display/display.w3p;query=Id%3A%22handbook%2Fnewhandbook%2F2014-10-31%2F0049%22', false ],
			[
				'https://www.google.se/maps/@60.0254617,14.9787602,3a,75y,133.6h,84.1t/data=!3m6!1e1!3m4!1sqMn_R4TRF0CerotZfLlg8g!2e0!7i13312!8i6656',
				false
			],
			[ 'https://en.wikipedia.org/nothing', true ],
			[ '//en.wikipedia.org/nothing', true ],
			[ 'http://worldchiropracticalliance.org/resources/greens/green4.htm', true ],
			[
				'http://203.221.255.21/opacs/TitleDetails?displayid=137394&collection=all&displayid=0&fieldcode=2&from=BasicSearch&genreid=0&ITEMID=$VARS.getItemId()&original=$VARS.getOriginal()&pageno=1&phrasecode=1&searchwords=Lara%20Saint%20Paul%20&status=2&subjectid=0&index=',
				true
			],
			[ 'mms://209.0.211.10/live', true ],
			[ 'chrome://examplefunction', null ],
			[
				'https:///http%3A//www.stat.kz/p_perepis/DocLib1/%D0%9F%D0%BE%D1%80%D1%82%D1%80%D0%B5%D1%82%20%D0%B3%D0%BE%D1%80%D0%BE%D0%B4%D0%B0%20%D1%80%D1%83%D1%81.pdf',
				true
			]
		];
		// @codingStandardsIgnoreEnd
		if ( function_exists( 'idn_to_ascii' ) ) {
			$tests[] = [ 'https://www.xn--80apbllt6f.xn--p1ai/', false ];
		}
		if ( CheckIfDead::isTorEnabled() ) {
			$tests[] = [ 'http://wiki2zkamfya6mnyvk4aom4yjyi2kwsz7et3e4wnikcrypqv63rsskid.onion/', false ];
			$tests[] = [ 'http://donionsixbjtiohce24abfgsffo2l4tk26qx464zylumgejukfq2vead.onion/onions.php', false ];
			$tests[] = [ 'http://xmhqwe3rnw6insl.onion/', true ];
		}

		return $tests;
	}

	/**
	 * Test an array of dead links
	 */
	public function testAreLinksDead() {
		$obj = new CheckIfDead( 30, 60, false, true, true );
		$urls = [
			'https://en.wikipedia.org/wiki/Main_Page',
			'https://en.wikipedia.org/nothing',
		];
		$result = $obj->areLinksDead( $urls );
		$expected = [ false, true ];
		$this->assertEquals( $expected, array_values( $result ) );
	}

	/**
	 * Test the URL cleaning function
	 *
	 * @param string $url URL
	 * @param expect $expect Expected cleaned URL
	 * @dataProvider provideCleanURL
	 */
	public function testCleanURL( $url, $expect ) {
		$obj = new CheckIfDead( 30, 60, false, true, true );
		$this->assertEquals( $expect, $obj->cleanURL( $url ) );
	}

	public function provideCleanURL() {
		return [
			[ 'http://google.com?q=blah', 'google.com?q=blah' ],
			[ 'https://www.google.com/', 'google.com' ],
			[ 'ftp://google.com/#param=1', 'google.com' ],
			[ '//google.com', 'google.com' ],
			[ 'www.google.www.com', 'google.www.com' ],
		];
	}

	/**
	 * Test the URL sanitizing function
	 *
	 * @param string $url URL
	 * @param expect $expect Expected sanitized URL
	 * @dataProvider provideSanitizeURL
	 */
	public function testSanitizeURL( $url, $expect ) {
		$obj = new CheckIfDead( 30, 60, false, true, true );
		$this->assertEquals( $expect, $obj->sanitizeURL( $url, true ) );
	}

	public function provideSanitizeURL() {
		// @codingStandardsIgnoreStart Line exceeds 100 characters
		$tests = [
			[ 'http://google.com?q=blah', 'http://google.com/?q=blah' ],
			[ '//google.com?q=blah', 'https://google.com/?q=blah' ],
			[
				'https://en.wikipedia.org/w/index.php?title=Bill_Gates&action=edit',
				'https://en.wikipedia.org/w/index.php?title=Bill_Gates&action=edit'
			],
			[ 'ftp://google.com/#param=1', 'ftp://google.com/' ],
			[ 'https://zh.wikipedia.org/wiki/猫', 'https://zh.wikipedia.org/wiki/%E7%8C%AB' ],
			[
				'http://www.discogs.com/Various-Kad-Jeknu-Dragačevske-Trube-2',
				'http://www.discogs.com/Various-Kad-Jeknu-Draga%C4%8Devske-Trube-2'
			],
			[ 'https:/zh.wikipedia.org/wiki/猫', 'https://zh.wikipedia.org/wiki/%E7%8C%AB' ],
			[ 'zh.wikipedia.org/wiki/猫', 'http://zh.wikipedia.org/wiki/%E7%8C%AB' ],
			[ 'http://www.cabelas.com/story-123/boddington_short_mag/10201/The+Short+Mag+Revolution.shtml',
				'http://www.cabelas.com/story-123/boddington_short_mag/10201/The+Short+Mag+Revolution.shtml'
			],
			[ 'http%3A%2F%2Fwww.sports-reference.com%2Folympics%2Fwinter%2F1994%2FNCO%2Fmens-team.html',
				'http://www.sports-reference.com/olympics/winter/1994/NCO/mens-team.html' ],
			[ 'http%3A//www%2Eatimes%2Ecom/atimes/Middle_East/FH13Ak05%2Ehtml',
				'http://www.atimes.com/atimes/Middle_East/FH13Ak05.html' ],
			[ 'http://www.eurostar.se/html/bokning.php?ort=Falk%F6ping',
				'http://www.eurostar.se/html/bokning.php?ort=Falk%F6ping' ],
			[ 'http://www.silvercityvault.org.uk/index.php?a=ViewItem&key=SHsiRCI6IlN1YmplY3QgPSBcIkJyaWRnZXNcIiIsIk4iOjUyLCJQIjp7InN1YmplY3RfaWQiOiIyMCIsImpvaW5fb3AiOjJ9fQ%3D%3D&pg=8&WINID=1384795972907#YqFdqg6Pj8MAAAFCbEWeJA/67',
				'http://www.silvercityvault.org.uk/index.php?a=ViewItem&key=SHsiRCI6IlN1YmplY3QgPSBcIkJyaWRnZXNcIiIsIk4iOjUyLCJQIjp7InN1YmplY3RfaWQiOiIyMCIsImpvaW5fb3AiOjJ9fQ%3D%3D&pg=8&WINID=1384795972907' ],
			[ 'http://example.com/blue+light%20blue?blue%2Blight+blue%23foobar#foobar',
				'http://example.com/blue+light%20blue?blue%2Blight+blue%23foobar' ],
			[ 'http://www.musicvf.com/Buck+Owens+%2526+Ringo+Starr.art',
				'http://www.musicvf.com/Buck+Owens+%2526+Ringo+Starr.art' ],
			[ '://www.musicvf.com/',
				'http://www.musicvf.com/' ],
			[ 'http://babel.hathitrust.org/cgi/pt?id=pst.000003356951;view=1up;seq=1', 'http://babel.hathitrust.org/cgi/pt?id=pst.000003356951;view=1up;seq=1' ]
		];
		// @codingStandardsIgnoreEnd
		if ( function_exists( 'idn_to_ascii' ) ) {
			$tests[] = [ 'http://кц.рф/ru/', 'http://xn--j1ay.xn--p1ai/ru/' ];
		}

		return $tests;
	}

	/**
	 * Test the URL parsing function
	 *
	 * @param string $url URL
	 * @param expect $expect Expected parsed URL
	 * @dataProvider provideParseURL
	 */
	public function testParseURL( $url, $expect ) {
		$obj = new CheckIfDead( 30, 60, false, true, true );
		$this->assertEquals( $expect, $obj->parseURL( $url ) );
	}

	public function provideParseURL() {
		return [
			[
				'http://кц.рф/ru/', [
				'scheme' => 'http',
				'host' => 'кц.рф',
				'path' => '/ru/',
			]
			],
			[
				'http://www.discogs.com/Various-Kad-Jeknu-Dragačevske-Trube-2', [
				'scheme' => 'http',
				'host' => 'www.discogs.com',
				'path' => '/Various-Kad-Jeknu-Dragačevske-Trube-2',
			]
			],
		];
	}
}
