<?php

/**
 * AnotherServer - Gestor de Conexión Profesional a BD Externa
 * 
 * Características:
 * - Pool de conexiones para evitar saturación
 * - Conexiones persistentes para mejor rendimiento
 * - Timeout y retry automático
 * - Circuit breaker para proteger el servidor
 * - Cache de consultas frecuentes
 * - Logging de errores y métricas
 * - Singleton pattern para uso global
 * 
 * @author ImporSuitPro Team
 * @version 2.0
 */

class AnotherServer
{
    private static $instance = null;
    private $connections = [];
    private $config = [];
    private $connectionPool = [];
    private $circuitBreaker = [];
    private $cache = [];
    private $metrics = [
        'total_queries' => 0,
        'failed_queries' => 0,
        'cache_hits' => 0,
        'avg_response_time' => 0
    ];

    // Configuración por defecto
    private $defaultConfig = [
        'max_connections' => 5,          // Pool máximo de conexiones
        'connection_timeout' => 5,       // Timeout de conexión (segundos)
        'query_timeout' => 30,          // Timeout de query (segundos)
        'retry_attempts' => 3,          // Intentos de reconexión
        'retry_delay' => 1,             // Delay entre intentos (segundos)
        'cache_ttl' => 300,            // TTL del cache (5 minutos)
        'circuit_breaker_threshold' => 5, // Fallos antes de abrir circuito
        'circuit_breaker_timeout' => 60,  // Tiempo para reintentar circuito
        'enable_persistent' => true,    // Conexiones persistentes
        'enable_cache' => true,         // Cache de consultas
        'log_queries' => true,          // Log de queries
        'log_file' => __DIR__ . '/../logs/another_server.log'
    ];

    /**
     * Constructor privado (Singleton)
     */
    private function __construct()
    {
        $this->loadConfig();
        $this->initializeCircuitBreaker();
        $this->createLogDirectory();
    }

    /**
     * Obtener instancia única (Singleton)
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Configurar servidor de base de datos
     */
    public function configure(string $name, array $config): void
    {
        $this->config[$name] = array_merge($this->defaultConfig, $config);

        // Validar configuración requerida
        $required = ['host', 'database', 'username', 'password'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new InvalidArgumentException("Campo requerido '{$field}' no configurado para servidor '{$name}'");
            }
        }

        // Inicializar pool para este servidor
        $this->connectionPool[$name] = [];
        $this->circuitBreaker[$name] = [
            'failures' => 0,
            'last_failure' => 0,
            'state' => 'closed' // closed, open, half_open
        ];

