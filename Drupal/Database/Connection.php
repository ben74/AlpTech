<?php
namespace Drupal\Core\Database;
use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Database\Query\Delete;
use Drupal\Core\Database\Query\Insert;
use Drupal\Core\Database\Query\Merge;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\Query\Truncate;
use Drupal\Core\Database\Query\Update;
use Drupal\Core\Database\Query\Upsert;
use Drupal\Core\Database\DatabaseExceptionWrapper;

abstract class Connection {#juste pour héritance, depuis ??
    protected $target = NULL;
    protected $key = NULL;
    protected $logger = NULL;
    protected $transactionLayers = [];

    /**
     * Index of what driver-specific class to use for various operations.
     *
     * @var array
     */
    protected $driverClasses = [];

    /**
     * The name of the Statement class for this connection.
     *
     * @var string
     */
    protected $statementClass = 'Drupal\Core\Database\Statement';

    /**
     * Whether this database connection supports transactions.
     *
     * @var bool
     */
    protected $transactionSupport = TRUE;

    /**
     * Whether this database connection supports transactional DDL.
     *
     * Set to FALSE by default because few databases support this feature.
     *
     * @var bool
     */
    protected $transactionalDDLSupport = FALSE;

    /**
     * An index used to generate unique temporary table names.
     *
     * @var int
     */
    protected $temporaryNameIndex = 0;

    /**
     * The actual PDO connection.
     *
     * @var \PDO
     */
    protected $connection;

    /**
     * The connection information for this connection object.
     *
     * @var array
     */
    protected $connectionOptions = [];

    /**
     * The schema object for this connection.
     *
     * Set to NULL when the schema is destroyed.
     *
     * @var \Drupal\Core\Database\Schema|null
     */
    protected $schema = NULL;

    /**
     * The prefixes used by this database connection.
     *
     * @var array
     */
    protected $prefixes = [];

    /**
     * List of search values for use in prefixTables().
     *
     * @var array
     */
    protected $prefixSearch = [];

    /**
     * List of replacement values for use in prefixTables().
     *
     * @var array
     */
    protected $prefixReplace = [];

    /**
     * List of un-prefixed table names, keyed by prefixed table names.
     *
     * @var array
     */
    protected $unprefixedTablesMap = [];

    /**
     * List of escaped database, table, and field names, keyed by unescaped names.
     *
     * @var array
     *
     * @deprecated in drupal:9.0.0 and is removed from drupal:10.0.0. This is no
     *   longer used. Use \Drupal\Core\Database\Connection::$escapedTables or
     *   \Drupal\Core\Database\Connection::$escapedFields instead.
     *
     * @see https://www.drupal.org/node/2986894
     */
    protected $escapedNames = [];

    /**
     * List of escaped table names, keyed by unescaped names.
     *
     * @var array
     */
    protected $escapedTables = [];

    /**
     * List of escaped field names, keyed by unescaped names.
     *
     * There are cases in which escapeField() is called on an empty string. In
     * this case it should always return an empty string.
     *
     * @var array
     */
    protected $escapedFields = ["" => ""];

    /**
     * List of escaped aliases names, keyed by unescaped aliases.
     *
     * @var array
     */
    protected $escapedAliases = [];

    /**
     * Post-root (non-nested) transaction commit callbacks.
     *
     * @var callable[]
     */
    protected $rootTransactionEndCallbacks = [];

    /**
     * The identifier quote characters for the database type.
     *
     * An array containing the start and end identifier quote characters for the
     * database type. The ANSI SQL standard identifier quote character is a double
     * quotation mark.
     *
     * @var string[]
     */
    protected $identifierQuotes;

