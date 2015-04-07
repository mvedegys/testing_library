<?php
/**
 * This file is part of OXID eSales Testing Library.
 *
 * OXID eSales Testing Library is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OXID eSales Testing Library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID eSales Testing Library. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2014
 */

define('OXID_PHP_UNIT', true);

require_once TEST_LIBRARY_PATH.'oxTestConfig.php';
require_once TEST_LIBRARY_PATH.'oxServiceCaller.php';
require_once TEST_LIBRARY_PATH.'oxFileCopier.php';
require_once TEST_LIBRARY_PATH .'test_utils.php';

class Bootstrap
{
    /** @var oxTestConfig */
    private $testConfig;

    /** @var int Whether to add demo data when installing the shop. */
    protected $addDemoData = 1;

    /**
     * Initiates class dependencies.
     */
    public function __construct()
    {
        $this->testConfig = new oxTestConfig();
    }

    /**
     * Prepares tests environment.
     */
    public function init()
    {
        $testConfig = $this->getTestConfig();

        $this->setGlobalConstants();

        if ($testConfig->getTempDirectory()) {
            $fileCopier = new oxFileCopier();
            $fileCopier->createEmptyDirectory($testConfig->getTempDirectory());
        }

        if ($testConfig->shouldRestoreShopAfterTestsSuite()) {
            $this->registerResetDbAfterSuite();
        }

        if ($testConfig->shouldInstallShop()) {
            $this->installShop();
        }

        $this->prepareShopModObjects();
    }

    /**
     * Returns tests config.
     *
     * @return oxTestConfig
     */
    public function getTestConfig()
    {
        return $this->testConfig;
    }

    /**
     * Installs the shop.
     *
     * @throws Exception
     */
    protected function installShop()
    {
        $config = $this->getTestConfig();
        
        $serviceCaller = new oxServiceCaller($this->getTestConfig());
        $serviceCaller->setParameter('serial', $config->getShopSerial());
        $serviceCaller->setParameter('addDemoData', $this->addDemoData);
        $serviceCaller->setParameter('turnOnVarnish', $config->shouldEnableVarnish());

        if ($setupPath = $config->getShopSetupPath()) {
            $fileCopier = new oxFileCopier();
            $remoteDirectory = $config->getRemoteDirectory();
            $shopDirectory = $remoteDirectory ? $remoteDirectory : $config->getShopPath();

            $fileCopier->copyFiles($setupPath, $shopDirectory.'/setup/');
        }

        try {
            $serviceCaller->callService('ShopInstaller');
        } catch (Exception $e) {
            exit("Failed to install shop with message:" . $e->getMessage());
        }
    }

    /**
     * Sets global constants, as these are still used a lot in tests.
     * This is used to maintain backwards compatibility, but should not be used anymore in new code.
     */
    protected function setGlobalConstants()
    {
        $testConfig = $this->getTestConfig();

        if (file_exists($testConfig->getShopPath() . "/_version_define.php")) {
            include_once $testConfig->getShopPath() . "/_version_define.php";
        } else {
            define('OXID_VERSION_SUFIX', '');
        }

        /** @deprecated use TestConfig::getShopPath() */
        define('oxPATH', $testConfig->getShopPath());

        /** @deprecated use TestConfig::getShopPath() */
        define('OX_BASE_PATH', $testConfig->getShopPath());

        /** @deprecated use TestConfig::getShopUrl() */
        define('shopURL', $testConfig->getShopUrl());

        /** @deprecated use TestConfig::getShopId() */
        define('oxSHOPID', $testConfig->getShopId());

        /** @deprecated use TestConfig::isSubShop() */
        define('isSUBSHOP', $testConfig->isSubShop());

        /** @deprecated use TestConfig::getCurrentTestSuite() */
        define('CURRENT_TEST_SUITE', $testConfig->getCurrentTestSuite());
    }

    /**
     * Creates original database dump and registers database restoration
     * after the tests suite.
     */
    protected function registerResetDbAfterSuite()
    {
        $serviceCaller = new oxServiceCaller($this->getTestConfig());
        $serviceCaller->setParameter('dumpDB', true);
        $serviceCaller->setParameter('dump-prefix', 'orig_db_dump');
        try {
            $serviceCaller->callService('ShopPreparation', 1);
        } catch (Exception $e) {
            define('RESTORE_SHOP_AFTER_TEST_SUITE_ERROR', true);
        }

        register_shutdown_function(function () {
            if (!defined('RESTORE_SHOP_AFTER_TEST_SUITE_ERROR')) {
                $serviceCaller = new oxServiceCaller();
                $serviceCaller->setParameter('restoreDB', true);
                $serviceCaller->setParameter('dump-prefix', 'orig_db_dump');
                $serviceCaller->callService('ShopPreparation', 1);
            }
        });
    }

    /**
     * Prepares shop config object.
     */
    protected function prepareShopModObjects()
    {
        $shopPath = $this->getTestConfig()->getShopPath();
        require_once $shopPath .'core/oxfunctions.php';

        $oConfigFile = new oxConfigFile($shopPath . "config.inc.php");
        oxRegistry::set("oxConfigFile", $oConfigFile);
        oxRegistry::set("oxConfig", new oxConfig());
    }
}
