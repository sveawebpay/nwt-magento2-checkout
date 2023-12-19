<?php declare(strict_types=1);

namespace Svea\Checkout\Controller\Order;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Quote\Model\Quote\ShippingAssignment\ShippingAssignmentProcessor;
use Magento\Framework\Message\ManagerInterface;
use Svea\Checkout\Model\Svea\Locale;

class ChangeCountry implements HttpPostActionInterface
{
    /**
     * @var HttpRequest
     */
    private HttpRequest $request;

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonResultFactory;

    /**
     * @var Locale
     */
    private Locale $localeHelper;

    /**
     * @var CartExtensionFactory
     */
    private CartExtensionFactory $cartExtensionFactory;

    /**
     * @var ShippingAssignmentProcessor
     */
    private ShippingAssignmentProcessor $shippingAssignmentProcessor;

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $quoteRepo;

    public function __construct(
        HttpRequest $request,
        Session $checkoutSession,
        JsonFactory $jsonResultFactory,
        Locale $localeHelper,
        CartExtensionFactory $cartExtensionFactory,
        ShippingAssignmentProcessor $shippingAssignmentProcessor,
        CartRepositoryInterface $quoteRepo
    ) {
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->localeHelper = $localeHelper;
        $this->cartExtensionFactory = $cartExtensionFactory;
        $this->shippingAssignmentProcessor = $shippingAssignmentProcessor;
        $this->quoteRepo = $quoteRepo;
    }

    /**
     * @inheritDoc
     */
    public function execute(): Json
    {
        $countryId = $this->request->getParam('country_id');
        if (!$this->countryHasChanged($countryId)) {
            return $this->sendNoChangedResponse();
        }

        // First check if country is allowed (should be valid outside of some very strange configuration)
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->getPayment()->getMethodInstance()->canUseForCountry($countryId)) {
            $result = $this->jsonResultFactory->create()->setData([
                'message' => __('We are sorry, this country is not allowed for Svea Checkout.'),
                'reload' => false,
            ]);
            return $result;
        }

        // Set new country
        $this->changeCountry($countryId);
        $result = $this->jsonResultFactory->create()->setData(['reload' => true]);
        return $result;
    }

    /**
     * @param $countryId
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function countryHasChanged($countryId): bool
    {
        $billingAddress = $this->getBillingAddress();
        return $countryId !== $billingAddress->getCountryId();
    }

    /**
     * @param $countryId
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function changeCountry($countryId): void
    {
        $quote = $this->checkoutSession->getQuote();
        $this->getBillingAddress()->setCountryId($countryId);
        $defaultData = $this->localeHelper->getDefaultDataByCountryCode($countryId);
        $defaultPostcode = $defaultData['PostalCode'] ?? '';
        $this->getBillingAddress()->setPostcode($defaultPostcode);
        if (!$quote->isVirtual()) {
            $shippingAddress = $this->getShippingAddress();
            $shippingAddress->setCountryId($countryId);
            $shippingAddress->setPostcode($defaultPostcode);

            $extAttributes = $quote->getExtensionAttributes();
            if (null === $extAttributes) {
                $extAttributes = $this->cartExtensionFactory->create();
            }
            $extAttributes->setShippingAssignments([$this->shippingAssignmentProcessor->create($quote)]);
            $quote->setExtensionAttributes($extAttributes);
        }
        $this->quoteRepo->save($quote);
    }

    /**
     * @return \Magento\Quote\Model\Quote\Address
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getBillingAddress(): \Magento\Quote\Model\Quote\Address
    {
        $quote = $this->checkoutSession->getQuote();
        $billingAddress = $quote->getBillingAddress();

        return $billingAddress;
    }

    /**
     * @return \Magento\Quote\Model\Quote\Address
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getShippingAddress(): \Magento\Quote\Model\Quote\Address
    {
        $quote = $this->checkoutSession->getQuote();
        $shippingAddress = $quote->getShippingAddress();

        return $shippingAddress;
    }

    /**
     * @return void
     */
    private function sendNoChangedResponse(): Json
    {
        $response = ['reload' => false];
        return $this->jsonResultFactory->create()->setData($response);
    }
}
