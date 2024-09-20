<?php

use Swoole\Process;
use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use nikolaypronchev\SwooleHotReload\app\App;

$root = realpath(__DIR__ . "/..");
require "$root/vendor/autoload.php";

$server = new Server("0.0.0.0", 80);

$server->on("WorkerStart", function (Server $server, int $workerId) use (&$app) {
    $app = new App($workerId);
});

$server->on("Request", function (Request $request, Response $response) use (&$app) {
    $app->resolve($request, $response);
});

# Данный процесс наблюдает за изменениями в файлах и перезапускает сервер при необходимости
$server->addProcess(new Process(function () use ($root, $server, &$inotify, &$watchDescriptors) {
    $inotify = inotify_init();
    stream_set_blocking($inotify, false);

    # Выбираем события, которые будем отслеживать
    $events = IN_MODIFY | IN_CREATE | IN_DELETE | IN_MOVED_TO | IN_MOVED_FROM;

    # Все дескрипторы наблюдения будем записывать в массив чтобы в дальнейшем корректно их закрыть
    $watchDescriptors = [];

    # Так как inotify игнорирует подпапки, то создаём рекурсивную функцию для наблюдения
    $watchRecursive = function(string $path) use ($events, $inotify, &$watchDescriptors, &$watchRecursive) {
        $watchDescriptors[] = inotify_add_watch($inotify, "$path", $events);
        foreach (new DirectoryIterator($path) as $fileInfo) {
            !$fileInfo->isDot() && $fileInfo->isDir() && $watchRecursive($fileInfo->getPathname());
        }
    };

    # Начинаем наблюдение за интересующей нас директорией
    $watchRecursive("$root/src/app");

    while (true) {
        # Перезагружаем сервер при наличии изменений
        inotify_read($inotify) && $server->reload();

        # Добавляем комфортную для работы задержку, чтобы не перегружать процессор
        usleep(100000);
    }
}));

$server->on('Shutdown', function () use ($inotify, $watchDescriptors) {
    if (!$inotify || !$watchDescriptors) {
        return;
    }

    foreach ($watchDescriptors as $descriptor) {
        inotify_rm_watch($inotify, $descriptor);
    }

    fclose($inotify);
});

$server->start();