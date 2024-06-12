<?php

declare(strict_types=1);

namespace Svea\Checkout\Helper;

use Magento\GiftCardAccount\Model\Giftcardaccount;

class GiftCard
{
    protected $giftCardAccountManagement;
    protected $giftCardAccountRepository;
    protected $giftCardAccountFactory;
    protected $searchCriteriaBuilder;
    protected $serializer;
    protected $giftCardFactory;

    public function __construct(
        \Magento\GiftCardAccount\Api\GiftCardAccountManagementInterface $managementService,
        \Magento\GiftCardAccount\Api\GiftCardAccountRepositoryInterface $giftCardAccountRepository,
        \Magento\GiftCardAccount\Api\Data\GiftCardAccountInterfaceFactory $giftCardAccountFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        \Magento\GiftCardAccount\Model\GiftCardFactory $giftCardFactory
    ) {
        $this->giftCardAccountManagement = $managementService;
        $this->giftCardAccountRepository = $giftCardAccountRepository;
        $this->giftCardAccountFactory = $giftCardAccountFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->serializer = $serializer;
        $this->giftCardFactory = $giftCardFactory;
    }

    public function deleteByQuoteId(int $quoteId, string $code)
    {
        $this->giftCardAccountManagement->deleteByQuoteId($quoteId, $code);
    }

    public function saveByQuoteId(int $quoteId, $code)
    {
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
