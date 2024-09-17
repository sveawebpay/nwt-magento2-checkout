<?php

declare(strict_types=1);

namespace Svea\Checkout\Helper;

use Magento\GiftCardAccount\Model\Giftcardaccount;
use Magento\GiftCardAccount\Api\GiftCardAccountManagementInterface;
use Magento\GiftCardAccount\Api\GiftCardAccountRepositoryInterface;
use Magento\GiftCardAccount\Model\GiftCardFactory;
use Magento\GiftCardAccount\Api\Data\GiftCardAccountInterfaceFactory;
use Magento\Framework\App\ObjectManager;

class GiftCard
{
    protected $giftCardAccountManagement;
    protected $giftCardAccountRepository;
    protected $giftCardAccountFactory;
    protected $searchCriteriaBuilder;
    protected $serializer;
    protected $giftCardFactory;

    public function __construct(
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        $giftCardAccountRepository = null,
        $giftCardAccountFactory = null,
        $giftCardFactory = null,
        $managementService = null
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->serializer = $serializer;
        if($moduleManager->isEnabled('Magento_GiftCardAccount')) {
            $this->giftCardAccountRepository = ObjectManager::getInstance()->get(GiftCardAccountRepositoryInterface::class);
            $this->giftCardAccountFactory = ObjectManager::getInstance()->get(GiftCardAccountInterfaceFactory::class);
            $this->giftCardFactory = ObjectManager::getInstance()->get(GiftCardFactory::class);
            $this->giftCardAccountManagement = ObjectManager::getInstance()->get(GiftCardAccountManagementInterface::class);
        } else {
            $this->giftCardAccountRepository = null;
            $this->giftCardAccountFactory = null;
            $this->giftCardFactory = null;
            $this->giftCardAccountManagement = null;
        }
    }

    public function deleteByQuoteId(int $quoteId, string $code)
    {
        if($this->giftCardAccountManagement === null) {
            return;
        }
        $this->giftCardAccountManagement->deleteByQuoteId($quoteId, $code);
    }

    public function saveByQuoteId(int $quoteId, $code)
    {
        if($this->giftCardAccountManagement === null) {
            return;
        }
        $this->giftCardAccountManagement->saveByQuoteId(
            $quoteId,
            $this->giftCardAccountFactory->create(['data' => ['gift_cards' => [$code]]])
        );
    }

    /**
     * @param string $giftcardsString
     * @return array
     */
    public function getGiftCards(string $giftcardsString): array
    {
        if (empty($giftcardsString)) {
            return [];
        }
        $giftcards = $this->serializer->unserialize($giftcardsString);

        return $this->createGiftCards($giftcards ?? []);
    }

    public function getGiftCardsByQuoteId(int $quoteId)
    {
        if($this->giftCardAccountManagement === null) {
            return [];
        }
        $giftCardAccount = $this->giftCardAccountManagement->getListByQuoteId($quoteId);

        return $this->getByCodes($giftCardAccount->getGiftCards());
    }

    /**
     * Retrieve set of giftcard accounts based on the codes
     *
     * @param array $giftCardCodes
     * @return array
     */
    private function getByCodes(array $giftCardCodes): array
    {
        if($this->giftCardAccountRepository === null) {
            return [];
        }
        return $this->giftCardAccountRepository->getList(
            $this->searchCriteriaBuilder->addFilter('code', $giftCardCodes, 'in')->create()
        )->getItems();
    }

    /**
     * Create Gift Cards Data Objects
     *
     * @param array $items
     * @return \Magento\GiftCardAccount\Model\GiftCard[]
     */
    private function createGiftCards(array $items): array
    {
        if($this->giftCardFactory === null) {
            return [];
        }
        $giftCards = [];
        foreach ($items as $item) {
            /** @var \Magento\GiftCardAccount\Model\GiftCard $giftCard */
            $giftCard = $this->giftCardFactory->create();
            $giftCard->setId($item[Giftcardaccount::ID]);
            $giftCard->setCode($item[Giftcardaccount::CODE]);
            $giftCard->setAmount($item[Giftcardaccount::AMOUNT]);
            $giftCard->setBaseAmount($item[Giftcardaccount::BASE_AMOUNT]);
            $giftCards[$giftCard->getCode()] = $giftCard;
        }

        return $giftCards;
    }
}
