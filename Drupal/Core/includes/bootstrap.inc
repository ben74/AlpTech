<?php
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Html;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Test\TestDatabase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Utility\Error;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
define('DRUPAL_ROOT',$_SERVER['DOCUMENT_ROOT']);#dirname(dirname(__DIR__))
const DRUPAL_MINIMUM_PHP = '7.0.8';
const DRUPAL_MINIMUM_SUPPORTED_PHP = '7.0.8';
const DRUPAL_RECOMMENDED_PHP = '7.3';
const DRUPAL_MINIMUM_PHP_MEMORY_LIMIT = '64M';
const ERROR_REPORTING_HIDE = 'hide';
const ERROR_REPORTING_DISPLAY_SOME = 'some';
const ERROR_REPORTING_DISPLAY_ALL = 'all';
const ERROR_REPORTING_DISPLAY_VERBOSE = 'verbose';
const DRUPAL_ANONYMOUS_RID = AccountInterface::ANONYMOUS_ROLE;
const DRUPAL_AUTHENTICATED_RID = AccountInterface::AUTHENTICATED_ROLE;
const DRUPAL_EXTENSION_NAME_MAX_LENGTH = 50;
define('REQUEST_TIME', (int) $_SERVER['REQUEST_TIME']);
const DRUPAL_PHP_FUNCTION_PATTERN = '[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';
const CONFIG_ACTIVE_DIRECTORY = 'active';
const CONFIG_SYNC_DIRECTORY = 'sync';
const CONFIG_STAGING_DIRECTORY = 'staging';

function config_get_config_directory($type) {
    global $config_directories;
    @trigger_error('config_get_config_directory() is deprecated in drupal:8.8.0 and is removed from drupal:9.0.0. Use \Drupal\Core\Site\Settings::get(\'config_sync_directory\') instead. See https://www.drupal.org/node/3018145', E_USER_DEPRECATED);
    $config_sync_directory = Settings::get('config_sync_directory', FALSE);
    if ($config_sync_directory) {
        $config_directories[CONFIG_SYNC_DIRECTORY] = $config_sync_directory;
    }
    // @todo Remove fallback in Drupal 9. https://www.drupal.org/node/2574943
    if ($type == CONFIG_SYNC_DIRECTORY && !isset($config_directories[CONFIG_SYNC_DIRECTORY]) && isset($config_directories[CONFIG_STAGING_DIRECTORY])) {
        $type = CONFIG_STAGING_DIRECTORY;
    }

    if (!empty($config_directories[$type])) {
        return $config_directories[$type];
    }
    // @todo https://www.drupal.org/node/2696103 Throw a more specific exception.
    throw new \Exception("The configuration directory type '$type' does not exist");
}

function drupal_get_filename($type, $name, $filename = NULL) {
    // Type 'core' only exists to simplify application-level logic; it always maps
    // to the /core directory, whereas $name is ignored. It is only requested via
    // drupal_get_path(). /core/core.info.yml does not exist, but is required
    // since drupal_get_path() returns the dirname() of the returned pathname.
    if ($type === 'core') {
        return 'core/core.info.yml';
    }

    try {
        /** @var \Drupal\Core\Extension\ExtensionList $extension_list */
        $extension_list = \Drupal::service("extension.list.$type");
        if (isset($filename)) {
            // Manually add the info file path of an extension.
            $extension_list->setPathname($name, $filename);
        }
        return $extension_list->getPathname($name);
    }
    catch (ServiceNotFoundException $e) {
        // Catch the exception. This will result in triggering an error.
        // If the service is unknown, create a user-level error message.
        trigger_error(
            sprintf('Unknown type specified: "%s". Must be one of: "core", "profile", "module", "theme", or "theme_engine".', $type),
            E_USER_WARNING
        );
    }
    catch (\InvalidArgumentException $e) {
        // Catch the exception. This will result in triggering an error.
        // If the filename is still unknown, create a user-level error message.
        trigger_error(
            sprintf('The following %s is missing from the file system: %s', $type, $name),
            E_USER_WARNING
        );
    }
}

/**
 * Returns the path to a system item (module, theme, etc.).
 *
 * @param $type
 *   The type of the item; one of 'core', 'profile', 'module', 'theme', or
 *   'theme_engine'.
 * @param $name
 *   The name of the item for which the path is requested. Ignored for
 *   $type 'core'.
 *
 * @return string
 *   The path to the requested item or an empty string if the item is not found.
 */
