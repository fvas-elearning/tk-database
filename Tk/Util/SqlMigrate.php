<?php
namespace Tk\Util;

use Tk\Util\SqlBackup;

/**
 * Class Migrate
 *
 * A script that iterated the project files and executes *.sql files
 * once the files are executed they are then logs and not executed again.
 * Great for upgrading and installing a systems DB
 *
 * Files should reside in a folder named `.../sql/{type}/*`
 *
 * For a mysql file it could look like `.../sql/mysql/000001.sql`
 * for a postgress file `.../sql/pgsql/000001.sql`
 *
 * It is a good idea to start with a number to ensure that the files are
 * executed in the required order. Files found will be sorted alphabetically.
 *
 * <code>
 *   $migrate = new \Tk\Db\Migrate(Factory::getDb(), $this->config->getSitePath());
 *   $migrate->run()
 * </code>
 *
 * Migration files can be of type .sql or .php.
 * The php files are called with the include() command.
 * It will then be up to the developer to include a script to install the required sql.
 *
 *
 * @author Michael Mifsud <info@tropotek.com>
 * @link http://www.tropotek.com/
 * @license Copyright 2016 Michael Mifsud
 */
class SqlMigrate
{

    /**
     * @var \Tk\Db\Pdo
     */
    protected $db = null;

    /**
     * @var string
     */
    protected $table = '';

    /**
     * @var string
     */
    protected $sitePath = '';

    /**
     * @var string
     */
    protected $tmpPath = '/tmp';


    /**
     * Migrate constructor.
     *
     * @param \Tk\Db\Pdo $db
     * @param string $sitePath
     * @param string $table
     */
    public function __construct($db, $sitePath, $table = 'migration')
    {
        $this->db = $db;
        $this->table = $table;
        $this->sitePath = $sitePath;
    }

    /**
     * Run the migration script and find all non executed sql files
     *
     * @param $path
     * @return array
     * @throws \Exception
     * @throws \Tk\Db\Exception
     */
    public function migrate($path)
    {
        if(!$this->db->tableExists($this->table)) {
            $this->installMigrationTable();
        }
        $list = $this->getFileList($path);
        $dump = new SqlBackup($this->db);
        $backupFile = $dump->save($this->tmpPath);
        $mlist = array();
        try {
            foreach ($list as $file) {
                if ($this->migrateFile($file)) {
                    $mlist[] = $this->toRelative($file);
                }
            }
        } catch (\Exception $e) {
            $dump->restore($backupFile);
            unlink($backupFile);
            throw $e;
        }
        unlink($backupFile);
        return $mlist;
    }

    /**
     * Check to see if there are any new migration sql files pending execution
     *
     * @param $path
     * @return bool
     */
    public function isPending($path)
    {
        if(!$this->db->tableExists($this->table)) {
            $this->installMigrationTable();
        }
        $list = $this->getFileList($path);
        $pending = false;
        foreach ($list as $file) {
            if (!$this->hasPath($file)) {
                $pending = true;
                break;
            }
        }
        return $pending;
    }


    /**
     * Set the temp path for db backup file
     * Default '/tmp'
     *
     * @param string $path
     * @return $this
     */
    public function setTmpPath($path)
    {
        $this->tmpPath = $path;
        return $this;
    }


    /**
     * search the path for *.sql files, also search the $path.'/'.$driver folder
     * for *.sql files.
     *
     * @param string $path
     * @return array
     */
    protected function getFileList($path)
    {
        $list = array();
        $list = array_merge($list, $this->search($path));
        $list = array_merge($list, $this->search($path.'/'.$this->db->getDriver()));
        sort($list);
        return $list;
    }

    /**
     * Execute a migration class or sql script...
     * the file is then added to the db and cannot be executed again.
     *
     *
     * @param string $file
     * @return bool
     */
    protected function migrateFile($file)
    {
        $file = $this->sitePath . $this->toRelative($file);
        if ($this->hasPath($file)) return false;
        if (!is_readable($file)) return false;

        if (preg_match('/\.php$/i', basename($file))) {   // Include .php files
            include($file);
        } else {    // is sql
            $this->db->multiQuery(file_get_contents($file));
        }
        $this->insertPath($file);
        return true;
    }

    /**
     * Search a path for sql files
     *
     * @param $path
     * @return array
     */
    protected function search($path)
    {
        $iterator = new \DirectoryIterator($path);
        $list = array();
        foreach(new \RegexIterator($iterator, '/\.(php|sql)$/') as $file) {
            if (preg_match('/^(_|\.)/', $file->getBasename())) continue;
            $list[] = $file->getPathname();
        }
        return $list;
    }


    // Migration DB access methods

    /**
     * install the migration table to track executed scripts
     *
     */
    protected function installMigrationTable()
    {
        $tbl = $this->db->quoteParameter($this->table);
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS $tbl (
  path varchar(255) NOT NULL DEFAULT '',
  created TIMESTAMP,
  PRIMARY KEY (path)
);
SQL;
        $this->db->exec($sql);
    }

    /**
     * exists
     *
     * @param string $path
     * @return bool
     */
    protected function hasPath($path)
    {
        $path = $this->db->escapeString($this->toRelative($path));
        $sql = sprintf('SELECT * FROM %s WHERE path = %s LIMIT 1', $this->db->quoteParameter($this->table), $this->db->quote($path));
        $res = $this->db->query($sql);
        if ($res->rowCount()) {
            return true;
        }
        return false;
    }

    /**
     * insert
     *
     * @param string $path
     * @return PDOStatement
     */
    protected function insertPath($path)
    {
        $path = $this->db->escapeString($this->toRelative($path));
        $sql = sprintf('INSERT INTO %s (path, created) VALUES (%s, NOW())', $this->db->quoteParameter($this->table), $this->db->quote($path));
        return $this->db->exec($sql);
    }

    /**
     * delete
     *
     * @param string $path
     * @return PDOStatement
     */
    protected function deletePath($path)
    {
        $path = $this->db->escapeString($this->toRelative($path));
        $sql = sprintf('DELETE FROM %s WHERE path = %s LIMIT 1', $this->db->quoteParameter($this->table), $this->db->quote($path));
        return $this->db->exec($sql);
    }

    /**
     * Return the relative path
     *
     * @param $path
     * @return string
     */
    private function toRelative($path)
    {
        return rtrim(str_replace($this->sitePath, '', $path), '/');
    }
    
}