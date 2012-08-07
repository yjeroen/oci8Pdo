<?php
/**
 * PDO Userspace Driver for Oracle (oci8)
 *
 * @category Database
 * @package Pdo
 * @subpackage Oci8
 * @author Ben Ramsey <ramsey@php.net>
 * @copyright Copyright (c) 2009 Ben Ramsey (http://benramsey.com/)
 * @license http://open.benramsey.com/license/mit  MIT License
 */

/**
 * @see Oci8PDO_Util
 */
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'oci8'.DIRECTORY_SEPARATOR.'Oci8PDO_Util.php');

/**
 * @see Oci8PDO_Statement
 */
require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'oci8'.DIRECTORY_SEPARATOR.'Oci8PDO_Statement.php');

/**
 * Oci8 class to mimic the interface of the PDO class
 *
 * This class extends PDO but overrides all of its methods. It does this so
 * that instanceof checks and type-hinting of existing code will work
 * seamlessly.
 */
class Oci8PDO extends PDO
{
    /**
     * Database handler
     *
     * @var resource
     */
    protected $_dbh;

    /**
     * Driver options
     *
     * @var array
     */
    protected $_options = array();

    /**
     * Whether currently in a transaction
     *
     * @var bool
     */
    protected $_isTransaction = false;

    /**
     * Constructor
     *
     * @param string $dsn
     * @param string $username
     * @param string $passwd
     * @param array $options
     * @return void
     */
    public function __construct($dsn,
                                $username = null,
                                $password = null,
                                array $options = array())
    {
        $parsedDsn = Oci8PDO_Util::parseDsn($dsn, array('dbname', 'charset'));

        if (isset($options[PDO::ATTR_PERSISTENT])
            && $options[PDO::ATTR_PERSISTENT]) {

            $this->_dbh = @oci_pconnect(
                $username,
                $password,
                $parsedDsn['dbname'],
                $parsedDsn['charset']);

        } else {

            $this->_dbh = @oci_connect(
                $username,
                $password,
                $parsedDsn['dbname'],
                $parsedDsn['charset']);

        }

        if (!$this->_dbh) {
            $e = oci_error();
            throw new PDOException($e['message']);
        }
        
        $this->_options = $options;
    }

    /**
     * Prepares a statement for execution and returns a statement object
     *
     * @param string $statement
     * @param array $options
     * @return Oci8PDO_Statement
     */
    public function prepare($statement, $options = null)
    {
        $sth = @oci_parse($this->_dbh, $statement);

        if (!$sth) {
            $e = oci_error($this->_dbh);
            throw new PDOException($e['message']);
        }

        if (!is_array($options)) {
            $options = array();
        }

        return new Oci8PDO_Statement($sth, $this, $options);
    }

    /**
     * Begins a transaction (turns off autocommit mode)
     *
     * @return void
     */
    public function beginTransaction()
    {
        if ($this->isTransaction()) {
            throw new PDOException('There is already an active transaction');
        }

        $this->_isTransaction = true;
        return true;
    }

    /**
     * Returns true if the current process is in a transaction
     *
     * @return bool
     */
    public function isTransaction()
    {
        return $this->_isTransaction;
    }

