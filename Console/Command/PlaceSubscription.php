<?php declare(strict_types=1);

namespace Svea\Checkout\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\OrderFactory as OrderResourceFactory;
use Magento\Store\Model\App\Emulation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Svea\Checkout\Service\SveaRecurringInfo\PlaceOrders;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;

class PlaceSubscription extends Command
{
    private PlaceOrders $placeOrders;

    private RecurringInfoRepositoryInterface $recurringInfoRepo;

    private OrderFactory $orderFactory;

    private OrderResourceFactory $orderResourceFactory;

    private Emulation $emulation;

    private State $appState;

    public function __construct(
        PlaceOrders $placeOrders,
        RecurringInfoRepositoryInterface $recurringInfoRepo,
        OrderFactory $orderFactory,
        OrderResourceFactory $orderResourceFactory,
        Emulation $emulation,
        State $appState,
        ?string $name = null
    ) {
        $this->placeOrders = $placeOrders;
        $this->recurringInfoRepo = $recurringInfoRepo;
        $this->orderFactory = $orderFactory;
        $this->orderResourceFactory = $orderResourceFactory;
        $this->emulation = $emulation;
        $this->appState = $appState;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('svea:subscription:place');
        $this->setDescription('Svea: Place a Subscription order using an token.');
        $this->addArgument('token', InputArgument::REQUIRED, 'Recurring Token');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $token = $input->getArgument('token');
        $recurringInfo = $this->recurringInfoRepo->getByRecurringToken($token);
        $order = $this->orderFactory->create();
        $this->orderResourceFactory->create()->load($order, $recurringInfo->getOriginalOrderId());

        $this->appState->setAreaCode(Area::AREA_FRONTEND);
        $this->emulation->startEnvironmentEmulation($order->getStoreId(), Area::AREA_FRONTEND, true);
        $this->placeOrders->placeRecurringOrders([$recurringInfo]);
        $this->emulation->stopEnvironmentEmulation();
        return 0;
    }
}
