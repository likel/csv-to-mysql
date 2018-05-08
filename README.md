# csv-to-mysql
Using PHP. Parse a CSV file, make an attempt at the column type and max length, and INSERT a MySQL table.

## Usage
### Through the CLI
```
$ php [src/csv_to_mysql.php](src/csv_to_mysql.php) [options]

options:
  -f, --csvfile            The path the CSV file you wish to insert
  -h, --dbhost             Database host
  -u, --dbusername         Database username
  -p, --dbpassword         Database password
  -d, --dbname             Database name
  -t, --mysqltablename     The table name for the newly inserted table
  -i, --help               Display these help commands
```

### Running from HTTP
Set the $OPTIONS values in the csv_to_mysql.php script and run it from your browser.

## Author

**Liam Kelly** - [likel](https://github.com/likel)

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details