    /**
     * Commits all statements issued during a transaction and ends the transaction
     *
     * @return bool
     */
    public function commit()
    {
        if (!$this->isTransaction()) {
            throw new PDOException('There is no active transaction');
        }

        if (oci_commit($this->_dbh)) {
            $this->_isTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * Rolls back a transaction
     *
     * @return bool
     */
    public function rollBack()
    {
        if (!$this->isTransaction()) {
            throw new PDOException('There is no active transaction');
        }

        if (oci_rollback($this->_dbh)) {
            $this->_isTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * Sets an attribute on the database handle
     *
     * @param int $attribute
     * @param mixed $value
     * @return bool
     */
    public function setAttribute($attribute, $value)
    {
    	//433: $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    	//435: $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,$this->emulatePrepare);
    	//612: $this->setAttribute(PDO::ATTR_CASE,$value); 
    	//632: $this->setAttribute(PDO::ATTR_ORACLE_NULLS,$value); 
    	//652: $this->setAttribute(PDO::ATTR_AUTOCOMMIT,$value); 
    	//672: return $this->setAttribute(PDO::ATTR_PERSISTENT,$value); 
        $this->_options[$attribute] = $value;
        return true;
    }

    /**
     * Executes an SQL statement and returns the number of affected rows
     *
     * @param string $query
     * @return int The number of rows affected
     */
    public function exec($query)
    {
        $stmt = $this->prepare($query);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Executes an SQL statement, returning the results as a Oci8PDO_Statement
     *
     * @param string $query
     * @param int|null $fetchType
     * @param mixed|null $typeArg
     * @param array|null $ctorArgs
     * @return Oci8PDO_Statement
     * @todo Implement support for $fetchType, $typeArg, and $ctorArgs.
     */
    public function query($query,
                          $fetchType = null,
                          $typeArg = null,
                          array $ctorArgs = array())
    {
        $stmt = $this->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Issues a PHP warning, just as with the PDO_OCI driver
     *
     * Oracle does not support the last inserted ID functionality like MySQL.
     * You must implement this yourself by returning the sequence ID from a
     * stored procedure, for example.
     *
     * @param string $name Sequence name; no use in this context
     * @return void
     */
    public function lastInsertId($name = null)
    {
        trigger_error(
            'SQLSTATE[IM001]: Driver does not support this function: driver does not support lastInsertId()',
            E_USER_WARNING);
    }

    /**
     * Returns the error code associated with the last operation
     *
     * While this returns an error code, it merely emulates the action. If
     * there are no errors, it returns the success SQLSTATE code (00000).
     * If there are errors, it returns HY000. See errorInfo() to retrieve
     * the actual Oracle error code and message.
     *
     * @return string
     */
    public function errorCode()
    {
        $error = $this->errorInfo();
        return $error[0];
    }

    /**
     * Returns extended error information for the last operation on the database
     *
     * @return array
     */
    public function errorInfo()
    {
        $e = oci_error($this->_dbh);

        if (is_array($e)) {
            return array(
                'HY000',
                $e['code'],
                $e['message']
            );
        }

        return array('00000', null, null);
    }

    /**
     * Retrieve a database connection attribute
     *
     * @return mixed
     */
    public function getAttribute($attribute)
    {
    	//438: $driver=strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)); 
    	//602: return $this->getAttribute(PDO::ATTR_CASE); 
    	//622: return $this->getAttribute(PDO::ATTR_ORACLE_NULLS); 
    	//642: return $this->getAttribute(PDO::ATTR_AUTOCOMMIT); 
    	//662: return $this->getAttribute(PDO::ATTR_PERSISTENT); 
    	//692: return $this->getAttribute(PDO::ATTR_CLIENT_VERSION); 
    	//702: return $this->getAttribute(PDO::ATTR_CONNECTION_STATUS); 
    	//711: return $this->getAttribute(PDO::ATTR_PREFETCH); 
    	//720: return $this->getAttribute(PDO::ATTR_SERVER_INFO); 
    	//729: return $this->getAttribute(PDO::ATTR_SERVER_VERSION); 
    	//738: return $this->getAttribute(PDO::ATTR_TIMEOUT); 
        if (isset($this->_options[$attribute])) {
            return $this->_options[$attribute];
        }
        return null;
    }

    /**
     * Quotes a string for use in a query
     *
     * @param string $string
     * @param int $parameter_type
     * @return string
     * @todo Implement support for $parameter_type.
     */
    public function quote($string, $parameter_type = PDO::PARAM_STR)
    {
    	if($parameter_type !== PDO::PARAM_STR) {
    		throw new PDOException('Only PDO::PARAM_STR is currently implemented for the $parameter_type of Oci8PDO::quote()');
    	}
        return "'" . str_replace("'", "''", $string) . "'";
    }
}