<?php

/******************************* LOADING & INITIALIZING BASE APPLICATION ****************************************/

// Load Composer's PSR-4 autoloader (necessary to load Slim, Mini etc.)
require '../vendor/autoload.php';

// Initialize Slim (the router/micro framework used)
$app = new \Slim\Slim(array(
    'mode' => 'production'
));

// and define the engine used for the view @see http://twig.sensiolabs.org
$app->view = new \Slim\Views\Twig();
$app->view->setTemplatesDirectory("../Mini/view");

$app->view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
);

/******************************************* THE CONFIGS *******************************************************/

// Configs for mode "development" (Slim's default), see the GitHub readme for details on setting the environment
$app->configureMode('development', function () use ($app) {
    // Set the configs for development environment
    include_once __DIR__.'/../lib/config.php';
    $app->config(array(
        'debug' => true,
        'database' => array(
            'db_host' => 'localhost',
            'db_port' => '',
            'db_name' => $db,
            'db_user' => $db_user,
            'db_pass' => $db_pw,
            'page_size' => 50
        )
    ));
});

// Configs for mode "production"
$app->configureMode('production', function () use ($app) {
    // Set the configs for production environment
    include_once __DIR__.'/../lib/config.php';
    $app->config(array(
        'debug' => false,
        'database' => array(
            'db_host' => 'localhost',
            'db_port' => '',
            'db_name' => $db,
            'db_user' => $db_user,
            'db_pass' => $db_pw,
            'page_size' => 50
        )
    ));

    $app->view->parserOptions = array(
        'cache' => '../cache'
    );
});

/******************************************** THE MODEL ********************************************************/

// Initialize the model, pass the database configs. $model can now perform all methods from Mini\model\model.php
$model = new \Mini\Model\Model($app->config('database'));

/************************************ THE ROUTES / CONTROLLERS *************************************************/

$lastUpdate = 1448793493;

$app->get('/', function () use ($app, $model, $lastUpdate) {
    $app->lastModified(max(array($lastUpdate, $model->getLastUpdate())));
    $app->expires('+1 day');

    $pageCount = $model->getPageCount();
    $page = 1;
    if(isset($_GET['page']) && is_numeric($_GET['page']))
        $page = $_GET['page'];

    if($page <= $pageCount && $page > 0)
        $bots = $model->getBots($page);
    else
        $bots = array();

    $app->render('index.twig', array(
        'pageCount' => $pageCount,
        'page' => $page,
        'bots' => $bots
    ));
});
$app->get('/submit', function () use ($app, $model, $lastUpdate) {
    $app->lastModified($lastUpdate);
    // 1 day expiration because of the types list
    $app->expires('+1 day');

    $token = $model->getToken("submit");
    $types = $model->getAllTypes();

    $app->render('submit.twig', array(
        'success' => $_GET['success'],
        'error' => $_GET['error'],
        'token' => $token,
        'types' => $types
    ));
})->name('submit');
$app->get('/check', function () use ($app, $lastUpdate) {
    $app->lastModified($lastUpdate);
    $app->expires('+1 week');
    $app->render('check.twig');
});
$app->get('/api', function () use ($app, $lastUpdate) {
    $app->lastModified($lastUpdate);
    $app->expires('+1 week');
    $app->render('api.twig');
});
$app->get('/about', function () use($app, $lastUpdate) {
    $app->lastModified($lastUpdate);
    $app->expires('+1 week');
    $app->render('about.twig');
});
$app->get('/submissions', function () use($app, $model, $lastUpdate) {
    $app->expires('+1 minute');
    $submissions = $model->getSubmissions();
    if(count($submissions) > 0) {
        $app->lastModified(max(array($lastUpdate, strtotime($submissions[0]->date))));
    }
    else {
        $app->lastModified(time());
    }

    $app->render('submissions.twig', array(
        'submissions' => $submissions
    ));
});

$app->put('/lib/submit', function () use($app, $model) {
    if($model->checkToken("submit", $app->request->params('token'))) {
        $model->addSubmission(
            $app->request->params('username'),
            $app->request->params('type'),
            $app->request->params('description')
        );
        $app->redirect($app->request->getUrl().$app->urlFor('submit').'?success=1');
    }
    else {
        $app->redirect($app->request->getUrl().$app->urlFor('submit').'?error=1');
    }
});

/******************************************* RUN THE APP *******************************************************/

$app->run();
