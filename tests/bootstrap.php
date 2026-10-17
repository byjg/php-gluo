<?php

require __DIR__ . '/../vendor/autoload.php';

// The tests always run on the test database. DBDRIVER_CONNECTION set in the environment (the rest
// container sets the dev one, a CI job its own) only chooses the server: its database is replaced.
// A SQLite database is a file, with no server: the one of config/test applies, whatever is set.
$connection = getenv('DBDRIVER_CONNECTION');
if (!empty($connection)) {
    $uri = new \ByJG\Util\Uri($connection);
    putenv($uri->getScheme() === 'sqlite' ? 'DBDRIVER_CONNECTION' : 'DBDRIVER_CONNECTION=' . $uri->withPath('/localtest'));
}
