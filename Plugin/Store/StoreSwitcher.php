<?php declare(strict_types=1);

namespace Svea\Checkout\Plugin\Store;

use Magento\Store\Model\StoreSwitcher as SubjectStoreSwitcher;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\QuoteRepository;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Model\CheckoutOrderNumberReference;
use Svea\Checkout\Model\SessionFactory as SveaSessionFactory;
use Svea\Checkout\Model\ResourceModel\Session as SveaSessionResource;

class StoreSwitcher
{
    private Data $helper;

    private Session $checkoutSession;

    private CheckoutOrderNumberReference $refHelper;

    private QuoteRepository $quoteRepo;

    private SveaSessionFactory $sveaSessionFactory;

    private SveaSessionResource $sveaSessionResource;

    public function __construct(
        Data $helper,
        Session $checkoutSession,
        CheckoutOrderNumberReference $refHelper,
        QuoteRepository $quoteRepo,
        SveaSessionFactory $sveaSessionFactory,
        SveaSessionResource $sveaSessionResource
    ) {
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        $this->refHelper = $refHelper;
        $this->quoteRepo = $quoteRepo;
        $this->sveaSessionFactory = $sveaSessionFactory;
        $this->sveaSessionResource = $sveaSessionResource;
    }

    /**
     * Unsets session and quote identifiers after store switch if already set
     * This is to ensure Svea order uses proper config and client order ID from current store
     *
     * @param SubjectStoreSwitcher $subject
     * @param string $targetUrl
     * @return void
     */
    public function afterSwitch(
        SubjectStoreSwitcher $subject,
        string $targetUrl,
        StoreInterface $fromStore,
        StoreInterface $targetStore
    ): string {
        if (!$this->helper->isEnabled($fromStore->getId())) {
            return $targetUrl;
        }

        if (!$this->checkoutSession->getQuoteId()) {
            return $targetUrl;
        }

        $sveaOrderId =  (int)$this->refHelper->getSveaOrderId();
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->getReservedOrderId() && !$sveaOrderId) {
            return $targetUrl;
        }

        // Remove identifiers in session and quote
        $quote->setReservedOrderId(null);
        $this->refHelper->unsetSessions(true);
        $this->quoteRepo->save($quote);

        // Delete associated svea session entity
        $sveaSessionModel = $this->sveaSessionFactory->create();
        $this->sveaSessionResource->deleteBySveaOrderId(
            $sveaSessionModel,
            $sveaOrderId
        );
        return $targetUrl;
    }
}
