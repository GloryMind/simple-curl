<?php

class Common_Misc {
	protected function prepare_name( $name ) {
		return strtolower(trim($name));
	}
}
class Cookie_Item {
	public function __construct( $name = null , $value = null , $expire = 0 , $path = null , $domain = null , $secure = false , $httponly = false ) {
		$this->name = $name;
		$this->value = $value;
		$this->expire = $expire;
		$this->path = $path;
		$this->domain = $domain;
		$this->secure = $secure;
		$this->httponly = $httponly;
	}
	public function Serialize() {
		return serialize( (Array) $this );
	}
	public static function Unserialize( $str ) {
		if ( !($a = @unserialize($str)) ) {
			return null;
		}
		$r = new self();
		$r->Change( $a );
		return $r;
	}
	public $name;
	public $value;
	public $expire;
	public $path;
	public $domain;
	public $secure;
	public $httponly;
}
class Cookie_List extends Common_Misc {
	public $change_enabled = true;
	private $cookie_list = Array();
	public function Set( Cookie_Item $cookie ) {
		if ( !$this->change_enabled ) { return $this; }
		$name = $cookie->name;
		$name = $this->prepare_name( $name );
		unset($this->cookie_list[ $name ]);
		$this->cookie_list[ $name ] = $cookie;
		return $this;
	}
	public function Get( $name ) {
		$name = $this->prepare_name( $name );
		if ( isset($cookie_list[ $name ]) ) {
			return $cookie_list[ $name ];
		}
		return null;
	}
	public function Has( $name ) {
		$name = $this->prepare_name( $name );
		return isset($cookie_list[ $name ]);
	}
	public function Del( $name ) {
		if ( !$this->change_enabled ) { return $this; }
		$name = $this->prepare_name( $name );
		unset($cookie_list[ $name ]);
		return $this;
	}
	public function Change( $name , $attrKey , $attrVal = null ) {
		if ( !$this->change_enabled ) { return $this; }
		if ( is_array($attrKey) ) {
			foreach($attrKey as $k=>$v) {
				$this->Change( $name , $k , $v );
			}
			return;
		}
		if ( isset($cookie_list[ $name ]) ) {
			$cookie_list[ $name ]->{$attrKey} = $attrVal;
		}
		return $this;
	}

	public function ByCurlFile( $path , $clear = true ) {
		if ( !$this->change_enabled ) { return $this; }
		if ( !file_exists($path) )    { return $this; }

		if ( $clear ) {
			$this->cookie_list = Array();
		}
		$lines = file( $path );
		
		foreach ($lines as $line) {

			$cookie = array();

			// detect httponly cookies and remove #HttpOnly prefix
			if (substr($line, 0, 10) == '#HttpOnly_') {
				$line = substr($line, 10);
				$cookie['httponly'] = true;
			} else {
				$cookie['httponly'] = false;
			} 

			// we only care for valid cookie def lines
			if($line[0] != '#' && substr_count($line, "\t") == 6) {

				// get tokens in an array
				$tokens = explode("\t", $line);

				// trim the tokens
				$tokens = array_map('trim', $tokens);

				// Extract the data
				$cookie['domain'] = $tokens[0]; // The domain that created AND can read the variable.
				$cookie['flag'] = ($tokens[1] !== 'FALSE');   // A TRUE/FALSE value indicating if all machines within a given domain can access the variable. 
				$cookie['path'] = $tokens[2];   // The path within the domain that the variable is valid for.
				$cookie['secure'] = ($tokens[3] !== 'FALSE'); // A TRUE/FALSE value indicating if a secure connection with the domain is needed to access the variable.

				$cookie['expire'] = $tokens[4];  // The UNIX time that the variable will expire on.   
				$cookie['name'] = urldecode($tokens[5]);   // The name of the variable.
				$cookie['value'] = urldecode($tokens[6]);  // The value of the variable.

				$this->Set(
					new Cookie_Item($cookie['name'] , $cookie['value'] , $cookie['expire'] , $cookie['path'] , $cookie['domain'] , $cookie['secure'] , $cookie['httponly'])
				);
			}
		}
		return $this;
	}
	public function ToCurlFile( $path ) {
		$s = "";
		$this->Each( function( $cookie ) use( &$s ) {
			$s .= 
				$cookie->domain . "\t" .
				'TRUE' . "\t" .
				$cookie->path . "\t" .
				($cookie->secure?'TRUE':'FALSE') . "\t" .
				$cookie->expire . "\t" .
				$cookie->name . "\t" .
				$cookie->value . "\r\n";
		} );
		@file_put_contents( $path , $s );
		return $this;
	}
	
