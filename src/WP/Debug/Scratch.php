<?php
/**
 * @package WP_Utilities
 */

namespace woprsk\WP\Debug;

class ScratchFileLoader
{
    private $scratchFilePath;
    private $onAction;

    public function __construct($scratchFilePath = '', $onAction = 'muplugins_loaded')
    {
        $this->scratchFilePath = $scratchFilePath;
        $this->onAction = $onAction;
        $this->loadScratchFile();
    }

    private function loadScratchFile()
    {
        if (!file_exists($this->scratchFilePath) || \did_action($this->onAction)) {
            return;
        }

        $scratchFilePath = $this->scratchFilePath;

        \add_action($this->onAction, function () use ($scratchFilePath) {
            include_once($scratchFilePath);
        }, -9999);
    }
}
