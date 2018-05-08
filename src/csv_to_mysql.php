<?php
/**
 * Parse a CSV file, make an attempt at the column type and max length, and INSERT a MySQL table.
 *
 * @package     csv-to-mysql
 * @author      Liam Kelly <https://github.com/likel>
 * @copyright   2018 Liam Kelly
 * @license     MIT License <https://github.com/likel/csv-to-mysql/blob/master/LICENSE>
 * @link        https://github.com/likel/csv-to-mysql
 * @version     1.0.0
 */
namespace Likel;

/** @var array You must set these options if you aren't planning to use the CLI */
$OPTIONS = array(
    "csvfile" => "",
    "dbhost" => "",
    "dbusername" => "",
    "dbpassword" => "",
    "dbname" => "",
    "mysqltablename" => ""
);

// Setup the class with the options
$CTM = new CSVToMySQL($OPTIONS);

// Convert the CSV and insert into a MySQL table, echo the result
echo $CTM->insert();

// Display any errors encountered
echo $CTM->outputErrors();

/**
 * This class helps us convert a CSV into a smart MySQL table
 * This is the model, the controller is found above
 */
class CSVToMySQL
{
    /** @var array The options that control the program */
    private $options = array();

    /** @var array Keep a record of errors and display them at the end of the script */
    private $error_lines = array();

    /** @var array The rows of the CSV file */
    private $csv_rows = array();

    /** @var array The header row and column information of the CSV file */
    private $csv_columns = array();

    /** @var int Buffer for the max length column */
    private $COLUMN_BUFFER = 3;

    /**
     * Construct the CSVToMySQL class
     * @return void
     */
    function __construct($options = array())
    {
        $this->options = $this->generateOptions($options);

        if(isset($this->options['help'])) {
            if($this->options['help'] == "1") {
                echo $this->outputHelp();
                die();
            }
        }
    }

