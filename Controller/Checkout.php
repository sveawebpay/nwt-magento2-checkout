<?php

namespace Svea\Checkout\Controller;

use Magento\Checkout\Controller\Action;
use Magento\Customer\Api\AccountManagementInterface;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Svea\Checkout\Model\Checkout as SveaCheckout;
use Svea\Checkout\Model\CheckoutContext as SveaCheckoutCOntext;

abstract class Checkout extends Action
{

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonResultFactory;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /** @var SveaCheckout $sveaCheckout */
    protected $sveaCheckout;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /** @var SveaCheckoutCOntext $sveaCheckoutContext */
    protected $sveaCheckoutContext;

    /** @var \Magento\Quote\Model\QuoteFactory $quoteFactory */
    protected $quoteFactory;

    /** @var \Svea\Checkout\Api\Data\PushInterfaceFactory $pushInterfaceFactory */
    protected $pushInterfaceFactory;

    /** @var \Svea\Checkout\Model\PushRepositoryFactory $pushRepositoryFactory */
    protected $pushRepositoryFactory;

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
        SveaCheckoutCOntext $sveaCheckoutContext

    ) {
        $this->sveaCheckout = $sveaCheckout;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->quoteFactory = $quoteFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager= $storeManager;
        $this->sveaCheckoutContext = $sveaCheckoutContext;

        $this->pushInterfaceFactory = $pushInterfaceFactory;
        $this->pushRepositoryFactory = $pushRepositoryFactory;


        parent::__construct(
            $context,
            $customerSession,
            $customerRepository,
            $accountManagement
        );

        $sveaCheckoutContext->getSveaShippingInfoService()->setExcludeSveaShipping(false);
    }

    /**
     * @return SveaCheckout
     */
    public function getSveaCheckout()
    {
        return $this->sveaCheckout;
    }

    protected function getCheckoutSession()
    {
        return $this->checkoutSession;
    }

    /**
     * Validate ajax request and redirect on failure
     *
     * @return bool
     */
    protected function ajaxRequestAllowed()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return false;
        }

        //check if quote was changed
        $ctrlkey    = (string)$this->getRequest()->getParam('ctrlkey');
        if (!$ctrlkey) {
            return false; //do not check
        }

        //check if cart was updated
        $currkey    = $this->getSveaCheckout()->getQuoteSignature();
        if ($currkey != $ctrlkey) {
            $response = [
                'reload'   => 1,
                'messages' =>(string)__('The cart was updated (from another location), reloading the checkout, please wait...')
            ];
            $this->messageManager->addErrorMessage(__('The requested changes were not applied. The cart was updated (from another location), please review the cart.'));
            $this->getResponse()->setBody(json_encode($response));
            return true;
        }

        return false;
    }
}
