<?php

namespace Svea\Checkout\Controller\Order;

use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\CheckoutException;

abstract class Update extends \Svea\Checkout\Controller\Checkout
{
    //ajax updates
    protected function _sendResponse($blocks = null, $updateCheckout = true)
    {
        $response = ['reload' => false];

        //reload the blocks even we have an error
        if (is_null($blocks)) {
            $blocks = ['shipping_method','cart','coupon','messages', 'svea','newsletter'];
        } elseif ($blocks) {
            $blocks = (array)$blocks;
        } else {
            $blocks = [];
        }

        if (!in_array('messages', $blocks)) {
            $blocks[] = 'messages';
        }

        $shouldUpdateSvea = false;
        if ($updateCheckout) {
            $key = array_search('svea', $blocks);
            if ($key !== false) {
                $shouldUpdateSvea = true;
                unset($blocks[$key]); //this will be set later
            }
        }

        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);

        if ($updateCheckout) {  //if blocks contains only "messages" do not update
            try {
                $checkout = $checkout->initCheckout();

                //set new quote signature
                $response['ctrlkey'] = $checkout->getQuoteSignature();

                if ($shouldUpdateSvea) {
                    //update svea iframe
                    $response['ctrlkey'] = $this->sendUpdateRequest();
                }
            } catch (CheckoutException $e) {
                if ($this->getSveaCheckout()->getHelper()->isTestMode()) {
                    $this->messageManager->addExceptionMessage(
                        $e,
                        $e->getMessage()
                    );
                }

                if ($e->isReload()) {
                    $response['reload'] = true;
                    $response['messages'] = $e->getMessage();
                    $this->messageManager->addNoticeMessage($e->getMessage());
                } elseif ($e->getRedirect()) {
                    $response['redirect'] = $e->getRedirect();
                    $response['messages'] = $e->getMessage();
                    $this->messageManager->addErrorMessage($e->getMessage());
                } else {
                    $this->messageManager->addErrorMessage($e->getMessage());
                }
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                //do nothing, we will just show the message
                $this->messageManager->addErrorMessage($e->getMessage() ? $e->getMessage() : __('Cannot update checkout (%1)', get_class($e)));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage() ? $e->getMessage() : __('Cannot initialize Svea Checkout (%1)', get_class($e)));
            }

            if (!empty($response['redirect'])) {
                if ($this->getRequest()->isXmlHttpRequest()) {
                    $response['redirect'] = $this->storeManager->getStore()->getUrl($response['redirect']);
                    $this->getResponse()->setBody(json_encode($response));
                } else {
                    $this->_redirect($response['redirect']);
                }
                return;
            }

            /*
            if($shouldUpdateSvea &&  (empty($updatedSveaPaymentId) || $updatedSveaPaymentId != $sveaPaymentId)) {
                //another svea order was created, add svea block (need to be reloaded)
                $blocks[] = 'svea';
                //if svea have same location, we will use svea api resume
            }
            */
        }

        if (!$this->getRequest()->isXmlHttpRequest()) {
            $this->_redirect('*');
            return;
        }

        $response['ok'] = true;  //to avoid empty response
        if ($blocks && !$response['reload']) {
            $this->_view->loadLayout('svea_checkout_order_update');
            foreach ($blocks as $id) {
                $name = "svea_checkout.{$id}";
                $block = $this->_view->getLayout()->getBlock($name);
                if ($block) {
                    $response['updates'][$id] = $block->toHtml();
                }
            }
        }

        if (in_array('svea_snippet', $blocks)) {
            $response['updates']['svea'] = '<div id="svea-checkout_svea">' . $checkout->getSveaPaymentHandler()->getIframeSnippet() . '</div>';
        }

        $this->getResponse()->setBody(json_encode($response));
    }

    /**
     * Sends the update to Svea and returns the new quote signature.
     * On errors, throws a CheckoutException with specific configuration based on the response code
     *
     * @return string
     * @throws CheckoutException
     */
    private function sendUpdateRequest(): string
    {
        $checkout = $this->getSveaCheckout();
        $sveaPaymentId = $this->getCheckoutSession()->getSveaOrderId();

        try {
            $checkout->updateSveaPayment($sveaPaymentId);
        } catch (ClientException $e) {
            $httpCode = (int)$e->getHttpStatusCode();
            if ($httpCode > 500 || in_array($httpCode, [401, 403])) {
                $this->throwCheckoutException('Svea Checkout could not be reached!', false, $e);
            }

            if (in_array($httpCode, [400, 404])) {
                $this->throwCheckoutException('Checkout needs to reload', true, $e);
            }
        }
        return $checkout->getQuoteSignature();
    }

    /**
     * Throw an appropriate Checkout Exception based on the provided params
     *
     * @param string $message
     * @param bool $reload If true, reload page. If false, redirect to cart.
     * @param \Exception $exception
     * @throws CheckoutException
     */
    private function throwCheckoutException(string $message, $reload = false, ?\Exception $exception = null): void
    {
        $testMode = $this->getSveaCheckout()->getHelper()->isTestMode();
        if (($exception instanceof \Exception) && $testMode) {
            $message .= sprintf(' Error: %s', $exception->getMessage());
        }

        $redirect = $reload ? '*/*' : 'checkout/cart';
        throw new CheckoutException(__($message), $redirect);
    }
}
