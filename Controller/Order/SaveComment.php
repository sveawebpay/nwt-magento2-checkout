<?php

namespace Svea\Checkout\Controller\Order;

/**
 * Class SaveComment
 * @package Svea\Checkout\Controller\Order
 */
class SaveComment extends Update
{

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {

        try {
            //init/check checkout
            $comment = $this->getRequest()->getPost('svea_customer_comment', '');
            $quote = $this->getSveaCheckout()->getQuote();
            $quote->setCustomerNote($comment)->setCustomerNoteNotify(false);
            $this->sveaCheckoutContext->getQuoteResource()->save($quote);

        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                $e->getMessage()
            );
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('We can\'t update your comment.')
            );
        }
        $this->_sendResponse('comment', $updateCheckout = false);
    }

}

