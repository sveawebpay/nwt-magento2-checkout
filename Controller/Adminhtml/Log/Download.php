<?php
namespace Svea\Checkout\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;

class Download extends Action
{
    const ADMIN_RESOURCE = 'Svea_Checkout::log_download';
    protected $fileFactory;
    protected $directoryList;
    protected $allowedLogFiles = [
        'svea_checkout.log',
        'svea_checkout_error.log'
    ];
    public function __construct(
        Action\Context $context,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        DirectoryList $directoryList
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->directoryList = $directoryList;
    }

    /**
     * @return ResponseInterface|void
     */
    public function execute()
    {
        try {
            $zipFilePath = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/log/svea_checkout_logs.zip';
            $zip = new \ZipArchive();
            if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new LocalizedException(__('Unable to create zip archive.'));
            }

            $filesAdded = false;
            foreach ($this->allowedLogFiles as $logFile) {
                $logFilePath = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/log/' . $logFile;
                if (file_exists($logFilePath)) {
                    $zip->addFile($logFilePath, $logFile);
                    $filesAdded = true;
                }
            }

            $zip->close();

            if (!$filesAdded) {
                throw new LocalizedException(__('No log files were found to download.'));
            }

            return $this->fileFactory->create(
                'svea_checkout_logs.zip',
                [
                    'type' => 'filename',
                    'value' => 'log/svea_checkout_logs.zip',
                    'rm' => true
                ],
                DirectoryList::VAR_DIR
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while trying to download the logs. Please try again later.'));
        }
        
        return $this->_redirect('admin/system_config/edit/section/svea_checkout');

    }
}