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
    "csvfile" => "/var/www/html/public_html/csv-to-mysql/src/candidates.csv",
    "dbhost" => "",
    "dbusername" => "",
    "dbpassword" => "",
    "dbname" => "",
    "mysqltablename" => ""
);

$CTM = new CsvToMysql($OPTIONS);

if($CTM->verifyOptions()) {

}

// Display any errors encountered
echo $CTM->outputErrors();

/**
 * This simple class calculates the chance of winning Frustration Solitaire given two values,
 */
class CsvToMysql
{
    /** @var array The options that control the program */
    private $options = array();

    /** @var array Keep a record of errors and display them at the end of the script */
    private $error_lines = array();

    /**
     * Construct the Frustration
     * Set the number_of_cards and number_of_suits
     * @param int $number_of_cards How many different cards are in the deck
     * @param int $number_of_suits How many different suits are in the deck
     * @return void
     */
    function __construct($options = array())
    {
        $this->options = $this->generateOptions($options);
    }

    /**
     * Generate the options either from the command line or from the PHP file
     * @param array $options Options passed through the constructor
     * @return array
     */
    private function generateOptions($options = array())
    {
        $short_options = "";
        $long_options = array("csvfile:", "dbhost:", "dbusername:", "dbpassword:", "dbname:", "mysqltablename:");
        $cli_options = getopt($short_options, $long_options);

        return array(
            "csvfile" => !empty($cli_options['csvfile']) ? $cli_options['csvfile'] : (!empty($options['csvfile']) ? $options['csvfile'] : false),
            "dbhost" => !empty($cli_options['dbhost']) ? $cli_options['dbhost'] : (!empty($options['dbhost']) ? $options['dbhost'] : false),
            "dbusername" => !empty($cli_options['dbusername']) ? $cli_options['dbusername'] : (!empty($options['dbusername']) ? $options['dbusername'] : false),
            "dbpassword" => !empty($cli_options['dbpassword']) ? $cli_options['dbpassword'] : (!empty($options['dbpassword']) ? $options['dbpassword'] : false),
            "dbname" => !empty($cli_options['dbname']) ? $cli_options['dbname'] : (!empty($options['dbname']) ? $options['dbname'] : false),
            "mysqltablename" => !empty($cli_options['mysqltablename']) ? $cli_options['mysqltablename'] : (!empty($options['mysqltablename']) ? $options['mysqltablename'] : false)
        );
    }

    public function verifyOptions()
    {
        $verified = true;
        foreach($this->options as $key => $an_option) {
            if(empty($an_option)) {
                $this->error_lines[] = "The {$key} option is empty";
                $verified = false;
            }
        }
        return $verified;
    }

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
}
