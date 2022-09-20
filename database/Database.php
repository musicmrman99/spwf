<?php
/**
 * A small library for interacting with a database and basic ORM functionality.
 * 
 * Currently supports the following databases (remember: your SQL is still
 * database-specific):
 * - MySQL
 * - SQLite
 * 
 * A Database takes ConnectionInfo and can connect to and disconnect from the
 * actual database referred to by that ConnectionInfo at any time. While the
 * connection is active, you can fetch one or many records from the database
 * with the Database::fetch() and Database::fetchAll() methods.
 * 
 * Database allows for construction of domain object graphs from the data
 * returned using three mechanisms:
 * 
 * eg. getBThingsUnderPrice($limit)
 * 
 * 1. Fetching object data into unique objects per primary object ID, while
 *    fetching foreign keys as additional fields. Fetching into objects of a
 *    specified class requires that class to be Bindable.
 * 
 * eg. $aThings = $db->fetchAll(
 *       "SELECT aThing.id, a, b, c, bThing.id AS bThing_id"
 *       ." FROM aThing"
 *       ." JOIN bThing ON aThing.fkey = bThing.id"
 *       ." WHERE bThing.d < :limit",
 *       ["limit" => $limit],  // Tags to bind
 *       domain\AThing::class, // The class for aThing
 *       null,                 // Primary object ID is first column
 *       [
 *         new Field("id"),    // All fields for AThing
 *         new Field("a"),
 *         new Field("b"),
 *         new Field("c"),
 *                             // Plus constants needed by AThing's constructor,
 *                             // eg. injected dependencies.
 *         $someDependencyA
 *       ]                     // Except foreign key
 *     );
 * 
 * 2. Using Database::groupByFKey() to group the returned records by a foreign
 *    key extracted into an additional field.
 * 
 * eg. $aThingsByBThing = Database::groupByFKey($aThings, "bThing_id")
 * 
 * 3. Using given Fields and RecordValues to construct the composite objects
 *    using primary/foreign key matches.
 * 
 * eg. $bThings = $db->fetchAll(
 *       "SELECT bThing.id, d, e, f"
 *       ." FROM bThing"
 *       ." WHERE d < :limit",
 *       ["limit" => $limit],  // Tags to bind
 *       domain\BThing::class, // The class for bThing
 *       null,                 // Primary object ID is first column
 *       [
 *         new Field("id"),    // All fields for BThing
 *         new Field("d"),
 *         new Field("e"),
 *         new Field("f"),
 *                             // Plus a RecordValue for the aThings
 *         new RecordValue($aThingsByBThing, []),
 *                             // Plus constants needed by BThing's constructor,
 *                             // eg. injected dependencies.
 *         $someDependencyB
 *       ]
 *     );
 */

namespace database;

use PDO, PDOException;
use util\Util;
use database\exceptions\DatabaseError;

/**
 * A wrapper around a PDO connection.
 * 
 * The connection does not always need to be active. It will be deactivated when
 * the Database object is destroyed, or from an explicit call to deactivate().
 * 
 * @author William Taylor (19009576)
 */
class Database {
    private $connectionInfo;
    private $connection;

    /* Activating and deactivating the connection
    -------------------------------------------------- */

    /**
     * Construct a logical Database connection, activing it if requested.
     * 
     * @param ConnectionInfo $connectionInfo The database connection information
     *   to use.
     * @param bool $activate (Optional) Whether to activate the connection
     *   immediately. Defaults to not activating immediately.
     */
    public function __construct($connectionInfo, $activate = false) {
        $this->connectionInfo = $connectionInfo;
        $this->connection = null;

        if ($activate) {
            $this->activate();
        }
    }

    /**
     * Deactivate the database if needed.
     */
    public function __destruct() {
        $this->deactivate();
    }

    /**
     * Activate the database.
     * 
     * Activation will connect to the database, open the file, etc., depending
     * on the database type.
     * 
     * @throws DatabaseError If the activation/connection attempt fails.
     */
    public function activate() {
        try {
            $this->connection = new PDO(
                ...$this->connectionInfo->get(),
                ...[[ // Avoid "cannot use positional argument after unpacking"
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
                ]]
            );
        } catch (PDOException $e) {
            throw new DatabaseError($e->getMessage(), $e);
        }
    }

