<?php

namespace Clue\Tests\React\HttpProxy;

use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends \PHPUnit\Framework\TestCase
{
    protected function expectCallableNever()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->never())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnce()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnceWith($value)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($value);

        return $mock;
    }

    protected function expectCallableOnceWithException($class, $message, $code)
    {
        return $this->expectCallableOnceWith($this->logicalAnd(
            $this->isInstanceOf($class),
            $this->callback(function (\Exception $e) use ($message, $code) {
                return strpos($e->getMessage(), $message) !== false && $e->getCode() === $code;
            })
        ));
    }

    /**
     * @link https://github.com/reactphp/react/blob/master/tests/React/Tests/Socket/TestCase.php (taken from reactphp/react)
     */
    protected function createCallableMock()
    {
        return $this->getMockBuilder('stdClass')->setMethods(array('__invoke'))->getMock();
    }
}

