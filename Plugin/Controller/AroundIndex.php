<?php declare(strict_types=1);

namespace Svea\Checkout\Plugin\Controller;

use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\ResultFactory;

class AroundIndex
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var \Magento\Framework\App\Response\RedirectInterface
     */
    private $redirect;

    /**
     * @var \Magento\Checkout\Model\Cart
     */
    private $cart;

    /**
     * BeforeIndex constructor.
     *
     * @param Session $session
     * @param ResultFactory $resultFactory
     * @param \Magento\Framework\App\Response\RedirectInterface $redirect
     */
    public function __construct(
        ResultFactory $resultFactory,
        \Magento\Framework\App\Response\RedirectInterface $redirect,
        \Magento\Checkout\Model\Cart $cart
    ) {
        $this->redirect             = $redirect;
        $this->resultFactory        = $resultFactory;
        $this->cart = $cart;
    }

    /**
     * @param \Svea\Checkout\Controller\Index\Index $controller
     * @param callable $exec
     *
     * @return \Magento\Framework\Controller\AbstractResult
     */
    public function aroundExecute(\Svea\Checkout\Controller\Index\Index $controller, callable $exec)
    {
        if ($this->checkZeroCartPrice()) {
            return $this->generateCheckoutRedirect();
        }

        return $exec();
    }

    /**
     * @return bool
     */
    private function checkZeroCartPrice()
    {
        $quote = $this->cart->getQuote();

        return $quote->getSubtotalWithDiscount() == 0;
    }

    /**
     * Redirect to standard Magento Checkout
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function generateCheckoutRedirect()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setUrl('/checkout');
    }
}
