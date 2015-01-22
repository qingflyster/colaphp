<?php

/**
 *
 */
abstract class Cola_Model
{

    const ERROR_VALIDATE_CODE = -400;

    /**
     * Db name
     *
     * @var string
     */
    protected $_db = '_db';

    /**
     * Table name, with prefix and main name
     *
     * @var string
     */
    protected $_table;

    /**
     * Primary key
     *
     * @var string
     */
    protected $_pk = 'id';

    /**
     * Cache config
     *
     * @var mixed, string for config key and array for config
     */
    protected $_cache = '_cache';

    /**
     * Cache expire time
     *
     * @var int
     */
    protected $_ttl = 60;

    /**
     * Validate rules
     *
     * @var array
     */
    protected $_validate = array();

    /**
     * Error infomation
     *
     * @var array
     */
    public $error = array();

    /**
     * Load data
     *
     * @param int $id
     * @return array
     */
    public function load($id, $col = null)
    {
        is_null($col) && $col = $this->_pk;

        $sql = "select * from {$this->_table} where {$col} = '{$id}'";

        try {
            return $this->db->row($sql);
        } catch (Exception $e) {
            $this->error = array('code' => $e->getCode(), 'msg' => $e->getMessage());
            return false;
        }
    }

    /**
     * Find result
     *
     * @param array $opts
     * @return array
     */
    public function find(array $opts = array())
    {
        is_string($opts) && $opts = array('where' => $opts);

        $opts += array('table' => $this->_table);

        try {
            return $this->db->find($opts);
        } catch (Exception $e) {
            $this->error = array('code' => $e->getCode(), 'msg' => $e->getMessage());
            return false;
        }
    }

    /**
     * Count result
     *
     * @param string $where
     * @param string $table
     * @return int
     */
    public function count($where, $table = null)
    {
        if (is_null($table)) {
            $table = $this->_table;
        }

        try {
            return $this->db->count($where, $table);
        } catch (Exception $e) {
            $this->error = array('code' => $e->getCode(), 'msg' => $e->getMessage());
            return false;
        }
    }

    /**
     * Get SQL result
     *
     * @param string $sql
     * @return array
     */
    public function sql($sql)
    {
        try {
            return $this->db->sql($sql);
        } catch (Exception $e) {
            $this->error = array('code' => $e->getCode(), 'msg' => $e->getMessage());
            return false;
        }
    }

    /**
     * Insert
     *
     * @param array $data
     * @param string $table
     * @return boolean
     */
    public function insert($data, $table = null)
    {
        if (is_null($table)) {
            $table = $this->_table;
        }

        try {
            return $this->db->insert($data, $table);
        } catch (Exception $e) {
            $this->error = array('code' => $e->getCode(), 'msg' => $e->getMessage());
            return false;
        }
    }

    /**
     * Update
     *
     * @param int $id
     * @param array $data
     * @return boolean
     */
    public function update($id, $data)
    {
        $where = $this->_pk . '=' . (is_int($id) ? $id : "'{$id}'");

        try {
            $this->db->update($data, $where, $this->_table);
            return true;
        } catch (Exception $e) {
            $this->error = array('code' => $e->getCode(), 'msg' => $e->getMessage());
            return false;
        }
    }

    /**
     * Delete
     *
     * @param string $id
     * @param string $col
     * @return boolean
     */
    public function delete($id, $col = null, $table = null)
    {
        is_null($col) && $col = $this->_pk;
        is_null($table) && $table = $this->_table;
        $id = $this->escape($id);
        $where = "{$col} = '{$id}'";

        try {
            return $this->db->delete($where, $table);
        } catch (Exception $e) {
            $this->error = array('code' => $e->getCode(), 'msg' => $e->getMessage());
            return false;
        }
    }

    /**
     * Escape string
     *
     * @param string $str
     * @return string
     */
    public function escape($str)
    {
        return $this->db->escape($str);
    }

    /**
     * Connect db from config
     *
     * @param array $config
     * @param string
     * @return Cola_Ext_Db
     */
    public function db($name = null)
    {
        is_null($name) && $name = $this->_db;

        if (is_array($name)) {
            return Cola::factory('Cola_Ext_Db', $name);
        }

        $regName = "_cola_db_{$name}";
        if (!$db = Cola::getReg($regName)) {
            $config = (array) Cola::getConfig($name) + array('adapter' => 'Pdo_Mysql');
            $db = Cola::factory('Cola_Ext_Db', $config);
            Cola::setReg($regName, $db);
        }

        return $db;
    }

    /**
     * Init Cola_Ext_Cache
     *
     * @param mixed $name
     * @return Cola_Ext_Cache
     */
    public function cache($name = null)
    {
        is_null($name) && ($name = $this->_cache);

        if (is_array($name)) {
            return Cola::factory('Cola_Ext_Cache', $name);
        }

        $regName = "_cola_cache_{$name}";
        if (!$cache = Cola::getReg($regName)) {
            $config = (array) Cola::getConfig($name);
            $cache = Cola::factory('Cola_Ext_Cache', $config);
            Cola::setReg($regName, $cache);
        }

        return $cache;
    }

    /**
     * Get function cache
     *
     * @param string $func
     * @param mixed $args
     * @param int $ttl
     * @param string $key
     * @return mixed
     */
    public function cached($func, $args = array(), $ttl = null, $key = null)
    {
        is_null($ttl) && ($ttl = $this->_ttl);

        if (!is_array($args)) {
            $args = array($args);
        }

        if (is_null($key)) {
            $key = get_class($this) . '-' . $func . '-' . sha1(serialize($args));
        }

        if (!$data = $this->cache->get($key)) {
            $data = call_user_func_array(array($this, $func), $args);
            $this->cache->set($key, $data, $ttl);
        }

        return $data;
    }

    /**
     * Validate
     *
     * @param array $data
     * @param boolean $ignoreNotExists
     * @param array $rules
     * @return boolean
     */
    public function validate($data, $ignoreNotExists = false, $rules = null)
    {
        is_null($rules) && $rules = $this->_validate;
        if (empty($rules)) {
            return true;
        }

        $validate = new Cola_Ext_Validate();

        $result = $validate->check($data, $rules, $ignoreNotExists);

        if (!$result) {
            $this->error = array('code' => self::ERROR_VALIDATE_CODE, 'msg' => $validate->errors);
            return false;
        }

        return true;
    }

    /**
     * Dynamic set vars
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value = null)
    {
        $this->$key = $value;
    }

    /**
     * Dynamic get vars
     *
     * @param string $key
     */
    public function __get($key)
    {
        switch ($key) {
            case 'db' :
                $this->db = $this->db();
                return $this->db;

            case 'cache' :
                $this->cache = $this->cache();
                return $this->cache;

            case 'config':
                $this->config = Cola::getInstance()->config;
                return $this->config;

            default:
                throw new Cola_Exception('Undefined property: ' . get_class($this) . '::' . $key);
        }
    }

}
