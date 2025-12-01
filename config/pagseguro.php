<?php

//config\pagseguro.php

return [

    // Ambiente (sandbox ou production)
    'environment' => getenv('PAGBANK_ENV') ?: 'sandbox',

    // Configuração SANDBOX
    'sandbox' => [
        'base_url' => 'https://sandbox.api.pagseguro.com',
        // Token sandbox 
        'api_key' => getenv('PAGBANK_SANDBOX_KEY') ?: 'eb7594c4-8465-4b32-9ad3-ff23e0d844430a41b8ac4b4e90e0d9c51339629ec1deb155-9b74-4e2f-acfb-7efa9eadf0a0',
    ],

    // Configuração PRODUÇÃO
    'production' => [
        'base_url' => 'https://api.pagseguro.com',
        // Token produção 
        'api_key' => getenv('PAGBANK_PRODUCTION_KEY') ?: 'eb7594c4-8465-4b32-9ad3-ff23e0d844430a41b8ac4b4e90e0d9c51339629ec1deb155-9b74-4e2f-acfb-7efa9eadf0a0',
    ],

    // URLs de retorno e notificação
    // Importante: localhost NÃO usa https
    'return_url' => getenv('PAGBANK_RETURN_URL') ?: 'http://localhost/e-commece-pronto-saudavel-todos-os-dias/config/pagseguro_redirect.php',

    'notification_url' => getenv('PAGBANK_NOTIFICATION_URL') ?: 'http://localhost/e-commece-pronto-saudavel-todos-os-dias/config/pagseguro_notification.php',
];
