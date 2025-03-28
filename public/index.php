<?php

require __DIR__. '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Views\PhpRenderer;
use Slim\Flash\Messages;
use Slim\Middleware\MethodOverrideMiddleware;

session_start();

$container = new Container();

$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__. '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});


$app = AppFactory::createFromContainer($container);

$router = $app->getRouteCollector()->getRouteParser();

$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$app->get('/users/new', function ($request, $response) use ($router) {
    // Получаем флеш-сообщения
    $flashMessages = $this->get('flash')->getMessages();
    
    return $this->get('renderer')->render($response, 'users/new.phtml', [
        'flash' => $flashMessages // Передаем флеш-сообщения в шаблон
    ]);
})->setName('users.new');

$app->post('/users', function ($request, $response) use ($router) {
    
    $userData = $request->getParsedBody();
    $errors =[];

    // Валидация никнейма
    if (strlen($userData['nickname']) < 4) {
        $errors['nickname'] = 'Никнейм должен содержать более 4 символов.';
    }
    // Если есть ошибки, сохраняем их во флеш-сообщениях и перенаправляем обратно
    if (!empty($errors)) {
        foreach ($errors as $error) {
            $this->get('flash')->addMessage('error', $error);
        }
        return $response->withRedirect($router->urlFor('users.new'));
    }
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

    $this->get('flash')->addMessage('success', 'Пользователь успешно создан!');

    return $response->withRedirect($router->urlFor('users.index'));
})->setName('users.store');


$app->get('/users', function ($request, $response) use ($router) {
    $usersJsonFile = __DIR__. '/../data/users.json';
    // Проверка на существование файла
    if (!file_exists($usersJsonFile)) {
        throw new \RuntimeException('File users.json not found');
    }
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
    // Проверка на тип данных
    if (!is_array($users)) {
        throw new \RuntimeException('Invalid data type in users.json file');
    }
    // Получение параметров запроса
    $queryParams = $request->getQueryParams();
    $searchTerm = $queryParams['search'] ?? ''; // Используем null coalescing operator

    // Фильтрация пользователей по имени
    if ($searchTerm) {
        $users = array_filter($users, function($user) use ($searchTerm) {
            return str_contains($user['nickname'], $searchTerm); // Поиск по никнейму
        });
    }

    // Передача данных в шаблон
    return $this->get('renderer')->render($response, 'users/index.phtml', [
        'users' => $users,
        'searchTerm' => $searchTerm,
        'flash' => $this->get('flash')->getMessages() // Передаем флеш-сообщения, если есть
    ]);
})->setName('users.index');


$app->get('/users/{id}', function ($request, $response, $args) use ($router) {
    $usersJsonFile = __DIR__. '/../data/users.json';
    $usersJson = file_get_contents($usersJsonFile);
    $users = json_decode($usersJson, true);

    $userId = $args['id'];

    $user = null;

    // Ищем пользователя по ID
    foreach ($users as $u) {
        if ($u['id'] == $userId) {
            $user = $u;
            break;
        }
    }

    // Если пользователь не найден, возвращаем 404
    if ($user === null) {
        return $response->withStatus(404)->write('User not found');
    }
    $params = [
        'id' => $user['id'],
        'nickname' => $user['nickname'],
        'email' => $user['email']
    ];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('users.show');



$app->get('/users/{id}/edit', function ($request, $response, $args) {
    $usersJsonFile = __DIR__ . '/../data/users.json';
    $users = json_decode(file_get_contents($usersJsonFile), true);

    // Ищем пользователя по ID
    $userId = $args['id'];
    $user = null;

    foreach ($users as $u) {
        if ($u['id'] == $userId) {
            $user = $u;
            break;
        }
    }

    // Если пользователь не найден, возвращаем 404
    if ($user === null) {
        return $response->withStatus(404)->write('User not found');
    }

    return $this->get('renderer')->render($response, 'users/edit.phtml', [
        'user' => $user,
        'flash' => $this->get('flash')->getMessages()
    ]);
})->setName('users.edit');

$app->patch('/users/{id}', function ($request, $response, array $args) use ($router) {
    $userId = $args['id'];
    $userData = $request->getParsedBody();
    $errors = [];

    // Валидация никнейма
    if (strlen($userData['nickname']) < 4) {
        $errors['nickname'] = 'Никнейм должен содержать более 4 символов.';
    }

    // Если есть ошибки, сохраняем их во флеш-сообщениях и перенаправляем обратно
    if (!empty($errors)) {
        foreach ($errors as $error) {
            $this->get('flash')->addMessage('error', $error);
        }
        return $response->withRedirect($router->urlFor('users.edit', ['id' => $userId]));
    }

    $usersJsonFile = __DIR__ . '/../data/users.json';
    $users = json_decode(file_get_contents($usersJsonFile), true);

    // Ищем пользователя по ID и обновляем его данные
    foreach ($users as &$u) {
        if ($u['id'] == $userId) {
            $u['nickname'] = $userData['nickname'];
            $u['email'] = $userData['email'];
            break;
        }
    }

    // Кодирование массива в JSON
    $usersJson = json_encode($users);

    // Запись JSON в файл
    file_put_contents($usersJsonFile, $usersJson);

    $this->get('flash')->addMessage('success', 'Пользователь успешно обновлён!');

    return $response->withRedirect($router->urlFor('users.index'));
})->setName('users.update');

$app->run();