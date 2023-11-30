<?php declare(strict_types=1);

namespace Svea\Checkout\Controller\Index;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Controller\Result\JsonFactory as ResultJsonFactory;
use Magento\Framework\Controller\Result\RedirectFactory as ResultRedirectFactory;
use Magento\Quote\Model\QuoteRepository;
use Svea\Checkout\Service\SveaShippingInfo;

class SetRecurring implements HttpPostActionInterface
{
    private HttpRequest $request;

    private Session $checkoutSession;

    private QuoteRepository $quoteRepo;

    private ResultRedirectFactory $resultRedirectFactory;

    private ResultJsonFactory $resultJsonFactory;

    private SveaShippingInfo $shippingInfoService;

    public function __construct(
        HttpRequest $request,
        Session $checkoutSession,
        QuoteRepository $quoteRepo,
        ResultRedirectFactory $resultRedirectFactory,
        ResultJsonFactory $resultJsonFactory,
        SveaShippingInfo $shippingInfoService
    ) {
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepo = $quoteRepo;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->shippingInfoService = $shippingInfoService;
    }

    public function execute()
    {
        $recurringEnabled = $this->request->getPostValue('recurring_enabled', false);
        $frequencyOption = $this->request->getPostValue('frequency_option');
        $frequencyOnly = $this->request->getPostValue('frequency_only', false);

        $this->shippingInfoService->setExcludeSveaShipping(false);

        $quote = $this->checkoutSession->getQuote();
        $payment = $quote->getPayment();
        $recurringInfo = $payment->getAdditionalInformation('svea_recurring_info') ?? [];
        $recurringInfo['enabled'] = !!$recurringEnabled;
        $recurringInfo['frequency_option'] = $frequencyOption;
        $payment->setAdditionalInformation('svea_recurring_info', $recurringInfo);
        $quote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates();
        $this->quoteRepo->save($quote);

        // Changing frequency does not require a full reload and is done as an Ajax request
        if ($frequencyOnly) {
            $result = $this->resultJsonFactory->create();
            $result->setData(['success' => true]);
            return $result;
        }

        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('*/*/index', ['_secure' => true]);
        return $redirect;
    }
}
