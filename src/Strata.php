<?php
namespace Strata;

use Strata\Logger\Logger;
use Strata\Router\Router;
use Strata\Utility\Hash;
use Strata\Utility\ErrorMessenger;
use Strata\Context\StrataContext;
use Strata\Model\CustomPostType\CustomPostTypeLoader;
use Strata\Middleware\MiddlewareLoader;
use Strata\Security\Security;

use Composer\Autoload\ClassLoader;
use Exception;

/**
 * Running Strata instance
 *
 * @package       Strata
 * @link          http://strata.francoisfaubert.com/docs/
 */
class Strata extends StrataContext {
    /**
     * @var array The configuration array specified in the theme's app.php
     */
    protected $_config = array();

    /**
     * @var bool Specifies the requirements have been met from the current configuration
     */
    protected $_ready = false;

    /**
     * @var \Composer\Autoload\ClassLoader Keeps a reference to the current project's Composer autoloader file.
     */
    protected $_loader = null;

    protected $_logger = null;
    private $middlewareLoader = null;

    public $i18n = null;
    public $router = null;

    /**
     * Prepares the object for its run.
     * @throws \Exception Throws an exception if the configuration array could not be loaded.
     */
    public function init()
    {
        $this->_ready = false;

        $this->configureLogger();
        $this->_includeUtils();
        $this->loadConfiguration();
        $this->setTimeZone();

        $this->localize();
        $this->middlewareLoader = new MiddlewareLoader($this->getLoader());

        $this->_ready = true;
    }

    /**
     * Kickstarts the router and preload the custom post types associated to the project.
     */
    public function run()
    {
        if (!$this->_ready) {
            $this->init();
        }

        $this->configureRouter();
        $this->configureCustomPostType();
        $this->addAppRoutes();
        $this->loadMiddleware();
        $this->improveSecurity();
    }

    public function loadConfiguration()
    {
        $this->_saveConfigValues(self::parseProjectConfigFile());

        if (is_null($this->_config)) {
            throw new Exception("Using the Strata bootstraper requires a file named 'config/strata.php' that declares a configuration array named \$strata.");
        }

        $this->_addProjectNamespace();
    }


    /**
     * Assigns a class loader to the application
     * @param ClassLoader $loader The application's class loader.
     */
    public function setLoader(ClassLoader $loader)
    {
        $this->_loader = $loader;
    }

    /**
     * Returns a class loader to the application
     * @return ClassLoader $loader The application's class loader.
     */
    public function getLoader()
    {
        return $this->_loader;
    }

    /**
     * Fetches a value in the app's configuration array for the duration of the runtime.
     * @param string $key In dot-notation format
     * @return mixed
     */
    public function getConfig($key)
    {
        return Hash::get($this->_config, $key);
    }

    /**
     * Saves a value in the app's configuration array for the duration of the runtime.
     * @param string $key In dot-notation format
     * @return mixed
     */
    public function setConfig($key, $value)
    {
        $this->_config = Hash::merge($this->_config, array($key => $value));
    }

    /**
     * Looks through all the packages in composer and if they
     * are in our Strata\Middleware namespace, we try to autoload them.
     */
    protected function loadMiddleware()
    {
        if (!is_null($this->middlewareLoader)) {
            $this->middlewareLoader->initialize();
        }
    }

    public function getMiddlewares()
    {
        return $this->middlewareLoader->getMiddlewares();
    }

    protected function configureLogger()
    {
        $this->_logger = new Logger();
    }

    public function log($message, $context = "[Strata]")
    {
        if (!is_null($this->_logger)) {
            return $this->_logger->log($message, $context);
        }
    }

    protected function localize()
    {
        $this->i18n = new I18n\I18n();
        $this->i18n->initialize();
    }

    /**
     * Configures the default router object to ensure that URL mapping
     * is automated.
     * @return null
     */
    protected function configureRouter()
    {
        $this->router = Router::urlRouting();
    }

    protected function addAppRoutes()
    {
        $routes = $this->getConfig('routes');
        if (is_array($routes)) {
            $this->router->addRoutes($routes);
        }
    }

    /**
     * Configures the post type loader
     * is automated.
     * @return null
     */
    protected function configureCustomPostType()
    {
        $postTypes = (array)$this->getConfig('custom-post-types');
        $loader = new CustomPostTypeLoader($postTypes);
        $loader->load();
    }

    /**
     * Saves an array of options to the _config attribute.
     * @param  array $values A list of project values.
     * @return null
     */
    protected function _saveConfigValues($values = array())
    {
        if (count($values) > 0) {
            $this->_config = Hash::normalize($values);
        }
    }

    /**
     * Includes the debugger classes.
     * @return boolean True if the file was correctly included.
     */
    protected function _includeUtils()
    {
        return $this->_saveCurrentPID() && $this->_includeDebug();
    }

    /**
     * Save the latest PHP process ID as a temp file in case an infinite loop
     * or any unexpected error breaks the server, but doesn't close it.
     */
    protected function _saveCurrentPID()
    {
        $pid = getmypid();

        if (!self::isCommandLineInterface()) {
            $this->log("", sprintf("[Strata] Loaded and running with process ID %s", $pid));
        }

        $filename = self::getTmpPath() . "pid";
        if (is_writable($filename)) {
            file_put_contents($filename, $pid);
        }

        return true;
    }

    protected function _includeDebug()
    {
        $debug = self::getUtilityPath() . "Debug.php";

        if (file_exists($debug)) {
            return include_once($debug);
        }
    }

    /**
     * Adds the current project namespace to the project's class loader.
     * @return null
     */
    protected function _addProjectNamespace()
    {
        $srcPath = self::getSRCPath();
        $namespace = self::getNamespace() . "\\";

        $this->_loader->setPsr4($namespace, $srcPath);
    }

    protected function setTimeZone()
    {
        $timezone = Strata::app()->getConfig("timezone");
        if (is_null($timezone)) {
            $timezone = 'America/New_York';
        }
        date_default_timezone_set($timezone);
    }

    protected function improveSecurity()
    {
        $security = new Security();
        $security->addMesures();
    }
}