    /**
     * Constructs a Connection object.
     *
     * @param \PDO $connection
     *   An object of the PDO class representing a database connection.
     * @param array $connection_options
     *   An array of options for the connection. May include the following:
     *   - prefix
     *   - namespace
     *   - Other driver-specific options.
     */
    public function __construct(\PDO $connection, array $connection_options) {
        if ($this->identifierQuotes === NULL) {
            @trigger_error('In drupal:10.0.0 not setting the $identifierQuotes property in the concrete Connection class will result in an RuntimeException. See https://www.drupal.org/node/2986894', E_USER_DEPRECATED);
            $this->identifierQuotes = ['', ''];
        }
        assert(count($this->identifierQuotes) === 2 && Inspector::assertAllStrings($this->identifierQuotes), '\Drupal\Core\Database\Connection::$identifierQuotes must contain 2 string values');

        // Work out the database driver namespace if none is provided. This normally
        // written to setting.php by installer or set by
        // \Drupal\Core\Database\Database::parseConnectionInfo().
        if (empty($connection_options['namespace'])) {
            $connection_options['namespace'] = (new \ReflectionObject($this))->getNamespaceName();
        }

        // Initialize and prepare the connection prefix.
        $this->setPrefix(isset($connection_options['prefix']) ? $connection_options['prefix'] : '');

        // Set a Statement class, unless the driver opted out.
        if (!empty($this->statementClass)) {
            $connection->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [$this->statementClass, [$this]]);
        }

