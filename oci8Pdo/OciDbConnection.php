<?php

require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'pdo'.DIRECTORY_SEPARATOR.'Oci8PDO.php');

class OciDbConnection extends CDbConnection
{
	
	public $pdoClass = 'Oci8PDO';
	
	/**
	* Creates the PDO instance.
	* When some functionalities are missing in the pdo driver, we may use
	* an adapter class to provides them.
	* @return PDO the pdo instance
	*/
	protected function createPdoInstance()
	{
		if(!empty($this->charset)) {
			Yii::trace('Error: OciDbConnection::$charset has been set to `'.$this->charset.'` in your config. The property is only used for MySQL and PostgreSQL databases. If you want to set the charset in Oracle to UTF8, add the following to the end of your OciDbConnection::$connectionString: ;charset=AL32UTF8;','ext.oci8Pdo.OciDbConnection');
        }
        
		try {
			Yii::trace('Opening Oracle connection','ext.oci8Pdo.OciDbConnection');
			$pdoClass = parent::createPdoInstance();
		}
		catch(PDOException $e) {
			throw $e;
		}
		return $pdoClass;
	}
	
	/**
	* Closes the currently active Oracle DB connection.
	* It does nothing if the connection is already closed.
	*/
	protected function close()
	{
		Yii::trace('Closing Oracle connection','ext.oci8Pdo.OciDbConnection');
		parent::close();
	}
	
}