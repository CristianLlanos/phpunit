<?php declare(strict_types=1);
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\Framework;

use PHPUnit\Util\Test as TestUtil;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class TestBuilder
{
    public function build(\ReflectionClass $theClass, string $methodName): Test
    {
        $className = $theClass->getName();

        if (!$theClass->isInstantiable()) {
            return new WarningTestCase(
                \sprintf('Cannot instantiate class "%s".', $className)
            );
        }

        $backupSettings = TestUtil::getBackupSettings(
            $className,
            $methodName
        );

        $preserveGlobalState = TestUtil::getPreserveGlobalStateSettings(
            $className,
            $methodName
        );

        $runTestInSeparateProcess = TestUtil::getProcessIsolationSettings(
            $className,
            $methodName
        );

        $runClassInSeparateProcess = TestUtil::getClassProcessIsolationSettings(
            $className,
            $methodName
        );

        $constructor = $theClass->getConstructor();

        if ($constructor === null) {
            throw new Exception('No valid test provided.');
        }

        $parameters = $constructor->getParameters();

        // TestCase() or TestCase($name)
        if (\count($parameters) < 2) {
            $test = $this->buildTestWithoutData($className);
        } // TestCase($name, $data)
        else {
            try {
                $data = TestUtil::getProvidedData(
                    $className,
                    $methodName
                );
            } catch (IncompleteTestError $e) {
                $message = \sprintf(
                    'Test for %s::%s marked incomplete by data provider',
                    $className,
                    $methodName
                );
                $message = $this->appendExceptionMessageIfAvailable($e, $message);
                $data    = new IncompleteTestCase($className, $methodName, $message);
            } catch (SkippedTestError $e) {
                $message = \sprintf(
                    'Test for %s::%s skipped by data provider',
                    $className,
                    $methodName
                );
                $message = $this->appendExceptionMessageIfAvailable($e, $message);
                $data    = new SkippedTestCase($className, $methodName, $message);
            } catch (\Throwable $t) {
                $message = \sprintf(
                    'The data provider specified for %s::%s is invalid.',
                    $className,
                    $methodName
                );
                $message = $this->appendExceptionMessageIfAvailable($t, $message);
                $data    = new WarningTestCase($message);
            }

            // Test method with @dataProvider.
            if (isset($data)) {
                $test = $this->buildDataProviderTestSuite(
                    $methodName,
                    $className,
                    $data,
                    $runTestInSeparateProcess,
                    $preserveGlobalState,
                    $runClassInSeparateProcess,
                    $backupSettings
                );
            } else {
                $test = $this->buildTestWithoutData($className);
            }
        }

        if ($test instanceof TestCase) {
            $test->setName($methodName);

            if ($runTestInSeparateProcess) {
                $test->setRunTestInSeparateProcess(true);

                if ($preserveGlobalState !== null) {
                    $test->setPreserveGlobalState($preserveGlobalState);
                }
            }

            if ($runClassInSeparateProcess) {
                $test->setRunClassInSeparateProcess(true);

                if ($preserveGlobalState !== null) {
                    $test->setPreserveGlobalState($preserveGlobalState);
                }
            }

            if ($backupSettings['backupGlobals'] !== null) {
                $test->setBackupGlobals($backupSettings['backupGlobals']);
            }

            if ($backupSettings['backupStaticAttributes'] !== null) {
                $test->setBackupStaticAttributes(
                    $backupSettings['backupStaticAttributes']
                );
            }
        }

        return $test;
    }

    private function appendExceptionMessageIfAvailable(\Throwable $e, string $message): string
    {
        $_message = $e->getMessage();

        if (!empty($_message)) {
            $message .= "\n" . $_message;
        }

        return $message;
    }

    private function buildTestWithoutData(string $className)
    {
        return new $className;
    }

    private function buildDataProviderTestSuite(
        string $methodName,
        string $className,
        $data,
        bool $runTestInSeparateProcess,
        ?bool $preserveGlobalState,
        bool $runClassInSeparateProcess,
        array $backupSettings
    ): DataProviderTestSuite {
        $dataProviderTestSuite = new DataProviderTestSuite(
            $className . '::' . $methodName
        );

        if (empty($data)) {
            $data = new WarningTestCase(
                \sprintf(
                    'No tests found in suite "%s".',
                    $dataProviderTestSuite->getName()
                )
            );
        }

        $groups = TestUtil::getGroups($className, $methodName);

        if ($data instanceof WarningTestCase ||
            $data instanceof SkippedTestCase ||
            $data instanceof IncompleteTestCase) {
            $dataProviderTestSuite->addTest($data, $groups);
        } else {
            foreach ($data as $_dataName => $_data) {
                $_test = new $className($methodName, $_data, $_dataName);

                \assert($_test instanceof TestCase);

                if ($runTestInSeparateProcess) {
                    $_test->setRunTestInSeparateProcess(true);

                    if ($preserveGlobalState !== null) {
                        $_test->setPreserveGlobalState($preserveGlobalState);
                    }
                }

                if ($runClassInSeparateProcess) {
                    $_test->setRunClassInSeparateProcess(true);

                    if ($preserveGlobalState !== null) {
                        $_test->setPreserveGlobalState($preserveGlobalState);
                    }
                }

                if ($backupSettings['backupGlobals'] !== null) {
                    $_test->setBackupGlobals(
                        $backupSettings['backupGlobals']
                    );
                }

                if ($backupSettings['backupStaticAttributes'] !== null) {
                    $_test->setBackupStaticAttributes(
                        $backupSettings['backupStaticAttributes']
                    );
                }

                $dataProviderTestSuite->addTest($_test, $groups);
            }
        }

        return $dataProviderTestSuite;
    }
}