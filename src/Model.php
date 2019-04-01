<?php
/**
 * File Model.php
 * @henter
 * Time: 2018-04-03 18:08
 *
 */

use Illuminate\Support\Str;

/**
 * eloquent 查询语法
 * 详细参考 https://laravel.com/docs/5.6/queries
 *
 * Class Model
 */
class Model
{
    /**
     * custom connection from db config key
     * @var string
     */
    protected $connection = 'default';

    //TODO, to be removed
    const FIELD_COMMON_TYPE = 0;

    use DbCompatible;

    public static $lastQuery = '';

    protected static $builders = [];

    protected static $instances = [];

    /**
     * @param null $class
     * @return static
     */
    public static function getInstance()
    {
        $class = static::class;
        if (empty(static::$instances[$class])) {
            self::$instances[$class] = new $class;
        }
        return self::$instances[$class];
    }

    /**
     * @param string $connection
     * @return $this
     */
    public function setConnection(string $connection)
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * @param null $connection
     * @return \Illuminate\Database\Connection
     */
    private function getConnection($connection = null)
    {
        return \DB::connection($connection ?: $this->connection);
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    public static function onWriteConnection()
    {
        return static::query()->useWritePdo();
    }

    /**
     * @param \Closure $callback
     * @param int $attempts
     * @throws \Exception
     * @throws \Throwable
     */
    public function transaction(\Closure $callback, $attempts = 5)
    {
        $this->getConnection()->transaction($callback, $attempts);
    }

    /**
     * @throws \Exception
     */
    public function beginTransaction()
    {
        $this->getConnection()->beginTransaction();
    }

    public function rollBack()
    {
        $this->getConnection()->rollBack();
    }

    public function commit()
    {
        $this->getConnection()->commit();
    }

    /**
     * @return string
     */
    public function getLastQuery()
    {
        return self::$lastQuery;
    }

    /**
     * @param $fresh
     * @return \Illuminate\Database\Query\Builder
     */
    private function getBuilder($fresh = false)
    {
        $class = static::class;
        if (empty(static::$builders[$class]) || $fresh) {
            self::$builders[$class] = $this->getConnection()->table($this->getTableName());
        }
        return self::$builders[$class];
    }

    private function setBuilder(\Illuminate\Database\Query\Builder $builder)
    {
        $class = static::class;
        self::$builders[$class] = $builder;
        return $this;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function currentBuilder()
    {
        return self::$builders[static::class] ?? null;
    }

    /**
     * for unit testing purpose only
     *
     * @return $this
     */
    public function clearBuilder()
    {
        unset(self::$builders[static::class]);
        return $this;
    }

    /**
     * @param string $connection
     * @return \Illuminate\Database\Query\Builder
     */
    public function newQuery($connection = null)
    {
        return $this->getConnection($connection)->table($this->getTableName());
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    public static function query()
    {
        return (new static)->newQuery();
    }

    /**
     * @param string $connection
     * @return \Illuminate\Database\Query\Builder
     */
    public static function on($connection = null)
    {
        return (new static)->newQuery($connection);
    }

    /**
     * @param array $where
     * @param int $limit
     * @param int $offset
     * @param array $fields
     * @return array
     */
    public function getList(array $where, int $limit = 20, int $offset = 0, array $fields = [])
    {
        $fields = $fields ?: ['*'];

        //$qb = self::where($where);
        $qb = $this->conditionsQuery($where); //已兼容旧的 conditions 参数 或 where 参数

        if (!$where && !$limit) {
            return [];
        }

        $qb->selectRaw(implode(', ', $fields));
        if ($offset) {
            $qb->offset($offset);
        }
        if ($limit) {
            $qb->limit($limit);
        }
        return $qb->get()->toArray();
    }

    /**
     * 根据条件获取全部数据，无分页
     * @param array $where
     * @param array $fields
     * @return array
     */
    public function getAllList(array $where, array $fields = [])
    {
        return $this->getList($where, 0, 0, $fields);
    }

    /**
     * 改进 where 查询条件
     * 新增以下查询方式
     * (仅在此静态 where 方法内生效，Builder 里的 where 链式调用不支持部分语法)
     *
     *      \xxModel::where('id', [1,2,3])
     *      \xxModel::where('id', 'in', [1,2,3])
     *      \xxModel::where('id', 'not in', [1,2,3])
     *      \xxModel::where([
     *          'type' => [1,2,3],
     *          'symbol' => 'BTC'
     *      ])
     *      \xxModel::where([
     *          ['id', 'in', [1,2,3]],
     *          ['symbol', '=', 'BTC']
     *      ])
     *      \xxModel::where([
     *          ['id', [1,2,3]],
     *          ['symbol', 'BTC']
     *      ])
     *
     * list demo:
     * \xxModel::where(..)->get()->toArray();
     *
     * single row:
     * \xxModel::where(..)->first();
     *
     * @param $column
     * @param null $op
     * @param null $value
     * @return \Illuminate\Database\Query\Builder
     */
    public static function where($column, $op = null, $value = null)
    {
        $q = static::query();
        if ($op == 'in') {
            return $q->whereIn($column, $value);
        } elseif ($op == 'not in' || $op == 'notin') {
            return $q->whereNotIn($column, $value);
        }

        //where in, if $op is array
        if (!is_array($column)) {
            if (is_null($value) && is_array($op)) {
                return $q->whereIn($column, $op);
            } else {
                return $q->where($column, $op, $value);
            }
        }

        //check for [c, o, v]
        if (isset($column[0]) && !is_array($column[0]) && count($column) >= 2) {
            return self::where(...$column);
        }

        //[[c, o, v],...] or [k => v, k2 => v2]
        foreach ($column as $key => $value) {
            //[[c, o, v],...]
            if (is_numeric($key) && is_array($value)) {
                if (count($value) == 3) {
                    list($k, $o, $v) = $value;
                    $o = strtolower($o);
                } else {
                    list($k, $v) = $value;
                    $o = '=';
                }
                if ($o == 'in') {
                    $q->whereIn($k, $v);
                } elseif ($o == 'not in' || $o == 'notin') {
                    $q->whereNotIn($k, $v);
                } elseif ($o == 'group') {
                    //for functions ?
                    if (self::checkRaw($k)) {
                        $as = $v ?: md5($k);
                        $q->select(['*']);//TODO
                        $q->selectRaw($k.' as '.$as);
                    } else {
                        $as = $v ?: $k;
                    }
                    $q->groupBy($as);
                } elseif ($o == 'order by') {
                    $q->orderBy($k, $v);
                } elseif (is_array($v)) {
                    $q->whereIn($k, $v);
                } else {
                    $q->where(...array_values($value));
                }
            } else {
                // [k => v, k2 => v2]
                if (is_array($value)) {
                    $q->whereIn($key, $value);
                } else {
                    $q->where($key, '=', $value, 'and');
                }
            }
        }
        return $q;
    }

    /**
     * @param $id
     * @param array $fields
     * @return array|null
     */
    public static function find($id, $fields = ['*'])
    {
        return self::query()->find($id, $fields);
    }

    /**
     * @param int $id
     * @return bool
     */
    public function delete($id = 0)
    {
        //旧的参数传入的是table name, 忽略掉
        $id = (int)$id;
        if (!$id) {
            if (!$this->currentBuilder()) {
                return false;
            }
            //禁止不带 where 条件的删除
            if (!$this->currentBuilder()->wheres) {
                return false;
            }
            $this->currentBuilder()->delete();
        } else {
            self::query()->delete($id);
        }

        //always return true, same as old dbObject class
        return true;
    }

    /**
     * @param array $condition
     * @return int
     */
    public function batchDelete(array $condition)
    {
        if (!$condition) {
            return false;
        }

        $qb = $this->conditionsQuery($condition);
        if (!$qb->wheres) {
            return false;
        }

        return $qb->delete();
    }

    /**
     * @param $data
     * @return int|bool
     */
    public function insertEntity(array $data)
    {
        //返回 ID 或 true (有些表无id主键) TODO
        $data = $this->filterFields($data);
        if (!$data) {
            return false;
        }

        $this->setCreatedAt($data);

        return self::query()->insertGetId($data) ?: true;
    }

    /**
     * @param array $data
     * @param bool $checkAffectRows
     * @return bool
     */
    public function updateEntity(array $data, $checkAffectRows = true)
    {
        if (!$this->currentBuilder()) {
            return false;
        }
        //禁止不带 where 条件的更新
        if (!$this->currentBuilder()->wheres) {
            return false;
        }

        $data = $this->filterFields($data);
        if (!$data) {
            return false;
        }

        $this->setUpdatedAt($data);

        $n = $this->currentBuilder()->update($data);

        //不检查更新条数
        if (!$checkAffectRows) {
            return true;
        }

        return $n > 0;
    }

    /**
     * @param array $attributes
     * @param array $data
     * @return bool
     */
    public function updateOrInsertEntity(array $attributes, array $data = [])
    {
        $q = self::query();

        $data = $this->filterFields($data);

        if (!$q->where($attributes)->exists()) {
            $this->setCreatedAt($data);
            return $q->insert(array_merge($attributes, $data));
        } else {
            $this->setUpdatedAt($data);
            return (bool)$q->take(1)->update($data);
        }
    }

    /**
     * set updated_at
     *
     * @param array $data
     * @param $timestamp
     */
    protected function setUpdatedAt(array &$data, $timestamp = null)
    {
        $timestamp = $timestamp ?? time();
        if (array_key_exists('updated_at', $this->dbFields) && !array_key_exists('updated_at', $data)) {
            $data['updated_at'] = $timestamp;
        }
    }

    /**
     * set created_at and updated_at
     *
     * @param array $data
     */
    protected function setCreatedAt(array &$data)
    {
        $timestamp = time();
        if (array_key_exists('created_at', $this->dbFields) && !array_key_exists('created_at', $data)) {
            $data['created_at'] = $timestamp;
        }

        $this->setUpdatedAt($data, $timestamp);
    }

    /**
     * @param array $data
     * @param int $id
     * @return bool
     */
    public function updateById(array $data, $id = 0)
    {
        $id = (int)$id;
        if (!$id) {
            return false;
        }

        $data = $this->filterFields($data);
        if (!$data) {
            return false;
        }

        $n = self::query()->where('id', $id)->update($data);
        return $n > 0;
    }

    /**
     * @param array $where
     * @param array $data
     * @param bool $checkAffectRows
     * @return bool
     */
    public function batchUpdate(array $where, array $data, $checkAffectRows = true)
    {
        $data = $this->filterFields($data);
        if (!$where || !$data) {
            return false;
        }

        //支持旧的 where 条件
        $qb = $this->conditionsQuery($where);
        if (!$qb->wheres) {
            return false;
        }

        $this->setUpdatedAt($data);
        $n = $qb->update($data);

        //不检查更新条数
        if (!$checkAffectRows) {
            return true;
        }

        //return self::where($where)->update($data);
        return $n > 0;
    }

    /**
     * 根据条件获取单条记录
     *
     * @param array $where
     * @param array $fields
     * @return array|null
     */
    public function findOne($where, $fields = [])
    {
        if (!$where) {
            return null;
        }
        $fields = $fields ?: ['*'];
        return $this->conditionsQuery($where)->selectRaw(implode(',', $fields))->first();
    }


    /**
     * 过滤字段
     * @param array $data
     * @return array
     */
    private function filterFields(array $data)
    {
        if (!$data || !$this->dbFields) {
            return [];
        }

        $_data = [];
        foreach ($this->dbFields as $fieldKey => $fieldOptions) {
            if (isset($data[$fieldKey])) {
                $_data[$fieldKey] = $data[$fieldKey];
            }
        }
        return $_data;
    }

    /**
     * @param $query
     * @param array $bindings
     * @return mixed
     */
    public function rawQuery($query, $bindings = [])
    {
        if (Str::startsWith(strtolower($query), ['select', 'show'])) {
            return $this->getConnection()->select($query, $bindings);
        } else {
            return $this->getConnection()->statement($query, $bindings);
        }
    }

    /**
     * @param $query
     * @param array $bindings
     * @return mixed|null
     */
    public function rawQueryOne($query, $bindings = [])
    {
        $ret = $this->rawQuery($query, $bindings);
        if (is_array($ret)) {
            return current($ret);
        }
        return null;
    }

    /**
     * @param array $where
     * @return int
     */
    public function count(array $where = [])
    {
        return $this->conditionsQuery($where)->count();
    }

    /**
     * @param $method
     * @param $params
     * @return mixed
     */
    public static function __callStatic($method, $params)
    {
        return (self::getInstance())->$method(...$params);
    }
}
