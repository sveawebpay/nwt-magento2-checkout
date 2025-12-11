<?php
namespace Svea\Checkout\Plugin\Store;

use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * Plugin to reset svea order on store switch
 */
class SwitchActionPlugin
{
    
    protected $checkoutSession;
    protected $cartRepository;
    public function __construct(
        Session $checkoutSession,
        CartRepositoryInterface $cartRepository
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
    }
    public function afterExecute(\Magento\Store\Controller\Store\SwitchAction $subject, $result)
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote && $quote->getId()) {
            $this->cartRepository->delete($quote);
            $this->checkoutSession->clearQuote();
        }
        return $result;
    }
}