    /**
     * Deactivate the database.
     * 
     * Deactivation will close the connection, close the file, etc., depending
     * on the database type.
     */
    public function deactivate() {
        $this->connection = null;
    }

    /* Uses of the Connection
    -------------------------------------------------- */

    /**
     * Run the SQL query and return the complete set of objects returned.
     * 
     * Create an instance of stdClass for every record in the SQL query results.
     * 
     * @param string $sql The SQL query to execute.
     * 
     * @param array<string,mixed> $values (Optional) An associative array of
     *   values that correspond to markers in the given SQL
     *   ({@see PDO::prepare()}).
     * 
     * @param class $class (Optional) A fully qualified reference to a class
     *   (possibly retrieved by using ::class). The class must implement
     *   Bindable.
     *   
     *   If this parameter is given, the default behaviour of creating an
     *   instance of stdClass for every record in the SQL query results is
     *   overridden. Instead, a "primary object ID" field is defined as the
     *   first field in the SQL query results. Then, for each unique primary
     *   object ID in the SQL query results, an instance of the given class is
     *   created. All fields from the fetched record except the primary object
     *   ID are passed to the class's constructor in the order they are returned
     *   in the SQL query results, then the primary object ID field is bound to
     *   the "id" property of the created object.
     *   
     *   If there some fields in the SQL query results (other than the primary
     *   object ID) that are not included in the class's constructor arguments
     *   (which can only happen if $constructorArgs is passed), then these are
     *   considered "additional fields". If there are any additional fields,
     *   then return an array of arrays (rather than an array of objects), where
     *   the inner arrays have the format:
     *   
     *     [
     *       <object>,
     *       <additionalFieldName> => <additionalFieldValue>,
     *       <additionalFieldName> => <additionalFieldValue>,
     *       ...
     *     ]
     *   
     *   If multiple records in the SQL query results define the same object
     *   (ie. have the same value for the primary object ID), then the
     *   previously-constructed object will be used. If there are additional
     *   fields, this means that object references for "<created object>" are
     *   not guaranteed to be unique across the returned records, but every
     *   object returned is guaranteed to be unique (ie. have a unique ID).
     * 
     * @param Field $idField (Optional) The field (column) of the ID for the
     *   given class. The class's ID property must be called "id". If not given,
     *   the first field returned by the SQL statement will be assumed to be the
     *   field for the class's ID.
     * 
     * @param array<Field|Value> $constructorArgs (Optional) A list of data
     *   sources of values to pass to the given class's constructor, in order.
     *   The sources may be Fields (to use the data in the record) or Values
     *   (to pass a literal value, possibly depending on the primary object ID).
     * 
     * @return array<mixed> A (possibly empty) array of fetched record objects,
     *   or of arrays containing [object, ...additionalFields].
     * 
     * @throws DatabaseError If the database has not be activated, or for any
     * PDO error.
     */
    public function fetchAll(
            $sql,
            $values = null,
            $class = null,
            $idField = null,
            $constructorArgs = null
    ) {
        $statement = $this->query($sql, $values);

        if ($class !== null) {
            $statement->setFetchMode(PDO::FETCH_ASSOC);
        }
        $objPool = [];
        $records = [];
        while ($record = $statement->fetch()) {
            if ($class !== null) {
                $record = $this->getRecord(
                    $record, $class, $idField, $constructorArgs, $objPool
                );
            }
            $records[] = $record;
        }
        return $records;
    }

