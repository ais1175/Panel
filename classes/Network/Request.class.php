<?php
	namespace fruithost\Network;

	class Request {
		public static function init() : void {
			RequestFactory::getInstance()->init();
		}
		
		public static function has(string $name) : bool {
			return RequestFactory::getInstance()->has($name);
		}
		
		public static function get(string $name) : mixed {
			return RequestFactory::getInstance()->get($name);
		}
		
		public static function url() : string {
			return RequestFactory::getInstance()->url();
		}
	}
?>