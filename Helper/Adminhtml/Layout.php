<?php

namespace Svea\Checkout\Helper\Adminhtml;

use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Backend\Block\Template\Context;

class Layout
{
    private ComponentRegistrarInterface $componentRegistrar;

    private ReadFactory $readFactory;

    private Json $serializer;

    public function __construct(
        ComponentRegistrarInterface $componentRegistrar,
        ReadFactory $readFactory,
        Json $serializer,
        Context $context,
        array $data = []
    ) {
        $this->componentRegistrar = $componentRegistrar;
        $this->readFactory = $readFactory;
        $this->serializer = $serializer;
    }

    /**
     * Get composer version
     *
     * @return string
     */
    public function getComposerVersion(): string
    {
        $moduleName = 'Svea_Checkout';
        $path = $this->componentRegistrar->getPath(
            \Magento\Framework\Component\ComponentRegistrar::MODULE,
            $moduleName
        );
        $directoryRead = $this->readFactory->create($path);
        $composerJsonData = $directoryRead->readFile('composer.json');
        $composerData = $this->serializer->unserialize($composerJsonData);
        return $composerData['version'] ?? '';
    }
}
