<?php
namespace Etapdmf\PhpFmtVending;

use Etapdmf\PhpFmtVending\lib\PhpSerial;
use Etapdmf\PhpFmtVending\lib\Helper;
use Exception;

class Controller
{
    protected $serial;
    protected $config;
    protected $command;
    protected $constant;
    protected $env_path;

    /**
     * Constructor for the FMTVending class.
     *
     * Initializes the FMTVending object and sets up necessary configurations.
     * Add any specific setup details or actions here.
     */
    public function __construct()
    {
        $this->config = include dirname(__DIR__) . "/src/config/app.php";
        $this->command = include dirname(__DIR__) . "/src/config/commands.php";
        $this->constant = include dirname(__DIR__) . "/src/config/constant.php";
        $this->env_path = dirname(__DIR__, 4) . '/.env';
    }

    /**
     * Initializes the serial device for communication.
     *
     *
     * @throws \Exception If the serial device cannot be set or opened.
     */
    protected function initialize()
    {
        try {
            $this->serial = new PhpSerial();

            // First we must specify the device. This works on both linux and windows (if
            // your linux serial device is /dev/ttyS0 for COM1, etc)
            $this->serial->deviceSet(
                $this->env('FMT_VENDING_PORT') ?? $this->config['fmt']['device']
            );

            // We can change the baud rate, parity, length, stop bits, flow control
            $this->serial->confBaudRate(
                $this->env('FMT_VENDING_BAUDRATE') ?? $this->config['fmt']['baudrate']
            );
            $this->serial->confParity(
                $this->env('FMT_VENDING_PARITY') ?? $this->config['fmt']['parity']
            );
            $this->serial->confCharacterLength(
                $this->env('FMT_VENDING_CHAR_LENGTH') ?? $this->config['fmt']['character_length']
            );
            $this->serial->confStopBits(
                $this->env('FMT_VENDING_STOP_BITS') ?? $this->config['fmt']['stop_bits']
            );
            $this->serial->confFlowControl(
                $this->env('FMT_VENDING_FLOW_CONTROL') ?? $this->config['fmt']['flow_control']
            );

            // Then we need to open it
            $this->serial->deviceOpen('w+');
        } catch (\Exception $err) {
            $this->log($err);
        }
    }

    /**
     * Closes the serial device connection.
     *
     *
     * @return void
     */
    protected function close()
    {
        try {
            if ($this->serial->deviceClose()) {
                $this->log("Device is closed");
                return;
            }

            $this->log("Device is not closed");
        } catch (\Exception $err) {
            $this->log($err);
        }
    }

    /**
     * Sends a command request to the serial device.
     *
     *
     * @param string $cmd The hexadecimal command to be sent to the device.
     *                    Defaults to an empty string if no command is provided.
     *
     * @return void
     */
    public function sendRequest($cmd = '')
    {
        try {
            $this->initialize();

            $message = preg_replace('/ /', "", $this->command[$cmd]);
            $this->log($message);
            $message = pack("H*", $message);
            $this->serial->sendMessage($message);
            sleep(5);

            $response = $this->serial->readPort();

            if (empty($response)) {
                $log = "No data recieved";
                $this->log($log);
                $this->close();
                return [
                    'status' => false,
                    'error' => $log
                ];
            }

            $hexResponse = (string) join("", unpack("H*", $response));

            $log = "Data has been recieved.";
            $this->close();

            $helper = new Helper();

            return [
                'status' => true,
                'data' => [
                    'log' => $log,
                    'hex' => $hexResponse,
                    'response' => $this->rxResponse($hexResponse),
                    'metadata' => $helper->readResponse($cmd, $this->rxResponse($hexResponse))
                ]
            ];
        } catch (\Exception $err) {
            $this->log($err->getMessage());
            return [
                'status' => false,
                'error' => $err->getMessage()
            ];
        }
    }

    /**
     * Dispenses an item based on the given row number.
     *
     *
     * @param int $row_num The row number representing the item to be dispensed. Defaults to 0.
     *
     * @return bool Returns `false` if the row number is invalid, otherwise proceeds with dispensing.
     */
    public function dispense($row_num = 1)
    {
        try {
            $dispense_cmds = [
                '1' => "PRODUCT_DISPEN_PRODUCT_ID_00_DISPEN_MODE_2",
                '2' => "PRODUCT_DISPEN_PRODUCT_ID_01_DISPEN_MODE_2",
                '3' => "PRODUCT_DISPEN_PRODUCT_ID_02_DISPEN_MODE_2",
                '4' => "PRODUCT_DISPEN_PRODUCT_ID_03_DISPEN_MODE_2",
                '5' => "PRODUCT_DISPEN_PRODUCT_ID_04_DISPEN_MODE_2"
            ];
            
            $cmd = $dispense_cmds[$row_num];
            $res = $this->sendRequest($cmd);

            if ($res['status'] == false) {
                throw new \Exception($res['error'] ?? 'Error occured upon dispense');
            }

            return [
                'status' => true,
                'data' => $res
            ];
        } catch (\Exception $err) {
            return [
                'status' => false,
                'error' => $err->getMessage()
            ];
            $this->log($err->getMessage());
        }
    }

    /**
     * Logs a message with a timestamp to a log file.
     *
     *
     * @param string|array $msg The message or array of messages to log.
     *
     * @return void
     */
    public function log($msg)
    {
        $msg = (is_array($msg)) ? implode(' ', $msg) : $msg;
        $message = date('Y-m-d H:i:s') . ': ' . $msg;

        $logDir = dirname(__DIR__, 4) . '/storage/logs';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFilePath = $logDir . '/fmt-vending-' . date('Y-m-d') . '.log';

        file_put_contents($logFilePath, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Retrieves the value of a specified environment variable from a .env file.
     *
     *
     * @param string $keyword The environment variable name to search for.
     * @return string|null The value of the environment variable, or null if not found or on error.
     */
    protected function env($keyword)
    {
        try {
            $fileHandle = fopen($this->env_path, 'r');

            if ($fileHandle) {
                while (($line = fgets($fileHandle)) !== false) {
                    if (strpos($line, $keyword) === 0) {
                        $parts = explode('=', $line, 2);
                        if (count($parts) === 2) {
                            $value = trim($parts[1]);
                            fclose($fileHandle);
                            return $value;
                        }
                    }
                }
                fclose($fileHandle);
            }

            return null;
        } catch (\Exception $err) {
            $this->log($err);
            return null;
        }
    }

    protected function rxResponse($hex)
    {
        // Split the hex string into chunks of 2 characters each (1 byte)
        $bytes = str_split($hex, 2);

        // Default
        $rx = $bytes[0];

        if (count($bytes) > 1) {
            // Grab bytes 1 to 6
            $constant = join('', array_slice($bytes, 1, 6));

            // Split the hex string based on the constant
            $hex_arr = explode($constant, $hex);

            // Join the result for the second part
            $hex_spec = $bytes[0] . $constant . end($hex_arr);
            $rx = strtoupper(join(' ', str_split($hex_spec, 2)));
        }

        return $rx;
    }
}
