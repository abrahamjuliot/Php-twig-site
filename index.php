<?php

require __DIR__ . '/vendor/autoload.php';
date_default_timezone_set('America/Los_Angeles');

$app = new \Slim\App(['settings' => ['displayErrorDetails' => true]]);

// Get container
$container = $app->getContainer();

// Register component on container
$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig('templates', [
        'cache' => false
    ]);
    $view->addExtension(new \Slim\Views\TwigExtension(
        $container['router'],
        $container['request']->getUri()
    ));

    return $view;
};


// Render Twig template in route
$app->get('/', function ($request, $response, $args) {
    return $this->view->render($response, 'about.twig');
})->setName('home');

$app->get('/contact', function ($request, $response, $args) {
    return $this->view->render($response, 'contact.twig');
})->setName('contact');

$app->post('/contact', function ($request, $response, $args){
    $body = $this->request->getParsedBody();
    $name = $body['name'];
    $email = $body['email'];
    $msg = $body['msg'];
    

    if (!empty($name)
    && !empty($email)
    && !empty($msg)) {
        $cleanName = filter_var($name, FILTER_SANITIZE_STRING);
        $cleanEmail = filter_var($email, FILTER_SANITIZE_EMAIL);
        $cleanMsg = filter_var($msg, FILTER_SANITIZE_STRING);
        
    } else {
        return $this->response->withStatus(200)->withHeader(
            'Location', '/Php-twig-site/contact'
        );
    }
    
    $transport = Swift_SendmailTransport::newInstance('/user/sbin/sendmail -bs');
    $mailer = Swift_Mailer::newInstance($transport);
    
    $message = Swift_Message::newInstance()
        ->setSubject('New email from your website')
        ->setFrom(array(
            $cleanEmail => $cleanName    
        ))
        ->setTo(array('abeletter@gmail.com'))
        ->setBody($cleanMsg);
    
    $result = $mailer->send($message);
    
    if ($result > 0) {
        return $this->response->withStatus(220)->withHeader(
            'Location', '/Php-twig-site/'
        );
    } else {
        return $this->response->withStatus(200)->withHeader(
            'Location', '/Php-twig-site/contact'
        );
    }

});

$app->run();

    