	public function Serialize() {
		$a = Array();
		foreach($this->cookie_list as $c) {
			$a[] = $c->Serialize();
		}
		return serialize( $a );
	}
	public static function Unserialize( $str ) {
		if ( !($a = @unserialize($str)) ) {
			return null;
		}
		$r = new self();
		foreach($a as $c) {
			$r->Set( Cookie_Item::Unserialize( $c ) );
		}
		return $r;
	}

	public function Each( $f ) {
		foreach($this->cookie_list as $c) {
			$f( $c );
		}
		return $this;
	}
}
class Header {
	public $name;
	public $values;
	public function __construct( $name , $values ) {
		if ( !is_array($values) ) {
			$values = Array($values);
		}
		$this->name = trim($name);
		foreach($values as &$v) {
			$v = trim($v);
		}
		$this->values = $values;
	}
	public function Add( $value ) {
		$this->values[] = trim($value);
	}
	public function ToString() {
		$result = "";
		foreach($this->values as $val) {
			$result .= trim( trim($this->name) . ": " . $this->prepare_value($val) ) . "\r\n";
		}
		return trim($result);
	}
	private function prepare_value( $value ) {
		return trim(str_replace( "\r\n" , "\r\n " , $value ));
	}
}
class Headers extends Common_Misc {
	public $head;
	public $headers = Array();
	public function Set( Header $header ) {
		$this->headers[ $this->prepare_name( $header->name ) ] = $header;
		return $this;
	}
	public function Add( $name , $value ) {
		if ( $h = $this->Get( $name ) ) {
			$h->Add($value);
		} else {
			$this->Set( new Header( $name , $value ) );
		}
	}
	public function Get( $name ) {
		if ( isset($this->headers[ $name = $this->prepare_name( $name ) ]) ) {
			return $this->headers[ $name ];
		}
		return null;
	}
	public function Has( $name ) {
		return isset($this->headers[ $name = $this->prepare_name( $header->name ) ]);
	}
	public function Del( $name , $index = null ) {
		if  ( $index === null ) {
			unset( $this->headers[ $name = $this->prepare_name( $header->name ) ] );
		} elseif ( $h = $this->Get( $name ) ) {
			array_splice($h->values , $index , 1);
		}
		return $this;
	}
	public function ToString( $use_head = true ) {
		$result = "";
		if ( $this->head && $use_head ) {
			$result = $this->head . "\r\n";
		}
		$this->Each( function( $header ) use (&$result) {
			$result .= $header->ToString() . "\r\n";
		} );
		return $result;
	}
	public static function ByString( &$string ) {
		$_this = new self();
		if ( ($pos = strpos( $string , "\r\n\r\n" )) === false ) {
			return $_this;
		}
		$headers_string = substr($string , 0 , $pos + 2);
		$string = substr($string , $pos+4);

		do {
			$rand_s = substr(str_shuffle( "qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM0123456789" ) , 0 , 32);
		} while( strpos($headers_string , $rand_s) !== false );
		
		$headers_string = str_replace( "\r\n " , $rand_s , $headers_string );
		$headers_string = str_replace( "\r\n\x09" , $rand_s , $headers_string );
		preg_match( "#([^\r\n:]*)\r\n#si" , $headers_string , $res );
		if ( !@$res[1] ) {
			return;
		}
		$_this->head = "";
		if ( strpos($res[1] , ":") !== false ) {
			$_this->head = trim( str_replace( $rand_s , "\r\n" , $res[1] ) );
		}
		
		preg_match_all( "#([^\r\n:]*):([^\r\n]*)\r\n#si" , $headers_string , $res );
		foreach($res[1] as $i=>$v) {
			$name = trim( str_replace( $rand_s , "\r\n" , $res[1][$i] ) );
			$value = trim( str_replace( $rand_s , "\r\n" , $res[2][$i] ) );
			$_this->Add( $name , $value );
		}
		return $_this;
	}
	public function Each( $f ) {
		foreach($this->headers as $header) {
			$f( $header );
		}
		return $this;
	}
	public function GetCurlArray() {
		$result = Array();
		$this->Each( function( $header ) use (&$result) {
			$result[] = trim($header->ToString());
		} );
		return $result;
	}

