<?php

/**
 * Class Hackathon_PSR0Autoloader_Model_Observer
 */
class Hackathon_PSR0Autoloader_Model_Observer extends Mage_Core_Model_Observer
{

    const CONFIG_PATH_PSR0NAMESPACES = 'global/psr0_namespaces';
    const CONFIG_PATH_COMPOSER_VENDOR_PATH = 'global/composer_vendor_path';
    const CONFIG_PATH_BASE_AUTOLOADER_DISABLE = 'global/base_autoloader_disable';

    /**
     * @var bool
     */
    private static $hasRun = false;

    /**
     * Get Magento node
     * @param $nodeName
     * @return object|null Node object if found in config, null if not
     */
    private function getNode($nodeName)
    {
        $config = Mage::getConfig();
        if (!is_object($config)) {
            return null;
        }

        $node = $config->getNode($nodeName);
        if (!is_object($node)) {
            return null;
        }

        return $node;
    }

    /**
     * Register namespaces with autoloader if set in config
     */
    private function registerNamespaces()
    {
        $namespaceList = $this->getNamespacesToRegister();
        if (empty($namespaceList) || !is_array($namespaceList)) {
            return;
        }

        foreach ($namespaceList as $namespace) {
            $namespaceDir = Mage::getBaseDir('lib') . DS . $namespace;
            if (is_dir($namespaceDir)) {
                $args = array($namespace, $namespaceDir);
                $autoloader = Mage::getModel("psr0autoloader/splAutoloader", $args);
                $autoloader->register();
            }
        }
    }

    /**
     * Get namespaces to register in autoloader
     * @return array with namespaces to load as values
     */
    private function getNamespacesToRegister()
    {
        $namespaces = array();
        $node = $this->getNode(self::CONFIG_PATH_PSR0NAMESPACES);
        if (!is_object($node)) {
            return $namespaces;
        }

        $nodeArray = $node->asArray();
        if (is_array($nodeArray)) {
            $namespaces = array_keys($nodeArray);
        }

        return $namespaces;
    }

    /**
     * Load composer autoloader if path is set in configuration
     */
    private function loadComposer()
    {
        $composerVendorPath = $this->getComposerVendorPath();
        if (
            empty($composerVendorPath)
            || !is_string($composerVendorPath)
        ) {
            return;
        }

        require_once $composerVendorPath . '/autoload.php';
    }

    /**
     * Get composer vendor path if set in configuration
     * @return string|null string if path set in config, null if not
     */
    private function getComposerVendorPath()
    {
        $node = $this->getNode(self::CONFIG_PATH_COMPOSER_VENDOR_PATH);
        if (empty($node)) {
            return null;
        }

        $path = str_replace('{{root_dir}}', Mage::getBaseDir(), (string) $node);
        return $path;
    }

    /**
     * Check if config is set to disable Magento autoloader
     * @return bool true if it should be disabled, false if not
     */
    private function shouldDisableBaseAutoloader()
    {
        $config = $this->getNode(self::CONFIG_PATH_BASE_AUTOLOADER_DISABLE);
        return (!empty($config) && filter_var((string) $config, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Disable Magento default autoloader if set in config files
     */
    private function disableMagentoAutoloader()
    {
        if ($this->shouldDisableBaseAutoloader()) {
            spl_autoload_unregister(array(Varien_Autoload::instance(), 'autoload'));
        }
    }

    /**
     * Register namespaces for autoload, load composer autoload and disable Magento default
     * autoloader according to config files.
     *
     * This method can only be run once.
     */
    public function addAutoloader()
    {
        if (self::$hasRun) {
            return;
        }

        $this->registerNamespaces();

        $this->loadComposer();

        $this->disableMagentoAutoloader();

        self::$hasRun = true;
    }
}
