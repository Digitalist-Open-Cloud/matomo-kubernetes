<?php

use Interop\Container\ContainerInterface;
use Piwik\Common;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\Mock\TestConfig;

return array(

    // Disable logging
    'Psr\Log\LoggerInterface' => DI\object('Psr\Log\NullLogger'),

    'Piwik\Cache\Backend' => function () {
        return \Piwik\Cache::buildBackend('file');
    },
    'cache.eager.cache_id' => 'eagercache-test-',

    // set in individual tests to override now value when needed
    'Tests.now' => false,

    // Disable loading core translations
    'Piwik\Translation\Translator' => DI\decorate(function ($previous, ContainerInterface $c) {
        $loadRealTranslations = $c->get('test.vars.loadRealTranslations');
        if (!$loadRealTranslations) {
            return new \Piwik\Translation\Translator($c->get('Piwik\Translation\Loader\LoaderInterface'), $directories = array());
        } else {
            return $previous;
        }
    }),

    'Piwik\Config' => DI\decorate(function ($previous, ContainerInterface $c) {
        $testingEnvironment = $c->get('Piwik\Tests\Framework\TestingEnvironmentVariables');

        $dontUseTestConfig = $c->get('test.vars.dontUseTestConfig');
        if (!$dontUseTestConfig) {
            $settingsProvider = $c->get('Piwik\Application\Kernel\GlobalSettingsProvider');
            return new TestConfig($settingsProvider, $testingEnvironment, $allowSave = false, $doSetTestEnvironment = true);
        } else {
            return $previous;
        }
    }),

    'Piwik\Access' => DI\decorate(function ($previous, ContainerInterface $c) {
        $testUseMockAuth = $c->get('test.vars.testUseMockAuth');
        if ($testUseMockAuth) {
            $idSitesAdmin = $c->get('test.vars.idSitesAdminAccess');
            $idSitesView = $c->get('test.vars.idSitesViewAccess');
            $idSitesWrite = $c->get('test.vars.idSitesWriteAccess');
            $idSitesCapabilities = $c->get('test.vars.idSitesCapabilities');
            $access = new FakeAccess();

            if (!empty($idSitesView)) {
                FakeAccess::$superUser = false;
                FakeAccess::$idSitesView = $idSitesView;
                FakeAccess::$idSitesWrite = !empty($idSitesWrite) ? $idSitesWrite : array();
                FakeAccess::$idSitesAdmin = !empty($idSitesAdmin) ? $idSitesAdmin : array();
                FakeAccess::$identity = 'viewUserLogin';
            } elseif (!empty($idSitesWrite)) {
                FakeAccess::$superUser = false;
                FakeAccess::$idSitesWrite = !empty($idSitesWrite) ? $idSitesWrite : array();
                FakeAccess::$idSitesAdmin = !empty($idSitesAdmin) ? $idSitesAdmin : array();
                FakeAccess::$identity = 'writeUserLogin';
            } elseif (!empty($idSitesAdmin)) {
                FakeAccess::$superUser = false;
                FakeAccess::$idSitesAdmin = $idSitesAdmin;
                FakeAccess::$identity = 'adminUserLogin';
            } else {
                FakeAccess::$superUser = true;
                FakeAccess::$superUserLogin = 'superUserLogin';
            }
            if (!empty($idSitesCapabilities)) {
                FakeAccess::$idSitesCapabilities = (array) $idSitesCapabilities;
            }
            return $access;
        } else {
            return $previous;
        }
    }),

    'observers.global' => DI\add(array(

        array('AssetManager.getStylesheetFiles', function (&$stylesheets) {
            $useOverrideCss = \Piwik\Container\StaticContainer::get('test.vars.useOverrideCss');
            if ($useOverrideCss) {
                $stylesheets[] = 'tests/resources/screenshot-override/override.css';
            }
        }),

        array('AssetManager.getJavaScriptFiles', function (&$jsFiles) {
            $useOverrideJs = \Piwik\Container\StaticContainer::get('test.vars.useOverrideJs');
            if ($useOverrideJs) {
                $jsFiles[] = 'tests/resources/screenshot-override/override.js';
            }
        }),

        array('Updater.checkForUpdates', function () {
            try {
                @\Piwik\Filesystem::deleteAllCacheOnUpdate();
            } catch (Exception $ex) {
                // pass
            }
        }),

        array('Test.Mail.send', function (\Zend_Mail $mail) {
            $outputFile = PIWIK_INCLUDE_PATH . '/tmp/' . Common::getRequestVar('module', '') . '.' . Common::getRequestVar('action', '') . '.mail.json';
            $outputContent = str_replace("=\n", "", $mail->getBodyText($textOnly = true));
            $outputContent = str_replace("=0A", "\n", $outputContent);
            $outputContent = str_replace("=3D", "=", $outputContent);
            $outputContents = array(
                'from' => $mail->getFrom(),
                'to' => $mail->getRecipients(),
                'subject' => $mail->getSubject(),
                'contents' => $outputContent
            );
            file_put_contents($outputFile, json_encode($outputContents));
        }),
    )),
);
