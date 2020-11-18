<?php
namespace Drupal\Core\Database;
$_ENV['lastInc']=0;
class Statement extends \PDOStatement implements StatementInterface {
    static function coucou(){return __METHOD__;}
    public $dbh;
    public $allowRowCount = FALSE;
    protected function __construct($dbh) {
        #Connection -> first found is database/Connection, not mysql/Connection
        $this->dbh = $dbh;
        $this->setFetchMode(\PDO::FETCH_OBJ);
    }
    public function execute($args = [], $options = []) {
        if (isset($options['fetch'])) {
            if (is_string($options['fetch'])) {
                // \PDO::FETCH_PROPS_LATE tells __construct() to run before properties
                // are added to the object.
                $this->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $options['fetch']);
            }
            else {
                $this->setFetchMode($options['fetch']);
            }
        }

        $logger = $this->dbh->getLogger();
        if (!empty($logger)) {
            $query_start = microtime(TRUE);
        }
        $_d=[];
        $_a=$this->queryString;
        $__fq=$this->interpolateQuery($_a, $args);
        $_ENV['lastKey']=$__fq;
#$_ENV['_sql0'][]=$__fq;
        $_b=$return = parent::execute($args);
#$_d=$this->fetchAll();
        if(0){
            foreach($this as $_item){$_d[]=$_item;}
            $_ENV['_sql'][$__fq]=$_d;
            reset($this);
        }
#foreach($this as $_item){$_d[]=$_item;}
#$_c=$this->fetchAll(PDO::FETCH_COLUMN);
        $a=1;


        if (!empty($logger)) {
            $query_end = microtime(TRUE);
            $logger->log($this, $args, $query_end - $query_start);
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryString() {
        return $this->queryString;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchCol($index = 0) {
        $a=$this->fetchAll(\PDO::FETCH_COLUMN, $index);
        $b = $this->queryString;
        return $a;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllAssoc($key, $fetch = NULL) {
        $return = [];
        if (isset($fetch)) {
            if (is_string($fetch)) {
                $this->setFetchMode(\PDO::FETCH_CLASS, $fetch);
            }
            else {
                $this->setFetchMode($fetch);
            }
        }

        foreach ($this as $record) {
            $record_key = is_object($record) ? $record->$key : $record[$key];
            $return[$record_key] = $record;
        }
        $_ENV['_sql0'][$_ENV['lastInc'].' '.$_ENV['lastKey']]=$return;$_ENV['lastInc']++;
        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAllKeyed($key_index = 0, $value_index = 1) {
        $return = [];
        $this->setFetchMode(\PDO::FETCH_NUM);
        foreach ($this as $record) {
            $return[$record[$key_index]] = $record[$value_index];
        }
        $_ENV['_sql0'][$_ENV['lastInc'].' '.$_ENV['lastKey']]=$return;$_ENV['lastInc']++;
        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchField($index = 0) {
        // Call \PDOStatement::fetchColumn to fetch the field.
        return $this->fetchColumn($index);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAssoc() {
        // Call \PDOStatement::fetch to fetch the row.
        return $this->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount() {
        // SELECT query should not use the method.
        if ($this->allowRowCount) {
            return parent::rowCount();
        }
        else {
            throw new RowCountException();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setFetchMode($mode, $a1 = NULL, $a2 = []) {
        // Call \PDOStatement::setFetchMode to set fetch mode.
        // \PDOStatement is picky about the number of arguments in some cases so we
        // need to be pass the exact number of arguments we where given.
        switch (func_num_args()) {
            case 1:
                return parent::setFetchMode($mode);

            case 2:
                return parent::setFetchMode($mode, $a1);

            case 3:
            default:
                return parent::setFetchMode($mode, $a1, $a2);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll($mode = NULL, $column_index = NULL, $constructor_arguments = NULL) {
        // Call \PDOStatement::fetchAll to fetch all rows.
        // \PDOStatement is picky about the number of arguments in some cases so we
        // need to be pass the exact number of arguments we where given.
        switch (func_num_args()) {
            case 0:
                $r=parent::fetchAll();
                $_ENV['_sql0'][$_ENV['lastInc'].' '.$_ENV['lastKey']]=$r;$_ENV['lastInc']++;return $r;

            case 1:
                $r=parent::fetchAll($mode);
                $_ENV['_sql0'][$_ENV['lastInc'].' '.$_ENV['lastKey']]=$r;$_ENV['lastInc']++;return $r;

            case 2:
                $r=parent::fetchAll($mode, $column_index);
                $_ENV['_sql0'][$_ENV['lastInc'].' '.$_ENV['lastKey']]=$r;$_ENV['lastInc']++;return $r;

            case 3:
            default:
                $r=parent::fetchAll($mode, $column_index, $constructor_arguments);
                $_ENV['_sql0'][$_ENV['lastInc'].' '.$_ENV['lastKey']]=$r;$_ENV['lastInc']++;return $r;
        }
    }
    public static function interpolateQuery($query, $params) {
        $keys = array();
        # build a regular expression for each parameter
        foreach ($params as $key => &$value) {
            if (is_string($key)) {
                if(substr($key,0,1)==':')$keys[] = '/'.$key.'/';
                else $keys[] = '/:'.$key.'/';
            } else {
                $keys[] = '/[?]/';
            }
            if (!is_numeric($value)) {
                $value = "'" . addslashes($value) . "'";
            }
        }
        unset($value);
        $query = preg_replace($keys, $params, $query, 1, $count);
        return $query;
    }
}
return;?>
