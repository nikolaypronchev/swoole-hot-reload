> _OpenSwoole loads all PHP files into memory and reuses the code for serving different requests. New changes to any code can't be seen immediately like with PHP-FPM because FPM uses a stateless shared nothing design, OpenSwoole is constant, using a stateful design, the script is started from PHP CLI and runs in memory. You have to reload the changed files with the hot reload function on demand._

Let's start a simple HTTP server that will forward requests to our application:
```php
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

$server->start();
```
In a local development environment, we expect changes to the application source code `nikolaypronchev\SwooleHotReload` to be automatically applied to the server.
For this, Swoole developers recommend monitoring file changes in a separate process and restarting the server when changes occur.

To monitor file changes, I will use the `inotify` extension. Let's add the process to our server:
```php
$server->addProcess(new Process(function () use ($root, $server, &$inotify, &$watchDescriptors) {
    $inotify = inotify_init();
    stream_set_blocking($inotify, false);

    # Select the events to monitor
    $events = IN_MODIFY | IN_CREATE | IN_DELETE | IN_MOVED_TO | IN_MOVED_FROM;

    # Store all watch descriptors in an array so they can be properly closed later
    $watchDescriptors = [];

    # Since inotify ignores subfolders, create a recursive function to monitor them
    $watchRecursive = function(string $path) use ($events, $inotify, &$watchDescriptors, &$watchRecursive) {
        $watchDescriptors[] = inotify_add_watch($inotify, "$path", $events);
        foreach (new DirectoryIterator($path) as $fileInfo) {
            !$fileInfo->isDot() && $fileInfo->isDir() && $watchRecursive($fileInfo->getPathname());
        }
    };

    # Start watching the directory with the application source code
    $watchRecursive("$root/src/app");

    while (true) {
        # Restart the server when changes are detected
        inotify_read($inotify) && $server->reload();

        # Add a comfortable delay to avoid overloading the CPU
        usleep(100000);
    }
}));
```
When shutting down the server, close the watch descriptors:
```php
$server->on('Shutdown', function () use ($inotify, $watchDescriptors) {
    if (!$inotify || !$watchDescriptors) {
        return;
    }

    foreach ($watchDescriptors as $descriptor) {
        inotify_rm_watch($inotify, $descriptor);
    }

    fclose($inotify);
});
```
To test the functionality, you can use the Docker Compose project from the repository.

## TODO
There are various scenarios where the code from this repository will not yield the desired result. For example, when creating or moving a subdirectory into the watched directory, inotify does not automatically start monitoring it. I suggest solving this and other potential issues independently.