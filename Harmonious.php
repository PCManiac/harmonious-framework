<?php
    //Ensure PHP session IDs only use the characters [a-z0-9]
    ini_set('session.hash_bits_per_character', 4);
    ini_set('session.hash_function', 0);

    //Slim's Encryted Cookies rely on libmcyrpt and these two constants.
    //If libmycrpt is unavailable, we ensure the expected constants
    //are available to avoid errors.
    if ( !defined('MCRYPT_RIJNDAEL_256') ) {
        define('MCRYPT_RIJNDAEL_256', 0);
    }
    if ( !defined('MCRYPT_MODE_CBC') ) {
        define('MCRYPT_MODE_CBC', 0);
    }

    //This determines which errors are reported by PHP. By default, all
    //errors (including E_STRICT) are reported.
    error_reporting(E_ALL | E_STRICT);

    //PHP 5.3 will complain if you don't set a timezone. If you do not
    //specify your own timezone before requiring Slim, this tells PHP to use UTC.
    if ( @date_default_timezone_set(date_default_timezone_get()) === false ) {
        date_default_timezone_set('UTC');
    }

    spl_autoload_register(array('Harmonious', 'autoloader'));
         
    class Harmonious
    {
        protected $template_params = array();
        protected $components;        
        protected $request;
        protected $response;
        protected $view;
        protected $log;
        protected $mode;
        protected $settings;
        protected $hooks = array(
            'app.before' => array(array()),
            'app.before.router' => array(array()),
            'app.before.run' => array(array()),
            'app.after.run' => array(array()),
            'app.after.router' => array(array()),
            'app.after' => array(array()),
            'error.not.found' => array(array()),
            'error.500' => array(array()),
            'error.halt' => array(array())
        );
        
        /**
         * Constructor
         * @param   array $userSettings
         * @return  void
         */
        public function __construct( $userSettings = array() ) {            
            //Merge application settings
            $this->settings = array_merge(array(
                //Mode
                'mode' => 'development',
                //Logging
                'log.enable' => false,
                'log.logger' => null,
                'log.path' => './logs',
                'log.level' => 4,
                //Debugging
                'debug' => true,
                //View
                'templates.path' => './templates',
                'view' => 'Slim_View',
                //Settings for all cookies
                'cookies.lifetime' => '20 minutes',
                'cookies.path' => '/',
                'cookies.domain' => '',
                'cookies.secure' => false,
                'cookies.httponly' => false,
                //Settings for encrypted cookies
                'cookies.secret_key' => 'CHANGE_ME',
                'cookies.cipher' => MCRYPT_RIJNDAEL_256,
                'cookies.cipher_mode' => MCRYPT_MODE_CBC,
                'cookies.encrypt' => true,
                'cookies.user_id' => 'DEFAULT',
                //Session handler
                'session.handler' => null, //new Harmonious_Session_Handler_Files(),
                'session.flash_key' => 'flash',
                //HTTP
                'http.version' => null,
                'controller_path'=>'./controllers'
            ), $userSettings);

            //Determine application mode
            $this->getMode();

            $this->components = new Harmonious_Components_Factory($this);
            
            //Setup HTTP request and response handling
            $this->request = $this->components['request'];
            $this->response = $this->components['response'];;
            $this->response->setCookieJar(new Slim_Http_CookieJar($this->settings['cookies.secret_key'], array(
                'high_confidentiality' => $this->settings['cookies.encrypt'],
                'mcrypt_algorithm' => $this->settings['cookies.cipher'],
                'mcrypt_mode' => $this->settings['cookies.cipher_mode'],
                'enable_ssl' => $this->settings['cookies.secure']
            )));
            $this->response->httpVersion($this->settings['http.version']);

            //Start session if not already started
            if ( session_id() === '' ) {
                $sessionHandler = $this->config('session.handler');
                if ( $sessionHandler instanceof Harmonious_Session_Handler ) {
                    $sessionHandler->register($this);
                }
                session_cache_limiter(false); 
                session_start();
            }

            //Setup view
            $this->view($this->config('view'));

            //Set global Error handler after Harmonious app instantiated
            set_error_handler(array('Harmonious', 'handleErrors'));

            //set_exception_handler(array('Harmonious', 'handleExceptions'));
            
            register_shutdown_function(array($this, 'FatalErrorCatcher'));
        }
   
        /**
         * Run the Harmonious application
         * @return void
         */
        public function run() {
            try {
                try {            
                    $this->applyHook('app.before', $this);
                    ob_start();
                    $this->applyHook('app.before.router', $this);
                    $httpMethod = $this->request->getMethod();
                    $uri = rtrim($this->request->getResourceUri(), "/");
                    if ($uri == '') $uri = '/index';
                    $controller_name = $this->config('controller_path') . $uri . "_controller.php";
                    $controller_class_name = substr($uri, strrpos($uri, '/') + 1) . "Controller";
                    if (file_exists($controller_name)) {
                        include ($controller_name);
                        //создать экземпляр класса и проверить поддерживается ли http метод, если нет - ошибка 405
                        $controller = new $controller_class_name();
                        if (!$controller->supportsHttpMethod($httpMethod)) $this->halt(405, 'HTTP Method not supported');
                        $this->applyHook('app.before.run', $this);
                        $controller->run($this);
                        $this->applyHook('app.after.run', $this);
                    } else {
                        //404
                        $this->getLog()->debug('404 controller_name: ' . $controller_name);
                        $this->notFound();
                    }
                    $this->response->write(ob_get_clean());
                    $this->applyHook('app.after.router', $this);
                    session_write_close();
                    $this->response->send();
                    $this->applyHook('app.after', $this);
                } catch ( Exception $e ) {
                    if ( $e instanceof Slim_Exception_Stop ) throw $e;
                    $this->getLog()->error($e);
                    if ( $this->config('debug') === true ) {
                        $this->halt(500, self::generateErrorMarkup($e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()));
                    } else {
                        $this->error($e);
                    }
                }
            } catch ( Slim_Exception_Stop $e ) {
                //Exit application context
            }
        }    
        
        /**
         * "Магические" методы для работы с компонентами Harmonious.
         * http://www.php.net/manual/ru/language.oop5.magic.php
         * 
         * Возвращает компонент Harmonious из фабрики. 
         * @param type $name - Имя компонента
         * @return type      - объект(компонент Harmonious)
         */
        public function __get($name) {
            if ($name == 'components') return $this->components;
            return $this->components[$name];
        }
        
        /**
         * Добавляет компонент в фабрику
         * @param type $name    - имя компонента
         * @param type $value   - объект(компонент Harmonious)
         */
        public function __set($name, $value)
        {
            $this->components[$name] = $value;
        }
        
        /****** Параметры шаблона ******/
        
        /**
         * Глобальный массив параметров шаблона используется для наполнения шаблона параметрами из различных
         * участков кода. Например, часть параметров может быть передана посредством хука, а остальные посредством
         * контроллена. См. также метод render, в котором этот глобальные массив объединяется с переданным перед
         * передачей в шаблон.
         * 
         * Объединяет два массива: переданный в параметре и глобальный массив параметров шаблона
         * @param type $array 
         */
        public function addTemplateParam($array) {
            $this->template_params = array_merge($this->template_params, $array);
        }
        
        /**
         * Чистит глобальный массив параметров шаблона
         */
        public function clearTemplateParams() {
            unset($this->template_params);
            $this->template_params = array();
        }
                
        /**
         * Возвращает глобальный массив параметров шаблона
         * @return type 
         */
        public function getTemplateParams() {
            return $this->template_params;
        }
        
        /**
         * Render a template
         * @param   string  $template   The name of the template passed into the View::render method
         * @param   array   $data       Associative array of data made available to the View
         * @param   int     $status     The HTTP response status code to use (Optional)
         * @return  void
         */
        public function render( $template, $data = array(), $status = null ) {
            if ( !is_null($status) ) $this->response->status($status);
            $data = array_merge($this->template_params, $data);
            $this->view->appendData($data);
            $this->view->display($template);
        }     
        
        /**
         * Render a template
         * @param   string  $template   The name of the template passed into the View::render method
         * @param   array   $data       Associative array of data made available to the View
         * @param   int     $status     The HTTP response status code to use (Optional)
         * @return  void
         */
        public function fetch( $template, $data = array(), $status = null ) {
            if ( !is_null($status) ) $this->response->status($status);
            $data = array_merge($this->template_params, $data);
            $this->view->appendData($data);
            return $this->view->fetch($template);
        }   
        
        /**
         * Harmonious auto-loader
         * Этот метод производит загрузку файлов с классами при первом обращении к ним. Грузит свои классы и классы Slim-а
         * @return void
         */
        public static function autoloader( $className ) {
            $className = str_replace('Slim', 'Harmonious', $className);
            
            if ( strpos($className, 'Harmonious') !== 0 ) {
                return;
            }
            $file = dirname(__FILE__) . '/' . str_replace('_', DIRECTORY_SEPARATOR, substr($className, 11)) . '.php';
            
            if ( file_exists($file) ) {
                require $file;
            }
        }
        
        
        /***** CONFIGURATION *****/        
        /**
         * Get application mode
         * @return string
         */
        public function getMode() {
        if ( !isset($this->mode) ) {
            $mode = $this->config('mode');
            if ( !is_null($mode) ) $this->mode = (string)$this->config('mode');
            else $this->mode = 'production';
        }
        return $this->mode;
        }
        
        /**
         * Configure Slim for a given mode
         *
         * This method will immediately invoke the callable if
         * the specified mode matches the current application mode.
         * Otherwise, the callable is ignored. This should be called
         * only _after_ you initialize your Slim app.
         *
         * @param   string  $mode
         * @param   mixed   $callable
         * @return  void
         */
        public function configureMode( $mode, $callable ) {
            if ( $mode === $this->getMode() && is_callable($callable) ) {
                call_user_func($callable);
            }
        }

        /**
         * Configure Slim Settings
         *
         * This method defines application settings and acts as a setter and a getter.
         *
         * If only one argument is specified and that argument is a string, the value
         * of the setting identified by the first argument will be returned, or NULL if
         * that setting does not exist.
         *
         * If only one argument is specified and that argument is an associative array,
         * the array will be merged into the existing application settings.
         *
         * If two arguments are provided, the first argument is the name of the setting
         * to be created or updated, and the second argument is the setting value.
         *
         * @param   string|array    $name   If a string, the name of the setting to set or retrieve. Else an associated array of setting names and values
         * @param   mixed           $value  If name is a string, the value of the setting identified by $name
         * @return  mixed           The value of a setting if only one argument is a string
         */
        public function config( $name, $value = null ) {
            if ( func_num_args() === 1 ) {
                if ( is_array($name) ) $this->settings = array_merge($this->settings, $name);
                else return in_array($name, array_keys($this->settings)) ? $this->settings[$name] : null;
            } else {
                $this->settings[$name] = $value;
            }
        }            
        
        /***** LOGGING *****/

        /**
         * Get application Log (lazy-loaded)
         * @return Slim_Log
         */
        public function getLog() {
            if ( !isset($this->log) ) {
                $this->log = new Slim_Log();
                $this->log->setEnabled($this->config('log.enable'));
                $logger = $this->config('log.logger');
                if ( $logger ) {
                    $this->log->setLogger($logger);
                } else {
                    $this->log->setLogger(new Slim_Logger($this->config('log.path'), $this->config('log.level')));
                }
            }
            return $this->log;
        }      

        /**
         * Not Found Handler
         * @return  void
         */
        public function notFound() {
                ob_start();
                $this->applyHook('error.not.found', $this);
                if (ob_get_contents() == '') call_user_func(array($this, 'defaultNotFound'));
                $this->halt(404, ob_get_clean());
        }

        /**
         * Error Handler
         * @return  void
         */
        public function error( $argument = null ) {
                ob_start();
                $this->applyHook('error.500', $argument );
                call_user_func_array(array($this, 'defaultError'), array($argument));
                $this->halt(500, ob_get_clean());
        }

        /***** ACCESSORS *****/        
        /**
         * Get and/or set the View
         *
         * This method declares the View to be used by the Slim application.
         * If the argument is a string, Slim will instantiate a new object
         * of the same class. If the argument is an instance of View or a subclass
         * of View, Slim will use the argument as the View.
         *
         * If a View already exists and this method is called to create a
         * new View, data already set in the existing View will be
         * transferred to the new View.
         *
         * @param   string|Slim_View $viewClass  The name of a Slim_View class;
         *                                       An instance of Slim_View;
         * @return  Slim_View
         */
        public function view( $viewClass = null ) {
            if ( !is_null($viewClass) ) {
                $existingData = is_null($this->view) ? array() : $this->view->getData();
                if ( $viewClass instanceOf Slim_View ) {
                    $this->view = $viewClass;
                } else {
                    $this->view = new $viewClass();
                }
                $this->view->appendData($existingData);
                $this->view->setTemplatesDirectory($this->config('templates.path'));
            }
            return $this->view;
        }

        /***** HTTP CACHING *****/

        /**
         * Set Last-Modified HTTP Response Header
         *
         * Set the HTTP 'Last-Modified' header and stop if a conditional
         * GET request's `If-Modified-Since` header matches the last modified time
         * of the resource. The `time` argument is a UNIX timestamp integer value.
         * When the current request includes an 'If-Modified-Since' header that
         * matches the specified last modified time, the application will stop
         * and send a '304 Not Modified' response to the client.
         *
         * @param   int                         $time   The last modified UNIX timestamp
         * @throws  SlimException                       Returns HTTP 304 Not Modified response if resource last modified time matches `If-Modified-Since` header
         * @throws  InvalidArgumentException            If provided timestamp is not an integer
         * @return  void
         */
        public function lastModified( $time ) {
            if ( is_integer($time) ) {
                $this->response->header('Last-Modified', date(DATE_RFC1123, $time));
                if ( $time === strtotime($this->request->headers('IF_MODIFIED_SINCE')) ) $this->halt(304);
            } else {
                throw new InvalidArgumentException('Slim::lastModified only accepts an integer UNIX timestamp value.');
            }
        }

        /**
         * Set ETag HTTP Response Header
         *
         * Set the etag header and stop if the conditional GET request matches.
         * The `value` argument is a unique identifier for the current resource.
         * The `type` argument indicates whether the etag should be used as a strong or
         * weak cache validator.
         *
         * When the current request includes an 'If-None-Match' header with
         * a matching etag, execution is immediately stopped. If the request
         * method is GET or HEAD, a '304 Not Modified' response is sent.
         *
         * @param   string                      $value  The etag value
         * @param   string                      $type   The type of etag to create; either "strong" or "weak"
         * @throws  InvalidArgumentException            If provided type is invalid
         * @return  void
         */
        public function etag( $value, $type = 'strong' ) {

            //Ensure type is correct
            if ( !in_array($type, array('strong', 'weak')) ) {
                throw new InvalidArgumentException('Invalid Slim::etag type. Expected "strong" or "weak".');
            }

            //Set etag value
            $value = '"' . $value . '"';
            if ( $type === 'weak' ) $value = 'W/'.$value;
            $this->response->header('ETag', $value);

            //Check conditional GET
            if ( $etagsHeader = $this->request->headers('IF_NONE_MATCH')) {
                $etags = preg_split('@\s*,\s*@', $etagsHeader);
                if ( in_array($value, $etags) || in_array('*', $etags) ) $this->halt(304);
            }

        }

        /***** COOKIES *****/

        /**
         * Set a normal, unencrypted Cookie
         *
         * @param   string  $name       The cookie name
         * @param   mixed   $value      The cookie value
         * @param   mixed   $time       The duration of the cookie;
         *                              If integer, should be UNIX timestamp;
         *                              If string, converted to UNIX timestamp with `strtotime`;
         * @param   string  $path       The path on the server in which the cookie will be available on
         * @param   string  $domain     The domain that the cookie is available to
         * @param   bool    $secure     Indicates that the cookie should only be transmitted over a secure
         *                              HTTPS connection to/from the client
         * @param   bool    $httponly   When TRUE the cookie will be made accessible only through the HTTP protocol
         * @return  void
         */
        public function setCookie( $name, $value, $time = null, $path = null, $domain = null, $secure = null, $httponly = null ) {
            $time = is_null($time) ? $this->config('cookies.lifetime') : $time;
            $path = is_null($path) ? $this->config('cookies.path') : $path;
            $domain = is_null($domain) ? $this->config('cookies.domain') : $domain;
            $secure = is_null($secure) ? $this->config('cookies.secure') : $secure;
            $httponly = is_null($httponly) ? $this->config('cookies.httponly') : $httponly;
            $this->response->getCookieJar()->setClassicCookie($name, $value, $time, $path, $domain, $secure, $httponly);
        }

        /**
         * Get the value of a Cookie from the current HTTP Request
         *
         * Return the value of a cookie from the current HTTP request,
         * or return NULL if cookie does not exist. Cookies created during
         * the current request will not be available until the next request.
         *
         * @param   string $name
         * @return  string|null
         */
        public function getCookie( $name ) {
            return $this->request->cookies($name);
        }

        /**
         * Set an encrypted Cookie
         *
         * @param   string  $name       The cookie name
         * @param   mixed   $value      The cookie value
         * @param   mixed   $time       The duration of the cookie;
         *                              If integer, should be UNIX timestamp;
         *                              If string, converted to UNIX timestamp with `strtotime`;
         * @param   string  $path       The path on the server in which the cookie will be available on
         * @param   string  $domain     The domain that the cookie is available to
         * @param   bool    $secure     Indicates that the cookie should only be transmitted over a secure
         *                              HTTPS connection from the client
         * @param   bool    $httponly   When TRUE the cookie will be made accessible only through the HTTP protocol
         * @return  void
         */
        public function setEncryptedCookie( $name, $value, $time = null, $path = null, $domain = null, $secure = null, $httponly = null ) {
            $time = is_null($time) ? $this->config('cookies.lifetime') : $time;
            $path = is_null($path) ? $this->config('cookies.path') : $path;
            $domain = is_null($domain) ? $this->config('cookies.domain') : $domain;
            $secure = is_null($secure) ? $this->config('cookies.secure') : $secure;
            $httponly = is_null($httponly) ? $this->config('cookies.httponly') : $httponly;
            $userId = $this->config('cookies.user_id');
            $this->response->getCookieJar()->setCookie($name, $value, $userId, $time, $path, $domain, $secure, $httponly);
        }

        /**
         * Get the value of an encrypted Cookie from the current HTTP request
         *
         * Return the value of an encrypted cookie from the current HTTP request,
         * or return NULL if cookie does not exist. Encrypted cookies created during
         * the current request will not be available until the next request.
         *
         * @param   string $name
         * @return  string|null
         */
        public function getEncryptedCookie( $name ) {
            $value = $this->response->getCookieJar()->getCookieValue($name);
            return ($value === false) ? null : $value;
        }

        /**
         * Delete a Cookie (for both normal or encrypted Cookies)
         *
         * Remove a Cookie from the client. This method will overwrite an existing Cookie
         * with a new, empty, auto-expiring Cookie. This method's arguments must match
         * the original Cookie's respective arguments for the original Cookie to be
         * removed. If any of this method's arguments are omitted or set to NULL, the
         * default Cookie setting values (set during Slim::init) will be used instead.
         *
         * @param   string  $name       The cookie name
         * @param   string  $path       The path on the server in which the cookie will be available on
         * @param   string  $domain     The domain that the cookie is available to
         * @param   bool    $secure     Indicates that the cookie should only be transmitted over a secure
         *                              HTTPS connection from the client
         * @param   bool    $httponly   When TRUE the cookie will be made accessible only through the HTTP protocol
         * @return  void
         */
        public function deleteCookie( $name, $path = null, $domain = null, $secure = null, $httponly = null ) {
            $path = is_null($path) ? $this->config('cookies.path') : $path;
            $domain = is_null($domain) ? $this->config('cookies.domain') : $domain;
            $secure = is_null($secure) ? $this->config('cookies.secure') : $secure;
            $httponly = is_null($httponly) ? $this->config('cookies.httponly') : $httponly;
            $this->response->getCookieJar()->deleteCookie( $name, $path, $domain, $secure, $httponly );
        }

        /***** HELPERS *****/

        /**
         * Get the Slim application's absolute directory path
         *
         * This method returns the absolute path to the Slim application's
         * directory. If the Slim application is installed in a public-accessible
         * sub-directory, the sub-directory path will be included. This method
         * will always return an absolute path WITH a trailing slash.
         *
         * @return string
         */
        public function root() {
            return rtrim($_SERVER['DOCUMENT_ROOT'], '/') . rtrim($this->request->getRootUri(), '/') . '/';
        }

        /**
         * Stop
         *
         * Send the current Response as is and stop executing the Slim
         * application. The thrown exception will be caught by the Slim
         * custom exception handler which exits this script.
         *
         * @throws  Slim_Exception_Stop
         * @return  void
         */
        public function stop() {
            session_write_close();
            $this->response->send();
            throw new Slim_Exception_Stop();
        }

        /**
         * Halt
         *
         * Halt the application and immediately send an HTTP response with a
         * specific status code and body. This may be used to send any type of
         * response: info, success, redirect, client error, or server error.
         * If you need to render a template AND customize the response status,
         * you should use Slim::render() instead.
         *
         * @param   int                 $status     The HTTP response status
         * @param   string              $message    The HTTP response body
         * @return  void
         */
        public function halt( $status, $message = '') {
            if ( ob_get_level() !== 0 ) {
                ob_clean();
            }
            $this->applyHook('error.halt', $this);
            $this->response->status($status);
            if (ob_get_contents() == '') $this->response->body($message);
            $this->stop();
        }

        /**
         * Set the HTTP response Content-Type
         * @param   string $type The Content-Type for the Response (ie. text/html)
         * @return  void
         */
        public function contentType( $type ) {
            $this->response->header('Content-Type', $type);
        }

        /**
         * Set the HTTP response status code
         * @param   int $status The HTTP response status code
         * @return  void
         */
        public function status( $code ) {
            $this->response->status($code);
        }

        /**
         * Redirect
         *
         * This method immediately redirects to a new URL. By default,
         * this issues a 302 Found response; this is considered the default
         * generic redirect response. You may also specify another valid
         * 3xx status code if you want. This method will automatically set the
         * HTTP Location header for you using the URL parameter and place the
         * destination URL into the response body.
         *
         * @param   string                      $url        The destination URL
         * @param   int                         $status     The HTTP redirect status code (Optional)
         * @throws  InvalidArgumentException                If status parameter is not a valid 3xx status code
         * @return  void
         */
        public function redirect( $url, $status = 302 ) {
            if ( $status >= 300 && $status <= 307 ) {
                $this->response->header('Location', (string)$url);
                $this->halt($status, (string)$url);
            } else {
                throw new InvalidArgumentException('Slim::redirect only accepts HTTP 300-307 status codes.');
            }
        }  
        
        /***** HOOKS *****/

        /**
         * Assign hook
         * @param   string  $name       The hook name
         * @param   mixed   $callable   A callable object
         * @param   int     $priority   The hook priority; 0 = high, 10 = low
         * @return  void
         */
        public function hook( $name, $callable, $priority = 10 ) {
            if ( !isset($this->hooks[$name]) ) {
                $this->hooks[$name] = array(array());
            }
            if ( is_callable($callable) ) {
                $this->hooks[$name][(int)$priority][] = $callable;
            }
        }

        /**
         * Invoke hook
         * @param   string  $name       The hook name
         * @param   mixed   $hookArgs   (Optional) Argument for hooked functions
         * @return  mixed
         */
        public function applyHook( $name, $hookArg = null ) {
            if ( !isset($this->hooks[$name]) ) {
                $this->hooks[$name] = array(array());
            }
            if( !empty($this->hooks[$name]) ) {
                // Sort by priority, low to high, if there's more than one priority
                if ( count($this->hooks[$name]) > 1 ) {
                    ksort($this->hooks[$name]);
                }
                foreach( $this->hooks[$name] as $priority ) {
                    if( !empty($priority) ) {
                        foreach($priority as $callable) {
                            $hookArg = call_user_func($callable, $hookArg);
                        }
                    }
                }
                return $hookArg;
            }
        }

        /**
         * Get hook listeners
         *
         * Return an array of registered hooks. If `$name` is a valid
         * hook name, only the listeners attached to that hook are returned.
         * Else, all listeners are returned as an associative array whose
         * keys are hook names and whose values are arrays of listeners.
         *
         * @param   string      $name A hook name (Optional)
         * @return  array|null
         */
        public function getHooks( $name = null ) {
            if ( !is_null($name) ) {
                return isset($this->hooks[(string)$name]) ? $this->hooks[(string)$name] : null;
            } else {
                return $this->hooks;
            }
        }

        /**
         * Clear hook listeners
         *
         * Clear all listeners for all hooks. If `$name` is
         * a valid hook name, only the listeners attached
         * to that hook will be cleared.
         *
         * @param   string  $name   A hook name (Optional)
         * @return  void
         */
        public function clearHooks( $name = null ) {
            if ( !is_null($name) && isset($this->hooks[(string)$name]) ) {
                $this->hooks[(string)$name] = array(array());
            } else {
                foreach( $this->hooks as $key => $value ) {
                    $this->hooks[$key] = array(array());
                }
            }
        }

        /***** EXCEPTION AND ERROR HANDLING *****/

        /**
         * Handle errors
         *
         * This is the global Error handler that will catch reportable Errors
         * and convert them into ErrorExceptions that are caught and handled
         * by each Slim application.
         *
         * @param   int     $errno      The numeric type of the Error
         * @param   string  $errstr     The error message
         * @param   string  $errfile    The absolute path to the affected file
         * @param   int     $errline    The line number of the error in the affected file
         * @return  true
         * @throws  ErrorException
         */
        public static function handleErrors( $errno, $errstr = '', $errfile = '', $errline = '' ) {
            if ( error_reporting() & $errno ) {
                if (isset($this)) $this->getLog()->error('Unhandled Error: '.$errstr);
                throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
            }
            return true;
        }

        public static function handleExceptions( $exception ) {
            $this->getLog()->error('Unhandled Exception: '.$exception->getMessage());
            throw $exception;
        }
        
        public function FatalErrorCatcher()
            {
                $error = error_get_last();
                if (isset($error)) {
                    if($error['type'] == E_ERROR
                        || $error['type'] == E_PARSE
                        || $error['type'] == E_COMPILE_ERROR
                        || $error['type'] == E_CORE_ERROR)
                    {
                        if (isset($this)) {
                            $this->getLog()->error('Fatal Error Catched: '.$error['message']);
                            if ( $this->config('debug') === true ) {
                                $this->halt(500, self::generateErrorMarkup($error['message'], $error['file'], $error['line'], ''));
                            } else {
                                $e = new Exception( $error['message'] ) ;
                                $this->error($e);
                            }
                        } else {
                            throw new Exception('Fatal Error Catched but not logged: ' . $error['message']);
                        }
                    } else {
                        if (isset($this)) {
                            $this->getLog()->warn('Shutdown Error Catched: '.$error['message']);
                        }
                    }
                }
        }
        
        /**
         * Generate markup for error message
         *
         * This method accepts details about an error or exception and
         * generates HTML markup for the 500 response body that will
         * be sent to the client.
         *
         * @param   string  $message    The error message
         * @param   string  $file       The absolute file path to the affected file
         * @param   int     $line       The line number in the affected file
         * @param   string  $trace      A stack trace of the error
         * @return  string
         */
        protected static function generateErrorMarkup( $message, $file = '', $line = '', $trace = '' ) {
            $body = '<p>The application could not run because of the following error:</p>';
            $body .= "<h2>Details:</h2><strong>Message:</strong> $message<br/>";
            if ( $file !== '' ) $body .= "<strong>File:</strong> $file<br/>";
            if ( $line !== '' ) $body .= "<strong>Line:</strong> $line<br/>";
            if ( $trace !== '' ) $body .= '<h2>Stack Trace:</h2>' . nl2br($trace);
            return self::generateTemplateMarkup('Harmonious Application Error', $body);
        }

        /**
         * Generate default template markup
         *
         * This method accepts a title and body content to generate
         * an HTML page. This is primarily used to generate the layout markup
         * for Error handlers and Not Found handlers.
         *
         * @param   string  $title The title of the HTML template
         * @param   string  $body The body content of the HTML template
         * @return  string
         */
        protected static function generateTemplateMarkup( $title, $body ) {
            $html = "<html><head><title>$title</title><style>body{margin:0;padding:30px;font:12px/1.5 Helvetica,Arial,Verdana,sans-serif;}h1{margin:0;font-size:48px;font-weight:normal;line-height:48px;}strong{display:inline-block;width:65px;}</style></head><body>";
            $html .= "<h1>$title</h1>";
            $html .= $body;
            $html .= '</body></html>';
            return $html;
        }

        /**
         * Default Not Found handler
         * @return void
         */
        protected function defaultNotFound() {
            echo self::generateTemplateMarkup('404 Page Not Found', '<p>The page you are looking for could not be found. Check the address bar to ensure your URL is spelled correctly. If all else fails, you can visit our home page at the link below.</p><a href="' . $this->request->getRootUri() . '">Visit the Home Page</a>');
        }

        /**
         * Default Error handler
         * @return void
         */
        protected function defaultError() {
            echo self::generateTemplateMarkup('Error', '<p>A website error has occured. The website administrator has been notified of the issue. Sorry for the temporary inconvenience.</p>');
        }
        
    }        
?>
