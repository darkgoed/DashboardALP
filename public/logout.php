<?php

require_once __DIR__ . '/../app/helpers/Auth.php';

Auth::logout();

header('Location: login.php');
exit;