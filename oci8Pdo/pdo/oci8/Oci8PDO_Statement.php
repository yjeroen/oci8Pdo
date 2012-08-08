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
 * Oci8 Statement class to mimic the interface of the PDOStatement class
 *
 * This class extends PDOStatement but overrides all of its methods. It does
 * this so that instanceof check and type-hinting of existing code will work
 * seamlessly.
 */
class Oci8PDO_Statement extends PDOStatement
{
    /**
     * Statement handler
     *
     * @var resource
     */
    protected $_sth;

    /**
     * PDO Oci8 driver
     *
     * @var Oci8PDO
     */
    protected $_pdoOci8;

    /**
     * Statement options
     *
     * @var array
     */
    protected $_options = array();
    
    /*
     * Default fetch mode for this statement
     * @var integer
     */
    protected $_fetchMode = null;
    
    /*
     * Bound columns for bindColumn()
     */
    protected $_boundColumns = array();

    /**
     * Constructor
     *
     * @param resource $sth Statement handle created with oci_parse()
     * @param Oci8PDO $pdoOci8 The Oci8PDO object for this statement
     * @param array $options Options for the statement handle
     * @return void
     */
    public function __construct($sth,
                                Oci8PDO $pdoOci8,
                                array $options = array())
    {
        if (strtolower(get_resource_type($sth)) != 'oci8 statement') {
            throw new PDOException(
                'Resource expected of type oci8 statement; '
                . (string) get_resource_type($sth) . ' received instead');
        }
        
        $this->_sth = $sth;
        $this->_pdoOci8 = $pdoOci8;
        $this->_options = $options;
    }

    /**
     * Executes a prepared statement
     *
     * @param array $inputParams
     * @return bool
     */
    public function execute($inputParams = null)
    {
        $mode = OCI_COMMIT_ON_SUCCESS;
        if ($this->_pdoOci8->isTransaction()) {
        	if(PHP_VERSION_ID > 503020) {
            	$mode = OCI_NO_AUTO_COMMIT;
        	} else {
        		$mode = OCI_DEFAULT;
        	}
        }

        // Set up bound parameters, if passed in
        if (is_array($inputParams)) {
            foreach ($inputParams as $key => $value) {
                $bound = $this->bindParam($key, $inputParams[$key]);
                if(!$bound) {
                	throw new PDOException($inputParams[$key].' could not be bound to '.$key.' with Oci8PDO_Statement::bindParam()');
                }
            }
        }

        if(@oci_execute($this->_sth, $mode)) {
            return true;
        } else {
            $e = oci_error($this->_sth);
            throw new PDOException($e['message']);
        }
    }

    /**
     * Fetches the next row from a result set
     *
     * @param int $fetch_style
     * @param int $cursor_orientation
     * @param int $cursorOf$cursor_offsetfset
     * @return mixed
     */
    public function fetch($fetch_style = PDO::FETCH_BOTH,
                          $cursor_orientation = PDO::FETCH_ORI_NEXT,
                          $cursor_offset = 0)
    {
    	if($cursor_orientation !== PDO::FETCH_ORI_NEXT || $cursor_offset !== 0) {
    		throw new PDOException('$cursor_orientation that is not PDO::FETCH_ORI_NEXT is not implemented for Oci8PDO_Statement::fetch()');
    	}
    	
    	if($this->_fetchMode !== null) {
    		$fetch_style = $this->_fetchMode;
    	}
    	
    	if($fetch_style === PDO::FETCH_ASSOC) {
    		$result = oci_fetch_array($this->_sth, OCI_ASSOC);
    	} elseif($fetch_style === PDO::FETCH_NUM) {
    		$result = oci_fetch_array($this->_sth, OCI_NUM);
    	} elseif($fetch_style === PDO::FETCH_BOTH) {
    		throw new PDOException('PDO::FETCH_BOTH is not implemented for Oci8PDO_Statement::fetch()');
    	} elseif($fetch_style === PDO::FETCH_BOUND) {
    		throw new PDOException('PDO::FETCH_BOUND is not implemented for Oci8PDO_Statement::fetch()');
    	} elseif($fetch_style === PDO::FETCH_CLASS) {
    	    throw new PDOException('PDO::FETCH_CLASS is not implemented for Oci8PDO_Statement::fetch()');
    	} elseif($fetch_style === PDO::FETCH_INTO) {
    		throw new PDOException('PDO::FETCH_INTO is not implemented for Oci8PDO_Statement::fetch()');
    	} elseif($fetch_style === PDO::FETCH_LAZY) {
    	    throw new PDOException('PDO::FETCH_LAZY is not implemented for Oci8PDO_Statement::fetch()');
    	} elseif($fetch_style === PDO::FETCH_OBJ) {
    		$result = oci_fetch_object($this->_sth);
    	} else {
    	    throw new PDOException('This $fetch_style combination is not implemented for Oci8PDO_Statement::fetch()');
    	}
    	
    	$this->bindToColumn($result);
    	return $result;
    }

