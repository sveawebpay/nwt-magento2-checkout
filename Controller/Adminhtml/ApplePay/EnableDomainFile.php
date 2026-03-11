<?php declare(strict_types=1);

namespace Svea\Checkout\Controller\Adminhtml\ApplePay;

use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;

/**
 * Enables apple pay domain file
 */
class EnableDomainFile extends Action
{
    const ADMIN_RESOURCE = 'Svea_Checkout::manage_apple_pay';

    const FILE_NAME = 'apple-developer-merchantid-domain-association';

    /**
     * @var ComponentRegistrarInterface
     */
    private ComponentRegistrarInterface $componentRegistrar;

    /**
     * @var DirectoryList
     */
    private DirectoryList $directoryList;

    /**
     * @var File
     */
    private File $fileSystem;

    public function __construct(
        Action\Context $context,
        ComponentRegistrarInterface $componentRegistrar,
        DirectoryList $directoryList,
        File $fileSystem
    ) {
        parent::__construct($context);
        $this->componentRegistrar = $componentRegistrar;
        $this->directoryList = $directoryList;
        $this->fileSystem = $fileSystem;
    }

    /**
     * @return ResponseInterface
     */
    public function execute()
    {
        try {
            $moduleBaseDir = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Svea_Checkout');
            $sourceFile = $moduleBaseDir . '/etc/resources/' . self::FILE_NAME;
            $targetDirectory = $this->directoryList->getPath(DirectoryList::MEDIA) . '/.well-known';
            $targetFile = $targetDirectory . '/' . self::FILE_NAME;

            if (!$this->fileSystem->isFile($sourceFile)) {
                throw new LocalizedException(__('Source Apple Pay domain association file was not found.'));
            }

            if (!$this->fileSystem->isDirectory($targetDirectory)
                && !$this->fileSystem->createDirectory($targetDirectory, 0755)
            ) {
                throw new LocalizedException(__('Unable to create target directory: %1', $targetDirectory));
            }

            if (!$this->fileSystem->copy($sourceFile, $targetFile)) {
                throw new LocalizedException(__('Unable to copy Apple Pay domain association file.'));
            }

            $this->messageManager->addSuccessMessage(__('Apple Pay domain association file has been enabled.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while enabling Apple Pay domain association file.')
            );
        }

        return $this->resultRedirectFactory->create()->setRefererUrl();
    }
}
