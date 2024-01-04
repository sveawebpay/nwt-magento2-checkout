<?php declare(strict_types=1);

namespace Svea\Checkout\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Session extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('svea_checkout_session', 'entity_id');
    }

    /**
     * Loads a session, if one exists, by unique identifiers
     *
     * @param \Svea\Checkout\Model\Session $session
     * @param integer $quoteId
     * @param string $countryId
     * @param boolean $recurring
     * @return void
     */
    public function loadByIdentifiers(
        \Svea\Checkout\Model\Session $session,
        int $quoteId,
        string $countryId,
        bool $recurring = false
    ): void {
        $connection = $this->getConnection();
        $select = $connection->select()->from(
            $this->getMainTable()
        )->where(
            'quote_id = ?',
            $quoteId
        )->where(
            'country_id = ?',
            $countryId
        )->where(
            'recurring = ?',
            $recurring
        );

        $data = $connection->fetchRow($select);
        if (empty($data)) {
            $session->setQuoteId($quoteId);
            $session->setCountryId($countryId);
            $session->setRecurring($recurring);
            return;
        }

        $this->load($session, $data['entity_id']);
    }
}
