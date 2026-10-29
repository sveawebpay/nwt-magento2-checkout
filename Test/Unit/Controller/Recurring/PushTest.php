<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Controller\Recurring;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Controller\Recurring\Push;
use Svea\Checkout\Model\Client\Api\TokenClient;
use Svea\Checkout\Model\RecurringInfo;
use Svea\Checkout\Service\SveaRecurringInfo;

/**
 * SC-304: the recurring push only calls the Svea API for our own subscriptions, with the
 * subscription's store credentials, and does not echo order data.
 */
class PushTest extends TestCase
{
    private const SVEA_ORDER_ID = 9001;
    private const TOKEN = '1c3b4b8f-6a2d-4e5f-9a7b-0c1d2e3f4a5b';
    private const ORIGINAL_ORDER_ID = 100;
    private const SUBSCRIPTION_STORE_ID = 2;
    private const REQUEST_STORE_ID = 5;

    private TokenClient&MockObject $tokenClient;
    private RecurringInfoRepositoryInterface&MockObject $recurringInfoRepo;
    private Raw&MockObject $result;

    protected function setUp(): void
    {
        $this->tokenClient = $this->createMock(TokenClient::class);
        $this->recurringInfoRepo = $this->createMock(RecurringInfoRepositoryInterface::class);
        $this->result = $this->createMock(Raw::class);
        $this->result->method('setHttpResponseCode')->willReturnSelf();
        $this->result->expects($this->never())->method('setContents');
    }

    public static function malformedTokenProvider(): array
    {
        return [
            'path traversal' => ['../../api/orders/1'],
            'slash' => [self::TOKEN . '/orders/1'],
            'query' => [self::TOKEN . '?x=1'],
            'empty' => [''],
        ];
    }

    #[DataProvider('malformedTokenProvider')]
    public function testRefusesMalformedTokenWithoutLookup(string $token): void
    {
        $this->recurringInfoRepo->expects($this->never())->method('getByRecurringToken');
        $this->tokenClient->expects($this->never())->method('getTokenOrder');
        $this->result->expects($this->once())->method('setHttpResponseCode')->with(404);

        $this->makePush($token)->execute();
    }

    public function testRefusesTokenThatIsNotOurSubscription(): void
    {
        // The repository returns an empty model when nothing matches.
        $this->recurringInfoRepo->method('getByRecurringToken')->willReturn($this->makeRecurringInfo(null));
        $this->tokenClient->expects($this->never())->method('resetCredentials');
        $this->tokenClient->expects($this->never())->method('getTokenOrder');
        $this->result->expects($this->once())->method('setHttpResponseCode')->with(404);

        $this->makePush(self::TOKEN)->execute();
    }

    public function testChecksOwnSubscriptionWithItsStoreCredentials(): void
    {
        $this->recurringInfoRepo->method('getByRecurringToken')
            ->with(self::TOKEN)
            ->willReturn($this->makeRecurringInfo(self::TOKEN));
        $this->tokenClient->expects($this->once())->method('resetCredentials')->with(self::SUBSCRIPTION_STORE_ID);
        $this->tokenClient->expects($this->once())->method('getTokenOrder')->with(self::SVEA_ORDER_ID, self::TOKEN);
        $this->result->expects($this->once())->method('setHttpResponseCode')->with(200);

        $this->assertSame($this->result, $this->makePush(self::TOKEN)->execute());
    }

    private function makePush(string $token): Push
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnMap([
            ['sid', null, (string)self::SVEA_ORDER_ID],
            ['token', null, $token],
            ['storeid', null, (string)self::REQUEST_STORE_ID],
        ]);

        $resultFactory = $this->createStub(RawFactory::class);
        $resultFactory->method('create')->willReturn($this->result);

        $originalOrder = $this->createStub(Order::class);
        $originalOrder->method('getStoreId')->willReturn(self::SUBSCRIPTION_STORE_ID);
        $recurringInfoService = $this->createStub(SveaRecurringInfo::class);
        $recurringInfoService->method('loadOrder')->willReturnMap([[self::ORIGINAL_ORDER_ID, $originalOrder]]);

        return new Push(
            $request,
            $this->tokenClient,
            $resultFactory,
            $this->recurringInfoRepo,
            $recurringInfoService
        );
    }

    private function makeRecurringInfo(?string $token): RecurringInfo
    {
        $recurringInfo = $this->getMockBuilder(RecurringInfo::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $recurringInfo->setData('recurring_token', $token);
        $recurringInfo->setData('original_order_id', self::ORIGINAL_ORDER_ID);

        return $recurringInfo;
    }
}
