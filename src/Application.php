<?php
namespace Downloader;

use Silex\Application as SilexApplication;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Downloader\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * Core of Downloader
 * @author: Johann-S
 */
class Application
{
    private $app;
    private static $appDownloader;

    private function __construct() {
        $this->app = new SilexApplication();
    }

    public static function boot() {
        $app = self::getApp();
        $app->loadConfig();
        $app->registerProviders();
        self::log('info', 'Boot Downloader...');
        $router = new Router($app->getSilexApp());
        $router->load();
        $app->run();
    }

    public static function log($type, $message, $vars = array()) {
        $app = self::getApp()->getSilexApp();
        if (!isset($app['log']) || !$app['log']) {
            return;
        }
        if ($vars === null) {
            $vars = array();
        }
        switch($type) {
            case 'debug':
                $app['monolog']->addDebug($message, $vars);
                break;
            case 'info':
                $app['monolog']->addInfo($message, $vars);
                break;
            case 'notice':
                $app['monolog']->addNotice($message, $vars);
                break;
            case 'warning':
                $app['monolog']->addWarning($message, $vars);
                break;
            case 'error':
                $app['monolog']->addError($message, $vars);
                break;
            case 'critical':
                $app['monolog']->addCritical($message, $vars);
                break;
            case 'alert':
                $app['monolog']->addAlert($message, $vars);
                break;
            case 'emergency':
                $app['monolog']->addEmergency($message, $vars);
                break;
            default:
                $app['monolog']->addInfo($message, $vars);
                break;
        }
    }

    public static function getParam($param) {
        $app = self::getApp()->getSilexApp();
        try {
            if (is_array($param) && count($param) === 2) {
                return $app[$param[0]][$param[1]];
            }
            return $app[$param];
        }
        catch (\InvalidArgumentException $e) {
            self::log('error', $e->getMessage());
            return null;
        }
    }

    public static function isSecure() {
        $app = self::getApp()->getSilexApp();
        if (isset($app['passModalHash'])) {
            return $app['passModalHash'] !== null;
        }

        return false;
    }

    private static function getApp() {
        if (!self::$appDownloader instanceof Application) {
            self::$appDownloader = new Application();
        }
        return self::$appDownloader;
    }

    private function loadConfig() {
        $config = Yaml::parse(file_get_contents(__DIR__ . '/../config/param.yml'));
        foreach ($config as $key => $value) {
            $this->app[$key] = $value;
        }
    }

    private function registerProviders() {
        $this->app->register(new ServiceControllerServiceProvider());
        $this->app->register(new TwigServiceProvider(), array(
            'twig.path' => __DIR__ . '/../views',
        ));
        // Register monolog only if log = true in config file
        if (isset($this->app['log']) && $this->app['log']) {
            $this->app->register(new MonologServiceProvider(), array(
                'monolog.name' => 'Downloader',
                'monolog.logfile' => __DIR__ . '/../logs/downloader-' . date('Y-m-d') . '.log'
            ));
        }

        $this->app->register(new SessionServiceProvider(), array(
            'session.storage.options' => array(
                'name'              => 'downloader',
                'cookie_lifetime'   => 0
            )
        ));
    }

    private function run() {
        $request = Request::createFromGlobals();
        $this->app->run($request);
    }

    private function getSilexApp() {
        return $this->app;
    }
}
