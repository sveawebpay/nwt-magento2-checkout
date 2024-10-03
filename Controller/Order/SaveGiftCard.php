<?php

declare(strict_types=1);

namespace Svea\Checkout\Controller\Order;

use Magento\GiftCardAccount\Api\Exception\TooManyAttemptsException;
use Magento\Checkout\Controller\Action;
use Magento\Customer\Api\AccountManagementInterface;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Svea\Checkout\Helper\GiftCard;
use Svea\Checkout\Model\Checkout as SveaCheckout;
use Svea\Checkout\Model\CheckoutContext as SveaCheckoutCOntext;

class SaveGiftCard extends \Svea\Checkout\Controller\Order\Update
{
    protected $giftcardHelper;
    protected $escaper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        CustomerRepositoryInterface $customerRepository,
        AccountManagementInterface $accountManagement,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Svea\Checkout\Api\Data\PushInterfaceFactory $pushInterfaceFactory,
        \Svea\Checkout\Model\PushRepositoryFactory $pushRepositoryFactory,
        SveaCheckout $sveaCheckout,
        SveaCheckoutCOntext $sveaCheckoutContext,
        \Svea\Checkout\Helper\GiftCard $giftCardHelper,
        \Magento\Framework\Escaper $escaper
    ) {
        parent::__construct(
            $context,
            $customerSession,
            $customerRepository,
            $accountManagement,
            $checkoutSession,
            $storeManager,
            $resultPageFactory,
            $jsonResultFactory,
            $quoteFactory,
            $pushInterfaceFactory,
            $pushRepositoryFactory,
            $sveaCheckout,
            $sveaCheckoutContext
        );
        $this->giftcardHelper = $giftCardHelper;
        $this->escaper = $escaper;
    }

    public function execute()
    {
        if ($this->ajaxRequestAllowed()) {
            return;
        }

        $quote = $this->getSveaCheckout()->getQuote();
        $code = (string)$this->getRequest()->getParam('giftcard_code');
        $this->addGiftcard($code);
        $this->_sendResponse(['cart','coupon','messages','shipping','svea', 'giftcard']);
    }

    /**
     * @param string $code
     */
    protected function addGiftcard(string $code): void
    {
        try {
            $this->giftcardHelper->saveByQuoteId((int)$this->getSveaCheckout()->getQuote()->getId(), $code);
            $this->messageManager->addSuccess(
                __(
                    'Gift Card "%1" was added.',
                    $this->escaper->escapeHtml($code)
                )
            );
        } catch (TooManyAttemptsException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Magento\Framework\Exception\LocalizedException $exception) {
            $this->messageManager->addError($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->messageManager->addExceptionMessage($exception, __('We cannot apply this gift card.'));
        }
    }
}
