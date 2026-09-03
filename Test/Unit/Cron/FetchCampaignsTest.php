<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Cron;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Cron\FetchCampaigns;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Service\CollectCampaigns;

class FetchCampaignsTest extends TestCase
{
    private Data&MockObject $config;
    private CollectCampaigns&MockObject $collectCampaigns;
    private FetchCampaigns $cron;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Data::class);
        $this->collectCampaigns = $this->createMock(CollectCampaigns::class);

        $this->cron = new FetchCampaigns($this->config, $this->collectCampaigns);
    }

    public function testGetMessagesReturnsEmptyArrayBeforeExecute(): void
    {
        $this->assertSame([], $this->cron->getMessages());
    }

    public function testExecuteDoesNothingWhenCampaignWidgetInactive(): void
    {
        $this->config->method('isCampaignWidgetActive')->willReturn(false);

        $this->collectCampaigns->expects($this->never())->method('collect');

        $this->cron->execute();

        $this->assertSame([], $this->cron->getMessages());
    }

    public function testExecuteDelegatesToCollectCampaignsWhenActive(): void
    {
        $this->config->method('isCampaignWidgetActive')->willReturn(true);

        $this->collectCampaigns->expects($this->once())
            ->method('collect')
            ->willReturn(['Imported 5 campaigns for store 1']);

        $this->cron->execute();
    }

    public function testGetMessagesReturnsCollectResultAfterExecute(): void
    {
        $expected = ['Imported 3 campaigns for store 1', 'Deleted 1 campaigns for store 1'];

        $this->config->method('isCampaignWidgetActive')->willReturn(true);
        $this->collectCampaigns->method('collect')->willReturn($expected);

        $this->cron->execute();

        $this->assertSame($expected, $this->cron->getMessages());
    }

    public function testExecuteReplacesPreviousMessages(): void
    {
        $this->config->method('isCampaignWidgetActive')->willReturn(true);
        $this->collectCampaigns->method('collect')
            ->willReturnOnConsecutiveCalls(
                ['First run message'],
                ['Second run message']
            );

        $this->cron->execute();
        $this->cron->execute();

        $this->assertSame(['Second run message'], $this->cron->getMessages());
    }
}
