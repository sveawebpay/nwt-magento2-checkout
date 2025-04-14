<?php declare(strict_types=1);

namespace Svea\Checkout\ViewModel\Checkout;

use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel providing a CSP nonce for use in checkout templates.
 */
class NonceProvider implements ArgumentInterface
{
    /**
     * CSP nonce provider helper.
     *
     * @var CspNonceProvider
     */
    private $cspNonceProvider;

    /**
     * Constructor.
     *
     * @param CspNonceProvider $cspNonceProvider CSP nonce provider helper.
     */
    public function __construct(CspNonceProvider $cspNonceProvider)
    {
        $this->cspNonceProvider = $cspNonceProvider;
    }

    /**
     * Generate a Content Security Policy (CSP) nonce.
     *
     * Returns null if the nonce could not be generated.
     *
     * @return string|null
     */
    public function generateNonce(): ?string
    {
        try {
            $nonce = $this->cspNonceProvider->generateNonce();
        } catch (LocalizedException $e) {
            return null;
        }

        return $nonce;
    }
}
