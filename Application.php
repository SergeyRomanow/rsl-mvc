<?php

namespace RSL\MVC;

use RSL\Event\EventManagerAwareInterface;
use RSL\Event\EventManagerInterface;
use RSL\Service\ServiceManager;
use RSL\Stdlib\ResponseInterface;

class Application implements
    ApplicationInterface,
    EventManagerAwareInterface
{
    const ERROR_CONTROLLER_CANNOT_DISPATCH
        = 'error-controller-cannot-dispatch';
    const ERROR_CONTROLLER_NOT_FOUND
        = 'error-controller-not-found';
    const ERROR_CONTROLLER_INVALID
        = 'error-controller-invalid';
    const ERROR_EXCEPTION
        = 'error-exception';
    const ERROR_ROUTER_NO_MATCH
        = 'error-router-no-match';

    /**
     * @var array
     */
    protected $configuration = null;


    /**
     * Default application event listeners
     * Приемники событий по умолчанию приложений
     *
     * @var array
     */
    protected $defaultListeners = array(
        'RouteListener',
        'DispatchListener',
        'HttpMethodListener',
        'ViewManager',
        'SendResponseListener',
    );

    /**
     * MVC event token
     * Идентификатор события MVC
     * @var MvcEvent
     */
    protected $event;

    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * @var \RSL\Stdlib\RequestInterface
     */
    protected $request;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var ServiceManager
     */
    protected $serviceManager = null;

    /**
     * Constructor
     *
     * @param mixed $configuration
     * @param ServiceManager $serviceManager
     */
    public function __construct(
        $configuration,
        ServiceManager $serviceManager
    )
    {
        $this->configuration  = $configuration;
        $this->serviceManager = $serviceManager;

        $this->setEventManager($serviceManager->get('EventManager'));

        $this->request        = $serviceManager->get('Request');
        $this->response       = $serviceManager->get('Response');
    }

    /**
     * Retrieve the application configuration
     * Получить конфигурацию приложения
     *
     * @return array|object
     */
    public function getConfig()
    {
        return $this->serviceManager->get('Config');
    }


    /**
     * Bootstrap the application
     * Загрузка приложения
     *
     * Defines and binds the MvcEvent, and passes it the request, response, and
     * router. Attaches the ViewManager as a listener. Triggers the bootstrap
     * event.
     *
     * Определяет и связывает MvcEvent
     * и передает ему запрос, ответ и маршрутизатор.
     * Прикрепляет ViewManager как слушателя.
     * Запускает событие бутстрапа.
     *
     * @param array $listeners List of listeners to attach.
     * @return Application
     */
    public function bootstrap(array $listeners = array())
    {
        $serviceManager     = $this->serviceManager;
        $events             = $this->events;

        $listeners = array_unique(
            array_merge($this->defaultListeners,
            $listeners)
        );

        foreach ($listeners as $listener)
        {
            $events->attach($serviceManager->get($listener));
        }

        // Setup MVC Event
        // Настройка события MVC
        $this->event = $event  = new MvcEvent();

        $event->setTarget($this);

        $event->setApplication($this)
                    ->setRequest($this->request)
                    ->setResponse($this->response)
                    ->setRouter($serviceManager->get('Router'));

        // Trigger bootstrap events
        // Триггерные загрузочные события
        $events->trigger(MvcEvent::EVENT_BOOTSTRAP, $event);
        return $this;
    }

    /**
     * Retrieve the service manager
     * Получить сервис-менеджера
     *
     * @return ServiceManager
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }

    /**
     * Get the request object
     * Получить объект [Запрос] Request
     *
     * @return \RSL\Stdlib\RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get the response object
     * Получить объект [Ответ] Response
     *
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Get the MVC event instance
     * Получить экземпляр события MVC
     *
     * @return MvcEvent
     */
    public function getMvcEvent()
    {
        return $this->event;
    }

    /**
     * Set the event manager instance
     * Установить экземпляр менеджера событий
     *
     * @param  EventManagerInterface $eventManager
     * @return Application
     */
    public function setEventManager(
        EventManagerInterface $eventManager
    )
    {
        $eventManager->setIdentifiers(
            array(
                __CLASS__,
                get_class($this),
            )
        );

        $this->events = $eventManager;
        return $this;
    }

    /**
     * Retrieve the event manager
     * Получить менеджер событий
     *
     * Lazy-loads an EventManager instance if none registered.
     * Lazy загружает экземпляр EventManager,
     * если он не зарегистрирован.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        return $this->events;
    }

    /**
     * Static method for quick and easy initialization of the Application.
     * Статический метод быстрой и легкой инициализации приложения.
     *
     * If you use this init() method, you cannot specify a service with the
     * name of 'ApplicationConfig' in your service manager config. This name is
     * reserved to hold the array from application.config.php.
     * Если вы используете этот метод init (),
     * вы не можете указать службу с именем «ApplicationConfig»
     * в конфигурации вашего менеджера сервисов.
     * Это имя <b>зарезервировано</b> для хранения массива
     * из application.config.php.
     *
     * The following services can only be overridden from application.config.php:
     * Следующие сервисы можно переопределить только из application.config.php:
     *
     * - ModuleManager
     * - SharedEventManager
     * - EventManager & RSL\EventManager\EventManagerInterface
     *
     * All other services are configured after module loading, thus can be
     * overridden by modules.
     * Все остальные службы настраиваются после загрузки модуля,
     * поэтому их можно переопределить модулями.
     *
     * @param array $configuration
     * @return Application
     */
    public static function init($configuration = array())
    {
        $smConfig = isset($configuration['service_manager']) ? $configuration['service_manager'] : array();
        $serviceManager =
            new ServiceManager(
                new Service\ServiceManagerConfig($smConfig)
            );
        $serviceManager->setService(
            'ApplicationConfig', $configuration);

        $serviceManager->get('ModuleManager')->loadModules();

        $listenersFromAppConfig =
            isset($configuration['listeners'])
            ? $configuration['listeners']
            : array();

        $config = $serviceManager->get('Config');

        $listenersFromConfigService =
            isset($config['listeners'])
            ? $config['listeners']
            : array();

        $listeners = array_unique(
            array_merge($listenersFromConfigService,
                        $listenersFromAppConfig)
            );

        return $serviceManager->get('Application')
                              ->bootstrap($listeners);
    }


}    // End of class Application

?>