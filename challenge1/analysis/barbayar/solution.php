<?php

class DataManager
{
    private $_inputFilePath = '../../data/training_dataset_500.csv';
    private $_data = null;

    private function _loadData()
    {
        $inputFile = @fopen($this->_inputFilePath, 'r');

        if ($inputFile === false) {
            throw new InvalidArgumentException("Couldn't open input file($this->_inputFilePath), please check the file name.");
        }

        $this->_data = array();
        while (($row = fgetcsv($inputFile)) !== false) {
            $houseId = $row[2];
            $year = $row[3];
            $month = $row[4];
            $temperature = $row[5];
            $daylight = $row[6];
            $energy = $row[7];

            $this->_data[$houseId][$year][$month]['temperature'] = $temperature;
            $this->_data[$houseId][$year][$month]['daylight'] = $daylight;
            $this->_data[$houseId][$year][$month]['energy'] = $energy;
        }

        fclose($inputFile);
    }

    public function __construct()
    {
        $this->_loadData();
    }

    public function getByHouseId($houseId)
    {
        return $this->_data[$houseId];
    }

    public function getHouseCount()
    {
        // first element is header label, so we shouldn't count that
        return count($this->_data) - 1;
    }
}

class Forecaster
{
    /*
     * predicts using the average coefficient of changing
     */
    public static function _simple($data) {
        $coefficient = 0;
        for ($i = 0; $i < 11; $i++) {
            $month = ($i  + 6) % 12 + 1;
            $year = (int)(($i  + 6) / 12) + 2012;
            // coefficient = currentYear / previousYear;
            $coefficient += $data[$year][$month]['energy'] / $data[$year - 1][$month]['energy'];
        }

        $averageCoefficient = $coefficient / 11;

        return $averageCoefficient * $data[2012][6]['energy'];
    }

    public static function predict($algorithm, $data)
    {
        if (!method_exists('Forecaster', "_$algorithm")) {
            throw new InvalidArgumentException("There is no algorithm called '$algorithm'.");
        }

        $algorithmImplementation = "_$algorithm";

        return self::$algorithmImplementation($data);
    }
}

class Tester
{
    private static $_testFilePath = '../../data/test_dataset_500.csv';
    private static $_outputFilePath = 'predicted_energy_production.csv';
    private static $_mapeFilePath = 'mape.txt';
    private static $_testData = null;

    private static function _loadTestData()
    {
        $testFile = @fopen(self::$_testFilePath, 'r');

        if ($testFile === false) {
            throw new InvalidArgumentException("Couldn't open test file(" . self::$_testFilePath . "), please check the file name.");
        }

        self::$_testData = array();
        while (($row = fgetcsv($testFile)) !== false) {
            $houseId = $row[2];
            $energy = $row[7];

            self::$_testData[$houseId] = $energy;
        }

        fclose($testFile);
    }

    private static function _saveTestResult($mape, $result)
    {
        file_put_contents(self::$_mapeFilePath, $mape);

        $outputFile = fopen(self::$_outputFilePath, 'w');
        fprintf($outputFile, "House,EnergyProduction\n");
        foreach ($result as $houseId => $energy) {
            fprintf($outputFile, "%d,%0.2f\n", $houseId, $energy);
        }
        fclose($outputFile);
    }

    private static function _calculateMAPE($result)
    {
        // first element is header label, so we shouldn't count that
        $testCount = count(self::$_testData) - 1;

        $mape = 0;
        for ($i = 1; $i <= $testCount; $i++) {
            $actual = self::$_testData[$i];
            $predicted = $result[$i];
            $mape += abs(($actual - $predicted) / $actual);
        }

        return $mape / $testCount;
    }

    public static function test($result)
    {
        if (is_null(self::$_testData)) {
            self::_loadTestData();
        }

        $mape = self::_calculateMAPE($result);
        self::_saveTestResult($mape, $result);
    }
}

// program starts here
$dataManager = new DataManager();

$result = array();
$houseCount = $dataManager->getHouseCount();

for ($i = 1; $i <= $houseCount; $i++) {
    $data = $dataManager->getByHouseId($i);
    $result[$i] = Forecaster::predict('simple', $data) . "\n";
}

Tester::test($result);