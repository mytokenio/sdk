# MyToken DB Adapter



## 介绍

DB 层基于 Laravel Illuminate 组件

对旧的 dbObject 类查询方法做了大量兼容，（旧方式已标记为 deprecated） ，详见 `DbCompatible.php`


model`静态 where 方法`对查询条件支持更灵活, 详见 `Model.php`


## 配置

`application.ini` 配置文件内，数据库连接配置在 `db` 命名空间下

注：如果未配置 `db`, 则默认使用旧的配置并转换为新格式，方便兼容

默认连接配置为 `default`， 可添加任意连接

示例如下

```
;默认连接
db.default.driver = 'mysql'
db.default.read.host = '127.0.0.1'      //只读
db.default.write.host = '127.0.0.1'     //写
db.default.port = '3306'
db.default.database = 'db_mytoken'
db.default.username = 'root'
db.default.password = ''
db.default.charset = 'utf8mb4'
db.default.collation = 'utf8mb4_unicode_ci'
db.default.prefix = ''
db.default.strict = false

;自定义更多连接 如 xxx
db.xxx.driver = 'mysql'
db.xxx.host = 'localhost'               //读写地址相同
db.xxx.port = '3306'
db.xxx.database = 'db_mytoken'
db.xxx.username = 'root'
db.xxx.password = ''
db.xxx.charset = 'utf8mb4'
db.xxx.collation = 'utf8mb4_unicode_ci'
db.xxx.prefix = ''
db.xxx.strict = false
```

SQL 查询时可指定连接
```
\DB::select($sql);                          //默认连接 default
\DB::connection('xxx')->select($sql)        //使用 xxx 连接
```

Model 文件内可指定连接配置
```
class AdminUserModel extends \Model
{
    protected $connection = 'xxx';

    ...
}
```

或在 model 查询时指定连接参数，优先级高于 model 文件内指定的连接
```
//默认连接
\UserModel::query()->where(...)->get()->toArray();

//使用 xxx 连接
\UserModel::on('xxx')->where(...)->get()->toArray();

//使用默认连接中的主库（db.default.write.host）
\UserModel::onWriteConnection()->where(...)->get()->toArray();
```

## 查询方式与 Illuminate 不同的地方

model 内的静态 where 方法，支持的查询条件更灵活（见下面的场景示例）

Illuminate 的 query builder 支持链式调用，其中的 where 方法跟 model 内的静态 where 方法不一样，仅支持常规语法

以下方法会返回 query builder
```
\xxModel::on('xxx')
\xxModel::onWriteConnection()
\xxModel::query()
\xxModel::where()
$xxModel->newQuery()
\DB::connection()->query()
\DB::table('xx_table')
```

举例：
```
下面两个 where 不一样，前者属于 model，后者属于 query builder
\xxModel::where(...)->where(...)
```


## 常见查询场景

以 `UserModel` 举例
```
Model 实例化
$model = \UserModel::getInstance();

多数场景可用静态方式直接查询，如下

返回单条
$user = \UserModel::find(1234);
$user = \UserModel::where(...)->first();

返回多条
$users = \UserModel::where(...)->get()->toArray();

常见查询
//以下5种方式相同
$user = \UserModel::find(1234);
$user = \UserModel::query()->find(1234);
$user = \UserModel::where('id', 1234)->first();
$user = \UserModel::where('id', '=', 1234)->first();        // "=" 参数可省略
$user = \UserModel::where(['id', '=', 1234])->first();      // "=" 参数可省略

//以下3种方式相同
$users = \UserModel::where('id', '>=', 1234)->get()->toArray();

$users = \UserModel::where(['id', '>=', 1234])->get()->toArray();

$users = \UserModel::where(
    [
        ['id', '>=', 1234],
    ]
)->get()->toArray();

//以下4种方式相同
$users = \UserModel::where('id', [1,2,3,4])->get()->toArray();

$users = \UserModel::where('id', 'in', [1,2,3,4])->get()->toArray();

$users = \UserModel::where(
    [
        ['id', [1,2,3,4]],
    ]
)->get()->toArray();

$users = \UserModel::where(
    [
        ['id', 'in', [1,2,3,4]],
    ]
)->get()->toArray();


多条件
$users = \UserModel::where(
    [
        ['id', '>=', 1234],
        ['enabled', '=', 1],        // "=" 参数可省略
        ['created_at', '>', 1523324089]
    ]
)->get()->toArray();

分页
\UserModel::where(...)->offset(0)->limit(20)->get()->toArray();


写入，返回新 id
$id = \UserModel::getInstance()->insertEntity($data);

更新指定 id 的用户
\UserModel::getInstance()->updateById($data, $id);

批量更新，配合条件查询
\UserModel::where('id', '>', 123)->update($data);

删除指定 id
\UserModel::getInstance()->delete($id);

批量删除
\UserModel::where('id', '>', 123)->delete();

原生 SQL（不常用）
$users = \DB::select('select * from users where id > 1234');
\DB::insert('insert into users (id, name) values (?, ?)', [123, 'henter']);

```

### 注意

`insertEntity` `updateEntity` `batchUpdate` `updateById` 等方法默认会过滤掉 null 字段

详见 `filterFields` 方法（跟旧的 db 类一样）

如果需要更新某字段值为 null ，请用 `update` 方法显式指定，如下

```
\xxxModel::where(['id' => 123])->update(['xxx' => null]);
```

### TODO

TODO


更多请参考 Laravel Illuminate Query Builder

https://laravel.com/docs/5.6/queries

https://laravel.com/docs/5.6/database

## 其它

Illuminate 附带的 Eloquent ORM 暂不支持，短期内不建议使用