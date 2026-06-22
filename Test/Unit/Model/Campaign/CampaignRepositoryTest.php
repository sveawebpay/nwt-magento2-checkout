<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Model\Campaign;

use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Model\Campaign\CampaignRepository;
use Svea\Checkout\Model\CampaignInfo;
use Svea\Checkout\Model\Resource\CampaignInfo\Collection;
use Svea\Checkout\Model\Resource\CampaignInfo\CollectionFactory;

class CampaignRepositoryTest extends TestCase
{
    private CollectionFactory&MockObject $collectionFactory;
    private CampaignRepository $repository;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->repository = new CampaignRepository($this->collectionFactory);
    }

    public function testGetByCodeReturnsCampaignWhenEntityIdIsSet(): void
    {
        $campaign = $this->makeCampaignWithEntityId(42);
        $collection = $this->makeCollectionReturningFirstItem($campaign);

        $this->collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $result = $this->repository->getByCode('CAMPAIGN_CODE_1');

        $this->assertSame($campaign, $result);
    }

    public function testGetByCodeThrowsNoSuchEntityExceptionWhenEntityIdIsNull(): void
    {
        $campaign = $this->makeCampaignWithEntityId(null);
        $collection = $this->makeCollectionReturningFirstItem($campaign);

        $this->collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $this->expectException(NoSuchEntityException::class);

        $this->repository->getByCode('NONEXISTENT_CODE');
    }

    public function testGetByCodeFiltersCollectionByCampaignCode(): void
    {
        $campaign = $this->makeCampaignWithEntityId(10);
        $collection = $this->createMock(Collection::class);

        $collection->expects($this->once())
            ->method('addFieldToFilter')
            ->with('campaign_code', 'MY_CODE');

        $collection->method('getFirstItem')->willReturn($campaign);

        $this->collectionFactory->method('create')->willReturn($collection);

        $this->repository->getByCode('MY_CODE');
    }

    public function testGetCodesDelegatesToGetColumnValues(): void
    {
        $expectedCodes = ['CODE_A', 'CODE_B', 'CODE_C'];
        $collection = $this->createMock(Collection::class);

        $collection->expects($this->once())
            ->method('getColumnValues')
            ->with('campaign_code')
            ->willReturn($expectedCodes);

        $this->collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $result = $this->repository->getCodes();

        $this->assertSame($expectedCodes, $result);
    }

    public function testGetCodesReturnsEmptyArrayWhenNoCodesExist(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getColumnValues')->willReturn([]);

        $this->collectionFactory->method('create')->willReturn($collection);

        $result = $this->repository->getCodes();

        $this->assertSame([], $result);
    }

    // --- Helpers ---

    private function makeCampaignWithEntityId(mixed $entityId): CampaignInfo&MockObject
    {
        /** @var CampaignInfo&MockObject $campaign */
        $campaign = $this->getMockBuilder(CampaignInfo::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $campaign->setData('entity_id', $entityId);

        return $campaign;
    }

    private function makeCollectionReturningFirstItem(CampaignInfo&MockObject $campaign): Collection&MockObject
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getFirstItem')->willReturn($campaign);

        return $collection;
    }
}
