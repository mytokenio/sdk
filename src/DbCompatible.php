<?php
/**
 * File DbCompatible.php
 * @henter
 * Time: 2018-04-03 18:08
 *
 */

/**
 * 兼容旧的 dbObject 类方法
 *
 * Trait DbCompatible
 */
trait DbCompatible
{
    public $pageLimit = 20;
    public $totalPages = 0;
    public $totalCount = 0;

    protected $dbTable = '';
    protected $dbFields = [];

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->dbTable;
    }

    public function getFields($type = self::FIELD_COMMON_TYPE)
    {
        return $this->fieldTypes[$type] ?? [];
    }

    /**
     * TODO, to be removed
     * @return $this
     * @deprecated
     */
    public function getDB()
    {
        return $this;
    }

    /**
     * @deprecated
     */
    public function startTransaction()
    {
        $this->beginTransaction();
    }

    /**
     * @deprecated
     */
    public function rollback()
    {
        $this->rollBack();
    }

    /**
     * TODO, to be removed
     *
     * @param array $fields
     * @return array|null
     * @deprecated
     */
    protected function getOne(array $fields = [])
    {
        if (!$this->currentBuilder()) {
            return null;
        }

        $fields = $fields ?: ['*'];
        return $this->currentBuilder()->selectRaw(implode(',', $fields))->first();
    }

    /**
     * TODO, 业务层不需要调用此方法，已在 db 类做过滤处理
     * @param $data
     * @return mixed
     * @deprecated
     * @throws \Exception
     */
    public function filterParams($data)
    {
        return $this->filterFields($data);
    }

    /**
     * @param $id
     * @param array $fields
     * @return array|null
     */
    protected function byId($id, array $fields)
    {
        $fields = $fields ?: ['*'];

        return $this->getBuilder(true)->where('id', $id)->selectRaw(implode(',', $fields))->first();
    }


    /**
     * 用于新旧查询条件兼容
     *
     * @param $conditions
     * @return \Illuminate\Database\Query\Builder
     */
    public function conditionsQuery($conditions)
    {
        self::_initConditions($conditions);
        return self::currentBuilder() ?: self::query();
    }


    /**
     * TODO, to be removed
     *
     * @param $conditions
     * @param array $option
     * @param array $fields
     * @return array
     * @throws \Exception
     * @deprecated
     */
    public function getListByConditions($conditions, $option = array(), $fields = array('id'))
    {
        $res = [];

        if (isset($option['need_pagination']) && true == $option['need_pagination']) {
            $this->pageLimit    = isset($option['limit'])   ? intval($option['limit'])   : $this->pageLimit;
            $pageNum            = isset($option['page_num']) ? intval($option['page_num']) : 1;
            $offset = $this->pageLimit * ($pageNum - 1);

            $res['list'] = $this->getList($conditions, $this->pageLimit, $offset, $fields);
            $this->totalCount = $this->currentBuilder()->getCountForPagination(); //一定要在 getList 之后执行以获取 query builder
            $this->totalPages = ceil($this->totalCount / $this->pageLimit);

            $res['total_page']  = $this->totalPages;
            $res['total_count'] = $this->totalCount;

        } else {

            $offset = $option['offset'] ?? 0;
            $limit = $option['limit'] ?? $this->pageLimit;
            $res['list'] = $this->getList($conditions, (int)$limit, (int)$offset, $fields);
        }

        return $res;
    }

    /**
     * TODO to be removed
     *
     * @param $conditions
     * @param array $fields
     * @return array
     * @deprecated
     */
    public function getAllListByConditions($conditions, $fields = [])
    {
        return [
            'list' => $this->getAllList($conditions, $fields)
        ];
    }

    /**
     * 根据条件获取列表数量
     * TODO, to be removed
     *
     * @param $conditions
     * @return int
     * @throws \Exception
     * @deprecated
     */
    public function getListCntByConditions($conditions)
    {
        return $this->count($conditions);
    }

    /**
     * TODO, to be removed
     *
     * @param $table
     * @param int $limit
     * @param array $fields
     * @return array
     * @deprecated
     */
    public function get($limit = null, array $fields = [])
    {
        $options = is_array($limit) ? $limit : [0, $limit];
        $fields = $fields ?: ['*'];
        list($offset, $limit) = $options;
        /**
         * @var $qb \Illuminate\Database\Query\Builder
         */
        $qb = $this->currentBuilder();
        if ($qb) {
            $qb->selectRaw(implode(', ', $fields));
            if ((int)$offset) {
                $qb->offset((int)$offset);
            }
            if ((int)$limit) {
                $qb->limit((int)$limit);
            }
            return $qb->get()->toArray();
        }
        return [];
    }

    /**
     * 检查是否需用原生 sql（如 用到函数、计算式等）
     * 有可能出现在 where 或 order by
     * @param $string
     * @return bool
     */
    public function checkRaw($string)
    {
        return strpos($string, ')')
            || strpos($string, '+')
            || strpos($string, '-')
            || strpos($string, '*')
            || strpos($string, '/')
            ;
    }

    /**
     * TODO, to be removed
     *
     * @param array $conditions
     * @return bool
     * @deprecated
     */
    protected function _initConditions(array $conditions)
    {
        /**
         * @var $builder \Illuminate\Database\Eloquent\Builder
         */
        $builder = $this->getBuilder(true);

        if (!$conditions) {
            return false;
        }

        foreach ($conditions as $condition) {
            //Illuminate的语法
            if (!isset($condition['op'])) {
                $this->setBuilder(self::where($conditions));
                return true;
            }

            switch (strtolower($condition['op'])) {
                case 'in':
                    $builder->whereIn($condition['field'], $condition['values']);
                    break;

                case 'not in':
                    $builder->whereNotIn($condition['field'], $condition['values']);
                    break;

                case 'between':
                    $builder->whereBetween($condition['field'], $condition['values']);
                    break;

                case 'not between':
                    $builder->whereNotBetween($condition['field'], $condition['values']);
                    break;

                case '>=':
                case '>':
                case '<=':
                case '<':
                    if ($this->checkRaw($condition['field'])) {
                        $builder->whereRaw($condition['field'].' '.$condition['op'].' '.$condition['value']);
                    } else {
                        $builder->where($condition['field'], $condition['op'], $condition['value']);
                    }
                    break;

                case '<>':
                case '<=>':
                case '!=':
                    $builder->where($condition['field'], '<>', $condition['value']);
                    break;

                case 'is':
                    if (is_null($condition['value'])) {
                        $builder->whereNull($condition['field']);
                    }
                    break;

                case 'is not':
                    if (is_null($condition['value'])) {
                        $builder->whereNotNull($condition['field']);
                    }
                    break;

                case 'like':
                    $builder->where($condition['field'], 'like', "%" .$condition['value']."%");
                    break;

                case 'lmatch':
                    $builder->where($condition['field'], 'like', $condition['value']."%");
                    break;

                case '=':
                case 'eq':
                    $builder->where($condition['field'], $condition['value']);
                    break;

                case 'col_eq':
                    $builder->whereColumn($condition['field'], $condition['value']);
                    break;

                case 'group':
                    //for functions ?
                    if ($this->checkRaw($condition['field'])) {
                        $as = md5($condition['field']);
                        $builder->select(['*']);//TODO
                        $builder->selectRaw($condition['field'].' as '.$as);
                    } else {
                        $as = $condition['field'];
                    }
                    $builder->groupBy($as);
                    break;

                case 'order by':
                    //support functions
                    if ($this->checkRaw($condition['field'])) {
                        $builder->orderByRaw($condition['field'].' '.$condition['type']);
                    } else {
                        $builder->orderBy($condition['field'], $condition['type']);
                    }
                    break;

                case 'custom_join':
                    if ($condition['joinType'] == 'INNER') {
                        $builder->join($condition['joinTable'], $condition['joinLKey'], '=', $condition['joinRKey']);
                    } else {
                        $builder->leftJoin($condition['joinTable'], $condition['joinLKey'], '=', $condition['joinRKey']);
                    }
                    break;

                case 'mutli_custom_join':
                    $builder->join($condition['joinTable'], function ($join) use ($condition) {
                        /**
                         * @var $join \Illuminate\Database\Query\JoinClause
                         */
                        foreach ($condition['joinKeys'] as $joinCondition) {
                            $join->on($joinCondition['joinLKey'], '=', $joinCondition['joinRKey']);
                        }
                    });
                    break;

                default:
                    break;
            }
        }
        return true;
    }
}
