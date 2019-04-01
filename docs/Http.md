## 介绍

SDK 项目已集成 Yaf 框架环境

可服务于整个 http 请求生命周期，并提供常见方法, 基于 `symfony/http-foundation` 组件

以下所有方法均向前兼容

### Bootstrap

yaf 的 `Bootstrap` 类，继承 `\SDK` 类即可

`./application/Bootstrap.php`

```
class Bootstrap extends \SDK {

    //可覆盖 getConfig 方法
    
}
```

sdk 会默认完成 db log cache kafka 等基础组件的初始化（不同项目的组件配置格式需一致）

后续若有其它组件，也会由 sdk 统一初始化，如 服务治理、rpc、配置管理等组件


### Request

controller 类需继承 `\Http\Controller`

action 类需继承 `\Http\Action`


#### 获取参数

以下三种方式相同，默认会依次从 $_GET, $_POST, json body 内取数据
```
$id = $this->getRequest()->getParam('id');
$id = $this->getRequest()->get('id');
$id = $this->get('id');
```

```
//强制获取某参数，为 null 时抛异常
$id = $this->forceParam('id');

//强制获取某参数且指定请求方式，为 null 时抛异常，或请求方式不匹配时抛异常
$id = $this->forceParam('id', 'POST');
```

仅取 GET 数据
```
$id = $this->getRequest()->query->get('id');
```

仅取 POST 数据
```
$id = $this->getRequest()->request->get('id');
```

取 FILES 数据
```
$id = $this->getRequest()->files->get('id');
```

#### 参数合法性

```
//仅允许指定请求方式， 不合法时会抛异常 request method error
$this->requireMethod('GET')
$this->requireMethod('POST')

//必传参数检测，任何一个为 null 则抛异常
$this->requireParams('aaa', 'bbb', 'ccc');

//必传参数检测，任何一个为 null 则抛异常, 且请求方式为 GET
$this->requireGet('aaa', 'bbb', 'ccc');

//必传参数检测，任何一个为 null 则抛异常, 且请求方式为 POST
$this->requirePost('aaa', 'bbb', 'ccc');

//可选参数检测，全部为 null 时抛异常
$this->requireParamsAny('aaa', 'bbb', 'ccc');
```

### Response

默认响应 json 类型数据, 默认 header 如下
``` 
'Content-Type' => 'application/json;charset=utf-8',
'Server' => 'apache/1.8.0',
```

设置 headers

```
$this->header('k', 'v');
```

响应数据, 完整 json 格式
```
{
    code: 0,
    message: "success",
    data: mixed,
    timestamp: 1234567890
}
```

`response` 和 `error` 方法分别对应成功数据和报错信息
```
$data = [
    'list' => [1,2,3]
];
return $this->response($data)

//注：在 action 文件内可仅返回数据本身
//return $data;
```

```
{
    code: 0,
    message: "success",
    data: {
        list: [1, 2,3]
    },
    timestamp: 1234567890
}
```

报错信息
```
return $this->error(123, '错误信息')
```


```
{
    code: 123,
    message: "错误信息",
    data: null,
    timestamp: 1234567890
}
```

### Exception

抛异常时默认会以错误信息格式返回 json

```
throw new \Exception('error msg', ErrorCode::PARAMS_ERROR);
```

```
{
    code: 123,
    message: "error msg",
    data: null,
    timestamp: 1234567890
}
```

提供了简便方法
```
// 抛出 LogicException
$this->throwError(ErrorCode::PARAMS_ERROR, 'error msg');

// 第二个参数 msg 可忽略，默认只用传入错误码，sdk 会自动解析出对应错误msg
$this->throwError(ErrorCode::PARAMS_ERROR);
```

#### Error Code

默认系统错误码内置于 sdk 内的 `\Http\ErrorCode` 文件，如下：

以注释方式标明错误信息，必须以 `//` 开头

```
//server error
const SERVER_ERROR = 990;

//未知错误，请尝试重新提交
const ERROR_UNKNOWN_B = 999;

//unknown error
const ERROR_UNKNOWN = 998;
```

业务内的错误码文件，需继承 `\Http\ErrorCode` 类，
且设置`Action::$errorCodeClass`


```
<?php
namespance XXX;
class ErrorCode extends \Http\ErrorCode
{
    //error test
    const ERROR_TEST = 1001;

    //xxx
    const ERROR_XXX = 1001;
}
```

```

abstract class Action extends \Http\Action
{
    public static $errorCodeClass = \XXX\ErrorCode::class;
    ...
}
```


业务代码内返回报错时：
```
return $this->error(\XXX\ErrorCode::ERROR_TEST);

or 

$this->throwError(\XXX\ErrorCode::ERROR_TEST);
```

响应：

```
{
    code: 1001,
    message: "error test",
    data: null,
    timestamp: 1234567890
}
```


### 其它

app版本判断

```
// >= 1.8.0
$this->versionGte('1.8.0');

// < 1.8.0
$this->versionLt('1.8.0');

// >= 1.8.0 且平台为 ios
$this->versionGte('1.8.0', 'ios');
```