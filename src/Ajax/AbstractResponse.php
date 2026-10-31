<?php

/**
 * @author Peter Bieling - 2026-01-23
 */

namespace PbClasses\Ajax;

use PbClasses\Conf;
use PbClasses\Exception\Logging;
use PbClasses\Ajax\BasePageConf;

abstract class AbstractResponse {

    protected array $rslt = ['succ' => 'ERROR',
                              'errortext' => ''];
    protected ?array $P = null;
    protected ?int $basePid = null;
    protected ?array $basePageConf = null;
    protected ?string $modNsp = null;

    abstract protected function action();

    public function __construct(
            protected readonly ?array $filterParam = null
    ) {
        $this->P = $this->filterParam !== null ? filter_input_array(INPUT_POST, $this->filterParam) : filter_input_array(INPUT_POST);

        $this->initializePageConfiguration();
        $this->initializeModuleConfiguration();
        $this->action();
    }

    protected function initializePageConfiguration(): void {
        if (empty($this->P['pbconfdata']['pid'])) {
            return;
        }

        $this->basePid = (int) $this->P['pbconfdata']['pid'];
        $this->basePageConf = BasePageConf::get($this->basePid);
    }

    protected function initializeModuleConfiguration(): void {
        if (empty($this->P['pbmodnsp']) || empty($this->P['pbmodname'])) {
            return;
        }

        $this->loadModuleConfig(
                $this->P['pbmodnsp'],
                $this->P['pbmodname']
        );
    }

    /**
     * 
     * @param string $modNsp Namespace of the module
     * @param string $modName Name of the module
     * @return void
     */
    protected function loadModuleConfig(string $modNsp, string $modName): void {
        $modulePath = Conf::get('MODULE_PATH');
        $configFile = $modulePath . $modName . '/module.conf.php';

        if (!file_exists($configFile)) {
            return;
        }

        require_once $configFile;

        $modClassName = "\\{$modNsp}\\Init";

        if (class_exists($modClassName)) {
            $this->modNsp = $modNsp;
            new $modClassName('ajax');
        }
    }

    /**
     * @param string $errorText Visible error text
     * @param string $file File name
     * @param int $line Line number
     * @param mixed $techError Technical error information
     * @param bool $logFlag Whether to log the error
     */
    protected function errorOutput(
            string $errorText,
            string $file = __FILE__,
            int $line = __LINE__,
            mixed $techError = null,
            bool $logFlag = true
    ): never {
        $techErrorString = is_scalar($techError) ? (string) $techError : print_r($techError, true);

        $fullError = $techError !== null ? "{$errorText} : {$techErrorString}" : $errorText;

        $this->rslt['succ'] = 'ERROR';
        $this->rslt['errortext'] = $errorText;

        if ($logFlag) {
            $params = print_r($this->rslt, true);
            new Logging("{$fullError} - {$params}", $file, $line);
        }

        $this->output(logFlag: false);
    }

    protected function okOutput(): never {
        $this->rslt['succ'] = 'OK';
        $this->output();
    }

    protected function output(
            string $file = __FILE__,
            int $line = __LINE__,
            bool $logFlag = true
    ): never {
        header('Content-Type: application/json; charset=utf-8');

        if (isset($this->rslt['succ']) && $this->rslt['succ'] === 'ERROR' && $logFlag) {
            new Logging(print_r($this->rslt, true), $file, $line);
        }

        echo json_encode($this->rslt, JSON_THROW_ON_ERROR);
        exit;
    }
}