    /**
     * Binds a parameter to the specified variable name
     *
     * @param string $parameter
     * @param mixed $variable
     * @param int $data_type
     * @param int $length
     * @param array $options
     * @return bool
     */
    public function bindParam($parameter,
                              &$variable,
                              $data_type = PDO::PARAM_STR,
                              $length = -1,
                              $driver_options = null)
    {
    	if($driver_options !== null) {
    		throw new PDOException('$driver_options is not implemented for Oci8PDO_Statement::bindParam()');
    	}
    	
    	//Not checking for $data_type === PDO::PARAM_INT, because this gives problems when inserting/updating integers into a VARCHAR column.
    	
//     	if($data_type === PDO::PARAM_INT) {
//     		if($length == -1) {
//         		 $length = strlen( (string)$variable );
//     		}
//     		return oci_bind_by_name($this->_sth, $parameter, $variable, $length, SQLT_INT);
//     	} else
    	if (is_array($variable)) {
            return oci_bind_array_by_name(
                $this->_sth,
                $parameter,
                $variable,
                count($variable),
            	$length
            );
        } else {
        	if($length == -1) {
        		 $length = strlen( (string)$variable );
        	}
        	return oci_bind_by_name($this->_sth, $parameter, $variable, $length);
        }

        
    }

    /**
     * Binds a column to a PHP variable
     *
     * @param mixed $column The number of the column or name of the column
     * @param mixed $param The PHP variable to which the column should be bound
     * @param int $type
     * @param int $maxLength
     * @param mixed $options
     * @return bool
     * @todo Implement this functionality by creating a table map of the
     *       variables passed in here, and, when iterating over the values
     *       of the query or fetching rows, assign data from each column
     *       to their respective variable in the map.
     */
    public function bindColumn($column,
                               &$param,
                               $type = PDO::PARAM_STR,
                               $maxlen = null,
                               $driverdata = null)
    {
    	if($maxlen !== null || $driverdata !== null) {
    		throw new PDOException('$maxlen and $driverdata parameters are not implemented for Oci8PDO_Statement::bindColumn()');
    	}
    	if($type !== PDO::PARAM_INT && $type !== PDO::PARAM_STR) {
    		throw new PDOException('Only PDO::PARAM_INT and PDO::PARAM_STR are implemented for the $type parameter of Oci8PDO_Statement::bindColumn()');
    	}
    	
    	$this->_boundColumns[] = array(
    		'column'=>$column,
    		'param'=>&$param,
    		'type'=>$type
    	);
    }
    
    protected function bindToColumn($result)
    {
    	if($result !== false) {
    		foreach($this->_boundColumns as $bound) {
    			$key = $bound['column']-1;
    			$array = array_slice($result, $key, 1);
    			if($bound['type']===PDO::PARAM_INT) {
    				$bound['param'] = (int)array_pop($array);
    			} else {
    				$bound['param'] = array_pop($array);
    			}
    		}
    	}
    }

