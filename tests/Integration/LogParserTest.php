<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VisitorGenerator\tests\Integration;
use Piwik\Plugins\VisitorGenerator\LogParser;

/**
 * @group VisitorGenerator
 * @group LogParserTest
 */
class LogParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var LogParser
     */
    private $logParser;

    public function setUp()
    {
        parent::setUp();

        $this->logParser = new LogParser(__DIR__ . '/../../data/access.log');
    }

    public function test_getLogLines()
    {
        $lines = $this->logParser->getLogLines();

        $this->assertEquals('##### YOU CAN REPLACE THIS FILE WITH YOUR OWN PIWIK LOGS', trim($lines[0]));
        $this->assertEquals('- - - [29/May/2011:06:18:38 +0200] "GET /piwik.php?idsite=1&rec=1&apiv=1&rand=1636495582&_id=e4002ba0f2c2dd70&fla=1&java=1&dir=0&qt=0&realp=0&pdf=0&wma=0&gears=0&ag=0&h=12&m=34&s=6&res=1024x768&cookie=1&_cvar=%7B%225%22%3A%5B%22VisitorType%22%2C%22NewLoggedOut%22%5D%7D&cvar=%7B%223%22%3A%5B%22_pks%22%2C%22SKU2%22%5D%2C%224%22%3A%5B%22_pkn%22%2C%22PRODUCT+name%22%5D%2C%225%22%3A%5B%22_pkc%22%2C%22Electronics+%26+Cameras%22%5D%7D&url=http%3A%2F%2Fexample.org%2Findex.htm&urlref=&action_name=incredible+title%21&urlref=http%3A%2F%2Fpiwik.org%2Fcontribute% HTTP/1.1" 200 43 "http://forum.piwik.org/read.php?2,76977" "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; en-us) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1"', trim($lines[4]));

        $this->assertGreaterThan(100, count($lines));
    }

    public function test_getParsedLogLines_shouldFineLines()
    {
        $lines = $this->logParser->getParsedLogLines();
        $this->assertGreaterThan(100, count($lines));
    }

    public function test_getParsedLogLines_shouldParseRawLineIntoArray()
    {
        $lines = $this->logParser->getParsedLogLines();

        foreach ($lines as $line) {
            $this->assertEquals(array('ip', 'time', 'url', 'referrer', 'ua'), array_keys($line));
            $this->assertContains('piwik.php', $line['url']);
        }
    }

    public function test_parseLogLine_shouldParseLineIntoParts()
    {
        $line = LogParser::parseLogLine('- - - [29/May/2011:06:18:38 +0200] "GET /piwik.php?idsite=1&rec=1&apiv=1&rand=1636495582&_id=e4002ba0f2c2dd70&fla=1&java=1&dir=0&qt=0&realp=0&pdf=0&wma=0&gears=0&ag=0&h=12&m=34&s=6&res=1024x768&cookie=1&_cvar=%7B%225%22%3A%5B%22VisitorType%22%2C%22NewLoggedOut%22%5D%7D&cvar=%7B%223%22%3A%5B%22_pks%22%2C%22SKU2%22%5D%2C%224%22%3A%5B%22_pkn%22%2C%22PRODUCT+name%22%5D%2C%225%22%3A%5B%22_pkc%22%2C%22Electronics+%26+Cameras%22%5D%7D&url=http%3A%2F%2Fexample.org%2Findex.htm&urlref=&action_name=incredible+title%21&urlref=http%3A%2F%2Fpiwik.org%2Fcontribute% HTTP/1.1" 200 43 "http://forum.piwik.org/read.php?2,76977" "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; en-us) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1"');

        $expected = array(
            'ip'       => '-',
            'time'     => '29/May/2011:06:18:38 +0200',
            'url'      => '/piwik.php?idsite=1&rec=1&apiv=1&rand=1636495582&_id=e4002ba0f2c2dd70&fla=1&java=1&dir=0&qt=0&realp=0&pdf=0&wma=0&gears=0&ag=0&h=12&m=34&s=6&res=1024x768&cookie=1&_cvar=%7B%225%22%3A%5B%22VisitorType%22%2C%22NewLoggedOut%22%5D%7D&cvar=%7B%223%22%3A%5B%22_pks%22%2C%22SKU2%22%5D%2C%224%22%3A%5B%22_pkn%22%2C%22PRODUCT+name%22%5D%2C%225%22%3A%5B%22_pkc%22%2C%22Electronics+%26+Cameras%22%5D%7D&url=http%3A%2F%2Fexample.org%2Findex.htm&urlref=&action_name=incredible+title%21&urlref=http%3A%2F%2Fpiwik.org%2Fcontribute% HTTP/1.1',
            'referrer' => 'http://forum.piwik.org/read.php?2,76977',
            'ua'       => 'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; en-us) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1',
        );

        $this->assertEquals($expected, $line);
    }

    public function test_parseLogLine_shouldFindAValidIp()
    {
        $line = LogParser::parseLogLine('192.168.33.10 - - [29/May/2011:06:18:38 +0200] "GET /piwik.php?idsite=1&rec=1&apiv=1&rand=1636495582&_id=e4002ba0f2c2dd70&fla=1&java=1&dir=0&qt=0&realp=0&pdf=0&wma=0&gears=0&ag=0&h=12&m=34&s=6&res=1024x768&cookie=1&_cvar=%7B%225%22%3A%5B%22VisitorType%22%2C%22NewLoggedOut%22%5D%7D&cvar=%7B%223%22%3A%5B%22_pks%22%2C%22SKU2%22%5D%2C%224%22%3A%5B%22_pkn%22%2C%22PRODUCT+name%22%5D%2C%225%22%3A%5B%22_pkc%22%2C%22Electronics+%26+Cameras%22%5D%7D&url=http%3A%2F%2Fexample.org%2Findex.htm&urlref=&action_name=incredible+title%21&urlref=http%3A%2F%2Fpiwik.org%2Fcontribute% HTTP/1.1" 200 43 "http://forum.piwik.org/read.php?2,76977" "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; en-us) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1"');

        $this->assertEquals('192.168.33.10', $line['ip']);
    }

    public function test_parseLogLine_shouldReturnEmptyArrayIfLineIsNotALog()
    {
        $line = LogParser::parseLogLine('##### YOU CAN REPLACE THIS FILE WITH YOUR OWN PIWIK LOGS');

        $this->assertEquals(array(), $line);
    }

    public function test_getParsedLogLines_shouldRemoveAllInvalidLogLines()
    {
        $lines = $this->logParser->getParsedLogLines();

        foreach ($lines as $line) {
            $this->assertNotEmpty($line);
        }
    }

}