    /**
     * Run the SQL query and return the single record returned, or null if no
     * records were returned.
     * 
     * If the SQL query results contain more than one record, then return only
     * the first record.
     * 
     * @param string $sql The SQL query to execute.
     * 
     * @param array<string,mixed> $values (Optional) An associative array of
     *   values that correspond to markers in the given SQL
     *   ({@see PDO::prepare()}).
     * 
     * @param class $class (Optional) A fully qualified reference to a class
     *   (possibly retrieved by using ::class). The class must implement
     *   Bindable.
     *   
     *   If this parameter is given, the default behaviour of creating an
     *   instance of stdClass for every record in the SQL query results is
     *   overridden. Instead, a "primary object ID" field is defined as the
     *   first field in the SQL query results. Then, for each unique primary
     *   object ID in the SQL query results, an instance of the given class is
     *   created. All fields from the fetched record except the primary object
     *   ID are passed to the class's constructor in the order they are returned
     *   in the SQL query results, then the primary object ID field is bound to
     *   the "id" property of the created object.
     * 
     *   If there some fields in the SQL query results (other than the primary
     *   object ID) that are not included in the class's constructor arguments
     *   (which can only happen if $constructorArgs is passed), then these are
     *   considered "additional fields". If there are any additional fields,
     *   then return an array of arrays (rather than an array of objects), where
     *   the inner arrays have the format:
     *   
     *     [
     *       <object>,
     *       <additionalFieldName> => <additionalFieldValue>,
     *       <additionalFieldName> => <additionalFieldValue>,
     *       ...
     *     ]
     * 
     * @param Field $idField (Optional) The field (column) of the ID for the
     *   given class. The class's ID property must be called "id". If not given,
     *   the first field returned by the SQL statement will be assumed to be the
     *   field for the class's ID.
     * 
     * @param array<Field|Value> $constructorArgs (Optional) A list of data
     *   sources of values to pass to the given class's constructor, in order.
     *   The sources may be Fields (to use the data in the record) or Values
     *   (to pass a literal value, possibly depending on the primary object ID).
     * 
     * @return mixed Either the fetched record object, an array containing the
     *   fetched record object then any additional fields that were fetched, or
     *   null if no records were returned.
     * 
     * @throws DatabaseError If the database has not be activated, or for any
     * PDO error.
     */
    public function fetch(
            $sql,
            $values = null,
            $class = null,
            $idField = null,
            $constructorArgs = null
    ) {
        $statement = $this->query($sql, $values);

        if ($class !== null) {
            $statement->setFetchMode(PDO::FETCH_ASSOC);
        }
        $record = $statement->fetch();
        if ($class !== null && $record) {
            $record = $this->getRecord(
                $record, $class, $idField, $constructorArgs
            );
        }

        if ($record) {
            return $record;
        }
        return null;
    }

    /**
     * Query the database without returning anything.
     * 
     * @param string $sql The SQL query to execute.
     * @param array<string,mixed> $values (Optional) An associative array of
     *   values that correspond to markers in the given SQL
     *   ({@see PDO::prepare()}).
     */
    public function execute($sql, $values = null) {
        $this->query($sql, $values);
    }

    /* Utils
    -------------------------------------------------- */

    /**
     * Query the database and return the statement object.
     * 
     * @param string $sql The SQL query to execute.
     * @param array<string,mixed> $values (Optional) An associative array of
     *   values that correspond to markers in the given SQL
     *   ({@see PDO::prepare()}).
     * 
     * @return PDOStatement The created statement.
     * 
     * @throws DatabaseError If the database has not be activated, or for any
     * PDO error.
     */
    private function query($sql, $values) {
        if ($values === null) $values = [];

        if (!$this->connection) {
            // Basically a NullPointerException, but with a much more specific
            // error message.
            throw new DatabaseError("Database connection not activated");
        }

        try {
            $statement = $this->connection->prepare($sql);
            $statement->execute($values);
        } catch (PDOException $e) {
            throw new DatabaseError($e->getMessage(), $e);
        }

        return $statement;
    }

