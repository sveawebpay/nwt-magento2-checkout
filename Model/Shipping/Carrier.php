<?php

namespace Svea\Checkout\Model\Shipping;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Psr\Log\LoggerInterface;
use Svea\Checkout\Service\SveaShippingInfo;

/**
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class Carrier extends AbstractCarrier implements CarrierInterface
{
    const CODE = 'sveanshift';

    const PLACEHOLDER_NAME = 'placeholder_name';

    const PLACEHOLDER_CARRIER = 'placeholder_carrier';

    /**
     * @var ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var MethodFactory
     */
    private $rateMethodFactory;

    /**
     * @var SveaShippingInfo
     */
    private $shippingInfoService;

    public function __construct(
        ResultFactory $resultFactory,
        MethodFactory $methodFactory,
        SveaShippingInfo $shippingInfoService,
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $errorFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($scopeConfig, $errorFactory, $logger);
        $this->_code = self::CODE;
        $this->rateResultFactory = $resultFactory;
        $this->rateMethodFactory = $methodFactory;
        $this->shippingInfoService = $shippingInfoService;
    }

    /**
     * @param RateRequest $request
     * @return Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active') || $this->shippingInfoService->getExcludeSveaShipping()) {
            return false;
        }

        $sveaInfo = $this->fetchSveaShippingInfo($request);
        if (null === $sveaInfo) {
            return false;
        }

        $result = $this->rateResultFactory->create();
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod(strtolower($sveaInfo->getCarrier()));
        $method->setMethodTitle($sveaInfo->getName());
        $method->setPrice($sveaInfo->getPrice());

        $result->append($method);

        return $result;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    /**
     * @param RateRequest $request
     *
     * @return DataObject|null
     */
    private function fetchSveaShippingInfo(RateRequest $request): ?DataObject
    {
        /** @var \Magento\Quote\Model\Quote\Item $quoteItem */
        $quoteItem = current($request->getAllItems());
        if (!$quoteItem) {
            return null;
        }

        $quote = $quoteItem->getQuote();
        return $this->shippingInfoService->getFromQuote($quote);
    }
}
