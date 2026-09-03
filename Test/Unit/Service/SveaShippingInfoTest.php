<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Service;

use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\AddressFactory;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Service\SveaShippingInfo;

class SveaShippingInfoTest extends TestCase
{
    private Json $jsonSerializer;
    private DataObjectFactory&MockObject $dataObjectFactory;
    private AddressFactory&MockObject $addressFactory;
    private SveaShippingInfo $service;

    protected function setUp(): void
    {
        $this->jsonSerializer = new Json();
        $this->dataObjectFactory = $this->createMock(DataObjectFactory::class);
        $this->addressFactory = $this->createMock(AddressFactory::class);

        $this->service = new SveaShippingInfo(
            $this->jsonSerializer,
            $this->dataObjectFactory,
            $this->addressFactory
        );
    }

    // --- getFromQuote ---

    public function testGetFromQuoteReturnsNullWhenExtShippingInfoIsNull(): void
    {
        $quote = $this->makeQuote(null);

        $result = $this->service->getFromQuote($quote);

        $this->assertNull($result);
    }

    public function testGetFromQuoteReturnsNullWhenExtShippingInfoIsEmptyString(): void
    {
        $quote = $this->makeQuote('');

        $result = $this->service->getFromQuote($quote);

        $this->assertNull($result);
    }

    public function testGetFromQuoteReturnsNullWhenJsonIsInvalid(): void
    {
        $quote = $this->makeQuote('not-valid-json{{{');

        $result = $this->service->getFromQuote($quote);

        $this->assertNull($result);
    }

    public function testGetFromQuoteReturnsNullWhenSveaShippingInfoKeyMissing(): void
    {
        $json = $this->jsonSerializer->serialize(['other_key' => ['foo' => 'bar']]);
        $quote = $this->makeQuote($json);

        $result = $this->service->getFromQuote($quote);

        $this->assertNull($result);
    }

    public function testGetFromQuoteReturnsNullWhenSveaShippingInfoIsEmpty(): void
    {
        $json = $this->jsonSerializer->serialize(['svea_shipping_info' => []]);
        $quote = $this->makeQuote($json);

        $result = $this->service->getFromQuote($quote);

        $this->assertNull($result);
    }

    public function testGetFromQuoteReturnsDataObjectWithCorrectData(): void
    {
        $sveaData = ['carrier' => 'postnord', 'service' => 'parcel'];
        $json = $this->jsonSerializer->serialize(['svea_shipping_info' => $sveaData]);
        $quote = $this->makeQuote($json);

        $this->dataObjectFactory->method('create')->willReturnCallback(fn() => new DataObject());

        $result = $this->service->getFromQuote($quote);

        $this->assertInstanceOf(DataObject::class, $result);
        $this->assertSame('postnord', $result->getData('carrier'));
        $this->assertSame('parcel', $result->getData('service'));
    }

    // --- setInQuote ---

    public function testSetInQuoteSerializesContentAndSetsOnQuoteAndPayment(): void
    {
        $content = ['carrier' => 'dhl', 'location_id' => '42'];

        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setAdditionalInformation'])
            ->getMock();

        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('svea_shipping_info', $content);

        // Quote: use onlyMethods(['getPayment']) so real DataObject magic works
        // for getExtShippingInfo / setExtShippingInfo.
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();

        $quote->setExtShippingInfo(null);
        $quote->method('getPayment')->willReturn($payment);

        $this->dataObjectFactory->method('create')->willReturnCallback(fn() => new DataObject());

        $this->service->setInQuote($quote, $content);

        $stored = $this->jsonSerializer->unserialize($quote->getExtShippingInfo());
        $this->assertSame($content, $stored['svea_shipping_info']);
    }

    public function testSetInQuoteMergesWithExistingExtShippingInfo(): void
    {
        $existing = ['other_info' => ['key' => 'value']];
        $content = ['carrier' => 'budbee'];

        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setAdditionalInformation'])
            ->getMock();

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();

        $quote->setExtShippingInfo($this->jsonSerializer->serialize($existing));
        $quote->method('getPayment')->willReturn($payment);

        $this->dataObjectFactory->method('create')->willReturnCallback(fn() => new DataObject());

        $this->service->setInQuote($quote, $content);

        $stored = $this->jsonSerializer->unserialize($quote->getExtShippingInfo());
        $this->assertArrayHasKey('other_info', $stored);
        $this->assertArrayHasKey('svea_shipping_info', $stored);
        $this->assertSame($content, $stored['svea_shipping_info']);
    }

    public function testSetInQuoteStartsFreshWhenExtShippingInfoIsInvalidJson(): void
    {
        $content = ['carrier' => 'instabox'];

        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setAdditionalInformation'])
            ->getMock();

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();

        $quote->setExtShippingInfo('{{invalid-json');
        $quote->method('getPayment')->willReturn($payment);

        $this->dataObjectFactory->method('create')->willReturnCallback(fn() => new DataObject());

        $this->service->setInQuote($quote, $content);

        $stored = $this->jsonSerializer->unserialize($quote->getExtShippingInfo());
        $this->assertSame(['svea_shipping_info' => $content], $stored);
    }