    /**
     * Get the output record for the given raw record.
     * 
     * A "primary object ID" field is defined as the first field in the SQL
     * query results. If no object pool is given, or if no object with the
     * primary object ID from given record exists in the object pool, then a new
     * object is created and added to the object pool. The object will be an
     * instance of the given class. All fields from the given record except the
     * primary object ID are passed to the class's constructor in the same order
     * as the record has them, then the primary object ID field is bound to the
     * "id" property of the created object.
     * 
     * The "output record" is ordinarily an object created from the given class.
     * If there some fields in the given record (other than the primary object
     * ID) that are not included in the class's constructor arguments (which can
     * only happen if $constructorArgs is passed), then these are considered
     * "additional fields". If there are any additional fields, then the "output
     * record" is an array of the format:
     * 
     *   [
     *     <object>,
     *     <additionalFieldName> => <additionalFieldValue>,
     *     <additionalFieldName> => <additionalFieldValue>,
     *     ...
     *   ]
     * 
     * @param array<mixed> $record The record to get or construct the object
     *   for.
     * 
     * @param class $class A fully qualified reference to the class to construct
     *   an object of (whether the literal class name, or using ::class). The
     *   class must implement Bindable.
     * 
     * @param Field $idField (Optional) The field (column) of the ID for the
     *   given class. The class's ID property must be called "id". If not given,
     *   the first field returned by the SQL statement will be assumed to be the
     *   field for the class's ID.
     * 
     * @param array<Field|Value> $constructorArgs (Optional) A list of data
     *   sources of values to pass to the given class's constructor, in order.
     *   The sources may be Fields (to use the data in the raw record) or Values
     *   (to pass a literal value, possibly depending on the primary object ID).
     * 
     * @param array<mixed,mixed> $objPool (Optional, By Reference) A mapping of
     *   unique objects for this class type, indexed by the class ID. If given,
     *   and an object exists in the pool with an ID the same as the primary
     *   object ID in the given record, then that object will be returned
     *   instead of creating a new object. If such an object does not already
     *   exist in the pool, an object is created and added to the pool.
     * 
     * @return mixed An instance of the given class containing the relevant data
     *   from the given record (or a previously found record with the same
     *   data), or an array of the format shown above containing that object and
     *   all additional fields.
     */
    private function getRecord(
            $record,
            $class,
            $idField = null,
            $constructorArgs = null,
            &$objPool = null
    ) {
        // Determine ID field
        if ($idField !== null) {
            $idFieldName = $idField->name();
        } else {
            reset($record);
            $idFieldName = key($record);
        }
        $id = $record[$idFieldName];

        // Get or make object
        if ($objPool !== null && array_key_exists($id, $objPool)) {
            $object = $objPool[$id];

        } else {
            if ($constructorArgs !== null) {
                // Use $constructorArgs
                $args = array_map(
                    function ($argSpec) use ($record, $id, &$additionalFields) {
                        if (!($argSpec instanceof ArgumentSpec)) {
                            return $argSpec;
                        }
                        return $argSpec->getValue($record, $id);
                    },
                    $constructorArgs
                );

            } else {
                // Use all fields from record, in the order returned by the SQL,
                // except the ID field.
                $args = Util::filterKeysValues(
                    $record,
                    function ($field, $value) use ($idFieldName) {
                        return $field !== $idFieldName;
                    },
                    false
                );
            }

            $object = new $class(...$args);
            $object->bind($idFieldName, $id, [$idFieldName]);

            // Add to pool
            if ($objPool !== null) {
                $objPool[$id] = $object;
            }
        }

        // Determine 'additional fields', ie. all fields, except those used in
        // constructing the object (ie. those given to constructor + the primary
        // object ID).
        if ($constructorArgs !== null) {
            $additionalFields = $record;
            unset($additionalFields[$idFieldName]);
            foreach ($constructorArgs as $argSpec) {
                if ($argSpec instanceof Field) {
                    unset($additionalFields[$argSpec->name()]);
                }
            }

        } else {
            // If constructor args is not given, all fields are used in
            // constructing the object by default.
            $additionalFields = [];
        }

        // Determine return record
        if (count($additionalFields) > 0) {
            $record = [$object] + $additionalFields;
        } else {
            $record = $object;
        }
        return $record;
    }

    /**
     * Group the objects in the given fetched records into arrays, indexed by
     * the named additional field.
     * 
     * @param mixed $record The records to group.
     * @param callback $field The field to group by.
     * @return array<mixed,array<mixed>> Each group (array), indexed by the
     *   given additional field.
     */
    public static function groupByFKey($records, $field) {
        $arr = [];
        foreach ($records as $record) {
            $key = $record[$field];
            if (!array_key_exists($key, $arr)) {
                $arr[$key] = [];
            }
            $arr[$key][] = $record[0];
        }
        return $arr;
    }
}