	public function __construct() {
		foreach( func_get_args() as $arg ) {
			if ( !is_array($arg) ) {
				$arg = Array($arg);
			}
			foreach($arg as $v) {
				$this->Set($v);
			}
		}
	}
}
class Curl_Return {
	public $error;
	public $info;
	public $headers;
	public $request_headers;
	public $content;
	public function __construct( $error , $content , $info ) {
		$this->error   = $error;
		$this->content = $content;
		$this->info    = $info;
		$this->headers = Headers::ByString( $this->content );
		$this->request_headers = Headers::ByString( $this->info['request_header'] );
	}
}
class Curl {
	public static function Get( $info = Array() , Headers $headers = null , Cookie_List $cookie = null ) {
		$info = (Array)$info;
		$info["method"] = "get";
		return new self( $info , $headers , $cookie );
	}
	public static function Post( $info = Array() , Headers $headers = null , Cookie_List $cookie = null ) {
		$info = (Array)$info;
		$info["method"] = "post";
		return new self( $info , $headers , $cookie );
	}

	private $curl;
	private $curl_multi;
	private $curl_active;
	
	public $url;
	public $useragent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.116 Safari/537.36';
	public $timeout = 10;
	public $connecttimeout = 3;
	public $method = 'get';
	public $fresh_connect = true;
	public $forbid_reuse = true;
	public $parameters = Array();

	public $cookie_tmp_dir = "MTGNHQDDNPYLMMIWFYNQOYBNWEWHQSLV";
	private $cookie_tmp_file;

	private $attr_map = Array( 
		'url' => CURLOPT_URL , 
		'useragent' => CURLOPT_USERAGENT , 
		'timeout' => CURLOPT_TIMEOUT , 
		'connecttimeout' => CURLOPT_CONNECTTIMEOUT ,
		'fresh_connect' => CURLOPT_FRESH_CONNECT ,
		'forbid_reuse' => CURLOPT_FORBID_REUSE
	);
	public function __construct( $info = Array() , Headers $headers = null , Cookie_List $cookie = null ) {
		if ( !is_dir( $this->cookie_tmp_dir ) ) {
			@mkdir($this->cookie_tmp_dir);
		}

		if ( $cookie === null  ) { $cookie = new Cookie_List(); }
		if ( $headers === null ) { $headers = new Headers(); }
		$this->cookie = $cookie;
		$this->headers = $headers;
		
		$this->Change( $info );
		return $this;
	}
	public function Change( $info = Array() , $value2 = null ) {
		if ( $value2 !== null ) {
			$info = Array( $info => $value2 );
		}
		$info = (Array)$info;
		foreach($info as $k=>$v) {
			$this->{$k} = $v;
		}	
	}

	public function Load( $url = null ) {	
		$this->startLoad();

		if ( $url !== null ) {
			$this->url = $url;
		}
	
		$isPost = (strtolower(trim($this->method)) === "post");

		$options = Array(
			CURLOPT_POST => $isPost,

			CURLOPT_HEADER => 1, 
			CURLOPT_RETURNTRANSFER => 1,
			CURLINFO_HEADER_OUT => 1,
			CURLOPT_COOKIEFILE => $this->cookie_tmp_file,
			CURLOPT_COOKIEJAR => $this->cookie_tmp_file,
			CURLOPT_SSL_VERIFYPEER => 0
		);
		
		foreach($this->attr_map as $k=>$v) {
			$options[ $v ] = $this->{$k};
		}
		
		if ( $isPost ) {
			$options[ CURLOPT_POSTFIELDS ] = $this->parameters;
		}

		$options[ CURLOPT_HTTPHEADER ] = $this->headers->GetCurlArray();
		
		$this->curl_active = null;
		
		$this->curl = @curl_init();
		@curl_setopt_array( $this->curl , $options );

		$this->curl_multi = @curl_multi_init();

		@curl_multi_add_handle( $this->curl_multi , $this->curl );
		
		$this->IsLoad();

		return $this;
	}
	public function IsLoad() {
		$status = @curl_multi_exec( $this->curl_multi , $this->curl_active );
		$result = !($status === CURLM_CALL_MULTI_PERFORM || $this->curl_active);
		if ( $result ) {
			$this->result = new Curl_Return( @curl_error( $this->curl ) , @curl_multi_getcontent( $this->curl ) , @curl_getinfo( $this->curl ) );
			@curl_multi_remove_handle($this->curl_multi , $this->curl);
			@curl_close($this->curl);
			@curl_multi_close($this->curl_multi);
			$this->endLoad();
		}
		return $result;
	}
	public function Wait() {
		while( !$this->IsLoad() ) {
			usleep( $this->wait_time );
		}
		return $this;
	}
	private $wait_time = 100000;
	
	public $result;
	public $cookie;
	public $headers;
	
	
	private function startLoad() {
		$this->result = null;
		$this->cookie_tmp_file = tempnam($this->cookie_tmp_dir , "tmp");
		$this->cookie->ToCurlFile( $this->cookie_tmp_file );
	}
	private function endLoad() {
		$this->cookie->ByCurlFile( $this->cookie_tmp_file );
		unlink($this->cookie_tmp_file);
	}
}
