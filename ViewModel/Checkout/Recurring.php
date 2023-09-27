<?php declare(strict_types=1);

namespace Svea\Checkout\ViewModel\Checkout;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Checkout\Model\Session;
use Svea\Checkout\Helper\Data;

class Recurring implements ArgumentInterface
{
    private Data $helper;

    private Json $serializer;

    private Session $checkoutSession;

    private UrlInterface $url;

    public function __construct(
        Data $helper,
        Json $serializer,
        Session $checkoutSession,
        UrlInterface $url
    ) {
        $this->helper = $helper;
        $this->serializer = $serializer;
        $this->checkoutSession = $checkoutSession;
        $this->url = $url;
    }

    /**
     * Get recurring status for current session
     *
     * @return bool
     */
    public function getMyRecurringStatus(): bool
    {
        $payment = $this->checkoutSession->getQuote()->getPayment();
        $recurringInfo = $payment->getAdditionalInformation('svea_recurring_info') ?? [];
        $status = $recurringInfo['enabled'] ?? false;
        return (bool)$status;
    }

    /**
     * Get recurring frequency options formatted for checkout display
     *
     * @return array
     */
    public function getRecurringFrequencyOptions(): array
    {
        $json = $this->helper->getRecurringFrequencyOptions();
        $unserializedValue = $this->serializer->unserialize($json);

        $payment = $this->checkoutSession->getQuote()->getPayment();
        $recurringInfo = $payment->getAdditionalInformation('svea_recurring_info') ?? [];
        $selectedFrequency = $recurringInfo['frequency_option'] ?? null;
        $formattedOptions = [];
        foreach ($unserializedValue as $key => $option) {
            if ($key === '__empty') {
                continue;
            }
            $value = $option['frequency'] . '|' . $option['time_unit'];
            $option = ['label' => $option['label'], 'value' => $value, 'selected' => false];

            if ($selectedFrequency === $value) {
                $option['selected'] = true;
            }
            $formattedOptions[] = $option;
        }
        return $formattedOptions;
    }

    /**
     * Action for recurring form, points to Svea\Checkout\Controller\Index\SetRecurring
     *
     * @return string
     */
    public function getFormAction(): string
    {
        return $this->url->getUrl('*/index/setRecurring');
    }
}
