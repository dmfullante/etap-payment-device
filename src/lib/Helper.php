<?php
namespace Etapdmf\PhpFmtVending\lib;

class Helper
{
    public function readResponse($cmd, $response = '')
    {
        switch ($cmd) {
            case 'GET_MACHINE_STATUS':
                return $this->readMachineStatus($response);
            case 'PRODUCT_DISPEN_PRODUCT_ID_00_DISPEN_MODE_2':
            case 'PRODUCT_DISPEN_PRODUCT_ID_01_DISPEN_MODE_2':
            case 'PRODUCT_DISPEN_PRODUCT_ID_02_DISPEN_MODE_2':
            case 'PRODUCT_DISPEN_PRODUCT_ID_03_DISPEN_MODE_2':
            case 'PRODUCT_DISPEN_PRODUCT_ID_04_DISPEN_MODE_2':
                return $this->readDispense($response);
            case 'FIRMWARE_VERSION_GET':
                return $this->readFirmVersion($response);
            default:
                return [];
        }
    }

    public function readMachineStatus($response)
    {
        try {
            $array = explode(' ', $response);

            //add ACK = 06 when unable to get acknowledgement
            if ($array[0] !== '06') {
                array_unshift($array, '06');
            }

            $machine_status = [
                'temperature' => $this->tempValue(array_slice($array, 7, 3)),
                'power_relay_status' => $this->statusIndicator(array_slice($array, 10, 1)),
                'fan_relay_status' => $this->statusIndicator(array_slice($array, 11, 1)),
                'bay_001_stocks' => hexdec(join('', array_slice($array, 12, 1))),
                'bay_002_stocks' => hexdec(join('', array_slice($array, 13, 1))),
                'bay_003_stocks' => hexdec(join('', array_slice($array, 14, 1))),
                'bay_004_stocks' => hexdec(join('', array_slice($array, 15, 1))),
                'bay_005_stocks' => hexdec(join('', array_slice($array, 16, 1))),
                'lamp_001_status' => $this->statusIndicator(array_slice($array, 17, 1)),
                'lamp_002_status' => $this->statusIndicator(array_slice($array, 18, 1)),
                'lamp_003_status' => $this->statusIndicator(array_slice($array, 19, 1)),
                'lamp_004_status' => $this->statusIndicator(array_slice($array, 20, 1)),
                'lamp_005_status' => $this->statusIndicator(array_slice($array, 21, 1)),
                'lamp_006_status' => $this->statusIndicator(array_slice($array, 22, 1))
            ];
            return $machine_status;
        } catch (\Exception $err) {
            return [
                'result' => ['error' => $err->getMessage()],
                'status' => 500
            ];
        }
    }

    public function readDispense($response)
    {
        try {
            $array = explode(' ', $response);

            //add ACK = 06 when unable to get acknowledgement
            if ($array[0] !== '06') {
                array_unshift($array, '06');
            }

            $dispenser_detail = [
                'bay' => $this->dispenseRow(array_slice($array, 7, 1)),
                'mode' => $this->dispenseMode(array_slice($array, 8, 1)),
                'item_dropped' => $this->statusIndicator(array_slice($array, 9, 1)),
                'use_time' => $this->dispenseUseTime(array_slice($array, 10, 2)),
                'stocks' => hexdec(join('', array_slice($array, 12, 1))),
            ];
            return $dispenser_detail;
        } catch (\Exception $err) {
            return [
                'result' => ['error' => $err->getMessage()],
                'status' => 500
            ];
        }
    }

    public function readFirmVersion($response)
    {
        try {
            $array = explode(' ', $response);

            //add ACK = 06 when unable to get acknowledgement
            if ($array[0] !== '06') {
                array_unshift($array, '06');
            }

            $detail = [
                'hardware_version' => (float) implode('', array_slice($array, 7, 1)),
                'software_version' => (float) implode('', array_slice($array, 8, 1))
            ];
            return $detail;
        } catch (\Exception $err) {
            return [
                'result' => ['error' => $err->getMessage()],
                'status' => 500
            ];
        }
    }

    public function dispenseRow($array)
    {
        $bays = [1,2,3,4,5];
        $bay = (int) ($array[0] ?? 0);
        return $bays[$bay];
    }

    public function dispenseMode($array)
    {
        $modes = [
            'Soilenoid', 'Belt', 'Spiral Motor',
            'Solenoid Hart Open', 'FMT Noodle Motor',
            'Spiral Motor with Sensor'
        ];
        $mode = (int) ($array[0] ?? 0);
        return $modes[$mode];
    }

    public function dispenseUseTime($array)
    {
        $msb = hexdec($array[0]); // 8
        $lsb = hexdec($array[1]); // 114
        $combined = ($msb << 8) | $lsb; // Combine the two bytes
        return $combined; // Output: 2162
    }

    public function statusIndicator($data)
    {
        $val = (int) $data[0] ?? 0;
        return $val === 1 ? true : false;
    }

    public function tempValue($data)
    {
        $temp = $data[0] == 50 ? '+' : '-';
        $temp .= hexdec($data[1]).'.'.hexdec($data[2]);
        $temp .= ' Celsius';
        return $temp;
    }
}
