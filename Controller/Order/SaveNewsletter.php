<?php

namespace Svea\Checkout\Controller\Order;

class SaveNewsletter extends \Svea\Checkout\Controller\Order\Update
{

    /**
     * Save newsletter subscription action
     */

    /**
     * @return void
     */
    public function execute()
    {
        if ($this->ajaxRequestAllowed()) {
            return;
        }
        try {
            $quote = $this->getSveaCheckout()->getQuote();

            $newsletter = (int)$this->getRequest()->getParam('newsletter', 0);
            if ($quote->getPayment()) {
                $payment = $quote->getPayment();
                $payment->setAdditionalInformation('svea_checkout_newsletter', $newsletter > 0 ? 1 : -1);
                $this->sveaCheckoutContext->getPaymentResource()->save($payment);
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    $e->getMessage()
                );
        } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('We can\'t update your subscription.')
                );
        }
        $this->_sendResponse('newsletter',$updateCheckout = false);
    }

}

