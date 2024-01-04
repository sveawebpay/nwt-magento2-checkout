<?php
namespace Svea\Checkout\Controller\Index;

use Svea\Checkout\Controller\Checkout;
use Svea\Checkout\Model\CheckoutException;

class Index extends Checkout
{

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|\Magento\Framework\View\Result\Page|void
     */
    public function execute()
    {
        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);

        try {
            $checkout->initCheckout(); // magento business logic
            $checkout->initSveaCheckout(); // handles magento and SVEA business logic
        } catch (CheckoutException $e) {
            if ($e->isReload()) {
                $this->messageManager->addNoticeMessage($e->getMessage());
            } else {
                $this->messageManager->addErrorMessage($e->getMessage());
            }

            if ($e->getRedirect()) {
                $this->_redirect($e->getRedirect());
                return;
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage() ? $e->getMessage() : __('Cannot initialize Svea Checkout (%1)', get_class($e)));
            $checkout->getLogger()->error("[" . __METHOD__ . "] (" . get_class($e) . ") {$e->getMessage()} ");
            $checkout->getLogger()->critical($e);

            $this->_redirect('checkout/cart');
            return;
        }

        $resultPage = $this->createLayoutPage();
        $resultPage->getConfig()->getTitle()->set(__('Svea Checkout'));
        $resultPage->getLayout()->getBlock('svea_checkout.svea')->setIframeSnippet($checkout->getSveaPaymentHandler()->getIframeSnippet());

        return $resultPage;
    }


    /**
     * Plugin extension point for extending the layout
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function createLayoutPage()
    {
        return $this->resultPageFactory->create();
    }
}
