<?php
class client{


	const r = 0b01; //读
	const w = 0b10; //写
	const w_r = 0b11; //读写
	private $sock;
	private $is_connect = false;
	
	private $setting;

	/*
	 * 连接服务器
	 * @param string $ip
	 * @param int $port
	 */
	public function connect($ip = '127.0.0.1',$port = 6666){
		$this -> sock = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
		socket_connect($this -> sock,$ip,$port);
		$this -> is_connect = true;
	}

	/*
	 * 断开连接
	 */
	public function disconnect(){
		socket_close($this -> sock);
		$this -> is_connect = false;
	}

	/*
	 * 设置参数
	 * @param string $name
	 * @param string $value
	 */
	public function setting($name,$value){
		$this -> setting[$name] = $value;
	}

	/*
	 * 验证
	 * @param string $token
	 * @return string
	 */
	public function auth($token = NULL){
		$this -> send([
			'token' => $token
		]);
		return $this -> dataread();
	}

	/*
	 * 心跳
	 * @return string
	 */
	public function heart(){
		$this -> send([
			'action' => 'heart'
		]);
		return $this -> dataread();
	}

	/*
	 * 写缓存
	 * @param string $key 键名
	 * @param string $value 值
	 * @param int $time 过期时间
	 * @param b $level 权限
	 * @return string
	 */
	public function set($key,$value,$time = 3600,$level = self::w_r){
		$this -> send([
			'action' => 'write',
			'key' => $key,
			'value' => $value,
			'exp' => $time,
			'level' => $level
		]);
		return $this -> dataread();
	}

	/*
	 * 读缓存
	 * @param string $key 键名
	 * @return string
	 */
	public function get($key){
		$this -> send([
			'action' => 'read',
			'key' => $key
		]);
		return $this -> dataread();
	}

	/*
	 * 复制缓存
	 * @param string $src 指向源
	 * @param string $key 缓存键
	 * @return string
	 */
	public function copy($src,$key){
		$this -> send([
			'action' => 'copy',
			'src' => $src,
			'key' => $key
		]);
		return $this -> dataread();
	}

	/*
	 * 读取数据
	 * @return string
	 */
	private function dataread(){
		if(!$this -> is_connect) return false;
		socket_recv($this -> sock,$buffer,4,0);
		if($buffer == NULL){
			socket_close($this -> sock);
			$this -> is_connect = false;
			return false;
		}
		$length = unpack("I*",$buffer)[1];
		socket_recv($this -> sock,$result,$length,0);
		return $result;
	}

	/*
	 * 发送
	 * @param string $data
	 * @return int|false
	 */
	public function send($data){
		if(!$this -> is_connect) return false;
		$json = json_encode($data);
		return socket_write($this -> sock,pack("I*",strlen($json)) . $json);
	}

}