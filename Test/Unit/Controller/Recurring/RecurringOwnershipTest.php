<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Controller\Recurring;

use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Controller\AbstractController\OrderViewAuthorizationInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Controller\Recurring\Cancel;
use Svea\Checkout\Controller\Recurring\Changepaymentmethod;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Model\Client\Api\TokenClient;
use Svea\Checkout\Model\RecurringInfo;
use Svea\Checkout\Service\SveaRecurringInfo;

/**
 * SC-301: the recurring token in the URL must belong to the subscription of the order
 * the customer is allowed to view.
 */
class RecurringOwnershipTest extends TestCase
{
    private const ORDER_ID = 100;
    private const OWN_TOKEN = 'own-token';
    private const OTHER_TOKEN = 'other-customers-token';

    private Http&Stub $request;
    private OrderViewAuthorizationInterface&Stub $orderAuthorization;
    private ManagerInterface&MockObject $messageManager;
    private SveaRecurringInfo&MockObject $recurringInfoService;
    private RecurringInfoRepositoryInterface&MockObject $recurringInfoRepo;
    private RedirectFactory&Stub $redirectFactory;
    private Redirect&Stub $redirect;
    private bool $subscriptionStubbed = false;

    protected function setUp(): void
    {
        $this->request = $this->createStub(Http::class);
        $this->orderAuthorization = $this->createStub(OrderViewAuthorizationInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->recurringInfoService = $this->createMock(SveaRecurringInfo::class);
        $this->recurringInfoRepo = $this->createMock(RecurringInfoRepositoryInterface::class);

        $this->redirect = $this->createStub(Redirect::class);
        $this->redirect->method('setPath')->willReturnSelf();
        $this->redirectFactory = $this->createStub(RedirectFactory::class);
        $this->redirectFactory->method('create')->willReturn($this->redirect);

        $order = $this->createStub(Order::class);
        $order->method('getEntityId')->willReturn(self::ORDER_ID);
        $this->recurringInfoService->method('loadOrder')->with(self::ORDER_ID)->willReturn($order);

        $this->recurringInfoRepo->expects($this->never())->method('getByRecurringToken');
    }

    public function testCancelRefusesTokenOfAnotherSubscription(): void
    {
        $this->stubRequest(self::OTHER_TOKEN);
        $this->stubCanView(true);
        $this->recurringInfoService->expects($this->never())->method('cancel');
        $this->recurringInfoRepo->expects($this->never())->method('save');
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->makeCancel()->execute();
    }

    public function testCancelRefusesOrderCustomerCannotView(): void
    {
        $this->stubRequest(self::OWN_TOKEN);
        $this->stubCanView(false);
        $this->recurringInfoService->expects($this->never())->method('cancel');
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->makeCancel()->execute();
    }

    public function testCancelRefusesOrderWithoutSubscription(): void
    {
        // The repository returns an empty model when nothing matches.
        $this->stubSubscriptionToken(null);
        $this->subscriptionStubbed = true;
        $this->stubRequest('');
        $this->stubCanView(true);
        $this->recurringInfoService->expects($this->never())->method('cancel');

        $this->makeCancel()->execute();
    }

    public function testCancelCancelsOwnSubscription(): void
    {
        $this->stubRequest(self::OWN_TOKEN);
        $this->stubCanView(true);
        $this->recurringInfoService->expects($this->once())
            ->method('cancel')
            ->with($this->callback(fn ($info) => $info->getRecurringToken() === self::OWN_TOKEN));
        $this->recurringInfoRepo->expects($this->once())->method('save');
        $this->messageManager->expects($this->once())->method('addSuccessMessage');

        $this->makeCancel()->execute();
    }

    public function testChangePaymentMethodRefusesTokenOfAnotherSubscription(): void
    {
        $this->stubRequest(self::OTHER_TOKEN);
        $this->stubCanView(true);
        $tokenClient = $this->createMock(TokenClient::class);
        $tokenClient->expects($this->never())->method('changePaymentMethod');
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->makeChangePaymentMethod($tokenClient)->execute();
    }

    public function testChangePaymentMethodUsesOwnSubscription(): void
    {
        $this->stubRequest(self::OWN_TOKEN);
        $this->stubCanView(true);
        $tokenClient = $this->createMock(TokenClient::class);
        $tokenClient->expects($this->once())
            ->method('changePaymentMethod')
            ->with(self::OWN_TOKEN)
            ->willReturn('<div></div>');
        $this->messageManager->expects($this->never())->method('addErrorMessage');

        $this->makeChangePaymentMethod($tokenClient)->execute();
    }

    private function makeCancel(): Cancel
    {
        return new Cancel(
            $this->request,
            $this->orderAuthorization,
            $this->messageManager,
            $this->recurringInfoService,
            $this->recurringInfoRepo,
            $this->redirectFactory
        );
    }

    private function makeChangePaymentMethod(TokenClient $tokenClient): Changepaymentmethod
    {
        $config = $this->createStub(Data::class);
        $config->method('getTermsUrl')->willReturn('https://shop.test/terms');
        $pageFactory = $this->createStub(PageFactory::class);
        $pageFactory->method('create')->willReturn($this->createStub(Page::class));

        return new Changepaymentmethod(
            $this->request,
            $this->orderAuthorization,
            $this->messageManager,
            $config,
            $tokenClient,
            $this->recurringInfoService,
            $this->recurringInfoRepo,
            $this->createStub(CspNonceProvider::class),
            $this->redirectFactory,
            $pageFactory
        );
    }

    private function makeRecurringInfo(?string $token): RecurringInfo
    {
        $recurringInfo = $this->getMockBuilder(RecurringInfo::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $recurringInfo->setData('recurring_token', $token);

        return $recurringInfo;
    }

    private function stubSubscriptionToken(?string $token): void
    {
        $this->recurringInfoRepo->method('getByOriginalOrderId')
            ->with(self::ORDER_ID)
            ->willReturn($this->makeRecurringInfo($token));
    }

    private function stubRequest(string $token): void
    {
        if (!$this->subscriptionStubbed) {
            // Unless a test says otherwise, the viewable order's subscription has OWN_TOKEN.
            $this->stubSubscriptionToken(self::OWN_TOKEN);
        }

        $this->request->method('getParam')->willReturnMap([
            ['token', null, $token],
            ['order_id', null, (string)self::ORDER_ID],
        ]);
    }

    private function stubCanView(bool $canView): void
    {
        $this->orderAuthorization->method('canView')->willReturn($canView);
    }
}
