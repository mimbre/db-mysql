<?php
namespace mimbre\db\mysql;
use \Mysqli;
use mimbre\db\DbConnection;
use mimbre\db\DbSource;
use mimbre\db\exception\DbException;

/**
 * A MySQL connection.
 */
class MySqlConnection implements DbConnection
{
    /**
     * Database connection.
     * @var Mysqli
     */
    private $_conn;

    /**
     * Constructor.
     *
     * @param string $dbname   Database name
     * @param string $username User name (not required)
     * @param string $password Password (not required)
     * @param string $server   Server machine (default is 'localhost')
     * @param string $charset  Character set (default is 'utf8')
     */
    public function __construct(
        $dbname,
        $username = "",
        $password = "",
        $server = "localhost",
        $charset = "utf8"
    ) {
        $this->_conn = @mysqli_connect($server, $username, $password);
        if ($this->_conn === false) {
            throw new DbException("Failed to connect to the database");
        }

        @mysqli_select_db($this->_conn, $dbname);
        if ($this->_conn->errno > 0) {
            throw new DbException(
                "{$this->_conn->error} (Error no. {$this->_conn->errno})"
            );
        }

        $this->_conn->set_charset($charset);
    }

    /**
     * {@inheritdoc}
     *
     * @param string  $sql       SQL statement
     * @param mixed[] $arguments List of strings (not required)
     *
     * @return int
     */
    public function exec($sql, $arguments = [])
    {
        $result = $this->_exec($sql, $arguments);
        return $this->_conn->affected_rows;
    }

    /**
     * {@inheritdoc}
     *
     * @param string  $sql       SQL statement
     * @param mixed[] $arguments Arguments
     *
     * @return DbSource
     */
    public function query($sql, $arguments = [])
    {
        $ret = new DbSource($this, $sql, $arguments);
        return $ret;
    }

    /**
     * {@inheritdoc}
     *
     * @param string  $sql       SQL statement
     * @param mixed[] $arguments List of arguments (not required)
     *
     * @return array
     */
    public function fetchRows($sql, $arguments = [])
    {
        $ret = array();
        $result = $this->_exec($sql, $arguments);

        // fetches all rows
        while ($row = $result->fetch_array()) {
            array_push($ret, $row);
        }
        $result->close();

        return $ret;
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function close()
    {
        $this->_conn->close();
    }

    /**
     * Escapes and quotes a value.
     *
     * @param string|null $value Value
     *
     * @return string
     */
    public function quote($value)
    {
        return is_null($value)
          ? "null"
          : "'" . mysqli_real_escape_string($this->_conn, $value) . "'";
    }

    /**
     * Executes an SQL statement.
     *
     * @param string  $sql       SQL statement
     * @param mixed[] $arguments List of arguments (not required)
     *
     * @return Mysqli_result
     */
    private function _exec($sql, $arguments = array())
    {
        $sql = $this->_replaceArguments($sql, $arguments);

        // executes the statement
        $result = $this->_conn->query($sql);
        if ($this->_conn->errno > 0) {
            throw new DbException(
                "Failed to execute the statement: " .
                "({$this->_conn->errno}) {$this->_conn->error}"
            );
        }

        return $result;
    }

    /**
     * Replaces arguments in an SQL statement.
     *
     * @param string  $sql       SQL statement
     * @param mixed[] $arguments List of arguments
     *
     * @return string
     */
    private function _replaceArguments($sql, $arguments)
    {
        // searches string segments (startPos, endPos)
        $stringSegments = [];
        $matches = [];
        $searchArgs = preg_match_all(
          '/(["\'`])((?:\\\\\1|.)*?)\1/', $sql, $matches, PREG_OFFSET_CAPTURE
        );
        if ($searchArgs) {
            foreach ($matches[2] as $match) {
                $startPos = $match[1];
                $endPos = $startPos + strlen($match[0]);
                array_push($stringSegments, [$startPos, $endPos]);
            }
        }

        // searches arguments position
        $argsPos = [];
        preg_match_all('/\?/', $sql, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $match) {
            array_push($argsPos, $match[1]);
        }

        // replaces arguments
        $matchCount = 0;
        $argCount = 0;
        return preg_replace_callback(
            '/\?/',
            function ($matches) use (
                &$argCount, &$matchCount, $arguments, $argsPos, $stringSegments
                ) {
                $ret = $matches[0];

                if ($argCount < count($arguments)) {
                    // is the current match inside a quoted string?
                    $argPos = $argsPos[$matchCount];
                    $isInsideQuotedString = false;
                    foreach ($stringSegments as $segment) {
                        if ($argPos >= $segment[0] &&  $argPos < $segment[1]) {
                            $isInsideQuotedString = true;
                            break;
                        }
                    }

                    if (!$isInsideQuotedString) {
                        $ret = $this->quote($arguments[$argCount++]);
                    }
                }

                $matchCount++;
                return $ret;
            },
            $sql
        );
    }
}
