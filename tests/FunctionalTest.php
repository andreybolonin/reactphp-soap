<?php

use Clue\React\Block;
use Clue\React\Buzz\Browser;
use Clue\React\Soap\Client;
use Clue\React\Soap\Proxy;
use PHPUnit\Framework\TestCase;

class BankResponse
{
}

/**
 * @group internet
 */
class FunctionalTest extends TestCase
{
    /**
     * @var React\EventLoop\LoopInterface
     */
    private $loop;

    /**
     * @var Client
     */
    private $client;

    // download WSDL file only once for all test cases
    private static $wsdl;
    public static function setUpBeforeClass()
    {
        self::$wsdl = file_get_contents('http://www.thomas-bayer.com/axis2/services/BLZService?wsdl');
    }

    public function setUp()
    {
        $this->loop = React\EventLoop\Factory::create();
        $this->client = new Client(new Browser($this->loop), self::$wsdl);
    }

    public function testBlzService()
    {
        $this->assertCount(2, $this->client->getFunctions());
        $this->assertCount(3, $this->client->getTypes());

        $api = new Proxy($this->client);

        $promise = $api->getBank(array('blz' => '12070000'));

        $result = Block\await($promise, $this->loop);

        $this->assertInternalType('object', $result);
        $this->assertTrue(isset($result->details));
        $this->assertTrue(isset($result->details->bic));
    }

    public function testBlzServiceWithClassmapReturnsExpectedType()
    {
        $this->client = new Client(new Browser($this->loop), self::$wsdl, array(
            'classmap' => array(
                'getBankResponseType' => 'BankResponse'
            )
        ));

        $this->assertCount(2, $this->client->getFunctions());
        $this->assertCount(3, $this->client->getTypes());

        $api = new Proxy($this->client);

        $promise = $api->getBank(array('blz' => '12070000'));

        $result = Block\await($promise, $this->loop);

        $this->assertInstanceOf('BankResponse', $result);
        $this->assertTrue(isset($result->details));
        $this->assertTrue(isset($result->details->bic));
    }

    public function testBlzServiceWithSoapV12()
    {
        $this->client = new Client(new Browser($this->loop), self::$wsdl, array(
            'soap_version' => SOAP_1_2
        ));

        $this->assertCount(2, $this->client->getFunctions());
        $this->assertCount(3, $this->client->getTypes());

        $api = new Proxy($this->client);

        $promise = $api->getBank(array('blz' => '12070000'));

        $result = Block\await($promise, $this->loop);

        $this->assertInternalType('object', $result);
        $this->assertTrue(isset($result->details));
        $this->assertTrue(isset($result->details->bic));
    }

    public function testBlzServiceNonWsdlModeReturnedWithoutOuterResultStructure()
    {
        $this->client = new Client(new Browser($this->loop), null, array(
            'location' => 'http://www.thomas-bayer.com/axis2/services/BLZService',
            'uri' => 'http://thomas-bayer.com/blz/',
        ));

        $api = new Proxy($this->client);

        // try encoding the "blz" parameter with the correct namespace (see uri)
        // $promise = $api->getBank(new SoapParam('12070000', 'ns1:blz'));
        $promise = $api->getBank(new SoapVar('12070000', XSD_STRING, null, null, 'blz', 'http://thomas-bayer.com/blz/'));

        $result = Block\await($promise, $this->loop);

        $this->assertInternalType('object', $result);
        $this->assertFalse(isset($result->details));
        $this->assertTrue(isset($result->bic));
    }

    /**
     * @expectedException Exception
     */
    public function testBlzServiceWithInvalidBlz()
    {
        $api = new Proxy($this->client);

        $promise = $api->getBank(array('blz' => 'invalid'));

        Block\await($promise, $this->loop);
    }

    /**
     * @expectedException Exception
     */
    public function testBlzServiceWithInvalidMethod()
    {
        $api = new Proxy($this->client);

        $promise = $api->doesNotexist();

        Block\await($promise, $this->loop);
    }

    /**
     * @expectedException Exception
     */
    public function testCancelMethodRejects()
    {
        $api = new Proxy($this->client);

        $promise = $api->getBank(array('blz' => '12070000'));
        $promise->cancel();

        Block\await($promise, $this->loop);
    }

    public function testGetLocationForFunctionName()
    {
        $this->assertEquals('http://www.thomas-bayer.com/axis2/services/BLZService', $this->client->getLocation('getBank'));
        $this->assertEquals('http://www.thomas-bayer.com/axis2/services/BLZService', $this->client->getLocation('getBank'));
    }

    public function testGetLocationForFunctionNumber()
    {
        $this->assertEquals('http://www.thomas-bayer.com/axis2/services/BLZService', $this->client->getLocation(0));
    }

    /**
     * @expectedException SoapFault
     */
    public function testGetLocationOfUnknownFunctionNameFails()
    {
        $this->client->getLocation('unknown');
    }

    /**
     * @expectedException SoapFault
     */
    public function testGetLocationForUnknownFunctionNumberFails()
    {
        $this->assertEquals('http://www.thomas-bayer.com/axis2/services/BLZService', $this->client->getLocation(100));
    }

    public function testGetLocationWithExplicitLocationOptionReturnsAsIs()
    {
        $this->client = new Client(new Browser($this->loop), self::$wsdl, array(
            'location' => 'http://example.com/'
        ));

        $this->assertEquals('http://example.com/', $this->client->getLocation(0));
    }
}