    /**
     * Binds a value to a parameter
     *
     * @param string $parameter
     * @param mixed $variable
     * @param int $dataType
     * @return bool
     */
    public function bindValue($parameter,
                              $variable,
                              $dataType = PDO::PARAM_STR)
    {
        return $this->bindParam($parameter, $variable, $dataType);
    }

    /**
     * Returns the number of rows affected by the last executed statement
     *
     * @return int
     */
    public function rowCount()
    {
        return oci_num_rows($this->_sth);
    }

    /**
     * Returns a single column from the next row of a result set
     *
     * @param int $colNumber
     * @return string
     */
    public function fetchColumn($colNumber = 0)
    {
    	$result = oci_fetch_array($this->_sth, OCI_NUM);
    	
    	if($result===false) {
    		return false;
    	} elseif(!isset($result[$colNumber])) {
    		return false;
    	} else {
    		return $result[$colNumber];
    	}    	
    }

    /**
     * Returns an array containing all of the result set rows
     *
     * @param int $fetch_style
     * @param mixed $fetch_argument
     * @param array $ctor_args
     * @return mixed
     */
    public function fetchAll($fetch_style = PDO::FETCH_BOTH,
                             $fetch_argument = null,
                             $ctor_args = null)
    {
    	if($this->_fetchMode !== null) {
    		$fetch_style = $this->_fetchMode;
    	}
    	
    	if($fetch_style === PDO::FETCH_ASSOC) {
    		oci_fetch_all($this->_sth, $result, 0, -1, OCI_FETCHSTATEMENT_BY_ROW+OCI_ASSOC );
    	} elseif($fetch_style === PDO::FETCH_NUM) {
    		oci_fetch_all($this->_sth, $result, 0, -1, OCI_FETCHSTATEMENT_BY_ROW+OCI_NUM );
    	} elseif($fetch_style === PDO::FETCH_COLUMN) {
    		oci_fetch_all($this->_sth, $preResult, 0, -1, OCI_FETCHSTATEMENT_BY_COLUMN+OCI_NUM );
    		$result = array();
    		foreach($preResult as $row) {
    			$result[] = $row[0];
    		}
    	} elseif($fetch_style === PDO::FETCH_BOTH) {
    		throw new PDOException('PDO::FETCH_BOTH is not implemented for Oci8PDO_Statement::fetchAll()');
    	} elseif($fetch_style === PDO::FETCH_BOUND) {
    		throw new PDOException('PDO::FETCH_BOUND is not implemented for Oci8PDO_Statement::fetchAll()');
    	} elseif($fetch_style === PDO::FETCH_CLASS) {
    		throw new PDOException('PDO::FETCH_CLASS is not implemented for Oci8PDO_Statement::fetchAll()');
    	} elseif($fetch_style === PDO::FETCH_INTO) {
    		throw new PDOException('PDO::FETCH_INTO is not implemented for Oci8PDO_Statement::fetchAll()');
    	} elseif($fetch_style === PDO::FETCH_LAZY) {
    		throw new PDOException('PDO::FETCH_LAZY is not implemented for Oci8PDO_Statement::fetchAll()');
    	} elseif($fetch_style === PDO::FETCH_OBJ) {
    		$result = array();
    		while(false !== ($row=$this->fetch($fetch_style))) {	//This HAS to be false !== XXX instead of XXX !== false, or else $row will only be true/false
    			$result[] = $row;
    		}
    	} else {
    	    throw new PDOException('This $fetch_style combination is not implemented for Oci8PDO_Statement::fetch()');
    	}
    	
    	return $result;
    }

