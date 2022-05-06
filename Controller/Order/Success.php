<?php

namespace Svea\Checkout\Controller\Order;

class Success extends \Svea\Checkout\Controller\Checkout
{
    public function execute()
    {
        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);
        $session = $this->getCheckoutSession();


        if (!$this->sessionIsValid()) {
            $checkout->getLogger()->error("Success Page: Success Validation invalid.");
            $checkout->getLogger()->error(json_encode($session->getData()));
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }


        $lastOrderId = $session->getLastOrderId();
        $sveaOrderId = $session->getSveaOrderId();

        $session->clearQuote(); //destroy quote, unset QuoteId && LastSuccessQuoteId
        $session->unsSveaOrderId(); // destroy session

        $iframeHtml = "";
        if ($sveaOrderId) {

            try {
                $payment = $checkout->getSveaPaymentHandler()->loadSveaOrderById($sveaOrderId, true);
                if ($payment->getStatus() !== "Created") {
                    $iframeHtml = $checkout->getSveaPaymentHandler()->getIframeSnippet();
                }
            } catch (\Exception $e) {
                $this->sveaCheckout->getLogger()->error("Success Page: Could not load svea order...");
                // shall we ignore it?
            }
        } else {
            $this->sveaCheckout->getLogger()->error("Success Page: Svea order not set...");
        }

        // need to be BEFORE event dispach (GA need to have layout loaded, to set the orderIds on the block)
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__("Order received"));
        $resultPage->getLayout()->getBlock('svea_checkout_success')->setIframeSnippet($iframeHtml);

        $this->_eventManager->dispatch(
            'checkout_onepage_controller_success_action',
            [
                'order_ids' => [$lastOrderId],
                'order' => $session->getLastRealOrder()
            ]
        );

        return $resultPage;
    }

    public function sessionIsValid()
    {
        if (!$this->getCheckoutSession()->getLastSuccessQuoteId()) {
            return false;
        }

        if (!$this->getCheckoutSession()->getLastOrderId()) {
            return false;
        }
        return true;
    }
}
