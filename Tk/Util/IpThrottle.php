<?php
namespace Tk\Util;

/**
 * Class IpThrottle
 * 
 * This object can track and give you the ability to throttle
 *  the number of actions/hits/submissions that a single IP can
 *  do within a given time-frame
 *
 *  <code>
 *     $ipt = IpThrottle::create($this->getDb());
 *
 *     $max = 5;
 *     $minutes = 10;
 *     $from = \Tk\Date::create()->sub(new \DateInterval('PT'.$minutes.'M'));
 *     $list = $ipt->getIpSubmissions($this->getRequest()->getClientIp(), $from, null, $this->getRequest()->getTkUri()->getRelativePath());
 *     vd(count($list));
 *     if (count($list) >= $max) {
 *        // TODO: exit/redirect or whatever suits the situation
 *     }
 *
 *      ...
 *
 *     // Log a page hit
 *     $ipt->logIp(IpThrottle::getClientIp(), IpThrottle::getUri());
 *
 *  </code>
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2016 Michael Mifsud
 */
class IpThrottle
{

    /**
     * EG: 2009-12-31 24:59:59
     */
    const FORMAT_ISO_DATETIME = 'Y-m-d H:i:s';

    /**
     * The default table name to use
     * Can be changed in the config if needed Data::$DB_TABLE = '';
     *
     * @var string
     */
    public static $DB_TABLE = '_ipthrottle';

    /**
     * @var \PDO
     */
    protected $db = null;

    /**
     * @param \PDO $db
     */
    public function __construct($db)
    {
        $this->setDb($db);
    }

    /**
     * @param \PDO|null $db
     * @return static
     */
    public static function create($db = null)
    {
        if (!$db) $db = \Tk\Config::getInstance()->getDb();
        $obj = new static($db);
        return $obj;
    }

