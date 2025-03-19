<?php declare(strict_types=1);

namespace Svea\Checkout\Api;

/**
 * Interface for classes that uses service containers from Svea Checkout Context
 */
interface UsesServiceContainerInterface
{
    /**
     * Get the name of the service container to use
     * @return string
     */
    public function getServiceContainerName(): string;
 
    /**
     * Assign services to properties
     * @return void
     */
    public function assignServices(): void;
}
