<?php
include('vendor/autoload.php');

include('vendor/metrophp/metrodi/container.php');

$container = New Metrodi_Container('.', array('.', '..', 'vendor'));

_didef('dataitem', 'Metrodb_Dataitem');
_didef('auth_handler', '\Metrou_Authdefault');

Metrodb_Connector::setDsn('default', 'mysqli://root:mysql@127.0.0.1/metrodb_test');
