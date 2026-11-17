<?php

namespace Svea\Checkout\Plugin\Reward\Controller\Cart\Remove;

use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\Action\Action;

class RedirectBack
{
    /**
     * @var RedirectInterface
     */
    private $redirect;

    /**
     * RedirectBack constructor.
     *
     * @param RedirectInterface $redirect
     */
    public function __construct(RedirectInterface $redirect)
    {
        $this->redirect = $redirect;
    }

    /**
     * @param Action $removeAction
     * @param $response
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function afterExecute(Action $removeAction, $response)
    {
        $this->redirect->redirect(
            $removeAction->getResponse(),
            $this->redirect->getRefererUrl()
        );

        return $removeAction->getResponse();
    }
}
