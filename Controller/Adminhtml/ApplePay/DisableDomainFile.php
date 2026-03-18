<?php declare(strict_types=1);

namespace Svea\Checkout\Controller\Adminhtml\ApplePay;

use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;

/**
 * Disables apple pay domain file
 */
class DisableDomainFile extends Action
{
    const ADMIN_RESOURCE = 'Svea_Checkout::manage_apple_pay';

    const FILE_NAME = 'apple-developer-merchantid-domain-association';

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
        DirectoryList $directoryList,
        File $fileSystem
    ) {
        parent::__construct($context);
        $this->directoryList = $directoryList;
        $this->fileSystem = $fileSystem;
    }

    /**
     * @return ResponseInterface
     */
    public function execute()
    {
        try {
            $targetFile = $this->directoryList->getPath(DirectoryList::MEDIA)
                . '/.well-known/' . self::FILE_NAME;

            if (!$this->fileSystem->isFile($targetFile)) {
                $this->messageManager->addNoticeMessage(__('Apple Pay domain association file is already disabled.'));
                return $this->resultRedirectFactory->create()->setRefererUrl();
            }

            if (!$this->fileSystem->deleteFile($targetFile)) {
                throw new LocalizedException(__('Unable to remove Apple Pay domain association file.'));
            }

            $this->messageManager->addSuccessMessage(__('Apple Pay domain association file has been disabled.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('An error occurred while disabling Apple Pay domain association file.')
            );
        }

        return $this->resultRedirectFactory->create()->setRefererUrl();
    }
}