        $this->connection = $connection;
        $this->connectionOptions = $connection_options;
    }

    /**
     * Opens a PDO connection.
     *
     * @param array $connection_options
     *   The database connection settings array.
     *
     * @return \PDO
     *   A \PDO object.
     */
    public static function open(array &$connection_options = []) {}

    /**
     * Destroys this Connection object.
     *
     * PHP does not destruct an object if it is still referenced in other
     * variables. In case of PDO database connection objects, PHP only closes the
     * connection when the PDO object is destructed, so any references to this
     * object may cause the number of maximum allowed connections to be exceeded.
     */
    public function destroy() {
        // Destroy all references to this connection by setting them to NULL.
        // The Statement class attribute only accepts a new value that presents a
        // proper callable, so we reset it to PDOStatement.
        if (!empty($this->statementClass)) {
            $this->connection->setAttribute(\PDO::ATTR_STATEMENT_CLASS, ['PDOStatement', []]);
        }
        $this->schema = NULL;
    }

    /**
     * Returns the default query options for any given query.
     *
     * A given query can be customized with a number of option flags in an
     * associative array:
     * - fetch: This element controls how rows from a result set will be
     *   returned. Legal values include PDO::FETCH_ASSOC, PDO::FETCH_BOTH,
     *   PDO::FETCH_OBJ, PDO::FETCH_NUM, or a string representing the name of a
     *   class. If a string is specified, each record will be fetched into a new
     *   object of that class. The behavior of all other values is defined by PDO.
     *   See http://php.net/manual/pdostatement.fetch.php
     * - return: Depending on the type of query, different return values may be
     *   meaningful. This directive instructs the system which type of return
     *   value is desired. The system will generally set the correct value
     *   automatically, so it is extremely rare that a module developer will ever
     *   need to specify this value. Setting it incorrectly will likely lead to
     *   unpredictable results or fatal errors. Legal values include:
     *   - Database::RETURN_STATEMENT: Return the prepared statement object for
     *     the query. This is usually only meaningful for SELECT queries, where
     *     the statement object is how one accesses the result set returned by the
     *     query.
     *   - Database::RETURN_AFFECTED: Return the number of rows affected by an
     *     UPDATE or DELETE query. Be aware that means the number of rows actually
     *     changed, not the number of rows matched by the WHERE clause.
     *   - Database::RETURN_INSERT_ID: Return the sequence ID (primary key)
     *     created by an INSERT statement on a table that contains a serial
     *     column.
     *   - Database::RETURN_NULL: Do not return anything, as there is no
     *     meaningful value to return. That is the case for INSERT queries on
     *     tables that do not contain a serial column.
     * - throw_exception: By default, the database system will catch any errors
     *   on a query as an Exception, log it, and then rethrow it so that code
     *   further up the call chain can take an appropriate action. To suppress
     *   that behavior and simply return NULL on failure, set this option to
     *   FALSE.
     * - allow_delimiter_in_query: By default, queries which have the ; delimiter
     *   any place in them will cause an exception. This reduces the chance of SQL
     *   injection attacks that terminate the original query and add one or more
     *   additional queries (such as inserting new user accounts). In rare cases,
     *   such as creating an SQL function, a ; is needed and can be allowed by
     *   changing this option to TRUE.
     * - allow_square_brackets: By default, queries which contain square brackets
     *   will have them replaced with the identifier quote character for the
     *   database type. In rare cases, such as creating an SQL function, []
     *   characters might be needed and can be allowed by changing this option to
     *   TRUE.
     *
     * @return array
     *   An array of default query options.
     */
    protected function defaultOptions() {
        return [
            'fetch' => \PDO::FETCH_OBJ,
            'return' => Database::RETURN_STATEMENT,
            'throw_exception' => TRUE,
            'allow_delimiter_in_query' => FALSE,
            'allow_square_brackets' => FALSE,
        ];
    }

    /**
     * Returns the connection information for this connection object.
     *
     * Note that Database::getConnectionInfo() is for requesting information
     * about an arbitrary database connection that is defined. This method
     * is for requesting the connection information of this specific
     * open connection object.
     *
     * @return array
     *   An array of the connection information. The exact list of
     *   properties is driver-dependent.
     */
    public function getConnectionOptions() {
        return $this->connectionOptions;
    }

    /**
     * Set the list of prefixes used by this database connection.
     *
     * @param array|string $prefix
     *   Either a single prefix, or an array of prefixes, in any of the multiple
     *   forms documented in default.settings.php.
     */
    protected function setPrefix($prefix) {
        if (is_array($prefix)) {
            $this->prefixes = $prefix + ['default' => ''];
        }
        else {
            $this->prefixes = ['default' => $prefix];
        }

        [$start_quote, $end_quote] = $this->identifierQuotes;
        // Set up variables for use in prefixTables(). Replace table-specific
        // prefixes first.
        $this->prefixSearch = [];
        $this->prefixReplace = [];
        foreach ($this->prefixes as $key => $val) {
            if ($key != 'default') {
                $this->prefixSearch[] = '{' . $key . '}';
                // $val can point to another database like 'database.users'. In this
                // instance we need to quote the identifiers correctly.
                $val = str_replace('.', $end_quote . '.' . $start_quote, $val);
                $this->prefixReplace[] = $start_quote . $val . $key . $end_quote;
            }
        }
        // Then replace remaining tables with the default prefix.
        $this->prefixSearch[] = '{';
        // $this->prefixes['default'] can point to another database like
        // 'other_db.'. In this instance we need to quote the identifiers correctly.
        // For example, "other_db"."PREFIX_table_name".
        $this->prefixReplace[] = $start_quote . str_replace('.', $end_quote . '.' . $start_quote, $this->prefixes['default']);
        $this->prefixSearch[] = '}';
        $this->prefixReplace[] = $end_quote;

        // Set up a map of prefixed => un-prefixed tables.
        foreach ($this->prefixes as $table_name => $prefix) {
            if ($table_name !== 'default') {
                $this->unprefixedTablesMap[$prefix . $table_name] = $table_name;
            }
        }
    }

    /**
     * Appends a database prefix to all tables in a query.
     *
     * Queries sent to Drupal should wrap all table names in curly brackets. This
     * function searches for this syntax and adds Drupal's table prefix to all
     * tables, allowing Drupal to coexist with other systems in the same database
     * and/or schema if necessary.
     *
     * @param string $sql
     *   A string containing a partial or entire SQL query.
     *
     * @return string
     *   The properly-prefixed string.
     */
    public function prefixTables($sql) {
        return str_replace($this->prefixSearch, $this->prefixReplace, $sql);
    }

    /**
     * Quotes all identifiers in a query.
     *
     * Queries sent to Drupal should wrap all unquoted identifiers in square
     * brackets. This function searches for this syntax and replaces them with the
     * database specific identifier. In ANSI SQL this a double quote.
     *
     * Note that :variable[] is used to denote array arguments but
     * Connection::expandArguments() is always called first.
     *
     * @param string $sql
     *   A string containing a partial or entire SQL query.
     *
     * @return string
     *   The string containing a partial or entire SQL query with all identifiers
     *   quoted.
     *
     * @internal
     *   This method should only be called by database API code.
     */
    public function quoteIdentifiers($sql) {
        return str_replace(['[', ']'], $this->identifierQuotes, $sql);
    }

    /**
     * Find the prefix for a table.
     *
     * This function is for when you want to know the prefix of a table. This
     * is not used in prefixTables due to performance reasons.
     *
     * @param string $table
     *   (optional) The table to find the prefix for.
     */
    public function tablePrefix($table = 'default') {
        if (isset($this->prefixes[$table])) {
            return $this->prefixes[$table];
        }
        else {
            return $this->prefixes['default'];
        }
    }

    /**
     * Gets a list of individually prefixed table names.
     *
     * @return array
     *   An array of un-prefixed table names, keyed by their fully qualified table
     *   names (i.e. prefix + table_name).
     */
    public function getUnprefixedTablesMap() {
        return $this->unprefixedTablesMap;
    }

    /**
     * Get a fully qualified table name.
     *
     * @param string $table
     *   The name of the table in question.
     *
     * @return string
     */
    public function getFullQualifiedTableName($table) {
        $options = $this->getConnectionOptions();
        $prefix = $this->tablePrefix($table);
        return $options['database'] . '.' . $prefix . $table;
    }

    /**
     * Prepares a query string and returns the prepared statement.
     *
     * This method caches prepared statements, reusing them when possible. It also
     * prefixes tables names enclosed in curly-braces and, optionally, quotes
     * identifiers enclosed in square brackets.
     *
     * @param $query
     *   The query string as SQL, with curly-braces surrounding the
     *   table names.
     * @param bool $quote_identifiers
     *   (optional) Quote any identifiers enclosed in square brackets. Defaults to
     *   TRUE.
     *
     * @return \Drupal\Core\Database\StatementInterface
     *   A PDO prepared statement ready for its execute() method.
     */
    public function prepareQuery($query, $quote_identifiers = TRUE) {
        $query = $this->prefixTables($query);
        if ($quote_identifiers) {
            $query = $this->quoteIdentifiers($query);
        }

        return $this->connection->prepare($query);
    }

    /**
     * Tells this connection object what its target value is.
     *
     * This is needed for logging and auditing. It's sloppy to do in the
     * constructor because the constructor for child classes has a different
     * signature. We therefore also ensure that this function is only ever
     * called once.
     *
     * @param string $target
     *   (optional) The target this connection is for.
     */
    public function setTarget($target = NULL) {
        if (!isset($this->target)) {
            $this->target = $target;
        }
    }

    /**
     * Returns the target this connection is associated with.
     *
     * @return string|null
     *   The target string of this connection, or NULL if no target is set.
     */
    public function getTarget() {
        return $this->target;
    }

    public function setKey($key) {
        if (!isset($this->key)) {
            $this->key = $key;
        }
    }

    public function getKey() {
        return $this->key;
    }

    public function setLogger(Log $logger) {
        $this->logger = $logger;
    }

    public function getLogger() {
        return $this->logger;
    }

    public function makeSequenceName($table, $field) {
        $sequence_name = $this->prefixTables('{' . $table . '}_' . $field . '_seq');
        // Remove identifier quotes as we are constructing a new name from a
        // prefixed and quoted table name.
        return str_replace($this->identifierQuotes, '', $sequence_name);
    }

    public function makeComment($comments) {
        if (empty($comments)) {
            return '';
        }

        // Flatten the array of comments.
        $comment = implode('. ', $comments);

        // Sanitize the comment string so as to avoid SQL injection attacks.
        return '/* ' . $this->filterComment($comment) . ' */ ';
    }

    protected function filterComment($comment = '') {
        // Change semicolons to period to avoid triggering multi-statement check.
        return strtr($comment, ['*' => ' * ', ';' => '.']);
    }
    public function query($query, array $args = [], $options = []) {
        // Use default values if not already set.
        $options += $this->defaultOptions();
        assert(!isset($options['target']), 'Passing "target" option to query() has no effect. See https://www.drupal.org/node/2993033');

        try {
            // We allow either a pre-bound statement object or a literal string.
            // In either case, we want to end up with an executed statement object,
            // which we pass to PDOStatement::execute.
            if ($query instanceof StatementInterface) {
                $stmt = $query;
                $stmt->execute(NULL, $options);
            }
            else {
                $this->expandArguments($query, $args);
                // To protect against SQL injection, Drupal only supports executing one
                // statement at a time.  Thus, the presence of a SQL delimiter (the
                // semicolon) is not allowed unless the option is set.  Allowing
                // semicolons should only be needed for special cases like defining a
                // function or stored procedure in SQL. Trim any trailing delimiter to
                // minimize false positives unless delimiter is allowed.
                $trim_chars = "  \t\n\r\0\x0B";
                if (empty($options['allow_delimiter_in_query'])) {
                    $trim_chars .= ';';
                }
                $query = rtrim($query, $trim_chars);
                if (strpos($query, ';') !== FALSE && empty($options['allow_delimiter_in_query'])) {
                    throw new \InvalidArgumentException('; is not supported in SQL strings. Use only one statement at a time.');
                }
                $stmt = $this->prepareQuery($query, !$options['allow_square_brackets']);
                $stmt->execute($args, $options);
            }

            // Depending on the type of query we may need to return a different value.
            // See DatabaseConnection::defaultOptions() for a description of each
            // value.
            switch ($options['return']) {
                case Database::RETURN_STATEMENT:
                    return $stmt;

                case Database::RETURN_AFFECTED:
                    $stmt->allowRowCount = TRUE;
                    return $stmt->rowCount();

                case Database::RETURN_INSERT_ID:
                    $sequence_name = isset($options['sequence_name']) ? $options['sequence_name'] : NULL;
                    return $this->connection->lastInsertId($sequence_name);

                case Database::RETURN_NULL:
                    return NULL;

                default:
                    throw new \PDOException('Invalid return directive: ' . $options['return']);
            }
        }
        catch (\PDOException $e) {
            // Most database drivers will return NULL here, but some of them
            // (e.g. the SQLite driver) may need to re-run the query, so the return
            // value will be the same as for static::query().
            $fq=$this->interpolateQuery($query, $args);
            $_ENV['_excSql'][]=$fq;

            return $this->handleQueryException($e, $query, $args, $options);
        }
    }

    protected function handleQueryException(\PDOException $e, $query, array $args = [], $options = []) {
        if ($options['throw_exception']) {
            // Wrap the exception in another exception, because PHP does not allow
            // overriding Exception::getMessage(). Its message is the extra database
            // debug information.
            $query_string = ($query instanceof StatementInterface) ? $query->getQueryString() : $query;
            $message = $e->getMessage() . ": " . $query_string . "; " . print_r($args, TRUE);
            // Match all SQLSTATE 23xxx errors.
            if (substr($e->getCode(), -6, -3) == '23') {
                $exception = new IntegrityConstraintViolationException($message, $e->getCode(), $e);
            }
            else {
                $exception = new DatabaseExceptionWrapper($message, 0, $e);
            }

            throw $exception;
        }

        return NULL;
    }

    protected function expandArguments(&$query, &$args) {
        $modified = FALSE;

        // If the placeholder indicated the value to use is an array,  we need to
        // expand it out into a comma-delimited set of placeholders.
        foreach ($args as $key => $data) {
            $is_bracket_placeholder = substr($key, -2) === '[]';
            $is_array_data = is_array($data);
            if ($is_bracket_placeholder && !$is_array_data) {
                throw new \InvalidArgumentException('Placeholders with a trailing [] can only be expanded with an array of values.');
            }
            elseif (!$is_bracket_placeholder) {
                if ($is_array_data) {
                    throw new \InvalidArgumentException('Placeholders must have a trailing [] if they are to be expanded with an array of values.');
                }
                // Scalar placeholder - does not need to be expanded.
                continue;
            }
            // Handle expansion of arrays.
            $key_name = str_replace('[]', '__', $key);
            $new_keys = [];
            // We require placeholders to have trailing brackets if the developer
            // intends them to be expanded to an array to make the intent explicit.
            foreach (array_values($data) as $i => $value) {
                // This assumes that there are no other placeholders that use the same
                // name.  For example, if the array placeholder is defined as :example[]
                // and there is already an :example_2 placeholder, this will generate
                // a duplicate key.  We do not account for that as the calling code
                // is already broken if that happens.
                $new_keys[$key_name . $i] = $value;
            }

            // Update the query with the new placeholders.
            $query = str_replace($key, implode(', ', array_keys($new_keys)), $query);

            // Update the args array with the new placeholders.
            unset($args[$key]);
            $args += $new_keys;

            $modified = TRUE;
        }

        return $modified;
    }

    public function getDriverClass($class) {
        if (empty($this->driverClasses[$class])) {
            $driver_class = $this->connectionOptions['namespace'] . '\\' . $class;
            if (class_exists($driver_class)) {
                $this->driverClasses[$class] = $driver_class;
            }
            else {
                switch ($class) {
                    case 'Condition':
                        $this->driverClasses[$class] = Condition::class;
                        break;

                    case 'Delete':
                        $this->driverClasses[$class] = Delete::class;
                        break;

                    case 'Insert':
                        $this->driverClasses[$class] = Insert::class;
                        break;

                    case 'Merge':
                        $this->driverClasses[$class] = Merge::class;
                        break;

                    case 'Schema':
                        $this->driverClasses[$class] = Schema::class;
                        break;

                    case 'Select':
                        $this->driverClasses[$class] = Select::class;
                        break;

                    case 'Transaction':
                        $this->driverClasses[$class] = Transaction::class;
                        break;

                    case 'Truncate':
                        $this->driverClasses[$class] = Truncate::class;
                        break;

                    case 'Update':
                        $this->driverClasses[$class] = Update::class;
                        break;

                    case 'Upsert':
                        $this->driverClasses[$class] = Upsert::class;
                        break;

                    default:
                        $this->driverClasses[$class] = $class;
                }
            }
        }
        return $this->driverClasses[$class];
    }

    public function select($table, $alias = NULL, array $options = []) {
        $class = $this->getDriverClass('Select');
        return new $class($this, $table, $alias, $options);
    }

    public function insert($table, array $options = []) {
        $class = $this->getDriverClass('Insert');
        return new $class($this, $table, $options);
    }

    public function merge($table, array $options = []) {
        $class = $this->getDriverClass('Merge');
        return new $class($this, $table, $options);
    }

    public function upsert($table, array $options = []) {
        $class = $this->getDriverClass('Upsert');
        return new $class($this, $table, $options);
    }
    public function update($table, array $options = []) {
        $class = $this->getDriverClass('Update');
        return new $class($this, $table, $options);
    }

    public function delete($table, array $options = []) {
        $class = $this->getDriverClass('Delete');
        return new $class($this, $table, $options);
    }

    public function truncate($table, array $options = []) {
        $class = $this->getDriverClass('Truncate');
        return new $class($this, $table, $options);
    }

    public function schema() {
        if (empty($this->schema)) {
            $class = $this->getDriverClass('Schema');
            $this->schema = new $class($this);
        }
        return $this->schema;
    }

    public function condition($conjunction) {
        $class = $this->getDriverClass('Condition');
        return new $class($conjunction);
    }

    public function escapeDatabase($database) {
        $database = preg_replace('/[^A-Za-z0-9_]+/', '', $database);
        [$start_quote, $end_quote] = $this->identifierQuotes;
        return $start_quote . $database . $end_quote;
    }

    public function escapeTable($table) {
        if (!isset($this->escapedTables[$table])) {
            $this->escapedTables[$table] = preg_replace('/[^A-Za-z0-9_.]+/', '', $table);
        }
        return $this->escapedTables[$table];
    }

    public function escapeField($field) {
        if (!isset($this->escapedFields[$field])) {
            $escaped = preg_replace('/[^A-Za-z0-9_.]+/', '', $field);
            [$start_quote, $end_quote] = $this->identifierQuotes;
            // Sometimes fields have the format table_alias.field. In such cases
            // both identifiers should be quoted, for example, "table_alias"."field".
            $this->escapedFields[$field] = $start_quote . str_replace('.', $end_quote . '.' . $start_quote, $escaped) . $end_quote;
        }
        return $this->escapedFields[$field];
    }

    public function escapeAlias($field) {
        if (!isset($this->escapedAliases[$field])) {
            [$start_quote, $end_quote] = $this->identifierQuotes;
            $this->escapedAliases[$field] = $start_quote . preg_replace('/[^A-Za-z0-9_]+/', '', $field) . $end_quote;
        }
        return $this->escapedAliases[$field];
    }

    public function escapeLike($string) {
        return addcslashes($string, '\%_');
    }

    public function inTransaction() {
        return ($this->transactionDepth() > 0);
    }

    public function transactionDepth() {
        return count($this->transactionLayers);
    }

    public function startTransaction($name = '') {
        $class = $this->getDriverClass('Transaction');
        return new $class($this, $name);
    }

    public function rollBack($savepoint_name = 'drupal_transaction') {
        if (!$this->supportsTransactions()) {
            return;
        }
        if (!$this->inTransaction()) {
            throw new TransactionNoActiveException();
        }
        // A previous rollback to an earlier savepoint may mean that the savepoint
        // in question has already been accidentally committed.
        if (!isset($this->transactionLayers[$savepoint_name])) {
            throw new TransactionNoActiveException();
        }

        // We need to find the point we're rolling back to, all other savepoints
        // before are no longer needed. If we rolled back other active savepoints,
        // we need to throw an exception.
        $rolled_back_other_active_savepoints = FALSE;
        while ($savepoint = array_pop($this->transactionLayers)) {
            if ($savepoint == $savepoint_name) {
                // If it is the last the transaction in the stack, then it is not a
                // savepoint, it is the transaction itself so we will need to roll back
                // the transaction rather than a savepoint.
                if (empty($this->transactionLayers)) {
                    break;
                }
                $this->query('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $this->popCommittableTransactions();
                if ($rolled_back_other_active_savepoints) {
                    throw new TransactionOutOfOrderException();
                }
                return;
            }
            else {
                $rolled_back_other_active_savepoints = TRUE;
            }
        }

        // Notify the callbacks about the rollback.
        $callbacks = $this->rootTransactionEndCallbacks;
        $this->rootTransactionEndCallbacks = [];
        foreach ($callbacks as $callback) {
            call_user_func($callback, FALSE);
        }

        $this->connection->rollBack();
        if ($rolled_back_other_active_savepoints) {
            throw new TransactionOutOfOrderException();
        }
    }

    public function pushTransaction($name) {
        if (!$this->supportsTransactions()) {
            return;
        }
        if (isset($this->transactionLayers[$name])) {
            throw new TransactionNameNonUniqueException($name . " is already in use.");
        }
        // If we're already in a transaction then we want to create a savepoint
        // rather than try to create another transaction.
        if ($this->inTransaction()) {
            $this->query('SAVEPOINT ' . $name);
        }
        else {
            $this->connection->beginTransaction();
        }
        $this->transactionLayers[$name] = $name;
    }

    public function popTransaction($name) {
        if (!$this->supportsTransactions()) {
            return;
        }
        // The transaction has already been committed earlier. There is nothing we
        // need to do. If this transaction was part of an earlier out-of-order
        // rollback, an exception would already have been thrown by
        // Database::rollBack().
        if (!isset($this->transactionLayers[$name])) {
            return;
        }

        // Mark this layer as committable.
        $this->transactionLayers[$name] = FALSE;
        $this->popCommittableTransactions();
    }

    public function addRootTransactionEndCallback(callable $callback) {
        if (!$this->transactionLayers) {
            throw new \LogicException('Root transaction end callbacks can only be added when there is an active transaction.');
        }
        $this->rootTransactionEndCallbacks[] = $callback;
    }

    protected function popCommittableTransactions() {
        // Commit all the committable layers.
        foreach (array_reverse($this->transactionLayers) as $name => $active) {
            // Stop once we found an active transaction.
            if ($active) {
                break;
            }

            // If there are no more layers left then we should commit.
            unset($this->transactionLayers[$name]);
            if (empty($this->transactionLayers)) {
                $this->doCommit();
            }
            else {
                $this->query('RELEASE SAVEPOINT ' . $name);
            }
        }
    }

    protected function doCommit() {
        $success = $this->connection->commit();
        if (!empty($this->rootTransactionEndCallbacks)) {
            $callbacks = $this->rootTransactionEndCallbacks;
            $this->rootTransactionEndCallbacks = [];
            foreach ($callbacks as $callback) {
                call_user_func($callback, $success);
            }
        }

        if (!$success) {
            throw new TransactionCommitFailedException();
        }
    }

    abstract public function queryRange($query, $from, $count, array $args = [], array $options = []);
    protected function generateTemporaryTableName() {
        return "db_temporary_" . $this->temporaryNameIndex++;
    }
    abstract public function queryTemporary($query, array $args = [], array $options = []);
    abstract public function driver();
    public function version() {
        return $this->connection->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }
    public function clientVersion() {
        return $this->connection->getAttribute(\PDO::ATTR_CLIENT_VERSION);
    }

    public function supportsTransactions() {
        return $this->transactionSupport;
    }
    public function supportsTransactionalDDL() {
        return $this->transactionalDDLSupport;
    }

    abstract public function databaseType();
    abstract public function createDatabase($database);
    abstract public function mapConditionOperator($operator);
    abstract public function nextId($existing_id = 0);

    public function commit() {
        throw new TransactionExplicitCommitNotAllowedException();
    }

    public function prepare($statement, array $driver_options = []) {
        return $this->connection->prepare($statement, $driver_options);
    }
    public function quote($string, $parameter_type = \PDO::PARAM_STR) {
        return $this->connection->quote($string, $parameter_type);
    }

    protected static function getSQLState(\Exception $e) {
        // The PDOException code is not always reliable, try to see whether the
        // message has something usable.
        if (preg_match('/^SQLSTATE\[(\w{5})\]/', $e->getMessage(), $matches)) {
            return $matches[1];
        }
        else {
            return $e->getCode();
        }
    }

    /**
     * Prevents the database connection from being serialized.
     */
    public function __sleep() {
        throw new \LogicException('The database connection is not serializable. This probably means you are serializing an object that has an indirect reference to the database connection. Adjust your code so that is not necessary. Alternatively, look at DependencySerializationTrait as a temporary solution.');
    }

    public static function createConnectionOptionsFromUrl($url, $root) {
        $url_components = parse_url($url);
        if (!isset($url_components['scheme'], $url_components['host'], $url_components['path'])) {
            throw new \InvalidArgumentException('Minimum requirement: driver://host/database');
        }

        $url_components += [
            'user' => '',
            'pass' => '',
            'fragment' => '',
        ];

        // Remove leading slash from the URL path.
        if ($url_components['path'][0] === '/') {
            $url_components['path'] = substr($url_components['path'], 1);
        }

        // Use reflection to get the namespace of the class being called.
        $reflector = new \ReflectionClass(get_called_class());

        $database = [
            'driver' => $url_components['scheme'],
            'username' => $url_components['user'],
            'password' => $url_components['pass'],
            'host' => $url_components['host'],
            'database' => $url_components['path'],
            'namespace' => $reflector->getNamespaceName(),
        ];

        if (isset($url_components['port'])) {
            $database['port'] = $url_components['port'];
        }

        if (!empty($url_components['fragment'])) {
            $database['prefix']['default'] = $url_components['fragment'];
        }

        return $database;
    }

    public static function createUrlFromConnectionOptions(array $connection_options) {
        if (!isset($connection_options['driver'], $connection_options['database'])) {
            throw new \InvalidArgumentException("As a minimum, the connection options array must contain at least the 'driver' and 'database' keys");
        }

        $user = '';
        if (isset($connection_options['username'])) {
            $user = $connection_options['username'];
            if (isset($connection_options['password'])) {
                $user .= ':' . $connection_options['password'];
            }
            $user .= '@';
        }

        $host = empty($connection_options['host']) ? 'localhost' : $connection_options['host'];

        $db_url = $connection_options['driver'] . '://' . $user . $host;

        if (isset($connection_options['port'])) {
            $db_url .= ':' . $connection_options['port'];
        }

        $db_url .= '/' . $connection_options['database'];

        // Add the module when the driver is provided by a module.
        if (isset($connection_options['module'])) {
            $db_url .= '?module=' . $connection_options['module'];
        }

        if (isset($connection_options['prefix']['default']) && $connection_options['prefix']['default'] !== '') {
            $db_url .= '#' . $connection_options['prefix']['default'];
        }

        return $db_url;
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

