<?php

namespace harpya {
    
    // Ruta absoluta del external vendor
    $loader_external = require getenv('EXTERNAL_VENDOR_PATH') . './autoload.php';
    // Autoload de clases internas
    $loader_internal = require __DIR__ . '/../vendor/autoload.php';

    use harpya\App;
    use harpya\middleware\Configuration;

    $app = new App();                                                                                       
    Configuration::register($app);
    if($app['debug']){
        // Modo sin seguridad
        $app->debug();
    }else{
        $app->production();
    }

    $app->run();
}