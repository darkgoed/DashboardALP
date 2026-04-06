<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';
$page = (new StaticContentPageService())->getLegalPage('termos');
StaticContentPageRenderer::render($page);
