<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Model;

use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Model\CheckoutOrderNumberReference;

class CheckoutOrderNumberReferenceTest extends TestCase
{
    private Session&MockObject $session;
    private CheckoutOrderNumberReference $reference;

    protected function setUp(): void
    {
        // createMock stubs ALL methods (including magic __call ones) to return null.
        // For tests requiring a non-null sequence, individual tests stub __call.
        $this->session = $this->createMock(Session::class);

        $this->reference = new CheckoutOrderNumberReference($this->session);
    }

    public function testHasClientOrderNumberReturnsFalseWhenQuoteHasNoClientOrderId(): void
    {
        $quote = $this->makeQuote();
        $this->reference->setQuote($quote);

        $this->assertFalse($this->reference->hasClientOrderNumber());
    }

    public function testHasClientOrderNumberReturnsTrueWhenQuoteHasClientOrderId(): void
    {
        $quote = $this->makeQuote();
        $quote->setData('svea_client_order_id', 'ORDER-100');
        $this->reference->setQuote($quote);

        $this->assertTrue($this->reference->hasClientOrderNumber());
    }

    public function testGetClientOrderNumberUsesReservedOrderIdWhenSequenceIsOne(): void
    {
        // getSveaCheckoutSequence returns null (default from createMock) → sequence defaults to 1
        $quote = $this->makeQuoteWithReservedOrderId('100000001');
        $this->reference->setQuote($quote);

        $result = $this->reference->getClientOrderNumber();

        $this->assertSame('100000001', $result);
    }

    public function testGetClientOrderNumberAppendsSequenceWhenSequenceIsGreaterThanOne(): void
    {
        $quote = $this->makeQuoteWithReservedOrderId('100000002');
        $this->reference->setQuote($quote);

        $this->stubSessionSequence(2);

        $result = $this->reference->getClientOrderNumber();

        $this->assertSame('100000002-2', $result);
    }

    public function testGetClientOrderNumberTruncatesToThirtyOneCharacters(): void
    {
        // 36-character reserved order ID — result must be capped at 31 chars
        $longOrderId = str_repeat('A', 36);
        $quote = $this->makeQuoteWithReservedOrderId($longOrderId);
        $this->reference->setQuote($quote);

        $result = $this->reference->getClientOrderNumber();

        $this->assertSame(31, strlen($result));
        $this->assertSame(substr($longOrderId, 0, 31), $result);
    }

    public function testGetClientOrderNumberTruncatesWithSequenceSuffix(): void
    {
        // 30 chars + '-3' = 32 chars — must be truncated to 31
        $thirtyCharId = str_repeat('B', 30);
        $quote = $this->makeQuoteWithReservedOrderId($thirtyCharId);
        $this->reference->setQuote($quote);

        $this->stubSessionSequence(3);

        $result = $this->reference->getClientOrderNumber();

        $this->assertSame(31, strlen($result));
        $this->assertSame(substr($thirtyCharId . '-3', 0, 31), $result);
    }

    public function testGetClientOrderNumberReservesOrderIdWhenNotAlreadyReserved(): void
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getReservedOrderId', 'reserveOrderId'])
            ->getMock();

        // First call: no reserved ID yet; second call: ID exists after reservation
        $quote->method('getReservedOrderId')->willReturnOnConsecutiveCalls(null, '200000001');
        $quote->expects($this->once())->method('reserveOrderId');

        $this->reference->setQuote($quote);

        $result = $this->reference->getClientOrderNumber();