    /**
     *
     * @param string $ip
     * @param \DateTime $dateFrom
     * @param null|\DateTime $dateTo (optional) Defaults to NOW if not used
     * @param null|string $key
     * @param null|int $level (optional) Only get records with a higher level than submitted null = get all
     * @return array|bool
     * @throws /Exception
     */
    public function getIpSubmissions($ip, $dateFrom, $dateTo = null, $key = '', $level = null)
    {
        if (!$this->hasTable($this->getTable())) return array();
        if (!$dateTo) $dateTo = new \DateTime();
        if ($dateFrom >= $dateTo) return array();
        if ($key) $key = sprintf("AND `key` LIKE '%s'", $key);
        $lvlSql = '';
        if ($level !== null) {
            $lvlSql = sprintf(' AND `level` > %s', $level);
        }

        //$sql = sprintf("SELECT * FROM %s WHERE ip LIKE '%s' AND timestamp > (DATE_ADD(NOW(), INTERVAL -{$timeToRestrict} HOUR))"
        $sql = sprintf("SELECT * FROM %s WHERE `ip` LIKE '%s' %s %s AND `timestamp` > '%s' AND `timestamp` <= '%s' ",
            $this->getTable(),
            $ip,
            $key,
            $lvlSql,
            $dateFrom->format(self::FORMAT_ISO_DATETIME),
            $dateTo->format(self::FORMAT_ISO_DATETIME)
        );
        $res = $this->getDb()->query($sql);
        return $res->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * @param string $key
     * @param \DateTime $dateFrom
     * @param null|\DateTime $dateTo (optional) Defaults to NOW if not used
     * @param string $ip
     * @param null|int $level
     * @return array|bool
     * @throws \Exception
     */
    public function getKeySubmissions($key, $dateFrom, $dateTo = null, $ip = '', $level = null)
    {
        if (!$this->hasTable($this->getTable())) return array();
        if (!$dateTo) $dateTo = new \DateTime();
        if ($dateFrom >= $dateTo) return array();
        if ($ip) $ip = sprintf("AND `ip` LIKE %s", $ip);
        $lvlSql = '';
        if ($level !== null) {
            $lvlSql = sprintf(' AND `level` > %s', $level);
        }

        //$sql = sprintf("SELECT * FROM %s WHERE ip LIKE '%s' AND timestamp > (DATE_ADD(NOW(), INTERVAL -{$timeToRestrict} HOUR))"
        $sql = sprintf("SELECT * FROM %s WHERE `key` LIKE '%s' %s %s AND `timestamp` > '%s' AND `timestamp` <= '%s' ",
            $this->getTable(),
            $key,
            $ip,
            $lvlSql,
            $dateFrom->format(self::FORMAT_ISO_DATETIME),
            $dateTo->format(self::FORMAT_ISO_DATETIME)
        );
        $res = $this->getDb()->query($sql);
        return $res->fetchAll(\PDO::FETCH_OBJ);
    }

    /**
     * Log an IP hit to the DB
     *
     * @param string $notes (optional)
     * @param int $level (optional) Threat Level value Higher values mean higher threat
     * @param string $ip (optional)
     * @param string $key (optional)
     * @param string $agent (optional)
     * @return false|\PDOStatement
     * @throws \Exception
     */
    public function logIp($notes = '', $level = 0, $ip = '', $key = '', $agent = '')
    {
        if ($ip &&
            (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
                !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
        ) {
            return false;
        }
        if (!$ip) $ip = self::getClientIp();
        if (!$key) $key = self::getUri();
        if (!$agent) $agent = $_SERVER['HTTP_USER_AGENT'];

        $this->install();
        $now = new \DateTime();
        $sql = sprintf("INSERT INTO %s (`key`, `ip`, `agent`, `level`, `notes`, `timestamp`) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')",
            $this->getTable(), $key, $ip, $agent, $level, $notes, $now->format(self::FORMAT_ISO_DATETIME)
        );
        return $this->getDb()->query($sql);
    }

    /**
     * This sql should be DB generic (tested on: mysql)
     *
     * @return $this
     * @throws \Exception
     */
    protected function install()
    {
        if (!$this->getDb() || $this->hasTable($this->getTable())) return $this;
        $tbl = $this->getTable();
        if ($this->getDb()->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'mysql') {
            $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS $tbl (
      `id` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `key` VARCHAR(255) NOT NULL DEFAULT '',
      `ip` VARCHAR(32) NOT NULL DEFAULT '',
      `agent` VARCHAR(255) NOT NULL DEFAULT '',
      `level` TINYINT NOT NULL DEFAULT 0,       -- Threat Level: set to >1 when an expired ip tries again after being warned to try later 
      `timestamp` DATETIME NOT NULL,
      `notes` TEXT,
      KEY `ip` (`ip`),
      KEY `key` (`key`),
      KEY `ip_key` (`ip`, `key`)
    ) ENGINE=InnoDB;
SQL;
            $this->getDb()->exec($sql);
        } else {
            throw new \Exception(get_class($this) . ' does not support the '.$this->getDb()->getAttribute(\PDO::ATTR_DRIVER_NAME).' Database driver');
        }
        return $this;
    }


    /**
     * @return \PDO
     */
    protected function getDb()
    {
        return $this->db;
    }

    /**
     * @param \PDO $db
     * @return $this
     */
    protected function setDb($db)
    {
        $this->db = $db;
        return $this;
    }

    /**
     * @return string
     */
    protected function getTable()
    {
        return self::$DB_TABLE;
    }

    /**
     * Check if a table exists in the current database
     *
     * @param string $table
     * @return bool
     * @version 2.0.15
     */
    protected function hasTable($table)
    {
        $list = $this->getTableList();
        return in_array($table, $list);
    }

    /**
     * @return array
     */
    protected function getTableList()
    {
        $list = array();
        if ($this->getDb()->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'mysql') {
            $sql = 'SHOW TABLES';
            $result = $this->getDb()->query($sql);
            $list = $result->fetchAll(\PDO::FETCH_COLUMN, 0);
        }
        return $list;
    }


    /**
     * @return string
     */
    public static function getUri()
    {
        return $_SERVER['REQUEST_URI'];
    }

    /**
     * @return mixed
     */
    public static function getClientIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            //check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }
}