        $this->log("Servidor '{$name}' configurado correctamente");
    }

    /**
     * Obtener conexión del pool
     */
    private function getConnection(string $serverName): ?mysqli
    {
        if (!isset($this->config[$serverName])) {
            throw new InvalidArgumentException("Servidor '{$serverName}' no configurado");
        }

        // Verificar circuit breaker
        if ($this->isCircuitOpen($serverName)) {
            $this->log("Circuit breaker abierto para '{$serverName}', rechazando conexión", 'WARNING');
            return null;
        }

        $config = $this->config[$serverName];

        // Buscar conexión disponible en el pool
        $connection = $this->getPooledConnection($serverName);
        if ($connection && $connection->ping()) {
            return $connection;
        }

        // Crear nueva conexión si hay espacio en el pool
        if (count($this->connectionPool[$serverName]) < $config['max_connections']) {
            return $this->createNewConnection($serverName);
        }

        // Pool lleno, esperar por conexión disponible
        $this->log("Pool de conexiones lleno para '{$serverName}', esperando...", 'WARNING');
        return $this->waitForAvailableConnection($serverName);
    }

    /**
     * Crear nueva conexión
     */
    private function createNewConnection(string $serverName): ?mysqli
    {
        $config = $this->config[$serverName];
        $startTime = microtime(true);

        try {
            // Configurar mysqli para timeouts
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            // Crear conexión (persistente si está habilitado)
            $host = $config['enable_persistent'] ? 'p:' . $config['host'] : $config['host'];
            $port = $config['port'] ?? 3306;

            $connection = new mysqli($host, $config['username'], $config['password'], $config['database'], $port);

            // Configurar opciones de conexión
            $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, $config['connection_timeout']);
            $connection->set_charset($config['charset'] ?? 'utf8mb4');

            // Configurar timeout de query
            $connection->query("SET SESSION wait_timeout = {$config['query_timeout']}");

            // Agregar al pool
            $this->connectionPool[$serverName][] = [
                'connection' => $connection,
                'created_at' => time(),
                'last_used' => time(),
                'in_use' => false
            ];

            $elapsed = (microtime(true) - $startTime) * 1000;
            $this->log("Nueva conexión creada para '{$serverName}' en {$elapsed}ms");

            // Reset circuit breaker en conexión exitosa
            $this->resetCircuitBreaker($serverName);

            return $connection;
        } catch (Exception $e) {
            $elapsed = (microtime(true) - $startTime) * 1000;
            $this->handleConnectionFailure($serverName, $e, $elapsed);
            return null;
        }
    }

    /**
     * Obtener conexión del pool
     */
    private function getPooledConnection(string $serverName): ?mysqli
    {
        if (!isset($this->connectionPool[$serverName])) {
            return null;
        }

        foreach ($this->connectionPool[$serverName] as &$poolItem) {
            if (!$poolItem['in_use']) {
                $poolItem['in_use'] = true;
                $poolItem['last_used'] = time();
                return $poolItem['connection'];
            }
        }

        return null;
    }

    /**
     * Liberar conexión al pool
     */
    private function releaseConnection(string $serverName, mysqli $connection): void
    {
        if (!isset($this->connectionPool[$serverName])) {
            return;
        }

        foreach ($this->connectionPool[$serverName] as &$poolItem) {
            if ($poolItem['connection'] === $connection) {
                $poolItem['in_use'] = false;
                $poolItem['last_used'] = time();
                break;
            }
        }
    }

    /**
     * Ejecutar query con reintentos y cache
     */
    public function query(string $serverName, string $sql, array $params = [], bool $useCache = true): ?array
    {
        $cacheKey = null;
        $startTime = microtime(true);

        // Verificar cache para SELECT queries
        if ($useCache && $this->config[$serverName]['enable_cache'] && stripos(trim($sql), 'SELECT') === 0) {
            $cacheKey = $this->getCacheKey($serverName, $sql, $params);
            $cachedResult = $this->getFromCache($cacheKey);

            if ($cachedResult !== null) {
                $this->metrics['cache_hits']++;
                $this->log("Cache hit para query en '{$serverName}': " . substr($sql, 0, 100) . "...");
                return $cachedResult;
            }
        }

        // Ejecutar query con reintentos
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->config[$serverName]['retry_attempts']) {
            $attempt++;

            $connection = $this->getConnection($serverName);
            if (!$connection) {
                $lastError = "No se pudo obtener conexión para '{$serverName}'";
                break;
            }

            try {
                $result = $this->executeQuery($connection, $sql, $params);

                // Liberar conexión
                $this->releaseConnection($serverName, $connection);

                // Guardar en cache si es SELECT
                if ($cacheKey && $result !== null) {
                    $this->saveToCache($cacheKey, $result);
                }

                // Métricas
                $elapsed = (microtime(true) - $startTime) * 1000;
                $this->updateMetrics(true, $elapsed);

                if ($this->config[$serverName]['log_queries']) {
                    $this->log("Query exitoso en '{$serverName}' (intento {$attempt}/{$this->config[$serverName]['retry_attempts']}, {$elapsed}ms): " . substr($sql, 0, 100) . "...");
                }

                return $result;
            } catch (Exception $e) {
                $this->releaseConnection($serverName, $connection);
                $lastError = $e->getMessage();

                if ($attempt < $this->config[$serverName]['retry_attempts']) {
                    $this->log("Error en query (intento {$attempt}), reintentando en {$this->config[$serverName]['retry_delay']}s: {$lastError}", 'WARNING');
                    sleep($this->config[$serverName]['retry_delay']);
                } else {
                    $this->handleConnectionFailure($serverName, $e, (microtime(true) - $startTime) * 1000);
                }
            }
        }

        // Todos los intentos fallaron
        $elapsed = (microtime(true) - $startTime) * 1000;
        $this->updateMetrics(false, $elapsed);
        $this->log("Todos los intentos de query fallaron en '{$serverName}' después de {$attempt} intentos ({$elapsed}ms): {$lastError}", 'ERROR');

        return null;
    }

    /**
     * Ejecutar query preparado
     */
    private function executeQuery(mysqli $connection, string $sql, array $params): ?array
    {
        if (empty($params)) {
            $result = $connection->query($sql);
            if ($result === false) {
                throw new Exception("Query falló: " . $connection->error);
            }
            return $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }

        // Query preparado
        $stmt = $connection->prepare($sql);
        if (!$stmt) {
            throw new Exception("Error preparando query: " . $connection->error);
        }

        if (!empty($params)) {
            $types = $this->getParamTypes($params);
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Error ejecutando query: " . $error);
        }

        $result = $stmt->get_result();
        $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $data;
    }

    /**
     * Métodos de conveniencia para operaciones comunes
     */
    public function select(string $serverName, string $sql, array $params = [], bool $useCache = true): ?array
    {
        return $this->query($serverName, $sql, $params, $useCache);
    }

    public function insert(string $serverName, string $sql, array $params = []): ?int
    {
        $this->query($serverName, $sql, $params, false);

        $connection = $this->getConnection($serverName);
        if ($connection) {
            $insertId = $connection->insert_id;
            $this->releaseConnection($serverName, $connection);
            return $insertId;
        }

        return null;
    }

    public function update(string $serverName, string $sql, array $params = []): ?int
    {
        $connection = $this->getConnection($serverName);
        if (!$connection) return null;

        try {
            $this->executeQuery($connection, $sql, $params);
            $affectedRows = $connection->affected_rows;
            $this->releaseConnection($serverName, $connection);
            return $affectedRows;
        } catch (Exception $e) {
            $this->releaseConnection($serverName, $connection);
            return null;
        }
    }

    public function delete(string $serverName, string $sql, array $params = []): ?int
    {
        return $this->update($serverName, $sql, $params);
    }

    /**
     * Verificar conectividad
     */
    public function ping(string $serverName): bool
    {
        $connection = $this->getConnection($serverName);
        if (!$connection) return false;

        $result = $connection->ping();
        $this->releaseConnection($serverName, $connection);

        return $result;
    }

    /**
     * Obtener métricas
     */
    public function getMetrics(string $serverName = null): array
    {
        $metrics = $this->metrics;

        if ($serverName) {
            $metrics['server'] = $serverName;
            $metrics['pool_size'] = count($this->connectionPool[$serverName] ?? []);
            $metrics['circuit_breaker'] = $this->circuitBreaker[$serverName] ?? null;
        } else {
            $metrics['total_servers'] = count($this->config);
            $metrics['total_connections'] = array_sum(array_map('count', $this->connectionPool));
        }

        return $metrics;
    }

    /**
     * Limpiar cache
     */
    public function clearCache(string $pattern = '*'): int
    {
        $cleared = 0;
        $now = time();

        foreach ($this->cache as $key => $item) {
            if ($pattern === '*' || fnmatch($pattern, $key)) {
                unset($this->cache[$key]);
                $cleared++;
            } elseif ($item['expires'] <= $now) {
                unset($this->cache[$key]);
                $cleared++;
            }
        }

        $this->log("Cache limpiado: {$cleared} elementos eliminados");
        return $cleared;
    }

    /**
     * Cerrar todas las conexiones
     */
    public function closeAll(): void
    {
        foreach ($this->connectionPool as $serverName => $pool) {
            foreach ($pool as $poolItem) {
                if ($poolItem['connection'] instanceof mysqli) {
                    $poolItem['connection']->close();
                }
            }
            $this->connectionPool[$serverName] = [];
        }

        $this->log("Todas las conexiones cerradas");
    }

    // =================================================
    // MÉTODOS PRIVADOS DE SOPORTE
    // =================================================

    private function loadConfig(): void
    {
        // Cargar configuración desde archivo si existe
        $configFile = __DIR__ . '/../Config/another_server_config.php';
        if (file_exists($configFile)) {
            $config = include $configFile;
            if (is_array($config)) {
                $this->defaultConfig = array_merge($this->defaultConfig, $config);
            }
        }
    }

    private function initializeCircuitBreaker(): void
    {
        $this->circuitBreaker = [];
    }

    private function createLogDirectory(): void
    {
        $logDir = dirname($this->defaultConfig['log_file']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }

    private function isCircuitOpen(string $serverName): bool
    {
        $cb = $this->circuitBreaker[$serverName];

        if ($cb['state'] === 'closed') {
            return false;
        }

        if ($cb['state'] === 'open') {
            $config = $this->config[$serverName];
            if (time() - $cb['last_failure'] > $config['circuit_breaker_timeout']) {
                $this->circuitBreaker[$serverName]['state'] = 'half_open';
                return false;
            }
            return true;
        }

        return false; // half_open permite intentos
    }

    private function handleConnectionFailure(string $serverName, Exception $e, float $elapsed): void
    {
        $this->circuitBreaker[$serverName]['failures']++;
        $this->circuitBreaker[$serverName]['last_failure'] = time();

        $config = $this->config[$serverName];
        if ($this->circuitBreaker[$serverName]['failures'] >= $config['circuit_breaker_threshold']) {
            $this->circuitBreaker[$serverName]['state'] = 'open';
            $this->log("Circuit breaker abierto para '{$serverName}' después de {$this->circuitBreaker[$serverName]['failures']} fallos", 'ERROR');
        }

        $this->log("Fallo de conexión en '{$serverName}' ({$elapsed}ms): {$e->getMessage()}", 'ERROR');
    }

    private function resetCircuitBreaker(string $serverName): void
    {
        $this->circuitBreaker[$serverName]['failures'] = 0;
        $this->circuitBreaker[$serverName]['state'] = 'closed';
    }

    private function waitForAvailableConnection(string $serverName): ?mysqli
    {
        $maxWait = 10; // máximo 10 segundos
        $waited = 0;

        while ($waited < $maxWait) {
            $connection = $this->getPooledConnection($serverName);
            if ($connection && $connection->ping()) {
                return $connection;
            }

            sleep(1);
            $waited++;
        }

        return null;
    }

    private function getCacheKey(string $serverName, string $sql, array $params): string
    {
        return "as_{$serverName}_" . md5($sql . serialize($params));
    }

    private function getFromCache(string $key): ?array
    {
        if (!isset($this->cache[$key])) {
            return null;
        }

        $item = $this->cache[$key];
        if ($item['expires'] <= time()) {
            unset($this->cache[$key]);
            return null;
        }

        return $item['data'];
    }

    private function saveToCache(string $key, array $data): void
    {
        $this->cache[$key] = [
            'data' => $data,
            'expires' => time() + $this->defaultConfig['cache_ttl']
        ];
    }

    private function getParamTypes(array $params): string
    {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    private function updateMetrics(bool $success, float $elapsed): void
    {
        $this->metrics['total_queries']++;

        if (!$success) {
            $this->metrics['failed_queries']++;
        }

        // Calcular promedio de tiempo de respuesta
        $total = $this->metrics['total_queries'];
        $current_avg = $this->metrics['avg_response_time'];
        $this->metrics['avg_response_time'] = (($current_avg * ($total - 1)) + $elapsed) / $total;
    }

    private function log(string $message, string $level = 'INFO'): void
    {
        if (!$this->defaultConfig['log_queries'] && $level === 'INFO') {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";

        file_put_contents($this->defaultConfig['log_file'], $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Destructor - limpiar recursos
     */
    public function __destruct()
    {
        $this->closeAll();
    }

    /**
     * Prevenir clonación (Singleton)
     */
    private function __clone() {}

    /**
     * Prevenir deserialización (Singleton)
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
