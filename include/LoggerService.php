<?php

/**
 * Controla la escritura de excepciones, errores, información, etc.
 * en un archivo de texto con el rol de "log"
 * 
 * @author drendon
 * @date 07.05.2026
 */

declare(strict_types=1);

date_default_timezone_set('America/Mexico_City');

use Throwable;

class LoggerService
{
  private array $arrAppConfig;

  private string $logDirectory;

  public function __construct()
  {
    # 1. Obtiene el directorio de logs desde la configuración de la aplicación
    $this->arrAppConfig = require __DIR__ . "/appconfig.php";
    $this->logDirectory = $this->arrAppConfig["LOG_DIRECTORY"];

    # 2. Verifica que el directorio existe, si no, lo crea con permisos 0755
    if (!is_dir($this->logDirectory)) {
      mkdir($this->logDirectory, 0755, true);
    }
  }

  /**
   * Genera la ruta completa del archivo basado en la fecha actual
   */
  private function getDailyFilePath(): string
  {
    $date = date('Y-m-d');
    return $this->logDirectory . "log_$date.log";
  }


  /**
   * Extrae el origen de la llamada o de la excepción
   */
  private function getDetailedOrigin(array &$context): array
  {
    // 1. Si hay una excepción en el contexto, extraemos su origen real
    if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
      $e = $context['exception'];

      // Extraemos la traza de la excepción para saber qué método falló
      $trace = $e->getTrace()[0] ?? [];

      $origin = [
        'file'     => basename($e->getFile()),
        'line'     => $e->getLine(),
        'class'    => $trace['class'] ?? 'N/A',
        'function' => $trace['function'] ?? 'N/A',
        'type'     => 'EXCEPTION_ORIGIN'
      ];

      // Limpiamos el objeto exception para no saturar el JSON del log, 
      // pero guardamos el mensaje.
      $context['error_detail'] = $e->getMessage();
      unset($context['exception']);

      return $origin;
    }

    // 2. Si no hay excepción, usamos el backtrace normal (para logs info/debug)
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
    $caller = $trace[2] ?? [];
    $fileCaller = $trace[1] ?? [];

    return [
      'file'     => basename($fileCaller['file'] ?? 'unknown'),
      'line'     => $fileCaller['line'] ?? 0,
      'class'    => $caller['class'] ?? 'Global',
      'function' => $caller['function'] ?? 'main',
      'type'     => 'CALL_POINT'
    ];
  }


  /**
   * Método principal para escribir en el log
   *
   * @param string $level
   * @param string $message
   * @param array $context
   * @return void
   */
  private function writeLog(string $level, string $message, array $context = []): void
  {
    $timestamp = date('Y-m-d H:i:s');
    $origin = $this->getDetailedOrigin($context);

    $location = sprintf(
      "%s [%s::%s en %s:%s]",
      $origin['type'],
      $origin['class'],
      $origin['function'],
      $origin['file'],
      $origin['line']
    );

    $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : '';

    $formattedMessage = sprintf(
      "[%s] [%s] %s: %s %s" . PHP_EOL,
      $timestamp,
      strtoupper($level),
      $location,
      $message,
      $contextJson
    );

    // Obtenemos la ruta dinámica (cambia cada día)
    $filePath = $this->getDailyFilePath();

    // FILE_APPEND: agrega al final del archivo sin borrar lo existente
    // LOCK_EX: Evita que otro proceso escriba al mismo tiempo
    file_put_contents($filePath, $formattedMessage, FILE_APPEND | LOCK_EX);
  }

  public function logInfo(string $message, array $context = []): void
  {
    $this->writeLog('INFO', $message, $context);
  }

  public function logError(string $message, array $context = []): void
  {
    $this->writeLog('ERROR', $message, $context);
  }

  public function logWarning(string $message, array $context = []): void
  {
    $this->writeLog('WARNING', $message, $context);
  }

  public function logDebug(string $message, array $context = []): void
  {
    $this->writeLog('DEBUG', $message, $context);
  }
}