    /**
     * The main CTM function that helps to transpose the CSV and insert the table
     * @return bool
     */
    public function insert()
    {
        if($this->verifyOptions()) {
            if($this->populateRows()) {
                if($this->createTable()){
                    if($this->insertRows()) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Create the actual MySQL table in preparation of inserts
     * @return bool
     */
    private function createTable()
    {
        $DB = new DB($this->options);

        $create_string = array();
        foreach($this->csv_columns as $key => $value) {
            $create_string[] = "$key " . strtoupper($value['type']) . "({$value['length']})";
        }
        $new_string = join(', ', $create_string);

        $DB->query("
            CREATE TABLE {$this->options['mysqltablename']} (
                $new_string
            )
        ");

        try {
            $DB->execute();
            return true;
        } catch (\Exception $ex) {
            $this->error_lines[] = $ex->getMessage();
            return false;
        }
    }

    /**
     * Insert the rows into the already created table
     * @return bool
     */
    private function insertRows()
    {
        $DB = new DB($this->options);

        $query_part_one = implode(", ", array_keys($this->csv_columns));
        $query_part_two = array();

        foreach($this->csv_rows as $key => $row) {
            $new = array();
            foreach($row as $key2 => $cell) {
        		$new[] = ":{$key}_$key2";
            }
            $query_part_two[] = "(" . join(",", $new) . ")";
        }

        $query_part_two_joined = join(", ", $query_part_two);

	$DB->query("
            INSERT INTO {$this->options['mysqltablename']} ({$query_part_one})
            VALUES {$query_part_two_joined}
        ");

	foreach($this->csv_rows as $key => $row) {
            foreach($row as $key2 => $cell) {
                $DB->bind(":{$key}_$key2", $cell);
            }
	}

        try {
            $DB->execute();
            return true;
        } catch (\Exception $ex) {
            $this->error_lines[] = $ex->getMessage();
            return false;
        }
    }

    /**
     * Populate the rows from the supplied CSV
     * @return bool
     */
    private function populateRows()
    {
        if (($handle = fopen($this->options["csvfile"], "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 100000, ",")) !== FALSE) {
                if(empty($this->csv_rows) && empty($header_row)) {
                    $header_row = $this->cleanseHeaderRow($data);
                } else {
                    $this->csv_rows[] = $data;
                }
            }
            fclose($handle);

            if(empty($header_row)) {
                $this->error_lines[] = "CSV column header is empty";
                return false;
            } else {
                $this->calculateColumnPreferences($header_row);
                return true;
            }
        } else {
            $this->error_lines[] = "Could not find csvfile";
        }
    }

    /**
     * Calculate the column length and type from the supplied header row
     * @param array $header_row The first row which acts as the header
     * @return void
     */
    private function calculateColumnPreferences($header_row)
    {
        $max_lengths = array();
        $types = array();

        foreach($this->csv_rows as $a_row){
            foreach($a_row as $key => $value){
                $the_key = $header_row[$key];
                if(empty($max_lengths[$the_key])) {
                    $max_lengths[$the_key] = strlen($value) + $this->COLUMN_BUFFER;
                } else {
                    if($max_lengths[$the_key] < strlen($value) + $this->COLUMN_BUFFER) {
                        $max_lengths[$the_key] = strlen($value) + $this->COLUMN_BUFFER;
                    }
                }

                if(is_numeric($value)) {
                    if(empty($types[$the_key])) {
                        $types[$the_key] = 'int';
                    } else {
                        if($types[$the_key] != "varchar") {
                            $types[$the_key] = 'int';
                        }
                    }
                } else {
                    $types[$the_key] = 'varchar';
                }
            }
        }

        foreach($header_row as $key => $column) {
            $this->csv_columns[$column] = array(
                "type" => $types[$column],
                "length" => $max_lengths[$column]
            );
        }
    }

    /**
     * Cleanse the dirty header row to ensure no symbols and spaces
     * @param array $header_row The first row which acts as the header
     * @return void
     */
    private function cleanseHeaderRow($header_row)
    {
        $new_header_row = array();

        foreach($header_row as $key => $a_row){
            $new_header_row[$key] = strtolower(str_replace(" ", "_", preg_replace("/[^ \w]+/", "_", trim($a_row))));
        }

        return $new_header_row;
    }

    /**
     * Generate the options either from the command line or from the PHP file
     * @param array $options Options passed through the constructor
     * @return array
     */
    private function generateOptions($options = array())
    {
        $short_options = "f:h:u:p:d:t:i::";
        $long_options = array("csvfile:", "dbhost:", "dbusername:", "dbpassword:", "dbname:", "mysqltablename:", "help");
        $cli_options = getopt($short_options, $long_options);

        return array(
            "csvfile" => !empty($cli_options['csvfile']) ? $cli_options['csvfile'] : (!empty($cli_options['f']) ? $cli_options['f'] : (!empty($options['csvfile']) ? $options['csvfile'] : false)),
            "dbhost" => !empty($cli_options['dbhost']) ? $cli_options['dbhost'] : (!empty($cli_options['h']) ? $cli_options['h'] : (!empty($options['dbhost']) ? $options['dbhost'] : false)),
            "dbusername" => !empty($cli_options['dbusername']) ? $cli_options['dbusername'] : (!empty($cli_options['u']) ? $cli_options['u'] : (!empty($options['dbusername']) ? $options['dbusername'] : false)),
            "dbpassword" => !empty($cli_options['dbpassword']) ? $cli_options['dbpassword'] : (!empty($cli_options['p']) ? $cli_options['p'] : (!empty($options['dbpassword']) ? $options['dbpassword'] : false)),
            "dbname" => !empty($cli_options['dbname']) ? $cli_options['dbname'] : (!empty($cli_options['d']) ? $cli_options['d'] : (!empty($options['dbname']) ? $options['dbname'] : false)),
            "mysqltablename" => !empty($cli_options['mysqltablename']) ? $cli_options['mysqltablename'] : (!empty($cli_options['t']) ? $cli_options['t'] : (!empty($options['mysqltablename']) ? $options['mysqltablename'] : false)),
            "help" => isset($cli_options['help']) ? "1" : (isset($cli_options['i']) ? "1" : "0")
        );
    }

    /**
     * Verify that none of the options have been left out
     * @return bool
     */
    private function verifyOptions()
    {
        $verified = true;
        foreach($this->options as $key => $an_option) {
            if(empty($an_option) && $key != "help") {
                $this->error_lines[] = "The {$key} option is empty";
                $verified = false;
            }
        }
        return $verified;
    }

    /**
     * Output any errors that have been logged
     * @return string
     */
    public function outputErrors()
    {
        $error_string = "";
        $error_string .= (php_sapi_name() === 'cli') ? "\033[1;31m" : "";
        $error_string .= "------------------------------------------------------\n" .
        "There were errors while running the script as below:\n" .
        "------------------------------------------------------\n";

        foreach($this->error_lines as $key => $an_error) {
            if(!empty($an_error)) {
                $error_string .= "[$key]: " . $an_error . "\n";
            }
        }

        $error_string .= (php_sapi_name() === 'cli') ? "\033[0m" : "";

        return empty($this->error_lines) ? "" : $error_string;
    }

    /**
     * Output help functions to the CLI
     * @return string
     */
    public function outputHelp()
    {
        $help_string = "";
        $help_string .= (php_sapi_name() === 'cli') ? "\033[0;32m" : "";
        $help_string .= "------------------------------------------------------\n" .
        "usage: php csvtomysql.php [options]\n\n" .
        "options:\n" .
        "-f, --csvfile            The path the CSV file you wish to insert\n" .
        "-h, --dbhost             Database host\n" .
        "-u, --dbusername         Database username\n" .
        "-p, --dbpassword         Database password\n" .
        "-d, --dbname             Database name\n" .
        "-t, --mysqltablename     The table name for the newly inserted table\n" .
        "-i, --help               Display these help commands\n" .
        "------------------------------------------------------\n";

        $help_string .= (php_sapi_name() === 'cli') ? "\033[0m" : "";

        return $help_string;
    }
}

/**
 * The database object which helps to abstract database functions
 * Uses and requires PDO, generally available after PHP 5.1
 */
class DB
{
    private $database_handler; // Stores the database connection
    private $statement; // The MySQL query with prepared values

    /**
     * Construct the database object
     * @param array $database_credentials The options array
     * @return void
     */
    public function __construct($database_credentials)
    {
        try {
            $this->database_handler = $this->loadDatabase($database_credentials);
        } catch (\Exception $ex) {
            echo $ex->getMessage();
        }
    }

    /**
     * Attempt to connect to the database
     * @param array $database_credentials The options array
     * @return mixed
     * @throws \Exception If credentials empty or not found
     */
    private function loadDatabase($database_credentials)
    {
        if(!empty($database_credentials)){
            try {
                $dsn = 'mysql:host=' . $database_credentials['dbhost'] . ';dbname=' . $database_credentials['dbname'];

                $options = array(
                    \PDO::ATTR_PERSISTENT    => true,
                    \PDO::ATTR_ERRMODE       => \PDO::ERRMODE_EXCEPTION
                );

                $pdo_object = new \PDO($dsn, $database_credentials['dbusername'], $database_credentials['dbpassword'], $options);

                return $pdo_object;
            } catch(\PDOException $e) {
                throw new \Exception($e->getMessage());
            }
        }
    }

    /**
     * Prepare the query from a supplied query string
     * @param string $query The prepared query
     * @return void
     */
    public function query($query)
    {
        $this->statement = $this->database_handler->prepare($query);
    }

    /**
     * Bind properties to the statement
     * E.G. $DB->bind(':fname', 'Liam');
     * @param string $param The parameter to replace
     * @param mixed $value The value replacement
     * @param mixed $type Force the PDO::PARAM type
     * @return void
     */
    public function bind($param, $value, $type = null)
    {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = \PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = \PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = \PDO::PARAM_NULL;
                    break;
                default:
                    $type = \PDO::PARAM_STR;
            }
        }

        $this->statement->bindValue($param, $value, $type);
    }

    /**
     * Execute the statement
     * Use result()/results() for insert queries
     * @return bool
     */
    public function execute()
    {
        return $this->statement->execute();
    }

    /**
     * Return multiple rows
     * @return array
     */
    public function results()
    {
        $this->execute();
        return $this->statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Return a single row
     * @return array
     */
    public function result()
    {
        $this->execute();
        return $this->statement->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Return the row count
     * @return int
     */
    public function rowCount()
    {
        return $this->statement->rowCount();
    }

    /**
     * Return if rows exists
     * @return bool
     */
    public function rowsExist()
    {
        return $this->rowCount() != 0;
    }

    /**
     * Return the id of the last inserted row
     * @return mixed
     */
    public function lastInsertId()
    {
        return $this->database_handler->lastInsertId();
    }

    /**
     * Return the table name with prefix
     * @param string $table_name The table name that's accessed
     * @return string
     */
    public function getTableName($table_name)
    {
        return $table_name;
    }

    /**
     * Dump the statement's current parameters
     * @return void
     */
    public function dumpStatement()
    {
        $this->statement->debugDumpParams();
    }

    /**
     * Return if the database has been initialised
     * @return bool
     */
    public function databaseInitialised()
    {
        return !empty($this->database_handler);
    }
}
