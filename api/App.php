<?php

namespace harpya\api {

    use Silex\Application;
    use Symfony\Component\Debug\ErrorHandler;
    use Symfony\Component\Debug\ExceptionHandler;
    
    use harpya\middleware\Database;
    use harpya\middleware\Business;
    use harpya\middleware\Resources;
    use harpya\middleware\Providers;
    use harpya\middleware\Test;
    use harpya\middleware\Middleware;
    use harpya\middleware\Session;
    use harpya\middleware\Security;
    use harpya\middleware\WEBProfiler;
    use harpya\middleware\ExceptionManager;

    class App extends Application {

        use Application\SecurityTrait;
        use Application\MonologTrait;
        use Application\UrlGeneratorTrait;
        use Application\TwigTrait;
        
        public function __construct(array $values = array()) {
            parent::__construct($values);
        }

        public function production() {
            $app = $this;
            date_default_timezone_set($app['config.timezone']);
            ExceptionHandler::register($app['debug']);
            ErrorHandler::register();
            Providers::register($app);
            ExceptionManager::register($app);
            
            $app->log("Modo Producción");
            
            Database::register($app);
            Business::register($app);
            Middleware::register($app);
            Resources::register($app);
            Session::register($app);
            Security::register($app);
            $app->boot();
        }

        public function debug() {
            $app = $this;
            date_default_timezone_set($app['config.timezone']);
            ExceptionHandler::register($app['debug']);
            ErrorHandler::register();
            Providers::register($app);
            ExceptionManager::register($app);
            $app->log("Modo Desarrollo");
            
            Database::register($app);
            Business::register($app);
            Middleware::register($app);
            Resources::register($app);
            Session::register($app);
            if ($app['debug.firewall']) {
                Security::register($app);
            }
            if ($app['debug.profiler']) {
                WEBProfiler::register($app);
            }
            Test::register($app);
        }

    }

}