function drupal_get_path($type, $name) {
    return dirname(drupal_get_filename($type, $name));
}

/**
 * Translates a string to the current language or to a given language.
 *
 * In order for strings to be localized, make them available in one of the ways
 * supported by the @link i18n Localization API. @endlink When possible, use
 * the \Drupal\Core\StringTranslation\StringTranslationTrait $this->t().
 * Otherwise create a new \Drupal\Core\StringTranslation\TranslatableMarkup
 * object directly.
 *
 * See \Drupal\Core\StringTranslation\TranslatableMarkup::__construct() for
 * important security information and usage guidelines.
 *
 * @param string $string
 *   A string containing the English text to translate.
 * @param array $args
 *   (optional) An associative array of replacements to make after translation.
 *   Based on the first character of the key, the value is escaped and/or
 *   themed. See
 *   \Drupal\Component\Render\FormattableMarkup::placeholderFormat() for
 *   details.
 * @param array $options
 *   (optional) An associative array of additional options, with the following
 *   elements:
 *   - 'langcode' (defaults to the current language): A language code, to
 *     translate to a language other than what is used to display the page.
 *   - 'context' (defaults to the empty context): The context the source string
 *     belongs to. See the @link i18n Internationalization topic @endlink for
 *     more information about string contexts.
 *
 * @return \Drupal\Core\StringTranslation\TranslatableMarkup
 *   An object that, when cast to a string, returns the translated string.
 *
 * @see \Drupal\Component\Render\FormattableMarkup::placeholderFormat()
 * @see \Drupal\Core\StringTranslation\StringTranslationTrait::t()
 * @see \Drupal\Core\StringTranslation\TranslatableMarkup::__construct()
 *
 * @ingroup sanitization
 */
function t($string, array $args = [], array $options = []) {
    return new TranslatableMarkup($string, $args, $options);
}

/**
 * Formats a string for HTML display by replacing variable placeholders.
 *
 * @deprecated in drupal:8.0.0 and is removed from drupal:9.0.0.
 *   Use \Drupal\Component\Render\FormattableMarkup instead.
 *
 * @see https://www.drupal.org/node/2302363
 * @see \Drupal\Component\Render\FormattableMarkup::placeholderFormat()
 * @see \Drupal\Component\Render\FormattableMarkup
 * @see t()
 * @ingroup sanitization
 */
function format_string($string, array $args) {
    @trigger_error("format_string() is deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use \Drupal\Component\Render\FormattableMarkup instead. See https://www.drupal.org/node/2302363", E_USER_DEPRECATED);
    return new FormattableMarkup($string, $args);
}

/**
 * Checks whether a string is valid UTF-8.
 *
 * All functions designed to filter input should use drupal_validate_utf8
 * to ensure they operate on valid UTF-8 strings to prevent bypass of the
 * filter.
 *
 * When text containing an invalid UTF-8 lead byte (0xC0 - 0xFF) is presented
 * as UTF-8 to Internet Explorer 6, the program may misinterpret subsequent
 * bytes. When these subsequent bytes are HTML control characters such as
 * quotes or angle brackets, parts of the text that were deemed safe by filters
 * end up in locations that are potentially unsafe; An onerror attribute that
 * is outside of a tag, and thus deemed safe by a filter, can be interpreted
 * by the browser as if it were inside the tag.
 *
 * The function does not return FALSE for strings containing character codes
 * above U+10FFFF, even though these are prohibited by RFC 3629.
 *
 * @param $text
 *   The text to check.
 *
 * @return bool
 *   TRUE if the text is valid UTF-8, FALSE if not.
 *
 * @see \Drupal\Component\Utility\Unicode::validateUtf8()
 *
 * @deprecated in drupal:8.0.0 and is removed from drupal:9.0.0.
 *   Use \Drupal\Component\Utility\Unicode::validateUtf8().
 *
 * @see https://www.drupal.org/node/1992584
 */
function drupal_validate_utf8($text) {
    return Unicode::validateUtf8($text);
}

/**
 * Logs an exception.
 *
 * This is a wrapper logging function which automatically decodes an exception.
 *
 * @param $type
 *   The category to which this message belongs.
 * @param $exception
 *   The exception that is going to be logged.
 * @param $message
 *   The message to store in the log. If empty, a text that contains all useful
 *   information about the passed-in exception is used.
 * @param $variables
 *   Array of variables to replace in the message on display or
 *   NULL if message is already translated or not possible to
 *   translate.
 * @param $severity
 *   The severity of the message, as per RFC 3164.
 * @param $link
 *   A link to associate with the message.
 *
 * @see \Drupal\Core\Utility\Error::decodeException()
 */
