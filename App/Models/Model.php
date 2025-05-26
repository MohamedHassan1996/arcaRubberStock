<?php 

//require_once __DIR__ . '/../../vendor/autoload.php';

namespace App\Models;

use Config\DbConnection;
use Exception;
use PDO;
use PDOException; 

class Model {
    protected static $table;
    protected static $primary_key = 'id';
    protected static $fillable = [];
    protected $exists = false;
    protected $attributes = [];

    // Query builder state
    protected static array $wheres = [];

    public function __construct(array $attributes = []) {
        $this->fill($attributes);
    }

    protected static function getTable(): string {
        if (isset(static::$table)) {
            return static::$table;
        }

        $className = get_called_class();
        $shortClass = substr(strrchr($className, '\\'), 1);
        $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortClass)) . 's';

        return $table;
    }

    public static function all(): array {
        $conn = DbConnection::connection();
        $stmt = $conn->prepare("SELECT * FROM " . static::getTable());
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id) {
        $conn = DbConnection::connection();
        $stmt = $conn->prepare("SELECT * FROM " . static::getTable() . " WHERE " . static::$primary_key . " = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $class = get_called_class();
            $object = new $class($result);
            $object->exists = true;
            return $object;
        }

        return null;
    }

    public static function create(array $props) {
        $object = new static($props);
        $object->save();
        return $object;
    }

    public function save() {
        $conn = DbConnection::connection();

        if ($this->exists) {
            $columns = array_keys($this->attributes);
            $values = array_values($this->attributes);
            $set = implode(' = ?, ', $columns) . ' = ?';

            $sql = "UPDATE " . static::getTable() . " SET $set WHERE " . static::$primary_key . " = ?";
            $stmt = $conn->prepare($sql);
            $values[] = $this->attributes[static::$primary_key];
            $stmt->execute($values);
        } else {
            $columns = array_keys($this->attributes);
            $values = array_values($this->attributes);
            $placeholders = array_fill(0, count($values), '?');

            $sql = "INSERT INTO " . static::getTable() . " (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $placeholders) . ")";
            $stmt = $conn->prepare($sql);
            $stmt->execute($values);
            $this->attributes[static::$primary_key] = $conn->lastInsertId();
            $this->exists = true;
        }
    }

    public function fill(array $attributes) {
        $this->attributes[static::$primary_key] = $attributes[static::$primary_key] ?? null;
        foreach ($attributes as $key => $value) {
            if (in_array($key, static::$fillable)) {
                $this->attributes[$key] = $value;
            }
        }
    }

    public function __set($name, $value) {
        if ($name === static::$primary_key) {
            throw new Exception(static::$primary_key . ' cannot be changed.');
        }
        if (in_array($name, static::$fillable)) {
            $this->attributes[$name] = $value;
        }
    }

    public function __get($name) {
        $method = 'get' . ucfirst($name) . 'Attribute';
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return $this->attributes[$name] ?? null;
    }

    public function toArray() {
        return $this->attributes;
    }

    public static function where(string $column, $value): static {
        static::$wheres[] = ['column' => $column, 'value' => $value];
        return new static;
    }

    protected static function resetWheres(): void {
        static::$wheres = [];
    }

    public function get(): array {
        $conn = DbConnection::connection();
        $sql = "SELECT * FROM " . static::getTable();
        $params = [];

        if (!empty(static::$wheres)) {
            $conditions = [];
            foreach (static::$wheres as $i => $where) {
                $placeholder = ":param{$i}";
                $conditions[] = "{$where['column']} = $placeholder";
                $params[$placeholder] = $where['value'];
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        static::resetWheres();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function first(): ?static {
        $conn = DbConnection::connection();
        $sql = "SELECT * FROM " . static::getTable();
        $params = [];

        if (!empty(static::$wheres)) {
            $conditions = [];
            foreach (static::$wheres as $i => $where) {
                $placeholder = ":param{$i}";
                $conditions[] = "{$where['column']} = $placeholder";
                $params[$placeholder] = $where['value'];
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        static::resetWheres();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $class = get_called_class();
            $object = new $class($result);
            $object->exists = true;
            return $object;
        }

        return null;
    }

    public static function createOrUpdate(array $conditions, array $data)
    {
        $conn = DbConnection::connection();
        if ($conn === null) {
            throw new Exception('Database connection failed');
        }

        $whereClauses = [];
        $whereValues = [];

        foreach ($conditions as $key => $value) {
            $whereClauses[] = "$key = ?";
            $whereValues[] = $value;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $stmt = $conn->prepare("SELECT * FROM " . static::getTable() . " WHERE $whereSql LIMIT 1");
        $stmt->execute($whereValues);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Record exists, update
            $class = get_called_class();
            $object = new $class($existing);
            $object->exists = true;

            // Update the object with new data
            $object->fill($data);
            $object->save();
            return $object;
        } else {
            // Record doesn't exist, create
            return static::create(array_merge($conditions, $data));
        }
    }

} 