    /**
     * Fetches the next row and returns it as an object
     *
     * @param string $className
     * @param array $ctor_args
     * @return mixed
     */
    public function fetchObject($className = 'stdClass', $ctor_args = null)
    {
    	if($className == 'stdClass') {
    		$result = oci_fetch_object($this->_sth);
    	} else {
    		$object = new $className($ctor_args);
    		$object_oci = oci_fetch_object($this->_sth);
    		foreach($object_oci as $k => $v) {
    			$object->$k = $v;
    		}
    		$result = $object;
    	}
    	
    	return $result;
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
        $e = oci_error($this->_sth);

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
     * Sets an attribute on the statement handle
     *
     * @param int $attribute
     * @param mixed $value
     * @return bool
     */
    public function setAttribute($attribute, $value)	
    {
        $this->_options[$attribute] = $value;
        return true;
    }

    /**
     * Retrieve a statement handle attribute
     *
     * @return mixed
     */
    public function getAttribute($attribute)
    {
        if (isset($this->_options[$attribute])) {
            return $this->_options[$attribute];
        }
        return null;
    }

    /**
     * Returns the number of columns in the result set
     *
     * @return int
     */
    public function columnCount()
    {
        return oci_num_fields($this->_sth);
    }

    /**
     * Returns metadata for a column in a result set
     *
     * The array returned by this function is patterned after that
     * returned by PDO::getColumnMeta(). It includes the following
     * elements:
     *
     *     native_type
     *     driver:decl_type
     *     flags
     *     name
     *     table
     *     len
     *     precision
     *     pdo_type
     *
     * @param int $column Zero-based column index
     * @return array
     */
    public function getColumnMeta($column)
    {
        // Columns in oci8 are 1-based; add 1 if it's a number
        if (is_numeric($column)) {
            $column++;
        }

        $meta = array();
        $meta['native_type'] = oci_field_type($this->_sth, $column);
        $meta['driver:decl_type'] = oci_field_type_raw($this->_sth, $column);
        $meta['flags'] = array();
        $meta['name'] = oci_field_name($this->_sth, $column);
        $meta['table'] = null;
        $meta['len'] = oci_field_size($this->_sth, $column);
        $meta['precision'] = oci_field_precision($this->_sth, $column);
        $meta['pdo_type'] = null;

        return $meta;
    }

    /**
     * Set the default fetch mode for this statement
     *
     * @param int $fetchType
     * @param mixed $colClassOrObj
     * @param array $ctorArgs
     * @return bool
     */
    public function setFetchMode($mode,
                                 $colClassOrObj = null,
                                 array $ctorArgs = array())
    {
    	//52: $this->_statement->setFetchMode(PDO::FETCH_ASSOC); 
    	if($colClassOrObj !== null || !empty($ctorArgs)) {
    		throw new PDOException('Second and third parameters are not implemented for Oci8PDO_Statement::setFetchMode()');
    		//see http://www.php.net/manual/en/pdostatement.setfetchmode.php
    	}
    	$this->_fetchMode = $mode;
    	return true;
    }

    /**
     * Advances to the next rowset in a multi-rowset statement handle
     *
     * @return bool
     */
    public function nextRowset()
    {
    	throw new PDOException('nextRowset() method is not implemented for Oci8PDO_Statement');
    }

    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @return bool
     */
    public function closeCursor()
    {
    	//Because we use OCI8 functions, we don't need this.
    	return oci_free_statement($this->_sth);
    }

    /**
     * Dump a SQL prepared command
     *
     * @return bool
     */
    public function debugDumpParams()
    {
    	throw new PDOException('debugDumpParams() method is not implemented for Oci8PDO_Statement');
    }

    /**
     * Returns the current row from the rowset
     *
     * @return array
     */
    public function current()
    {
    	throw new PDOException('current() method is not implemented for Oci8PDO_Statement');
    }

    /**
     * Returns the key for the current row
     *
     * @return mixed
     */
    public function key()
    {
    	throw new PDOException('key() method is not implemented for Oci8PDO_Statement');
    }

    /**
     * Advances the cursor forward and returns the next row
     *
     * @return void
     */
    public function next()
    {
    	throw new PDOException('next() method is not implemented for Oci8PDO_Statement');
    }

    /**
     * Rewinds the cursor to the beginning of the rowset
     *
     * @return void
     */
    public function rewind()
    {
    	throw new PDOException('rewind() method is not implemented for Oci8PDO_Statement');
    }

    /**
     * Checks whether there is a current row
     *
     * @return bool
     */
    public function valid()
    {
    	throw new PDOException('valid() method is not implemented for Oci8PDO_Statement');
    }
}