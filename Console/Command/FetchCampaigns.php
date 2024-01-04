<?php declare(strict_types=1);

namespace Svea\Checkout\Console\Command;

use Svea\Checkout\Cron\FetchCampaigns as FetchCampaignsCron;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FetchCampaigns extends Command
{
    /**
     * @var FetchCampaignsCron
     */
    private $fetchCampaignsCron;

    /**
     * FetchCampaigns constructor.
     *
     * @param FetchCampaignsCron $fetchCampaignsCron
     */
    public function __construct(
        FetchCampaignsCron $fetchCampaignsCron,
        ?string $name = null
    ) {
        $this->fetchCampaignsCron = $fetchCampaignsCron;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('svea:campaign:fetch');
        $this->setDescription('Svea: Fetch product campaigns.');

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<comment>Starting fetching of campaigns.</comment>');
        $this->fetchCampaignsCron->execute();
        $messages = $this->fetchCampaignsCron->getMessages();

        if (empty($messages)) {
            $output->writeln('<info>Nothing fetched</info>');
            return 0;
        }

        foreach ($messages as $message) {
            $output->writeln('<info>' . $message . '</info>');
        }
        $output->writeln('<info>Finished</info>');
        return 0;
    }
}
