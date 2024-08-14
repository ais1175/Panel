<?php
    /**
     * fruithost | OpenSource Hosting
     *
     * @author Adrian Preuß
     * @version 1.0.0
     * @license MIT
     */

    namespace fruithost\System;

    use fruithost\Accounting\Auth;
    use fruithost\Accounting\Session;
    use fruithost\Localization\I18N;
    use fruithost\Modules\Modules;
    use fruithost\Network\Request;
    use fruithost\Network\Response;
    use fruithost\Network\Router;
    use fruithost\Security\Encryption;
    use fruithost\Storage\Database;
    use fruithost\Templating\Template;
    use fruithost\UI\Icon;
	use PHPMailer\Exception;

	class Core extends Loader {
		private ?Modules $modules	= null;
		private ?Hooks $hooks		= null;
		private ?Router $router		= null;
		private ?Template $template	= null;
		private ?CoreAdmin $admin	= null;
		private bool $modules_disabled = false;
		
		public function __construct() {
            parent::__construct();

			$this->init();
		}
		
		public function getAdminCore() : ?CoreAdmin {
			return $this->admin;
		}

		public function disableModules() {
			$this->modules_disabled = true;
		}

		public function enableModules() {
			$this->modules_disabled = false;
		}

		public function getModules() : ?Modules {
			return $this->modules;
		}
		
		public function getHooks() : ?Hooks {
			return $this->hooks;
		}
		
		public function getTemplate() : ?Template {
			return $this->template;
		}
		
		public function getRouter() : ?Router {
			return $this->router;
		}

		public function hasSettings(string $name) : bool {
			return Database::exists('SELECT `id` FROM `' . DATABASE_PREFIX . 'settings` WHERE `key`=:key LIMIT 1', [
				'key'		=> $name
			]);
		}

		public function removeSettings(string $name) : void {
			Database::delete(DATABASE_PREFIX . 'settings', [
				'key' => $name
			]);
		}

		public function getSettings(string $name, mixed $default = null) : mixed {
			$result = Database::single('SELECT * FROM `' . DATABASE_PREFIX . 'settings` WHERE `key`=:key LIMIT 1', [
				'key'		=> $name
			]);
			
			if(!empty($result) && !empty($result->value)) {
				// Is Boolean: False
				if(in_array(strtolower($result->value), [
					'off', 'false', 'no'
				])) {
					return false;
				// Is Boolean: True
				} else if(in_array(strtolower($result->value), [
					'on', 'true', 'yes'
				])) {
					return true;
				}
				
				return $result->value;
			}
			
			return $default;
		}
		
		public function setSettings(string $name, mixed $value = null) : void {
			if(is_bool($value)) {
				$value = ($value ? 'true' : 'false');
			}
				
			if(Database::exists('SELECT `id` FROM `' . DATABASE_PREFIX . 'settings` WHERE `key`=:key LIMIT 1', [
				'key'		=> $name
			])) {
				Database::update(DATABASE_PREFIX . 'settings', [ 'key' ], [
					'key'			=> $name,
					'value'			=> $value
				]);
			} else {
				Database::insert(DATABASE_PREFIX . 'settings', [
					'id'			=> null,
					'key'			=> $name,
					'value'			=> $value
				]);
			}
		}
		
		public function init() : void {
			Request::init();

			$this->hooks = new Hooks();
			$this->modules = new Modules($this, $this->modules_disabled);
			$this->template = new Template($this);
			$this->router = new Router($this);
			$this->admin = new CoreAdmin($this, null);

			Icon::init($this);

			Update::bind($this)::check();

			$this->getHooks()->runAction('core_pre_init');
			$this->initRoutes();
			$this->getHooks()->runAction('core_init');
			$this->router->run();
		}

		protected function initRoutes() : void {
			$this->router->addRoute('/', function() {
				if(Auth::isLoggedIn()) {
					Response::redirect('/overview');
				}
				
				Response::redirect('/login');
			});
			
			$this->router->addRoute('/logout', function() {
				if(Auth::isLoggedIn()) {
					Auth::logout();
				}

				Response::redirect('/');
			});
			
			$this->router->addRoute('/overview', function() {
				if(!Auth::isLoggedIn()) {
					Response::redirect('/');
				}

				$this->template->display('overview');
			});
			
			$this->router->addRoute('^/settings(?:/([a-zA-Z0-9\-_]+))?$', function(?string $tab = null) {
				if(!Auth::isLoggedIn()) {
					Response::redirect('/');
				}
				
				
				$this->template->display('settings', [
					'tab'		=> $tab,
					'languages'	=> I18N::getLanguages(),
					'timezones'	=> json_decode(file_get_contents(dirname(PATH) . '/config/timezones.json'))
				]);
			});
			
			$this->router->addRoute('^/account(?:/([a-zA-Z0-9\-_]+))?$', function(?string $tab = null) {
				if(!Auth::isLoggedIn()) {
					Response::redirect('/');
				}

				$data = Database::single('SELECT * FROM `' . DATABASE_PREFIX . 'users_data` WHERE `user_id`=:user_id LIMIT 1', [
					'user_id'	=> Auth::getID()
				]);
				
				if($data !== false) {
					foreach($data AS $index => $entry) {
						if(in_array($index, [ 'id', 'user_id' ])) {
							continue;
						}
						
						$data->{$index} = Encryption::decrypt($entry, ENCRYPTION_SALT);
					}
				}
				
				$this->template->display('account', [
					'tab'	=> $tab,
					'data'	=> $data
				]);
			});
			
			$this->router->addRoute('^/module(?:(?:/([a-zA-Z0-9\-_]+)?)(?:/([a-zA-Z0-9\-_]+)(?:/([a-zA-Z0-9\-_]+))?)?)?$', function(?string $module = null, ?string $submodule = null, ?string $action = null) {
				if(!Auth::isLoggedIn()) {
					Response::redirect('/');
				}
				
				if(Session::has('success')) {
					$this->template->assign('success', Session::get('success'));
					Session::remove('success');
				}
				
				if(Session::has('error')) {
					$this->template->assign('error', Session::get('error'));
					Session::remove('error');
				}

				$errors = [];

				foreach(($this->getModules()->getList()) AS $m) {
					try {
						if($m != null && $m->getInstance() != null && method_exists($m->getInstance(), 'preLoad')) {
							$m->getInstance()->preLoad();
						}
					} catch(\Exception $e) {
						$errors[] = (object) [
							'name'		=> $m->getInfo()->getName(),
							'message'	=> $e->getMessage(),
							'stack'		=> $e->getTrace()
						];
					}
				}

				if(count($errors) > 0) {
					$message = I18N::get('Following Modules have some Errors:');
					$message .= '<br />';

					foreach($errors AS $entry) {
						$message .= $entry->name;
					}

					$this->template->assign('error', $message);
				}


				if(empty($module)) {
					$this->template->display('error/module', array_merge($this->template->getAssigns(), [
						'module'	=> $module,
						'submodule'	=> $submodule,
						'action'	=> $action
					]));
					return;
				}

				$module = $this->getModules()->getModule($module);

				if(empty($module)) {
					$this->template->display('error/module', array_merge($this->template->getAssigns(), [
						'module'	=> $module,
						'submodule'	=> $submodule,
						'action'	=> $action
					]));
					return;
				}
				
				if(method_exists($module->getInstance(), 'load')) {
					$module->getInstance()->load($submodule, $action);
				}
				
				if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && method_exists($module->getInstance(), 'onPOST')) {
					$module->getInstance()->onPOST($_POST);
				}
				
				if(!method_exists($module->getInstance(), 'content') && !method_exists($module->getInstance(), 'frame')) {
					$this->template->display('error/module_empty', array_merge($this->template->getAssigns(), [
						'module'	=> $module,
						'submodule'	=> $submodule,
						'action'	=> $action
					]));
					return;
				}
				
				$this->template->display('module', array_merge($this->template->getAssigns(), [
					'module'	=> $module,
					'submodule'	=> $submodule,
					'action'	=> $action
				]));
			});
			
			$this->router->addRoute('^/lost-password(?:/([a-zA-Z0-9\-_]+))?$', function(?string $token = null) {
				if(Auth::isLoggedIn()) {
					Response::redirect('/');
				}
				
				$this->template->display('lost-password', [
					'token' => $token
				]);
			});
			
			$this->router->addRoute('/login', function() {
				if(Auth::isLoggedIn()) {
					Response::redirect('/');
				}
				
				$this->template->display('login');
			});
			
			$this->router->addRoute('/ajax', function() {
				if(!Auth::isLoggedIn()) {
					header('HTTP/1.1 403 Forbidden');
					require_once(dirname(PATH) . '/placeholder/errors/403.html');
					exit();
				}
				
				if(empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
					header('HTTP/1.1 405 Method Not Allowed');
					require_once(dirname(PATH) . '/placeholder/errors/405.html');
					exit();
				}
				
				$this->getHooks()->runAction('ajax');
				$this->router->run(true);
				
				// Fire modals
				if(!empty($_POST['modal'])) {
					$modals = $this->getHooks()->applyFilter('modals', []);
					
					if(count($modals) == 0) {
						print 'No registred modals.';
					} else {
						foreach($modals AS $modal) {
							if($modal->getName() === $_POST['modal']) {
								$callback	= $modal->getCallback('save');
								$data		= [];
								
								foreach($_POST AS $name => $value) {
									if(is_array($value)) {
										$data[trim($name)] = [];
										
										foreach($value AS $key => $entry) {
											$data[trim($name)][trim($key)] = trim($entry);
										}
									} else {
										$data[trim($name)] = trim($value);
									}
								}

								$result		= call_user_func_array($callback, [ $data ]);
								
								if(is_bool($result)) {
									print json_encode($result);
									return;
								}
								
								print $result;
							}
						}
					}
				}
			});
		}
	}
?>