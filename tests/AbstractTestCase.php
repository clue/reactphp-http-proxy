<?php

namespace Tests\Clue\React\HttpProxy;

use PHPUnit_Framework_TestCase;

abstract class AbstractTestCase extends PHPUnit_Framework_TestCase
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
            ->with($this->equalTo($value));

        return $mock;
    }

    protected function expectCallableOnceWithExceptionCode($code)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(function ($e) use ($code) {
                return $e->getCode() === $code;
            }));

        return $mock;
    }


    protected function expectCallableOnceParameter($type)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->isInstanceOf($type));

        return $mock;
    }

    /**
     * @link https://github.com/reactphp/react/blob/master/tests/React/Tests/Socket/TestCase.php (taken from reactphp/react)
     */
    protected function createCallableMock()
    {
        return $this->getMockBuilder('Tests\\Clue\\React\\HttpProxy\\CallableStub')->getMock();
    }
}

class CallableStub
{
    public function __invoke()
    {
    }
}

