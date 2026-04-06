<?php

require_once __DIR__ . '/../../app/config/bootstrap.php';
$page = (new StaticContentPageService())->getLegalPage('privacidade');
StaticContentPageRenderer::render($page);
