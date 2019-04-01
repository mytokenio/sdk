## 介绍

基于 guzzle 组件封装了 internalapi 请求方法, 类名是 `Internal`

避免大量重复代码，详见：https://github.com/mytokenio/OpenApi/pull/409/files

InternalAPI 项目默认响应格式为
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
`Internal` 类默认返回其中的 `data` 字段数据（也可以返回全部数据用于部分需判断特定 code 的场景，后续介绍）

注：

接口请求失败或HTTP状态码非200时会返回 false

`code` 不为 0 时，会抛出异常，以 `code` 和 `message` 分别作为异常编号和错误信息


### 用法

GET 请求

```

// 无参数时
$userCount = \Internal::get('/user/usercount');

// 传入参数
$news = \Internal::get('/news/newslist', [
    'page_num'          => $page,
    'page_limit'        => $size,
]);

// getList 仅返回 data.list 字段数据，用于只取列表的场景
$newsList = \Internal::getList('/news/newslist', [
    'page_num'          => $page,
    'page_limit'        => $size,
]);


```

POST 请求
```
\Internal::post('/currency/addfavorite', [
    'user_id'               => $userInfo['user_id'],
    'currency_id'           => $currencyId,
    'com_id'                => $comId,
    'market_id'             => $marketId,
]);
```

通用请求

```
$news = \Internal::request('GET', '/news/newslist');


\Internal::request('POST', /currency/addfavorite', []);


// 传入第四个参数为 true，返回原生数据
$response = \Internal::request('GET', '/news/newslist', [], true);
{
    code: 0,
    message: "success",
    data: {
        list: [...]
    },
    timestamp: 1234567890
}
```


### 其它

默认附加字段如下，跟之前兼容

`preRequestId` 请求 ID

`language` 当前语言
