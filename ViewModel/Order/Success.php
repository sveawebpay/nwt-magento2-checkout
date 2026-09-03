<?php declare(strict_types=1);

namespace Svea\Checkout\ViewModel\Order;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order as SalesOrder;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;
use Svea\Checkout\Helper\GiftCard as GiftCardHelper;
use Svea\Checkout\Model\CheckoutContext as SveaCheckoutContext;
use Svea\Checkout\ViewModel\Checkout\NonceProvider;

/**
 * View model for the standard order success page Svea additions.
 *
 * Reads the Svea order id stored in the checkout session by the Confirmation
 * controller, fetches the iframe snippet, exposes the placed order, items,
 * and gift cards. Clears the Svea session key on first read so the snippet
 * is not surfaced again on a refresh.
 */
class Success implements ArgumentInterface
{
    private CheckoutSession $checkoutSession;

    private HttpContext $httpContext;

    private OrderRepository $orderRepository;

    private GiftCardHelper $giftCardHelper;

    private NonceProvider $nonceProvider;

    private SveaCheckoutContext $sveaCheckoutContext;

    private UrlInterface $urlBuilder;

    private LoggerInterface $logger;

    private bool $sveaOrderLoaded = false;

    private ?string $iframeSnippet = null;

    private ?SalesOrder $order = null;

    public function __construct(
        CheckoutSession $checkoutSession,
        HttpContext $httpContext,
        OrderRepository $orderRepository,
        GiftCardHelper $giftCardHelper,
        NonceProvider $nonceProvider,
        SveaCheckoutContext $sveaCheckoutContext,
        UrlInterface $urlBuilder,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->httpContext = $httpContext;
        $this->orderRepository = $orderRepository;
        $this->giftCardHelper = $giftCardHelper;
        $this->nonceProvider = $nonceProvider;
        $this->sveaCheckoutContext = $sveaCheckoutContext;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
    }

    /**
     * @return integer|null
     */
    public function getRealOrderId(): ?int
    {
        $id = $this->checkoutSession->getLastOrderId();
        return $id ? (int) $id : null;
    }

    /**
     * @return string|null
     */
    public function getIncrementId(): ?string
    {
        $id = $this->checkoutSession->getLastRealOrderId();
        return $id ? (string) $id : null;
    }

    /**
     * @return SalesOrder|null
     */
    public function getOrder(): ?SalesOrder
    {
        if ($this->order === null) {
            $orderId = $this->getRealOrderId();
            if (!$orderId) {
                return null;
            }
            try {
                /** @var SalesOrder $order */
                $order = $this->orderRepository->get($orderId);
                $this->order = $order;
            } catch (\Exception $e) {
                $this->logger->error(
                    'Success ViewModel: could not load order ' . $orderId . ' — ' . $e->getMessage()
                );
                return null;
            }
        }

        return $this->order;
    }

    /**
     * @return \Magento\Sales\Model\Order\Item[]
     */
    public function getOrderItems(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }

        return $order->getAllVisibleItems() ?? [];
    }

    /**
     * @return string
     */
    public function getIframeSnippet(): string
    {
        $this->loadSveaOrder();
        return $this->iframeSnippet ?? '';
    }

    /**
     * @return array
     */
    public function getGiftCards(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }

        $giftCards = $order->getData('gift_cards');
        if (!$giftCards) {
            return [];
        }

        return $this->giftCardHelper->getGiftCards($giftCards);
    }

    /**
     * @return string|null
     */
    public function getNonce(): ?string
    {
        return $this->nonceProvider->generateNonce();
    }

    /**
     * Format an order item quantity for display.
     *
     * Returns a plain integer string when the quantity has no fractional part,
     * otherwise a decimal string with trailing zeros stripped (e.g. "2.5", "1.25").
     */
    public function formatQty($qty): string
    {
        $qty = (float) $qty;
        if ($qty == (int) $qty) {
            return (string) (int) $qty;
        }

        return rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
    }

    /**
     * @return boolean
     */
    public function getCanViewOrder(): bool
    {
        return (bool) $this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_AUTH);
    }

    /**
     * @return string
     */
    public function getViewOrderUrl(): string
    {
        $orderId = $this->getRealOrderId();
        if (!$orderId) {
            return '';
        }
        return $this->urlBuilder->getUrl('sales/order/view', ['order_id' => $orderId]);
    }

    /**
     * @return string
     */
    public function getContinueShoppingUrl(): string
    {
        return $this->urlBuilder->getUrl();
    }

    /**
     * Read sveaOrderId from session, fetch the iframe snippet once, then clear the session key.
     *
     * The session is cleared here (not in a controller plugin) because the standard
     * success controller renders its result page after execute() returns, so an
     * afterExecute plugin would wipe sveaOrderId before the template runs.
     */
    private function loadSveaOrder(): void
    {
        if ($this->sveaOrderLoaded) {
            return;
        }
        $this->sveaOrderLoaded = true;

        $sveaOrderId = $this->checkoutSession->getSveaOrderId();
        if (!$sveaOrderId) {
            return;
        }

        $this->checkoutSession->unsSveaOrderId();

        try {
            $orderHandler = $this->sveaCheckoutContext->getSveaOrderHandler();
            $payment = $orderHandler->loadSveaOrderById($sveaOrderId, true);
            if ($payment->getStatus() !== 'Created') {
                $this->iframeSnippet = $orderHandler->getIframeSnippet();
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Success ViewModel: could not load Svea order ' . $sveaOrderId . ' — ' . $e->getMessage()
            );
        }
    }
}
