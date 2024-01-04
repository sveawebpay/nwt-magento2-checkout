<?php declare(strict_types=1);

namespace Svea\Checkout\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * A model to help separate different Svea orders in the same quote
 *
 * @method self setQuoteId(int $quoteId)
 * @method int getQuoteId()
 * @method self setStoreId(int $storeId)
 * @method int getStoreId()
 * @method self setSveaOrderId(string $sveaOrderId)
 * @method string getSveaOrderId()
 * @method self setSveaClientOrderId(string $sveaClientOrderId)
 * @method string getSveaClientOrderId()
 * @method self setCountryId(string $countryId)
 * @method string getCountryId()
 * @method self setRecurring(bool $recurring)
 * @method bool getRecurring()
 */
class Session extends AbstractModel
{
   /**
    * @inheritDoc
    */
    protected function _construct()
    {
        $this->_init(ResourceModel\Session::class);
    }
}