function watchdog_exception($type, Exception $exception, $message = NULL, $variables = [], $severity = RfcLogLevel::ERROR, $link = NULL) {

    // Use a default value if $message is not set.
    if (empty($message)) {
        $message = '%type: @message in %function (line %line of %file).';
    }

    if ($link) {
        $variables['link'] = $link;
    }

    $variables += Error::decodeException($exception);

    \Drupal::logger($type)->log($severity, $message, $variables);
}

function drupal_set_message($message = NULL, $type = 'status', $repeat = FALSE) {
    @trigger_error('drupal_set_message() is deprecated in Drupal 8.5.0 and will be removed before Drupal 9.0.0. Use \Drupal\Core\Messenger\MessengerInterface::addMessage() instead. See https://www.drupal.org/node/2774931', E_USER_DEPRECATED);
    $messenger = \Drupal::messenger();
    if (isset($message)) {
        $messenger->addMessage($message, $type, $repeat);
    }
    return $messenger->all();
}

function drupal_get_messages($type = NULL, $clear_queue = TRUE) {
    @trigger_error('drupal_get_message() is deprecated in Drupal 8.5.0 and will be removed before Drupal 9.0.0. Use \Drupal\Core\Messenger\MessengerInterface::all() or \Drupal\Core\Messenger\MessengerInterface::messagesByType() instead. See https://www.drupal.org/node/2774931', E_USER_DEPRECATED);
    $messenger = \Drupal::messenger();
    if ($messages = $messenger->all()) {
        if ($type) {
            if ($clear_queue) {
                $messenger->deleteByType($type);
            }
            if (isset($messages[$type])) {
                return [$type => $messages[$type]];
            }
        }
        else {
            if ($clear_queue) {
                $messenger->deleteAll();
            }
            return $messages;
        }
    }
    return [];
}

function drupal_get_user_timezone() {
    @trigger_error('drupal_get_user_timezone() is deprecated in drupal:8.8.0. It will be removed from drupal:9.0.0. Use date_default_timezone_get() instead. See https://www.drupal.org/node/3009387', E_USER_DEPRECATED);
    return date_default_timezone_get();
}

function _drupal_error_handler($error_level, $message, $filename = NULL, $line = NULL, $context = NULL) {
    require_once __DIR__ . '/errors.inc';
    _drupal_error_handler_real($error_level, $message, $filename, $line, $context);
}

function _drupal_exception_handler($exception) {
    require_once __DIR__ . '/errors.inc';
    $a=1;
    try {
        // Log the message to the watchdog and return an error page to the user.
        _drupal_log_error(Error::decodeException($exception), TRUE);
    }
        // Catch \Throwable, which covers both Error and Exception throwables.
    catch (\Throwable $error) {
        _drupal_exception_handler_additional($exception, $error);
    }
}

function _drupal_exception_handler_additional($exception, $exception2) {
    // Another uncaught exception was thrown while handling the first one.
    // If we are displaying errors, then do so with no possibility of a further
    // uncaught exception being thrown.
    if (error_displayable()) {
        print '<h1>Additional uncaught exception thrown while handling exception.</h1>';
        print '<h2>Original</h2><p>' . Error::renderExceptionSafe($exception) . '</p>';
        print '<h2>Additional</h2><p>' . Error::renderExceptionSafe($exception2) . '</p><hr />';
    }
}

