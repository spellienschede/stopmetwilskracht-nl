<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;

Auth::logout();
redirect('/admin/book/login.php');
