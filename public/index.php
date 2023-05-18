<?php

use DI\Container;
use Slim\Views\Twig;
use Slim\Factory\AppFactory;
use Slim\Views\TwigMiddleware;
use Slim\Middleware\MethodOverrideMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';
$container = new Container();
AppFactory::setContainer($container);

$container->set('db', function () {
    $db = new \PDO("sqlite:" . __DIR__ . '/../database/database.sqlite');
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(\PDO::ATTR_TIMEOUT, 5000);
    $db->exec("PRAGMA journal_mode = WAL");
    return $db;
});

$app = AppFactory::create();

$app->addBodyParsingMiddleware();

$twig = Twig::create(__DIR__ . '/../twig', ['cache' => false]);

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});

$app->get('/users', function (Request $request, Response $response, $args) {
    // GET Query params
    // $query_params = $request->getQueryParams();
    // dump($query_params);
    // die;

    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM users");
    $sth->execute();
    $users = $sth->fetchAll(\PDO::FETCH_OBJ);
    // dump($users);
    // die;

    //2
    /*$queryParams = $request->getQueryParams();
    $format = $queryParams['format'];
    // Проверяем значение параметра и возвращаем соответствующее содержимое
    if ($format === 'json') {
        $response = $response->withHeader('Content-Type', 'application/json');     // преобразует в JSON
        $response->getBody()->write(json_encode($users)); //возвращает JSON-представление данных
        return $response;
    } else {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'users.html', [
            'users' => $users
        ]);
    }*/

    $queryParams = $request->getQueryParams();
    $format = $queryParams['format'];

    // Проверяем значение параметра и возвращаем соответствующее содержимое
    if ($format === 'json') {
         $response = $response->withHeader('Content-Type', 'application/json');     // преобразует в JSON
         $response->getBody()->write(json_encode($users)); //возвращает JSON-представление данных
         return $response;
    } elseif ($format === 'text') {
        $response = $response->withHeader('Content-Type', 'text/plain');
        $fields = ['first_name', 'last_name'];
        $userFields = array_map(function ($user) use ($fields) {
            return array_intersect_key(get_object_vars($user), array_flip($fields));
        }, $users);
        $response->getBody()->write(json_encode($userFields));
         return $response;
    } else {
        $view = Twig::fromRequest($request);
         return $view->render($response, 'users.html', [
            'users' => $users
    ]);
    }
    
});
//3
$app->get('/users-by-header', function (Request $request, Response $response, $args) {
    $queryParams = $request->getQueryParams();
    // Получаем заголовок Accept из запроса
$acceptHeader = $request->getHeaderLine('Accept');
// Получаем объект базы данных
$db = $this->get('db');
// Подготавливаем SQL-запрос для получения всех пользователей из таблицы
$sth = $db->prepare("SELECT * FROM users");
// Выполняем SQL-запрос
$sth->execute();
// Получаем результаты запроса в виде массива объектов
$users = $sth->fetchAll(\PDO::FETCH_OBJ);

// Проверяем заголовок Accept и возвращаем соответствующее содержимое
if (strpos($acceptHeader, 'application/json') !== false) {
        // Если заголовок Accept содержит application/json, то устанавливаем Content-Type для ответа
    $response = $response->withHeader('Content-Type', 'application/json');
        // Преобразуем массив объектов пользователей в формат JSON и записываем его в тело ответа
    $response->getBody()->write(json_encode($users));
    return $response;
} elseif (strpos($acceptHeader, 'text/plain') !== false) {
        // Если заголовок Accept содержит text/plain, то устанавливаем Content-Type для ответа
    $response = $response->withHeader('Content-Type', 'text/plain');
        // Создаем массив полей, которые нужно вывести для каждого пользователя
    $fields = ['first_name', 'last_name', 'email'];
    $userFields = array_map(function ($user) use ($fields) {
        return array_intersect_key(get_object_vars($user), array_flip($fields));
    }, $users);
        // С помощью функций implode и array_map формируем строку, содержащую информацию о пользователях, и записываем ее в тело ответа
    $response->getBody()->write(implode(PHP_EOL, array_map(function ($user) {
        return implode(' ', $user);
    }, $userFields)));
    return $response;
} else {     // Если заголовок Accept не содержит ни application/json, ни text/plain, то возвращаем HTML-страницу с помощью шаблонизатора Twig
    $view = Twig::fromRequest($request);
    return $view->render($response, 'users.html', [
        'users' => $users
    ]);
}
// Если дошли до этой точки, значит, не удалось обработать запрос, выводим ошибку 404
    return $response->withStatus(404);
});

