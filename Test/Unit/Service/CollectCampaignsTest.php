<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Service;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Logger\Logger;
use Svea\Checkout\Model\CampaignInfo;
use Svea\Checkout\Model\CampaignInfoFactory;
use Svea\Checkout\Model\Client\Api\CampaignManagement;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Resource\CampaignInfo as CampaignInfoResource;
use Svea\Checkout\Model\Resource\CampaignInfo\Collection as CampaignCollection;
use Svea\Checkout\Model\Resource\CampaignInfo\CollectionFactory;
use Svea\Checkout\Service\CollectCampaigns;

class CollectCampaignsTest extends TestCase
{
    private CampaignManagement&MockObject $campaignManagement;
    private CampaignInfoFactory&MockObject $campaignInfoFactory;
    private CampaignInfoResource&MockObject $campaignResource;
    private CollectionFactory&MockObject $collectionFactory;
    private Logger&MockObject $logger;
    private StoreManagerInterface&MockObject $storeManager;
    private CollectCampaigns $service;

    protected function setUp(): void
    {
        $this->campaignManagement = $this->createMock(CampaignManagement::class);
        $this->campaignInfoFactory = $this->createMock(CampaignInfoFactory::class);
        $this->campaignResource = $this->createMock(CampaignInfoResource::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->logger = $this->createMock(Logger::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $this->service = new CollectCampaigns(
            $this->campaignManagement,
            $this->campaignInfoFactory,
            $this->campaignResource,
            $this->collectionFactory,
            $this->logger,
            $this->storeManager
        );
    }

    public function testCollectReturnsEmptyArrayWhenNoStores(): void
    {
        $this->storeManager->method('getStores')->willReturn([]);

        $messages = $this->service->collect();

        $this->assertSame([], $messages);
    }

    public function testCollectReturnsSilentlyWhenNoCampaignsFetchedAndNoneDeleted(): void
    {
        $store = $this->makeStore(1);
        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->campaignManagement->method('getAvailablePartPaymentCampaigns')->willReturn([]);

        $collection = $this->makeEmptyCollection();
        $this->collectionFactory->method('create')->willReturn($collection);

        $messages = $this->service->collect();

        $this->assertSame([], $messages);
    }

    public function testCollectReportsImportedCampaignCount(): void
    {
        $store = $this->makeStore(1);
        $this->storeManager->method('getStores')->willReturn([$store]);

        $fetchedCampaigns = [
            ['campaign_code' => 100, 'name' => 'Campaign A'],
            ['campaign_code' => 101, 'name' => 'Campaign B'],
        ];
        $this->campaignManagement->method('getAvailablePartPaymentCampaigns')->willReturn($fetchedCampaigns);

        $campaignModel = $this->createMock(CampaignInfo::class);
        $campaignModel->method('getId')->willReturn(null);
        $this->campaignInfoFactory->method('create')->willReturn($campaignModel);
        $this->campaignResource->method('loadByCampaignCodeAndStoreId');
        $this->campaignResource->method('save');

        $collection = $this->makeEmptyCollection();
        $this->collectionFactory->method('create')->willReturn($collection);

        $messages = $this->service->collect();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('2', (string)$messages[0]);
        $this->assertStringContainsString('1', (string)$messages[0]); // store ID
    }

    public function testCollectReportsDeletedCampaignCount(): void
    {
        $store = $this->makeStore(1);
        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->campaignManagement->method('getAvailablePartPaymentCampaigns')->willReturn([]);

        $staleCampaign = $this->createMock(CampaignInfo::class);
        $collection = $this->makeCollectionWithItems([$staleCampaign]);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->campaignResource->expects($this->once())->method('delete')->with($staleCampaign);

        $messages = $this->service->collect();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('1', (string)$messages[0]); // deleted count
    }

    public function testCollectSavesCampaignWithCorrectStoreId(): void
    {
        $store = $this->makeStore(5);
        $this->storeManager->method('getStores')->willReturn([$store]);

        $fetchedCampaigns = [['campaign_code' => 200, 'name' => 'Campaign X']];
        $this->campaignManagement->method('getAvailablePartPaymentCampaigns')->willReturn($fetchedCampaigns);

        $campaignModel = $this->createMock(CampaignInfo::class);
        $campaignModel->method('getId')->willReturn(null);
        $this->campaignInfoFactory->method('create')->willReturn($campaignModel);

        $campaignModel->expects($this->once())->method('setStoreId')->with(5);
        $this->campaignResource->expects($this->once())->method('save')->with($campaignModel);

        $collection = $this->makeEmptyCollection();
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->service->collect();
    }

    public function testCollectPreservesEntityIdForExistingCampaigns(): void
    {
        $store = $this->makeStore(1);
        $this->storeManager->method('getStores')->willReturn([$store]);

        $fetchedCampaigns = [['campaign_code' => 300, 'name' => 'Updated Campaign']];
        $this->campaignManagement->method('getAvailablePartPaymentCampaigns')->willReturn($fetchedCampaigns);

        $campaignModel = $this->createMock(CampaignInfo::class);
        $campaignModel->method('getId')->willReturn(42); // existing entity
        $this->campaignInfoFactory->method('create')->willReturn($campaignModel);

        // setId should be called with the existing ID to preserve it after setData() overwrites
        $campaignModel->expects($this->once())->method('setId')->with(42);
        $this->campaignResource->method('save');

        $collection = $this->makeEmptyCollection();
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->service->collect();
    }

    public function testCollectLogsErrorAndContinuesOnApiException(): void
    {
        $storeGood = $this->makeStore(2);
        $this->storeManager->method('getStores')->willReturn([$storeGood]);

        $this->campaignManagement->method('getAvailablePartPaymentCampaigns')
            ->willThrowException(new ClientException('API error'));

        $this->logger->expects($this->once())->method('error');

        $messages = $this->service->collect();

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Error', (string)$messages[0]);
    }

    public function testCollectProcessesMultipleStoresIndependently(): void
    {
        $store1 = $this->makeStore(1);
        $store2 = $this->makeStore(2);
        $this->storeManager->method('getStores')->willReturn([$store1, $store2]);

        $this->campaignManagement->method('getAvailablePartPaymentCampaigns')->willReturn([]);

        $collection = $this->makeEmptyCollection();
        $this->collectionFactory->method('create')->willReturn($collection);

        // No exception — both stores processed silently
        $messages = $this->service->collect();

        $this->assertSame([], $messages);
    }

    // --- Helpers ---

    private function makeStore(int $id): StoreInterface&MockObject
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($id);

        return $store;
    }

    private function makeEmptyCollection(): CampaignCollection&MockObject
    {
        $collection = $this->createMock(CampaignCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(0);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));

        return $collection;
    }

    private function makeCollectionWithItems(array $items): CampaignCollection&MockObject
    {
        $collection = $this->createMock(CampaignCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn(count($items));
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));

        return $collection;
    }
}
