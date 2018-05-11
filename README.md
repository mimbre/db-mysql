# MySQL connection.

This library contains the `MySqlConnection` class, used to connect to a MySQL database.

## Install

This library uses the [Composer package manager](https://getcomposer.org/). Simply execute the following command from a terminal:

```bash
composer require mimbre\db-mysql
```

## Example

```php
require_once "path/to/vendor/autoload.php";
use mimbre\db\mysql\MySqlConnection;

$db = new MySqlConnection("test", "root", "chum11145");
```

## Developer notes

```bash
# verifies that the source code conforms the current coding standards
phpcs --standard=phpcs.xml src
```
