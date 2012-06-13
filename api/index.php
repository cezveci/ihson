<?php

require 'Utils.php';
require 'Contracts.php';
require 'Products.php';
require 'Tenderees.php';
require 'Participants.php';
require 'Slim/Slim.php';
require 'Slim-Extras/TimestampLogFileWriter.php';

//$app = new Slim();

$app = new Slim(array(
    'log.enable' => true,
    'log.level' => 4,
'log.writer' => new TimestampLogFileWriter()
));

//contracts methods
$app->get('/contracts/references', 'getReferenceData');
$app->get('/contracts/:id', 'getContract');
$app->get('/contracts/search/:query', 'findByContractName');
$app->get('/contracts/search/', 'getContractNames');
$app->post('/contracts', 'saveContracts');

//products
$app->get('/products/search/:query', 'findProducts');
$app->get('/products/search/', 'getProducts');
$app->get('/products', 'getProducts');
$app->post('/products', 'saveProducts');

//tenderees
$app->get('/tenderees/search/:query', 'findTenderees');
$app->get('/tenderees/search/', 'getTenderees');
$app->get('/tenderees', 'getTenderees');
$app->post('/tenderees', 'saveTenderees');

//participants
$app->get('/participants/search/:query', 'findParticipants');
$app->get('/participants/search/', 'getParticipants');
$app->get('/participants', 'getParticipants');
$app->post('/participants', 'saveParticipants');

$app->run();
?>
