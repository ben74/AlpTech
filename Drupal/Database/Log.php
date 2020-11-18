<?php
namespace Drupal\Core\Database;
class Log {
    static function coucou(){return __METHOD__;}
  protected $queryLog = [];
  protected $connectionKey = 'default';
  public function __construct($key = 'default') {
    $this->connectionKey = $key;
  }
  public function start($logging_key) {
    if (empty($this->queryLog[$logging_key])) {
      $this->clear($logging_key);
    }
  }
  public function get($logging_key) {
    return $this->queryLog[$logging_key];
  }
  public function clear($logging_key) {
    $this->queryLog[$logging_key] = [];
  }
  public function end($logging_key) {
    unset($this->queryLog[$logging_key]);
  }
#webprofiler
  public function log(/*StatementInterface */$statement, $args, $time) {
    foreach (array_keys($this->queryLog) as $key) {
      $this->queryLog[$key][] = [
        'query' => $statement->getQueryString(),
        'args' => $args,
        'target' => $statement->dbh->getTarget(),
        'caller' => $this->findCaller(),
        'time' => $time,
      ];
    }
    $a=1;
  }

  public function findCaller() {
    $stack = $this->getDebugBacktrace(-2);
    $driver_namespace = Database::getConnectionInfo($this->connectionKey)['default']['namespace'];
    // Starting from the very first entry processed during the request, find
    // the first function call that can be identified as a call to a
    // method/function in the database layer.
    for ($n = count($stack) - 1; $n >= 0; $n--) {
      // If the call was made from a function, 'class' will be empty. We give
      // it a default empty string value in that case.
      $class = $stack[$n]['class'] ?? '';

      if (strpos($class, __NAMESPACE__, 0) === 0 || strpos($class, $driver_namespace, 0) === 0) {
        break;
      }
    }

    // Return the previous function call whose stack entry has a 'file' key,
    // that is, it is not a callback or a closure.
    for ($i = $n; $i < count($stack); $i++) {
      if (!empty($stack[$i]['file'])) {
        return [
          'file' => $stack[$i]['file'],
          'line' => $stack[$i]['line'],
          'function' => $stack[$i + 1]['function'],
          'class' => $stack[$i + 1]['class'] ?? NULL,
          'type' => $stack[$i + 1]['type'] ?? NULL,
          'args' => $stack[$i + 1]['args'] ?? [],
        ];
      }
    }
  }
  protected function getDebugBacktrace() {
    return debug_backtrace(-2);
  }
}
