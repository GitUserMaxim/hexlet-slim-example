<?php

require __DIR__. '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Views\PhpRenderer;
use Slim\Flash\Messages;

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__. '/../templates');
});
$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$app->get('/users/new', function ($request, $response) {
    return $this->get('renderer')->render($response, 'users/new.phtml');
});

$app->post('/users/new', function ($request, $response) {
    $userData = $request->getParsedBody();

    // Генерация случайного id
    $userData['id'] = uniqid();

    $usersJsonFile = __DIR__. '/../data/users.json';

    // Чтение существующих данных из файла
    $existingUsers = json_decode(file_get_contents($usersJsonFile), true);

    // Добавление новых данных пользователя в массив
    $existingUsers[] = $userData;

    // Кодирование массива в JSON
    $usersJson = json_encode($existingUsers);

    // Запись JSON в файл
    file_put_contents($usersJsonFile, $usersJson);

    return $response->withRedirect('/users');
});

$app->get('/users', function ($request, $response) {
    $usersJsonFile = __DIR__. '/../data/users.json';

    // Чтение данных из файла
    $usersJson = file_get_contents($usersJsonFile);

    // Обработка ошибок при чтении файла
    if ($usersJson === false) {
        throw new \RuntimeException('Failed to read users.json file');
    }

    // Разбор JSON
    $users = json_decode($usersJson, true);

    // Обработка ошибок при разборе JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \RuntimeException('Invalid JSON in users.json file');
    }

    return $this->get('renderer')->render($response, 'users/index.phtml', ['users' => $users]);
});

$app->run();