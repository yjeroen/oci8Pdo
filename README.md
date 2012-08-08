oci8Pdo
=======

## A Yii Extension to simulate the Oracle PDO using the PHP OCI8 functions. The PDO driver simulation files can be used without Yii as well.

The goal of this PDO class is to simulate 99% of the PDO functions that you use in an application.  
If you find any problems, I would love the feedback. Please open an Issue. Creating UnitTests is on my TODO list.

## 1. Usage standalone
Include the Oci8PDO.php file into your project.
```php
<?php
    require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'pdo'.DIRECTORY_SEPARATOR.'Oci8PDO.php');
```

## 2. Install for Yii Framework
Step 1: Copy the _oci8Pdo_ folder to /protected/extensions/.

Step 2: Edit your config file to configure another database component. Please read the comments.

```php
<?php
    // application components
    'components'=>array(        
        // MySQL database config
        'db'=>array(
              'connectionString' => 'mysql:host=myDataBaseHost.com;dbname=myDataBase',
              'username' => '',
              'password' => '',
              'charset' => 'utf8',
        ),
        'dbOracle'=>array(
              'class'=>'ext.oci8Pdo.OciDbConnection',
              /*
               * Note: Normally you use the Easy Connect string, but your server has 
               * to be correctly set-up for that.
               * 
               * ** Easy Connect String **
               * If you get the following error: `ORA-12154: TNS:could not resolve the connect identifier specified`,
               * go to /opt/oracle/instantclient/sqlnet.ora and change the following line:
               *    NAMES.DIRECTORY_PATH= (TNSNAMES)
               * Change this to:
               *    NAMES.DIRECTORY_PATH= (TNSNAMES, EZCONNECT)
               */
       //    'connectionString' => 'oci:dbname=//myOracleHost.com:1526/ccq',
              /*
               * ** Full Connection String **
               * Use this method incase your Easy Connect gives you errors and you can't edit the sqlnet.ora file.
               * You can set the charset in this string as well, add `;charset=AL32UTF8;` at the end for UTF-8.
               */
              'connectionString' => 'oci:dbname=(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=myOracleHost.com)
                                                 (PORT=1526))(CONNECT_DATA=(SERVICE_NAME=myService.intern)));charset=AL32UTF8;',
              'username' => '',
              'password' => '',
              /*
               * enableProfiling and enableParamLogging are working like normal; Default is false.
               */
              'enableProfiling' => true,
              'enableParamLogging' => true,
        ),
    ),
```

Step 3: There is no step three. You're done!


## 3. Usage
To start, you need to understand that Oracle Databases have **CASE-SENSITIVE** columns. Most DataBase Administrators in the Oracle world use capital table names and capital column names. That means you have to do that as well in your application.

### 3.1 DOA and the Query Builder both work. As long as you use the _right_ database component.
A few examples:
```php
<?php
    //Getting the Oracle DB connection
    $oci = Yii::app()->dbOracle;    
    $sql = <<<SQL
SELECT
  t.ANNOUNCEMENT
  , t.DESCRIPTION
  , t.TYPE
FROM
  CCQ.ANNOUNCEMENT_LIST t
WHERE
  t.TYPE = 0
  AND t.DESCRIPTION NOT LIKE '%MENU%'
  AND t.DESCRIPTION NOT LIKE '%Menu%'
  AND rownum<=2
ORDER BY 1
SQL;
    
    $command = $ivr->createCommand($sql);       
    $dataReader = $command->query();
    foreach($dataReader as $row) {
        echo $row['DESCRIPTION'],'$dataReader';
    }
```
```php
<?php
    $oci = Yii::app()->dbOracle;   
    $command = $oci->createCommand();
    $rows = $command->insert('CCQ.MY_ANNOUNCEMENTS', array(
        'PKEY'=>new CDbExpression('(select max(PKEY)+1 from "CCQ"."MY_ANNOUNCEMENTS")'),
        'PC_TO'=>'1111',
        'PC_FROM'=>'1112',
        'PRODUCTTYPE'=>'Internet',
        'TTS'=>'',
        'ANNOUNCEMENT'=>'7501.wav',
        'TYPE'=>'0',
        'DATETIME'=>new CDbExpression('SYSDATE'),
        'TICKET'=>'123123',
    ));
```
```php
<?php
    $row = Yii::app()->dbOracle->createCommand()
            ->select('t.DESCRIPTION')
            ->from('CCQ.MY_ANNOUNCEMENTS t')
            ->where('TICKET=:ticket', array(':ticket'=>123))
            ->queryRow();
```

### 3.2 ActiveRecord works as well!
There are two important things to consider however:
* Column names are case-sensitive, that means that the Models' **attributes** are as well!
* The database schema for Oracle (`COciSchema`) can be quite heavy on performance. That means that if you want to use Oracle in combination of ActiveRecord, you **HAVE** to cache the schema, or else you will experience a lot of performance degradation. Check the following two links:
    - http://www.yiiframework.com/doc/blog/1.1/en/final.deployment#enabling-schema-caching
    - http://www.yiiframework.com/wiki/118/incresing-ar-performance-in-connections-with-oracle/

You also have to add a `getDbConnection()` method to your Model, so the right database connection is used.

Some examples:
```php
<?php
class IvrModel extends CActiveRecord
{
    /**
	 * Returns the Oracle database connection used by this active record.
	 * @return OciDbConnection the Oracle database connection used by this active record.
	 */
      public function getDbConnection()
      {
            if(self::$db!==null) {
                  return self::$db;
            } else {
                  self::$db = Yii::app()->dbOracle;
                  return self::$db;
            }
      }
     
      /**
      * Returns the static model of the specified AR class.
      * @param string $className active record class name.
      * @return IvrModel the static model class
      */
      public static function model($className=__CLASS__)
      {
            return parent::model($className);
      }
     
      /**
      * @return string the associated database table name
      */
      public function tableName()
      {
            return 'CCQ.MY_ANNOUNCEMENTS';
      }
     
      /**
      * @return array validation rules for model attributes.
      */
      public function rules()
      {
            return array(
                  array('PKEY', 'default', 'value'=>new CDbExpression('(select max(PKEY)+1 from "CCQ"."MY_ANNOUNCEMENTS")'),
                                                      'setOnEmpty'=>false, 'on'=>'insert'),
                  array('DATETIME', 'default', 'value'=>new CDbExpression('SYSDATE'), 'setOnEmpty'=>false, 'on'=>'insert'),
                  array('DATETIME', 'default', 'value'=>new CDbExpression('SYSDATE'), 'setOnEmpty'=>false, 'on'=>'update'),
            );
      }
}
```
```php
<?php
    $oneModel = IvrModel::model()->find('TICKET=:ticket', array(':ticket'=>12345));
    $allModels = IvrModel::model()->findAll();
```
```php
<?php
    $model = new IvrModel;
     //Note the attributes are fully capitalized
    $model->PC_TO = 1111;
    $model->PC_FROM = 1113;
    $model->PRODUCTTYPE = 'Internet';
    $model->TTS = '';
    $model->ANNOUNCEMENT = '7501.wav';
    $model->TYPE = 0;
    $model->TICKET = 5555;
    $model->save();
```