function drupal_valid_test_ua($new_prefix = NULL) {
    static $test_prefix;

    if (isset($new_prefix)) {
        $test_prefix = $new_prefix;
    }
    if (isset($test_prefix)) {
        return $test_prefix;
    }
    // Unless the below User-Agent and HMAC validation succeeds, we are not in
    // a test environment.
    $test_prefix = FALSE;

    // A valid Simpletest request will contain a hashed and salted authentication
    // code. Check if this code is present in a cookie or custom user agent
    // string.
    $http_user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : NULL;
    $user_agent = isset($_COOKIE['SIMPLETEST_USER_AGENT']) ? $_COOKIE['SIMPLETEST_USER_AGENT'] : $http_user_agent;
    if (isset($user_agent) && preg_match("/^simple(\w+\d+):(.+):(.+):(.+)$/", $user_agent, $matches)) {
        list(, $prefix, $time, $salt, $hmac) = $matches;
        $check_string = $prefix . ':' . $time . ':' . $salt;
        // Read the hash salt prepared by drupal_generate_test_ua().
        // This function is called before settings.php is read and Drupal's error
        // handlers are set up. While Drupal's error handling may be properly
        // configured on production sites, the server's PHP error_reporting may not.
        // Ensure that no information leaks on production sites.
        $test_db = new TestDatabase($prefix);
        $key_file = DRUPAL_ROOT . '/' . $test_db->getTestSitePath() . '/.htkey';
        if (!is_readable($key_file) || is_dir($key_file)) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
            exit;
        }
        $private_key = file_get_contents($key_file);
        // The string from drupal_generate_test_ua() is 74 bytes long. If we don't
        // have it, tests cannot be allowed.
        if (empty($private_key) || strlen($private_key) < 74) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
            exit;
        }
        // The file properties add more entropy not easily accessible to others.
        $key = $private_key . filectime(__FILE__) . fileinode(__FILE__);
        $time_diff = REQUEST_TIME - $time;
        $test_hmac = Crypt::hmacBase64($check_string, $key);
        // Since we are making a local request a 600 second time window is allowed,
        // and the HMAC must match.
        if ($time_diff >= 0 && $time_diff <= 600 && hash_equals($test_hmac, $hmac)) {
            $test_prefix = $prefix;
        }
        else {
            header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden (SIMPLETEST_USER_AGENT invalid)');
            exit;
        }
    }
    return $test_prefix;
}

/**
 * Generates a user agent string with a HMAC and timestamp for simpletest.
 */
function drupal_generate_test_ua($prefix) {
    static $key, $last_prefix;

    if (!isset($key) || $last_prefix != $prefix) {
        $last_prefix = $prefix;
        $test_db = new TestDatabase($prefix);
        $key_file = DRUPAL_ROOT . '/' . $test_db->getTestSitePath() . '/.htkey';
        // When issuing an outbound HTTP client request from within an inbound test
        // request, then the outbound request has to use the same User-Agent header
        // as the inbound request. A newly generated private key for the same test
        // prefix would invalidate all subsequent inbound requests.
        // @see \Drupal\Core\Test\HttpClientMiddleware\TestHttpClientMiddleware
        if (DRUPAL_TEST_IN_CHILD_SITE && $parent_prefix = drupal_valid_test_ua()) {
            if ($parent_prefix != $prefix) {
                throw new \RuntimeException("Malformed User-Agent: Expected '$parent_prefix' but got '$prefix'.");
            }
            // If the file is not readable, a PHP warning is expected in this case.
            $private_key = file_get_contents($key_file);
        }
        else {
            // Generate and save a new hash salt for a test run.
            // Consumed by drupal_valid_test_ua() before settings.php is loaded.
            $private_key = Crypt::randomBytesBase64(55);
            file_put_contents($key_file, $private_key);
        }
        // The file properties add more entropy not easily accessible to others.
        $key = $private_key . filectime(__FILE__) . fileinode(__FILE__);
    }
    // Generate a moderately secure HMAC based on the database credentials.
    $salt = uniqid('', TRUE);
    $check_string = $prefix . ':' . time() . ':' . $salt;
    return 'simple' . $check_string . ':' . Crypt::hmacBase64($check_string, $key);
}

/**
 * Enables use of the theme system without requiring database access.
 *
 * Loads and initializes the theme system for site installs, updates and when
 * the site is in maintenance mode. This also applies when the database fails.
 *
 * @see _drupal_maintenance_theme()
 */
function drupal_maintenance_theme() {
    require_once __DIR__ . '/theme.maintenance.inc';
    _drupal_maintenance_theme();
}

function drupal_installation_attempted() {
    @trigger_error('drupal_installation_attempted() is deprecated in drupal:8.8.0 and is removed from drupal:9.0.0. Use \Drupal\Core\Installer\InstallerKernel::installationAttempted() instead. See https://www.drupal.org/node/3035275', E_USER_DEPRECATED);
    return InstallerKernel::installationAttempted();
}

function drupal_get_profile() {
    global $install_state;

    @trigger_error('drupal_get_profile() is deprecated in drupal:8.3.0 and is removed from drupal:9.0.0. Use the install_profile container parameter or \Drupal::installProfile() instead. If you are accessing the value before it is written to configuration during the installer use the $install_state global. If you need to access the value before container is available you can use BootstrapConfigStorageFactory to load the value directly from configuration. See https://www.drupal.org/node/2538996', E_USER_DEPRECATED);

    if (InstallerKernel::installationAttempted()) {
        // If the profile has been selected return it.
        if (isset($install_state['parameters']['profile'])) {
            $profile = $install_state['parameters']['profile'];
        }
        else {
            $profile = NULL;
        }
    }
    else {
        if (\Drupal::hasContainer()) {
            $profile = \Drupal::installProfile();
        }
        else {
            $profile = BootstrapConfigStorageFactory::getDatabaseStorage()->read('core.extension')['profile'];
        }
    }

    return $profile;
}

