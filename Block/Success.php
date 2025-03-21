<?php

namespace Svea\Checkout\Block;

/**
 * Class Success
 * @package Svea\Checkout\Block
 */
class Success extends \Magento\Checkout\Block\Onepage\Success
{

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    protected $orderRepository;

    /** @var $iframeSnippet string */
    protected $iframeSnippet;
    /** @var \Svea\Checkout\Helper\GiftCard */
    protected $giftcardHelper;

    /**
     * Success constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param Order\Config $orderConfig
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param OrderInterface $orderInterface
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Http\Context $httpContext,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Svea\Checkout\Helper\GiftCard $giftCardHelper,
        array $data = []
    )
    {
        parent::__construct(
            $context,
            $checkoutSession,
            $orderConfig,
            $httpContext,
            $data
        );
        $this->orderRepository = $orderRepository;
        $this->giftcardHelper = $giftCardHelper;

    }

    /**
     * @return mixed
     */
    public function getRealOrderId()
    {
        return $this->_checkoutSession->getLastOrderId();
    }

    /**
     * @param $orderId
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getOrderById($orderId)
    {
        return $this->orderRepository->get($orderId);
    }

    /**
     * @param $orderId
     * @return mixed
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getOrderItems($orderId)
    {
        return $this->getOrderById($orderId)->getAllVisibleItems();
    }

    public function getIframeSnippet()
    {
        return $this->iframeSnippet;
    }

    public function setIframeSnippet($snippet)
    {
        $this->iframeSnippet = $snippet;
    }

    /**
     * @param $order
     * @return array|\Magento\GiftCardAccount\Model\GiftCard[]
     */
    public function getGiftCards($order): array
    {
        if (!$order->getGiftCards()) {
            return [];
        }
        return $this->giftcardHelper->getGiftCards($order->getGiftCards());
    }
}

