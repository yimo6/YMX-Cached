<?php
error_reporting(0);
set_time_limit(0);

class YMX_Cached {

	const r = 0b01; //读
	const w = 0b10; //写
	const w_r = 0b11; //读写
	
	const MSG_SERVER = 0;
	const MSG_CACHE_SERVER = 1;
	const MSG_HIGN_CACHE_SERVER = 2;
	const MSG_NONE = 3;

	public $server;
	public $client;
	
	public $_socket = array();
	public $socket = array();

	public $_block; //缓存区块
	public $block; //缓存信息
	
	public $config = array(
		'token' => '123456',
		'sleep' => 0, //休眠时间(单位:微秒)[0为不休眠]
		'exp' => 3600, //默认缓存过期时间
		'autogc' => 1800, //自动回收时间(单位: 秒)
		'ip' => '0.0.0.0', //绑定IP
		'timeout' => 5, //超时时间
		'level' => 0
		/*
			0: 开启所有
			1: 仅缓存服务器
			2: 高级缓存服务器
			3: 仅系统消息
			4: 关闭一切
		*/
	);
	
	public function __construct($config = []){
		if($config != NULL) $this -> config = $config;
	}

	/*
	 * 创建服务器
	 * @param int $port
	 */
	public function server_create($port = 6666){
		$this -> _socket[] = $this -> server = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
		socket_bind($this->server,$this->config['ip'],$port);
		socket_listen($this->server);
	}

	/*
	 * 运行
	 */
	public function server_run(){
		$i = 0;
		$sleep = $this -> config['sleep'];
		$autogc_lasttime = time();
		$timeout = $this -> config['timeout'];
		while(1){
			$autogc_times = time() - $autogc_lasttime;
			$sockets = $this -> _socket;
			$read = socket_select($sockets,$write,$except,NULL);
			if(!$read) return array('socket_select_error', $err_code = socket_last_error(), socket_strerror($err_code));
			foreach($sockets as $sock){
				if($sock == $this ->server){
					$accept = socket_accept($this->server);
					if(!$accept) continue;
					$this -> connect($accept);
				}else{
					if((time() - $this -> socket[(int)$sock]['lasttime']) > $timeout){
						$this -> disconnect($sock,'time out');
					}else{
						$head_length = socket_recv($sock, $header, 4, 0);
						if ($head_length === false){
							$this -> disconnect($sock);
							continue;
						}
						if($head_length < 0){
							$this -> disconnect($sock);
							continue;
						}
						if(!$header) continue;
						$block = unpack("I*",$header)[1];
						$length = socket_recv($sock, $data, $block, 0);
						if ($length === false) continue;
						if ($length <= 0){
							$this -> disconnect($sock);
						}else{
							$this -> event($sock,$data);
						}
					}
				}
			}
 			if(isset($this -> config['autogc']) && $autogc_times > $this -> config['autogc']){
				$this -> auto_gc();
				$autogc_lasttime = time();
			}
			if($sleep) usleep($sleep);
		}
	}

	/*
	 * 事件处理
	 * @param resource $sock
	 * @param string $data
	 */
	private function event($sock,$data){
		$this -> update($sock); //更新数据
		if(!$data = json_decode($data,true)){
			$this -> send($sock,-1,'','错误的数据格式');
			$this -> disconnect($sock,'action Error');
			return;
		}
		if(!$this -> auth($sock)){
			if(!isset($data['token'])){
				$this -> send($sock,-1,'','没有找到Token');
				return;
			}
			if($this -> auth($sock,$data['token'])){
				$this -> send($sock,-1,'','请先验证权限');
				return;
			}
		}
		isset($data['type']) ? $type = $data['type'] : $type = NULL;
		isset($data['value']) ?: $data['value'] = '';
		isset($data['exp']) ? $exp = $data['exp'] : $exp = $this -> config['exp'];
		switch($data['action']){
			case "heart": //心跳
				$result = '';
				break;
			case "read": //读
				$result = $this -> read($data['key']);
				break;
			case "write": //写
				$result = $this -> write($data['key'],$data['value'],$exp,$type);
				break;
			case "copy": //复制
				$result = $this -> copy($data['src'],$data['key']);
				break;
			default:
				$result = '???';
				break;
		}

		$this -> send($sock,200,$result,'Success');
	}

	/*
	 * 发送
	 * @param resource $sock
	 * @param int $code
	 * @param array $data
	 * @param string $msg
	 */
	private function send($sock,$code=0,$data=[],$msg=''){
		$data = json_encode([
			'code' => $code,
			'data' => $data,
			'msg'  => $msg
		]);
		$length = pack("I*",strlen($data));
		socket_write($sock,$length . $data);
	}

