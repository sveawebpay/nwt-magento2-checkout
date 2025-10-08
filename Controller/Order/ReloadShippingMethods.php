<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Model\Quote;
use Magento\Checkout\Model\Session;
use Magento\Framework\Module\Manager;

class ReloadShippingMethods extends Action
{

    /**
     * @var PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var JsonFactory
     */
    protected $_resultJsonFactory;

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var Manager
     */
    private Manager $moduleManager;

    /**
     * View constructor.
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param Session $checkoutSession
     * @param Manager $moduleManager
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        Session $checkoutSession,
        Manager $moduleManager
    ) {
        $this->_resultPageFactory = $resultPageFactory;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->moduleManager = $moduleManager;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->_resultJsonFactory->create();
        $resultPage = $this->_resultPageFactory->create();
        $block = $resultPage->getLayout()
            ->createBlock(\Svea\Checkout\Block\Checkout::class);

        $block->setTemplate('Svea_Checkout::checkout/shipping/method.phtml');

        if ($this->moduleManager->isEnabled('Hyva_Theme')) {
            // Using object manager here instead of DI since we can't be sure if Hyva theme is installed and the class exists
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $hyvaThemeService = $objectManager->get(\Hyva\Theme\Service\CurrentTheme::class);

            if ($hyvaThemeService && $hyvaThemeService->isHyva()) {
                $block->setTemplate('Svea_Checkout::hyva_compatibility/checkout/shipping/method.phtml');
                $this->getResponse()->setNoCacheHeaders(); // Disable the cache here, block should not be cached
            }
        }

        $output = $block->toHtml();

        $quote = $block->getQuote();
        $result->setData([
            'output' => $output,
            'requiredShippingAction' => $this->getRequiredShippingAction($quote)
        ]);
        return $result;
    }

    /**
     * Checks for required shipping method action from customer
     *
     * @param Quote $quote
     * @return int
     *  - 0: no action required
     *  - 1: must select initial shipping rate
     *  - 2: must change previously selected shipping rate
     */
    private function getRequiredShippingAction(Quote $quote): int
    {
        if ($quote->getIsVirtual()) {
            return 0;
        }

        $predefinedRequiredShippingAction = $this->checkoutSession->getPredefinedRequiredShippingAction();
        if ($predefinedRequiredShippingAction) {
            $this->checkoutSession->unsPredefinedRequiredShippingAction();
            return $predefinedRequiredShippingAction;
        }

        $address = $quote->getShippingAddress();
        if (!$address->getShippingMethod()) {
            return 1;
        }

        $groups = $address->getGroupedAllShippingRates();
        if ($groups && $address) {
            // determine current selected code
            foreach ($groups as $rates) {
                foreach ($rates as $rate) {
                    if ($address->getShippingMethod() === $rate->getCode()) {
                        return 0;
                    }
                }
            }

            return 2;
        }

        return 0;
    }
}
