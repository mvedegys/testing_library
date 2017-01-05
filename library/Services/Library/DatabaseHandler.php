<?php

/**
 * This file is part of OXID eSales Testing Library.
 *
 * OXID eSales Testing Library is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OXID eSales Testing Library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID eSales Testing Library. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link          http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2014
 */
namespace OxidEsales\TestingLibrary\Services\Library;

use Exception;
use PDO;
use PDOStatement;
use PDOException;
use OxConfigFile;

/**
 * Simple database connector.
 */
class DatabaseHandler
{
    /** @var oxConfigFile */
    private $configFile;

    /** @var PDO Database connection. */
    private $dbConnection;

    /**
     * Initiates class dependencies.
     *
     * @param oxConfigFile $configFile
     *
     * @throws Exception
     */
    public function __construct($configFile)
    {
        $this->configFile = $configFile;
        if (!extension_loaded('pdo_mysql')) {
            throw new \Exception("the php pdo_mysql extension is not installed!\n");
        }

        $dsn = 'mysql' .
               ':host=' . $this->getDbHost() .
               (empty($this->getDbPort()) ? '' : ';port=' . $this->getDbPort());

        try{
            $this->dbConnection = new PDO(
                $dsn,
                $this->getDbUser(),
                $this->getDbPassword(),
                array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8')
            );
        } catch (\PDOException $exception) {
            throw new \Exception("Could not connect to '{$this->getDbHost()}' with user '{$this->getDbUser()}'\n");
        }
    }

    /**
     * Execute sql statements from sql file
     *
     * @param string $sqlFile     SQL File name to import.
     * @param string $charsetMode Charset of imported file. Will use shop charset mode if not set.
     *
     * @throws Exception
     */
    public function import($sqlFile, $charsetMode = null)
    {
        if (file_exists($sqlFile)) {
            $charsetMode = $charsetMode ? $charsetMode : $this->getCharsetMode();
            $this->executeCommand($this->getImportCommand($sqlFile, $charsetMode));
        } else {
            throw new Exception("File '$sqlFile' was not found.");
        }
    }

    /**
     * @param string $sqlFile
     * @param array  $tables
     */
    public function export($sqlFile, $tables)
    {
        $this->executeCommand($this->getExportCommand($sqlFile, $tables));
    }

    /**
     * Executes query on database.
     *
     * @param string $sql Sql query to execute.
     *
     * @return PDOStatement|false
     */
    public function query($sql)
    {
        $this->useConfiguredDatabase();
        return $this->getDbConnection()->query($sql);
    }

    /**
     * This function is intended for write access to the database like INSERT, UPDATE
     *
     * @param string $sql Sql query to execute.
     *
     * @return int
     */
    public function exec($sql)
    {
        $this->useConfiguredDatabase();
        $success = $this->getDbConnection()->exec($sql);
        return $success;
    }

    /**
     * Executes sql query. Returns query execution resource object
     *
     * @param string $sql query to execute
     *
     * @throws Exception exception is thrown if error occured during sql execution
     *
     * @return PDOStatement|false|int
     */
    public function execSql($sql)
    {
        try {
            list ($statement) = explode(" ", ltrim($sql));
            if (in_array(strtoupper($statement), array('SELECT', 'SHOW'))) {
                $oStatement = $this->query($sql);
            } else {
                return $this->exec($sql);
            }

            return $oStatement;
        } catch (PDOException $e) {
            throw new Exception("Could not execute sql: " . $sql);
        }
    }

    /**
     * The database if not chosen when the connection is made because the database can be e.g. dropped afterwards
     * and then the connection gets lost.
     *
     * @throws Exception
     */
    protected function useConfiguredDatabase()
    {
        try {
            $this->getDbConnection()->exec("USE " . $this->getDbName());
        } catch (Exception $e) {
            throw new Exception("Could not connect to database " . $this->getDbName());
        }
    }

    /**
     * @param string $value
     * @return string
     */
    public function escape($value)
    {
        return $this->getDbConnection()->quote($value);
    }

    /**
     * Returns charset mode
     *
     * @return string
     */
    public function getCharsetMode()
    {
        return 'utf8';
    }

    /**
     * @return string
     */
    public function getDbName()
    {
        return $this->configFile->dbName;
    }

    /**
     * @return string
     */
    public function getDbUser()
    {
        return $this->configFile->dbUser;
    }

    /**
     * @return string
     */
    public function getDbPassword()
    {
        return $this->configFile->dbPwd;
    }

    /**
     * @return string
     */
    public function getDbHost()
    {
        return $this->configFile->dbHost;
    }

    /**
     * @return string
     */
    public function getDbPort()
    {
        return $this->configFile->dbPort;
    }

    /**
     * Returns database resource
     *
     * @return PDO
     */
    public function getDbConnection()
    {
        return $this->dbConnection;
    }

    /**
     * Returns CLI import command, execute sql from given file
     *
     * @param string $fileName    SQL File name to import.
     * @param string $charsetMode Charset of imported file.
     *
     * @return string
     */
    protected function getImportCommand($fileName, $charsetMode)
    {
        $command = 'mysql -h' . escapeshellarg($this->getDbHost());
        $command .= ' -u' . escapeshellarg($this->getDbUser());
        if ($password = $this->getDbPassword()) {
            $command .= ' -p' . escapeshellarg($password);
        }
        $command .= ' --default-character-set=' . $charsetMode;
        $command .= ' ' .escapeshellarg($this->getDbName());
        $command .= ' < ' . escapeshellarg($fileName) . ' 2>&1';

        return $command;
    }

    /**
     * Returns CLI command for db export to given file name
     *
     * @param string $fileName file name
     * @param array  $tables   Tables to export
     *
     * @return string
     */
    protected function getExportCommand($fileName, $tables = null)
    {
        $command = 'mysqldump';
        $command .= ' -h' . escapeshellarg($this->getDbHost());
        $command .= ' -u' . escapeshellarg($this->getDbUser());
        if ($password = $this->getDbPassword()) {
            $command .= ' -p' . escapeshellarg($password);
        }
        if (!empty($tables)) {
            array_map('escapeshellarg', $tables);
            $tables = ' ' . implode($tables);
        }
        $command .= ' ' . escapeshellarg($this->getDbName()) . $tables;
        $command .= ' > ' . escapeshellarg($fileName);

        return $command;
    }

    /**
     * Execute shell command
     *
     * @param string $command
     *
     * @throws Exception
     */
    protected function executeCommand($command)
    {
        exec($command, $output, $resultCode);

        if ($resultCode > 0) {
            sleep(1);
            exec($command, $output, $resultCode);

            if ($resultCode > 0) {
                $output = implode("\n", $output);
                throw new Exception("Failed to execute command: '$command' with output: '$output' ");
            }
        }
    }
}