$app->get('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];

    $db = $this->get('db');
    $sth = $db->prepare('SELECT * FROM users WHERE id=:id LIMIT 1');
    $sth->bindValue(':id', $id);
    $sth->execute();

    $user = $sth->fetch(\PDO::FETCH_OBJ);

    $view = Twig::fromRequest($request);
    return $view->render($response, 'user.html', [
        'user' => $user
    ]);
});
//4
$app->post('/users', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    $parsedBody = $request->getParsedBody();

    $first_name = $parsedBody['first_name'];
    $last_name = $parsedBody['last_name'];
    $email = $parsedBody['email'];
    // получаем тело запроса
    dump($parsedBody);
    // Получаем максимальное значение id из таблицы users
    $sth = $db->prepare("SELECT MAX(id) as max_id FROM users");
    $sth->execute();
    $row = $sth->fetch();
    $max_id = $row['max_id'];

    // Увеличиваем значение на 1
    $next_id = $max_id + 1;

    // Добавляем запись в таблицу users с увеличенным id
    $sth = $db->prepare("INSERT INTO users (id, first_name, last_name, email) VALUES (?,?,?,?)");
    $sth->execute([$next_id, $first_name, $last_name, $email]);
});
//5
$app->patch('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');
    $parsedBody = $request->getParsedBody();

    $first_name = $parsedBody['first_name'];
    $last_name = $parsedBody['last_name'];
    $email = $parsedBody['email'] ?? user->email;

    dump($parsedBody);
    $sth = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
    $sth->execute([$first_name, $last_name, $email, $id]);
});

$app->put('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');
    $parsedBody = $request->getParsedBody();
    
    $first_name = $parsedBody["first_name"];
    $last_name = $parsedBody["last_name"];
    $email = $parsedBody["email"];
    
    $sth = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?');
    $sth->execute([$first_name, $last_name, $email, $id]);
    return $response->withStatus(302)->withHeader('Location', '/users');
    //dump($parsedBody);
    //die;
    });
    

//6
$app->delete('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');

    // Сохранить максимальный id перед удалением строки
    $sth = $db->prepare("SELECT MAX(id) FROM users");
    $sth->execute();
    $max_id = $sth->fetchColumn();

    // Удалить строку с указанным id
    $sth = $db->prepare("DELETE FROM users WHERE id=?");
    $sth->execute([$id]);

    // Если удалена строка не была последней по порядку,
    // то обновить id для всех последующих строк
    if ($id < $max_id) {
        $sth = $db->prepare("UPDATE users SET id=id-1 WHERE id > ?");
        $sth->execute([$id]);
    }
});
$app->get('/download', function ($request, $response, $args) {
    // Получаем доступ к базе данных
    $db = $this->get('db');
    // Подготавливаем запрос на выбор всех записей из таблицы
    $sth = $db->prepare("SELECT * FROM users");
    $sth->execute();
    $users = $sth->fetchAll(\PDO::FETCH_OBJ);
    
    // Импортируем класс TCPDF и создаем экземпляр
    require_once('C:\Users\79647\Desktop\Back\phpslim-backend-main\tcpdf.php');
    echo __DIR__;
    $pdf = new TCPDF();
    // Добавляем новую страницу
    $pdf->AddPage();
    // Устанавливаем шрифт
    $pdf->SetFont('helvetica', '', 12);
    // Выводим заголовок
    $pdf->Cell(0, 10, 'User report', 0, 1);
    $pdf->Ln();
    // Выводим данные из базы данных в таблицу
    $pdf->Cell(50, 10, 'First name', 1, 0);
    $pdf->Cell(50, 10, 'Last name', 1, 0);
    $pdf->Cell(90, 10, 'Email', 1, 1);
    foreach ($users as $user) {
    $pdf->Cell(50, 10, $user->first_name, 1, 0);
    $pdf->Cell(50, 10, $user->last_name, 1, 0);
    $pdf->Cell(90, 10, $user->email, 1, 1);
    }
    // Отправляем файл пользователю для скачивания
    ob_end_clean();
    $pdf->Output('user_report_' . date('Y-m-d') . '.pdf', 'D');
    });
$methodOverrideMiddleware = new MethodOverrideMiddleware();
$app->add($methodOverrideMiddleware);

$app->add(TwigMiddleware::create($app, $twig));
$app->run();