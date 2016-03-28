<?php
include('vendor/autoload.php');

include('vendor/metrophp/metrodi/container.php');

_didef('dataitem', 'Metrodb_Dataitem');
_didef('auth_handler', '\Metrou_Authdefault');

Metrodb_Connector::setDsn('default', 'mysql://root:mysql@127.0.0.1/metrodb_test');