        $this->assertSame('200000001', $result);
    }

    public function testGetSveaHashReturnsSha1OfClientOrderNumber(): void
    {
        $quote = $this->makeQuoteWithReservedOrderId('100000003');
        $this->reference->setQuote($quote);

        $hash = $this->reference->getSveaHash();

        $this->assertSame(hash('sha1', '100000003'), $hash);
    }

    public function testGetSveaHashCachesHashOnQuote(): void
    {
        $quote = $this->makeQuoteWithReservedOrderId('100000004');
        $this->reference->setQuote($quote);

        $hash1 = $this->reference->getSveaHash();
        $hash2 = $this->reference->getSveaHash();

        $this->assertSame($hash1, $hash2);
        // Verify the hash was written to the quote via setSveaHash DataObject magic
        $this->assertSame($hash1, $quote->getSveaHash());
    }

    public function testPaymentIsExpiredReturnsTrueWhenCreatedAtIsZero(): void
    {
        $payment = $this->makePaymentWithCreatedAt(0);
        $quote = $this->makeQuoteWithPayment($payment);
        $this->reference->setQuote($quote);

        $this->assertTrue($this->reference->paymentIsExpired());
    }

    public function testPaymentIsExpiredReturnsFalseWhenCreatedAtIsCurrentTime(): void
    {
        $payment = $this->makePaymentWithCreatedAt(time());
        $quote = $this->makeQuoteWithPayment($payment);
        $this->reference->setQuote($quote);

        $this->assertFalse($this->reference->paymentIsExpired());
    }

    public function testAddToSequenceIncrementsFromOneToTwo(): void
    {
        // No prior sequence: getSveaCheckoutSequence returns null → getSequence() seeds
        // the session with 1 (setSveaCheckoutSequence(1)), then addToSequence increments
        // to 2 (setSveaCheckoutSequence(2)). The final stored value must be 2.
        $setCalls = [];
        $this->session->method('__call')
            ->willReturnCallback(static function (string $method, array $args) use (&$setCalls) {
                if ($method === 'setSveaCheckoutSequence') {
                    $setCalls[] = $args[0];
                }
                return null;
            });

        $this->reference->addToSequence();

        $this->assertContains(2, $setCalls, 'setSveaCheckoutSequence(2) was never called');
        $this->assertSame(2, end($setCalls), 'The last setSveaCheckoutSequence call should be 2');
    }

    public function testAddToSequenceIncrementsFromExistingValue(): void
    {
        // getSequence reads 3, so setSveaCheckoutSequence should be called with 4
        $callCount = 0;
        $this->session->method('__call')
            ->willReturnCallback(function (string $method, array $args) use (&$callCount) {
                if ($method === 'getSveaCheckoutSequence') {
                    return 3;
                }
                if ($method === 'setSveaCheckoutSequence') {
                    $callCount++;
                    $this->assertSame([4], $args);
                }
                return null;
            });

        $this->reference->addToSequence();

        $this->assertSame(1, $callCount, 'setSveaCheckoutSequence was not called exactly once with 4');
    }

    public function testGetSessionLifetimeSecondsReturnsDefaultValue(): void
    {
        $this->assertSame(172800, $this->reference->getSessionLifetimeSeconds());
    }

    public function testCustomSessionLifetimePassedViaConstructorIsUsed(): void
    {
        $reference = new CheckoutOrderNumberReference($this->session, 3600);

        $this->assertSame(3600, $reference->getSessionLifetimeSeconds());
    }

    public function testCustomSessionLifetimeIsUsedInPaymentIsExpiredCalculation(): void
    {
        $reference = new CheckoutOrderNumberReference($this->session, 60);

        // Created more than 60 seconds ago — must be considered expired
        $payment = $this->makePaymentWithCreatedAt(time() - 120);
        $quote = $this->makeQuoteWithPayment($payment);
        $reference->setQuote($quote);

        $this->assertTrue($reference->paymentIsExpired());
    }

    public function testSetQuoteReturnsSelf(): void
    {
        $quote = $this->makeQuote();

        $result = $this->reference->setQuote($quote);

        $this->assertSame($this->reference, $result);
    }

    // --- Helpers ---

    /**
     * Plain Quote mock — all DataObject magic methods (getData/setData etc.) work as real implementations.
     */
    private function makeQuote(): Quote&MockObject
    {
        return $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    /**
     * Quote mock with getReservedOrderId pre-configured; reserveOrderId is a no-op.
     */
    private function makeQuoteWithReservedOrderId(string $reservedOrderId): Quote&MockObject
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getReservedOrderId', 'reserveOrderId'])
            ->getMock();

        $quote->method('getReservedOrderId')->willReturn($reservedOrderId);

        return $quote;
    }

    private function makePaymentWithCreatedAt(int $timestamp): Payment&MockObject
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')
            ->with('svea_created_at')
            ->willReturn($timestamp);

        return $payment;
    }

    private function makeQuoteWithPayment(Payment&MockObject $payment): Quote&MockObject
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();

        $quote->method('getPayment')->willReturn($payment);

        return $quote;
    }

    /**
     * Configure the session mock to return a specific sequence value from getSveaCheckoutSequence.
     * Uses __call interception since getSveaCheckoutSequence is a magic method on Session.
     */
    private function stubSessionSequence(int $sequence): void
    {
        $this->session->method('__call')
            ->willReturnCallback(static function (string $method) use ($sequence) {
                if ($method === 'getSveaCheckoutSequence') {
                    return $sequence;
                }
                return null;
            });
    }
}
