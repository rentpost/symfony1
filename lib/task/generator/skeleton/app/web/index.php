<?php

##IP_CHECK##
require_once(__DIR__.'/../config/ProjectConfiguration.class.php');

// Default config to not use debug mode - change accordingly
$configuration = ProjectConfiguration::getApplicationConfiguration('##APP_NAME##', '##ENVIRONMENT##', false);
sfContext::createInstance($configuration)->dispatch();
