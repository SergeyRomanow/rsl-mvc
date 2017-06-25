<?php

namespace RSL\Mvc;

use RSL\Manager\Event\EventsCapableInterface;

interface ApplicationInterface 
    extends 
        EventsCapableInterface
{
    /**
    * 
    * Get the locator object
    * Получить объект: Локатор
    * @return \RSL\Manager\Service\ServiceLocatorInterface
    */
    public function getServiceManager();

    /**
     * Get the request object
     * Получить объект: Request
     *
     * @return \RSL\Stdlib\RequestInterface
     */
    public function getRequest();
    
    /**
     * Get the response object
     * Получить объект: Response
     *
     * @return \RSL\Stdlib\ResponseInterface
     */
    public function getResponse();
    
    /**
     * Run the application
     *
     * @return self
     */
    public function run();

}
?>