	/*
	 * 断开连接
	 * @param resource $sock
	 * @param string $msg
	 */
	private function disconnect($sock,$msg = 'are disconnected'){
		socket_close($sock);
		$addr = $this -> socket[(int)$sock]['addr'];
		$port = $this -> socket[(int)$sock]['port'];
		$this -> console('Server',$addr . ':' . $port .' ' . $msg,0);
		unset($this->socket[(int)$sock],$this->_socket[(int)$sock]);
	}

	/*
	 * 连接
	 * @param resource $sock
	 */
	private function connect($sock){
		socket_getpeername($sock,$addr,$port);
		$this -> _socket[(int)$sock] = $sock;
		$this -> socket[(int)$sock] = [
			'addr' => $addr,
			'port' => $port,
			'lasttime' => time(),
			'token' => false
		];
		$this -> console('Server',$addr . ':' . $port . ' connected',self::MSG_SERVER);
	}

	/*
	 * 更新
	 * @param resource $sock
	 */
	private function update($sock){
		$this -> socket[(int)$sock]['lasttime'] = time();
	}

	/*
	 * Token验证
	 * @param resource $sock
	 * @param string $value
	 */
	private function auth($sock,$value = NULL){
		if($value == $this -> config['token']){
			$this -> socket[(int)$sock]['token'] = true;
			socket_getpeername($sock,$addr,$port);
			$this -> console('Server',$addr . ':' . $port . ' token to be true',self::MSG_SERVER);
			return true;
		}else{
			return $this -> socket[(int)$sock]['token'];
		}
	}

	/*
	 * 自动回收
	 */
	private function auto_gc(){
		$blocks = $this -> block;
		$this -> console('Cache Server','AutoGC running',self::MSG_CACHE_SERVER);
		$memall = memory_get_usage();
		$count = 0;
		if($blocks == NULL) return;
		foreach($blocks as $block_key=>$block){
			$mem = memory_get_usage();
			$times = time() - $block['lasttime'];
			if($times > $block['exp']){
				$this -> gc($block_key);
				$count++;
			}
		}
		unset($blocks,$block,$block_key,$times);
		$this -> console('Cache Server','Auto_GC All removed(' . $count . ') -> ' . $this -> mem($memall - memory_get_usage()),self::MSG_HIGN_CACHE_SERVER);
	}

	/*
	 * 回收缓存块
	 * @param string $key 块名称
	 */
	private function gc($key){
		unset($this -> _block[$key],$this -> block[$key]);
		$this -> console('Cache Server','GC -> ' . $key,self::MSG_CACHE_SERVER);
	}

	private function mem($value){
		if($value > 1048576){
			return round($value / 1048576,3) . 'MB';
		}else if($value > 1024){
			return round($value / 1024,5) . 'KB';
		}else{
			return $value . 'B';
		}
	}

	/*
	 * 读缓存
	 * @param resource $sock
	 * @param string $defaultValue 默认值
	 */
	private function read($key,$defaultValue = NULL){
		if(!isset($this->_block[$key])) return $defaultValue;
		if(($this->block[$key]['level'] & self::r) != self::r){
			return false;
		}
		$this -> block[$key]['lasttime'] = time();
		return $this -> _block[$key];
	}

	/*
	 * 写缓存
	 * @param resource $sock
	 * @param string $value
	 * @param int $exp 过期时间
	 * @param b $level 读写权限
	 */
	private function write($key,$value,$exp = 3600,$level = NULL){
		if(isset($this->block[$key]) && ($this->block[$key]['level'] & self::w) != self::w){
				return false;
		}
		if(!$level) $level = self::w_r;
		$this -> _block[$key] = $value;
		$this -> block[$key] = [
			'exp' => $exp,
			'level' => $level,
			'lasttime' => time()
		];
		$length = strlen($value);
		if($length > 15){
			$new = substr($value,0,$length);
		}else{
			$new = $value;
		}
		$this -> console('Cache Server',$key . ' -> ' . $new,self::MSG_CACHE_SERVER);
		return true;
	}

	/*
	 * 复制缓存
	 * @param string $src 指向源
	 * @param string $key 缓存键
	 */
	private function copy($src,$key){
		if(isset($this->block[$key]) && ($this->block[$key]['level'] & self::w) != self::w){
			return false;
		}
		$this -> _block[$src] = $this -> _block[$key];
		$this -> block[$src] = $this -> block[$key];
		return true;
	}

	/*
	 * 输出
	 * @param string $name 名称
	 * @param string $message 信息
	 * @param b $level 等级
	 */
	private function console($name,$message,$level = 0){
		if($level >= $this->config['level']) printf("[%s]: %s\n",$name,$message);
	}

}
?>