<?php
namespace Svea\Checkout\Block\Checkout;

use Svea\Checkout\Api\GetQtyIncrementConfigInterface;
use Magento\Quote\Model\Quote\Item;

class Cart extends \Magento\Checkout\Block\Cart\Totals
{
    /**
     * @var \Magento\Sales\Model\Order\Address
     */
    protected $_address;

    /**
     * Return review shipping address
     *
     * @return \Magento\Sales\Model\Order\Address
     */
    public function getAddress()
    {
        if (empty($this->_address)) {
            $this->_address = $this->getQuote()->getShippingAddress();
        }
        return $this->_address;
    }

    /**
     * Return review quote totals
     *
     * @return array
     */
    public function getTotals()
    {
        return $this->getQuote()->getTotals();
    }



    /**
     * @var \Svea\Checkout\Helper\Data
     */
    protected $helper;

    /**
     * @var GetQtyIncrementConfigInterface
     */
    private $getQtyIncrement;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Config $salesConfig,
        \Svea\Checkout\Helper\Data $helper,
        GetQtyIncrementConfigInterface $getQtyIncrement,
        array $layoutProcessors = [],
        array $data = []
    ) {
        $this->helper = $helper;
        $this->getQtyIncrement = $getQtyIncrement;
        parent::__construct($context, $customerSession, $checkoutSession,$salesConfig, $layoutProcessors,$data);
    }

    public function showCouponCode()
    {

        return $this->helper->showCouponLayout();
    }

    /**
     * Get qty increment configuration and set on item object
     * Then get item row html
     *
     * @param \Magento\Quote\Model\Quote\Item $item
     * @return  string
     */
    public function getItemHtml(Item $item)
    {
        $qtyIncrements = 1;
        try {
            $qtyIncrement = $this->getQtyIncrement->execute($item->getProduct());
            $qtyIncrements = ($qtyIncrement->isEnableQtyIncrements()) ? $qtyIncrement->getQtyIncrements() : 1;
        } finally {
            $item->setData('qty_increments', $qtyIncrements);
            return parent::getItemHtml($item);
        }
    }
}