    // --- getFromOrder ---

    public function testGetFromOrderReturnsNullWhenNoSveaShippingInfoInPayment(): void
    {
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAdditionalInformation'])
            ->getMock();

        $payment->method('getAdditionalInformation')->willReturn([]);

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();

        $order->method('getPayment')->willReturn($payment);

        $result = $this->service->getFromOrder($order);

        $this->assertNull($result);
    }

    public function testGetFromOrderReturnsNullWhenSveaShippingInfoIsEmpty(): void
    {
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAdditionalInformation'])
            ->getMock();

        $payment->method('getAdditionalInformation')->willReturn(['svea_shipping_info' => []]);

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();

        $order->method('getPayment')->willReturn($payment);

        $result = $this->service->getFromOrder($order);

        $this->assertNull($result);
    }

    public function testGetFromOrderReturnsDataObjectWithCorrectData(): void
    {
        $sveaData = ['carrier' => 'postnord', 'service_code' => 'MyPack'];

        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAdditionalInformation'])
            ->getMock();

        $payment->method('getAdditionalInformation')->willReturn(['svea_shipping_info' => $sveaData]);

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();

        $order->method('getPayment')->willReturn($payment);

        $this->dataObjectFactory->method('create')->willReturnCallback(fn() => new DataObject());

        $result = $this->service->getFromOrder($order);

        $this->assertInstanceOf(DataObject::class, $result);
        $this->assertSame('postnord', $result->getData('carrier'));
        $this->assertSame('MyPack', $result->getData('service_code'));
    }

    // --- createOrderAddressFromLocation ---

    public function testCreateOrderAddressFromLocationMapsAllFields(): void
    {
        $location = new DataObject([
            'name' => 'My Pickup Store',
            'address' => [
                'countryCode' => 'SE',
                'postalCode' => '12345',
                'city' => 'Stockholm',
                'streetAddress' => 'Main Street 1',
            ],
        ]);

        $address = $this->makeRealAddress();
        $this->addressFactory->method('create')->willReturn($address);

        $result = $this->service->createOrderAddressFromLocation($location);

        $this->assertSame('My Pickup Store', $result->getCompany());
        $this->assertSame('SE', $result->getCountryId());
        $this->assertSame('12345', $result->getPostcode());
        $this->assertSame('Stockholm', $result->getCity());
        $this->assertSame(['Main Street 1'], $result->getStreet());
    }

    public function testCreateOrderAddressFromLocationIncludesStreetAddress2WhenSet(): void
    {
        $location = new DataObject([
            'name' => 'Depot North',
            'address' => [
                'countryCode' => 'SE',
                'postalCode' => '99999',
                'city' => 'Göteborg',
                'streetAddress' => 'Dock Road 5',
                'streetAddress2' => 'Unit B',
            ],
        ]);

        $address = $this->makeRealAddress();
        $this->addressFactory->method('create')->willReturn($address);

        $result = $this->service->createOrderAddressFromLocation($location);

        $this->assertSame(['Dock Road 5', 'Unit B'], $result->getStreet());
    }

    public function testCreateOrderAddressFromLocationOmitsStreetAddress2WhenEmpty(): void
    {
        $location = new DataObject([
            'name' => 'City Store',
            'address' => [
                'countryCode' => 'SE',
                'postalCode' => '11111',
                'city' => 'Malmö',
                'streetAddress' => 'High Street 10',
                'streetAddress2' => '',
            ],
        ]);

        $address = $this->makeRealAddress();
        $this->addressFactory->method('create')->willReturn($address);

        $result = $this->service->createOrderAddressFromLocation($location);

        $this->assertSame(['High Street 10'], $result->getStreet());
    }

    // --- getExcludeSveaShipping / setExcludeSveaShipping ---

    public function testGetExcludeSveaShippingDefaultsToTrue(): void
    {
        $this->assertTrue($this->service->getExcludeSveaShipping());
    }

    public function testSetExcludeSveaShippingToFalseChangesTheValue(): void
    {
        $this->service->setExcludeSveaShipping(false);

        $this->assertFalse($this->service->getExcludeSveaShipping());
    }

    public function testSetExcludeSveaShippingToTrueKeepsItTrue(): void
    {
        $this->service->setExcludeSveaShipping(true);

        $this->assertTrue($this->service->getExcludeSveaShipping());
    }

    // --- Helpers ---

    /**
     * Creates a Quote mock where getExtShippingInfo / setExtShippingInfo work via
     * real DataObject magic, while getPayment() is not needed in these simple cases.
     */
    private function makeQuote(mixed $extShippingInfo): Quote&MockObject
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $quote->setExtShippingInfo($extShippingInfo);

        return $quote;
    }

    /**
     * Creates an Address mock where all real DataObject setters/getters work.
     */
    private function makeRealAddress(): Address&MockObject
    {
        return $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }
}
