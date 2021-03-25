# YMX-Cached

一个菜鸡写的Socket缓存器2.0 (不是

比1.0效率更高(本机情况下)

## 注意事项

1.客户端类未做数据判断,请自行实现

## 功能列表

1.write(写缓存)

2.read(读缓存)

3.copy(复制缓存)

## 目录结构

```
├─src
│  ├─ class.YMX-Cached.php //服务端类
│  └─ class.client.php //客户端类
├─ client.php          //客户端测试
└─ server.php          //服务端
```

## 配置相关

初始化: new YMX_Cached($config);

$config:

> (string)ip: 绑定IP(默认值: 0.0.0.0)

> (string)token: 授权密匙(默认值: 123456)

> (int)sleep: 休眠时间(单位:微秒,默认值: 0(不休眠))

> (int)exp: 默认缓存过期时间(默认值: 3600)

> (int)autogc: 自动回收缓存时间(默认值: 1800)

> (int)timeout: 客户端连接超时时间(默认值: 5)

> (int)level: (服务端)控制台输出信息(默认值: 0)

		0: 全部信息
    
		1: 仅缓存服务器
    
		2: 高级缓存服务器
    
		3: 仅系统消息
    
		4: 不输出任何信息

## 安装

### 1.下载ZIP

### 2.Git

```
git clone https://github.com/yimo6/YMX-Cached.git
```

### 返回说明

#### 服务端返回数据结构(json):
```
{"code":200,"data":"","msg":"Success"}
```

## 使用许可

[MIT](LICENSE) © Richard Littauer