function drupal_classloader_register($name, $path) {
    @trigger_error('drupal_classloader_register() is deprecated in Drupal 8.8.0 and will be removed before Drupal 9.0.0. Use the method ::addPsr4() of the class_loader service to register the namespace. See https://www.drupal.org/node/3035275.', E_USER_DEPRECATED);
    $loader = \Drupal::service('class_loader');
    $loader->addPsr4('Drupal\\' . $name . '\\', \Drupal::root() . '/' . $path . '/src');
}

function &drupal_static($name, $default_value = NULL, $reset = FALSE) {
    static $data = [], $default = [];
    // First check if dealing with a previously defined static variable.
    if (isset($data[$name]) || array_key_exists($name, $data)) {
        // Non-NULL $name and both $data[$name] and $default[$name] statics exist.
        if ($reset) {
            // Reset pre-existing static variable to its default value.
            $data[$name] = $default[$name];
        }
        return $data[$name];
    }
    // Neither $data[$name] nor $default[$name] static variables exist.
    if (isset($name)) {
        if ($reset) {
            // Reset was called before a default is set and yet a variable must be
            // returned.
            return $data;
        }
        // First call with new non-NULL $name. Initialize a new static variable.
        $default[$name] = $data[$name] = $default_value;
        return $data[$name];
    }
    // Reset all: ($name == NULL). This needs to be done one at a time so that
    // references returned by earlier invocations of drupal_static() also get
    // reset.
    foreach ($default as $name => $value) {
        $data[$name] = $value;
    }
    // As the function returns a reference, the return should always be a
    // variable.
    return $data;
}

function drupal_static_reset($name = NULL) {
    drupal_static($name, NULL, TRUE);
}

function drupal_placeholder($text) {
    return '<em class="placeholder">' . Html::escape($text) . '</em>';
}

function &drupal_register_shutdown_function($callback = NULL) {
    // We cannot use drupal_static() here because the static cache is reset during
    // batch processing, which breaks batch handling.
    static $callbacks = [];

    if (isset($callback)) {
        // Only register the internal shutdown function once.
        if (empty($callbacks)) {
            register_shutdown_function('_drupal_shutdown_function');
        }
        $args = func_get_args();
        // Remove $callback from the arguments.
        unset($args[0]);
        // Save callback and arguments
        $callbacks[] = ['callback' => $callback, 'arguments' => $args];
    }
    return $callbacks;
}

/**
 * Executes registered shutdown functions.
 */
function _drupal_shutdown_function() {
    $callbacks = &drupal_register_shutdown_function();

    // Set the CWD to DRUPAL_ROOT as it is not guaranteed to be the same as it
    // was in the normal context of execution.
    chdir(DRUPAL_ROOT);

    try {
        reset($callbacks);
        // Do not use foreach() here because it is possible that the callback will
        // add to the $callbacks array via drupal_register_shutdown_function().
        while ($callback = current($callbacks)) {
            call_user_func_array($callback['callback'], $callback['arguments']);
            next($callbacks);
        }
    }
        // Catch \Throwable, which covers both Error and Exception throwables.
    catch (\Throwable $error) {
        _drupal_shutdown_function_handle_exception($error);
    }
}

/**
 * Displays and logs any errors that may happen during shutdown.
 *
 * @param \Exception|\Throwable $exception
 *   The exception object that was thrown.
 *
 * @see _drupal_shutdown_function()
 */
function _drupal_shutdown_function_handle_exception($exception) {
    // If using PHP-FPM then fastcgi_finish_request() will have been fired
    // preventing further output to the browser.
    if (!function_exists('fastcgi_finish_request')) {
        // If we are displaying errors, then do so with no possibility of a
        // further uncaught exception being thrown.
        require_once __DIR__ . '/errors.inc';
        if (error_displayable()) {
            print '<h1>Uncaught exception thrown in shutdown function.</h1>';
            print '<p>' . Error::renderExceptionSafe($exception) . '</p><hr />';
        }
    }
    error_log($exception);
}
